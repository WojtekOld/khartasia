<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Sources get the entity translation being displayed.
 *
 * The entity given to a plugin type is not always that translation: a
 * formatter on an untranslatable field gets the default translation, a views
 * row gets the entity as loaded.
 *
 * @internal
 */
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class DisplayedTranslationTest extends SourcePluginsTestBase {

  private const FIELD_NAME = 'field_shared';

  /**
   * A component showing the node title.
   */
  private const COMPONENT = [
    'component_id' => 'ui_patterns_test:test-component',
    'props' => [
      'string' => [
        'source_id' => 'entity_field',
        'source' => [
          'derivable_context' => 'field:node:page:title',
          'field:node:page:title' => [
            'value' => ['source_id' => 'field_property:node:title:value'],
          ],
        ],
      ],
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['language', 'content_translation'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['language', 'content_translation']);
    ConfigurableLanguage::createFromLangcode('fr')->save();
    $this->container->get('content_translation.manager')->setEnabled('node', 'page', TRUE);
  }

  /**
   * A formatter on an untranslatable field shows the page translation.
   *
   * @param string[] $modules
   *   The modules providing the formatter.
   * @param string $formatter
   *   The formatter.
   */
  #[DataProvider('providerFormatters')]
  public function testFormatterOnUntranslatableField(array $modules, string $formatter): void {
    $this->enableModules($modules);
    $is_source_field = $formatter === 'ui_patterns_source';
    $this->createEntityField('node', 'page', self::FIELD_NAME, $is_source_field ? 'ui_patterns_source' : 'string')
      ->set('translatable', FALSE)
      ->save();
    $this->container->get(EntityDisplayRepositoryInterface::class)
      ->getViewDisplay('node', 'page')
      ->setComponent(self::FIELD_NAME, [
        'type' => $formatter,
        'settings' => $is_source_field ? [] : ['ui_patterns' => self::COMPONENT],
      ])
      ->save();
    $node = $this->createTranslatedNode([
      self::FIELD_NAME => $is_source_field ? ['source_id' => 'component', 'source' => ['component' => self::COMPONENT]] : 'shared',
    ]);

    self::assertStringContainsString('Title EN', $this->renderNode($node));
    $html = $this->renderNode($node->getTranslation('fr'));
    self::assertStringContainsString('Title FR', $html);
    self::assertStringNotContainsString('Title EN', $html);
  }

  /**
   * The formatters building source contexts from field items.
   */
  public static function providerFormatters(): array {
    return [
      'component' => [['ui_patterns_field_formatters'], 'ui_patterns_component'],
      'component per item' => [['ui_patterns_field_formatters'], 'ui_patterns_component_per_item'],
      'source' => [['ui_patterns_field'], 'ui_patterns_source'],
    ];
  }

  /**
   * A views component row follows the view rendering language.
   *
   * @param string $rendering_language
   *   The view rendering language.
   * @param string[] $expected
   *   The titles shown, one per result row.
   */
  #[DataProvider('providerRenderingLanguages')]
  public function testViewsRow(string $rendering_language, array $expected): void {
    $this->enableModules(['views', 'ui_patterns_views']);
    $this->installConfig(['views']);
    $this->createTranslatedNode();
    View::create([
      'id' => 'translations',
      'base_table' => 'node_field_data',
      'base_field' => 'nid',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_plugin' => 'default',
          'position' => 0,
          'display_options' => [
            'row' => ['type' => 'ui_patterns', 'options' => ['ui_patterns' => self::COMPONENT]],
            'sorts' => [
              'langcode' => [
                'id' => 'langcode',
                'table' => 'node_field_data',
                'field' => 'langcode',
                'plugin_id' => 'standard',
              ],
            ],
            'rendering_language' => $rendering_language,
          ],
        ],
      ],
    ])->save();
    $view = Views::getView('translations');
    $build = $view->preview('default');
    $html = (string) $this->container->get('renderer')->renderInIsolation($build);

    \preg_match_all('/Title [A-Z]{2}/', $html, $matches);
    self::assertSame($expected, $matches[0]);
  }

  /**
   * The rendering languages: one per row, or a fixed one.
   */
  public static function providerRenderingLanguages(): array {
    return [
      'row language' => ['***LANGUAGE_entity_translation***', ['Title EN', 'Title FR']],
      'fixed language' => ['fr', ['Title FR', 'Title FR']],
    ];
  }

  /**
   * A views component row also works on a table without entities.
   */
  public function testViewsRowWithoutEntity(): void {
    $this->enableModules(['views', 'ui_patterns_views', 'dblog']);
    $this->installConfig(['views']);
    $this->installSchema('dblog', ['watchdog']);
    $this->container->get('logger.factory')->get('test')->notice('Logged');
    View::create([
      'id' => 'log',
      'base_table' => 'watchdog',
      'base_field' => 'wid',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_plugin' => 'default',
          'position' => 0,
          'display_options' => [
            'fields' => [
              'wid' => ['id' => 'wid', 'table' => 'watchdog', 'field' => 'wid', 'plugin_id' => 'standard'],
            ],
            'row' => [
              'type' => 'ui_patterns',
              'options' => [
                'ui_patterns' => [
                  'component_id' => 'ui_patterns_test:test-component',
                  'props' => ['string' => ['source_id' => 'textfield', 'source' => ['value' => 'Log row']]],
                ],
              ],
            ],
          ],
        ],
      ],
    ])->save();
    $build = Views::getView('log')->preview('default');

    self::assertStringContainsString('Log row', (string) $this->container->get('renderer')->renderInIsolation($build));
  }

  /**
   * Creates a page titled "Title EN", with a French translation.
   */
  private function createTranslatedNode(array $values = []): NodeInterface {
    $node = Node::create(['type' => 'page', 'title' => 'Title EN'] + $values);
    $node->save();
    $node->addTranslation('fr', ['title' => 'Title FR'])->save();
    return $node;
  }

  /**
   * Renders a node translation, as core does for its page.
   */
  private function renderNode(NodeInterface $node): string {
    $build = $this->container->get('entity_type.manager')->getViewBuilder('node')->view($node, 'full', $node->language()->getId());
    return (string) $this->container->get('renderer')->renderInIsolation($build);
  }

}
