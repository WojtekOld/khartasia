<?php

namespace Drupal\Tests\xntt_views\Functional;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests views contextual filters on external entities.
 *
 * Regression test for
 * https://www.drupal.org/project/external_entities/issues/3590257
 * "Fatal Error when attempting to set up a contextual filter in Views":
 * core argument handlers call $query->placeholder() (NumericArgument does so
 * unconditionally) and $query->addWhereExpression(), which only exist on the
 * SQL views query plugin. Rendering a view page with such a contextual
 * filter caused "Call to undefined method
 * ExternalEntityViewsQuery::placeholder()".
 */
#[Group('ExternalEntities')]
#[Group('xntt_views')]
#[RunTestsInSeparateProcesses]
class ViewsContextualFilterTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'external_entities',
    'xntt_views',
    'views',
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  protected $profile = 'minimal';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The views base table of the external entity type.
   *
   * @var string
   */
  protected $baseTable = 'xntt_views_planet_test';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // One record per file; sizes are made distinct per planet except
    // "earth" and "venus" which share the same size on purpose.
    $file_system = $this->container->get('file_system');
    $dir = 'public://xntt_planet_test';
    $file_system->prepareDirectory(
      $dir,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
    );
    $records = [
      'mercury' => 'MERCURY demo record!',
      'venus' => 'VENUS demo record!',
      'earth' => 'EARTH demo record!',
      'mars' => 'MARS demo record',
    ];
    foreach ($records as $id => $content) {
      file_put_contents("$dir/$id.txt", $content);
    }

    // External entity type backed by the "files" storage client.
    $type_storage = $this->container->get('entity_type.manager')->getStorage('external_entity_type');
    /** @var \Drupal\external_entities\Entity\ExternalEntityType $type */
    $type = $type_storage->create([
      'id' => 'planet_test',
      'label' => 'Planet test',
      'label_plural' => 'Planet tests',
      'base_path' => 'planet-test',
      'description' => '',
      'read_only' => TRUE,
      'debug_level' => 0,
      'field_mappers' => [],
      'field_mapping_notes' => '',
      'data_aggregator' => [],
      'data_aggregator_notes' => '',
      'language_settings' => [],
      'persistent_cache_max_age' => 0,
    ]);
    $type->setDataAggregatorId('single')->setDataAggregatorConfig([
      'storage_clients' => [
        [
          'id' => 'files',
          'config' => [
            'root' => $dir,
            'structure' => '{id}.txt',
            'matching_only' => FALSE,
            'record_type' => 2,
            'performances' => ['use_index' => FALSE, 'index_file' => ''],
            'data_fields' => [
              'md5_checksum' => FALSE,
              'sha1_checksum' => FALSE,
              'field_list' => [],
            ],
          ],
        ],
      ],
    ]);
    // The first save creates the derived content entity type which is
    // required before field mappers can be configured.
    $type->save();
    $this->container->get('entity_type.manager')->clearCachedDefinitions();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    // An integer field, to get a contextual filter using the "numeric"
    // argument handler: NumericArgument::query() unconditionally calls
    // $query->placeholder(), the exact fatal of issue #3590257.
    FieldStorageConfig::create([
      'field_name' => 'field_size',
      'entity_type' => 'planet_test',
      'type' => 'integer',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_size',
      'entity_type' => 'planet_test',
      'bundle' => 'planet_test',
      'label' => 'File size',
    ])->save();

    // A timestamp field, to get a contextual filter using the "xntt_date"
    // argument handler.
    FieldStorageConfig::create([
      'field_name' => 'field_mtime',
      'entity_type' => 'planet_test',
      'type' => 'timestamp',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_mtime',
      'entity_type' => 'planet_test',
      'bundle' => 'planet_test',
      'label' => 'Modified',
    ])->save();

    $direct = function (string $mapping) {
      return [
        'property_mappings' => [
          'value' => [
            'id' => 'direct',
            'config' => [
              'mapping' => $mapping,
              'required_field' => TRUE,
              'main_property' => TRUE,
              'data_processors' => [[]],
            ],
          ],
        ],
        'debug_level' => 0,
      ];
    };
    $type = $type_storage->load('planet_test');
    $type->setFieldMapperId('id', 'generic')->setFieldMapperConfig('id', $direct('id'));
    $type->setFieldMapperId('uuid', 'generic')->setFieldMapperConfig('uuid', $direct('id'));
    $type->setFieldMapperId('title', 'generic')->setFieldMapperConfig('title', $direct('basename'));
    $type->setFieldMapperId('field_size', 'generic')->setFieldMapperConfig('field_size', $direct('size'));
    $type->setFieldMapperId('field_mtime', 'generic')->setFieldMapperConfig('field_mtime', $direct('mtime'));
    $type->save();

    // Anonymous access to the external entities.
    $role = $this->container->get('entity_type.manager')->getStorage('user_role')->load('anonymous');
    $role->grantPermission('view planet_test external entity');
    $role->save();

    $this->createContextualFilterView(
      'planet_by_size',
      'planet-by-size/%',
      'field_size',
      'numeric'
    );
    $this->createContextualFilterView(
      'planet_by_title',
      'planet-by-title/%',
      'title',
      'string'
    );
    $this->createContextualFilterView(
      'planet_by_date',
      'planet-by-date/%',
      'field_mtime',
      'xntt_date'
    );

    $this->resetAll();
  }

  /**
   * Creates a view page with a single contextual filter.
   *
   * @param string $view_id
   *   The view ID.
   * @param string $path
   *   The page path including the "%" argument placeholder.
   * @param string $argument_field
   *   The views field name used as contextual filter.
   * @param string $argument_plugin
   *   The argument handler plugin ID ("numeric", "string", ...).
   */
  protected function createContextualFilterView(string $view_id, string $path, string $argument_field, string $argument_plugin): void {
    View::create([
      'id' => $view_id,
      'label' => $view_id,
      'base_table' => $this->baseTable,
      'core_version_requirement' => '*',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_title' => 'Default',
          'display_plugin' => 'default',
          'position' => 0,
          'display_options' => [
            'access' => ['type' => 'none'],
            'cache' => ['type' => 'none'],
            'query' => ['type' => 'views_query'],
            'row' => ['type' => 'fields'],
            'fields' => [
              'title' => [
                'id' => 'title',
                'table' => $this->baseTable,
                'field' => 'title',
                'plugin_id' => 'external_entity_field',
                'label' => '',
              ],
            ],
            'arguments' => [
              $argument_field => [
                'id' => $argument_field,
                'table' => $this->baseTable,
                'field' => $argument_field,
                'plugin_id' => $argument_plugin,
                'default_action' => 'empty',
                'summary' => ['format' => 'default_summary'],
                'specify_validation' => FALSE,
              ],
            ],
            'pager' => [
              'type' => 'full',
              'options' => ['items_per_page' => 10],
            ],
          ],
        ],
        'page_1' => [
          'id' => 'page_1',
          'display_title' => 'Page',
          'display_plugin' => 'page',
          'position' => 1,
          'display_options' => [
            'path' => $path,
          ],
        ],
      ],
    ])->save();
  }

  /**
   * Tests numeric and string contextual filters on external entities.
   *
   * Both scenarios live in one test method on purpose: each method of a
   * functional test triggers a full Drupal installation.
   *
   * NumericArgument::query() calls $query->placeholder() unconditionally and
   * then $query->addWhereExpression(): without the fix the numeric pages
   * return a 500 "Call to undefined method
   * ExternalEntityViewsQuery::placeholder()".
   */
  public function testContextualFilters(): void {
    // "mars" record is the only 16-byte one.
    $this->drupalGet('planet-by-size/16');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('mars.txt');
    $this->assertSession()->pageTextNotContains('mercury.txt');
    $this->assertSession()->pageTextNotContains('venus.txt');
    $this->assertSession()->pageTextNotContains('earth.txt');

    // "venus" and "earth" records share 18 bytes.
    $this->drupalGet('planet-by-size/18');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('venus.txt');
    $this->assertSession()->pageTextContains('earth.txt');
    $this->assertSession()->pageTextNotContains('mars.txt');

    // No record matches: the page still renders, with no rows.
    $this->drupalGet('planet-by-size/99');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('.txt');

    // String contextual filter on the title field.
    $this->drupalGet('planet-by-title/mercury.txt');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('mercury.txt');
    $this->assertSession()->pageTextNotContains('mars.txt');

    // An unknown value renders an empty result, not an error.
    $this->drupalGet('planet-by-title/pluto.txt');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('mars.txt');

    // Date contextual filter on the timestamp field: all records were
    // created "today". The expected day is derived from the stored value
    // itself, interpreted in the site default timezone (the one used for
    // the anonymous request), so this cannot race around midnight.
    $entity = $this->container->get('entity_type.manager')
      ->getStorage('planet_test')->load('mars');
    $timezone = new \DateTimeZone(
      $this->config('system.date')->get('timezone.default') ?: date_default_timezone_get()
    );
    $today = (new \DateTime('@' . $entity->get('field_mtime')->value))
      ->setTimezone($timezone)->format('Y-m-d');
    $this->drupalGet("planet-by-date/$today");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('mars.txt');
    $this->assertSession()->pageTextContains('mercury.txt');

    // A day without records: empty result.
    $this->drupalGet('planet-by-date/2001-01-01');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('.txt');

    // An invalid date fails closed: page renders, no rows leak.
    $this->drupalGet('planet-by-date/not-a-date');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('.txt');
  }

}
