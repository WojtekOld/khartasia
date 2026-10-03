<?php

namespace Drupal\Tests\external_entities\Functional;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests base path validation on the external entity type form.
 *
 * Covers:
 * - A base path that is the parent of another external entity type's base
 *   path is valid (sub-paths are created by this module by design and are
 *   correctly resolved by the router).
 * - An exact base path collision with another external entity type is still
 *   rejected.
 * - A base path conflicting with routes of another module is still rejected.
 *
 * @see https://www.drupal.org/project/external_entities/issues/3589995
 */
#[Group('ExternalEntities')]
#[RunTestsInSeparateProcesses]
class ExternalEntityTypeBasePathValidationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'external_entities',
    'external_entities_test',
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
   * The base path conflict error message (start of).
   */
  protected const CONFLICT_MESSAGE = 'This base path is already in use by another module';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_user = $this->drupalCreateUser([
      'administer external entity types',
    ]);
    $this->drupalLogin($admin_user);
  }

  /**
   * Creates a minimal external entity type programmatically.
   *
   * @param string $id
   *   The external entity type ID.
   * @param string $label
   *   The label.
   * @param string $base_path
   *   The base path.
   */
  protected function createExternalEntityType(string $id, string $label, string $base_path): void {
    $this->container->get('entity_type.manager')
      ->getStorage('external_entity_type')
      ->create([
        'id' => $id,
        'label' => $label,
        'label_plural' => $label . 's',
        'base_path' => $base_path,
        'description' => '',
        'read_only' => TRUE,
        'field_mappers' => [],
        'data_aggregator' => [
          'id' => 'single',
          'config' => ['storage_clients' => []],
        ],
        'language_settings' => [],
        'persistent_cache_max_age' => 0,
        'locks' => [],
      ])->save();
  }

  /**
   * Tests editing a type whose base path is the parent of a sibling's path.
   *
   * Regression test for the originally reported scenario: type A uses
   * "catalog", type B uses "catalog/detail"; re-saving type A must not
   * report a base path conflict caused by type B's routes.
   */
  public function testSubPathSiblingDoesNotBlockParentTypeEdit(): void {
    $this->createExternalEntityType('catalog', 'Catalog', 'catalog');
    $this->createExternalEntityType('catalog_detail', 'Catalog detail', 'catalog/detail');

    $this->drupalGet('admin/structure/external-entity-types/catalog');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'label' => 'Catalog renamed',
      'label_plural' => 'Catalogs renamed',
      'base_path' => 'catalog',
    ], 'Save');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains(static::CONFLICT_MESSAGE);
  }

  /**
   * Tests creating a type whose base path is the parent of a sibling's path.
   */
  public function testParentPathCreationAllowedWhenChildExists(): void {
    $this->createExternalEntityType('catalog_detail', 'Catalog detail', 'catalog/detail');

    $this->drupalGet('admin/structure/external-entity-types/add');
    $this->submitForm([
      'label' => 'Catalog',
      'id' => 'catalog',
      'label_plural' => 'Catalogs',
      'base_path' => 'catalog',
    ], 'Save');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains(static::CONFLICT_MESSAGE);
  }

  /**
   * Tests that an exact base path collision with a sibling is still rejected.
   */
  public function testExactBasePathCollisionRejected(): void {
    $this->createExternalEntityType('catalog_detail', 'Catalog detail', 'catalog/detail');

    $this->drupalGet('admin/structure/external-entity-types/add');
    $this->submitForm([
      'label' => 'Catalog clone',
      'id' => 'catalog_clone',
      'label_plural' => 'Catalog clones',
      'base_path' => 'catalog/detail',
    ], 'Save');

    $this->assertSession()->pageTextContains(static::CONFLICT_MESSAGE);
  }

  /**
   * Tests that a conflict with another module's routes is still rejected.
   */
  public function testForeignModuleRouteConflictRejected(): void {
    $this->drupalGet('admin/structure/external-entity-types/add');
    $this->submitForm([
      'label' => 'System clash',
      'id' => 'system_clash',
      'label_plural' => 'System clashes',
      'base_path' => 'system',
    ], 'Save');

    $this->assertSession()->pageTextContains(static::CONFLICT_MESSAGE);
  }

}
