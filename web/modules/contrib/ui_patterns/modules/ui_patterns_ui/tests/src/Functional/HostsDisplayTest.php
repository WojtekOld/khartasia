<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_ui\Functional;

use Behat\Mink\Driver\BrowserKitDriver;
use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\editor\Entity\Editor;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Section;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\ui_patterns_ui\Traits\ComponentFormDisplayCreationTrait;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Every host shows the plain form, the display alone, or the selector.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_ui')]
#[RunTestsInSeparateProcesses]
final class HostsDisplayTest extends BrowserTestBase {

  use ComponentFormDisplayCreationTrait;

  private const COMPONENT = 'ui_patterns_test:test-component';

  private const FORMAT = 'component_test';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'block',
    'field',
    'field_ui',
    'layout_builder',
    'views',
    'views_ui',
    'ckeditor5',
    'ui_patterns',
    'ui_patterns_test',
    'ui_patterns_blocks',
    'ui_patterns_layouts',
    'ui_patterns_field_formatters',
    'ui_patterns_views',
    'ui_patterns_ckeditor5',
    'ui_patterns_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The IDs of the displays created so far.
   *
   * @var string[]
   */
  private array $displays = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $component = ['component_id' => self::COMPONENT, 'variant_id' => NULL, 'props' => [], 'slots' => []];
    $this->drupalCreateContentType(['type' => 'page']);

    // A field formatter on the default display, a layout builder section on
    // the teaser: layout builder hides the field table of its display.
    FieldStorageConfig::create([
      'field_name' => 'field_ref',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
    ])->save();
    FieldConfig::create(['field_name' => 'field_ref', 'entity_type' => 'node', 'bundle' => 'page'])->save();
    LayoutBuilderEntityViewDisplay::load('node.page.default')
      ->setComponent('field_ref', ['type' => 'ui_patterns_component', 'settings' => ['ui_patterns' => $component]])
      ->save();
    LayoutBuilderEntityViewDisplay::load('node.page.teaser')
      ->enableLayoutBuilder()
      ->removeAllSections()
      ->appendSection(new Section('ui_patterns:ui_patterns_test:test_component', ['ui_patterns' => $component]))
      ->save();

    // A view styled by the component.
    $view = View::load('frontpage');
    $options = &$view->getDisplay('default');
    $options['display_options']['style'] = [
      'type' => 'ui_patterns',
      'options' => ['ui_patterns' => ['ui_patterns' => $component]],
    ];
    $view->save();

    // A text format with the editor dialog.
    FilterFormat::create([
      'format' => self::FORMAT,
      'name' => 'Component test',
      'filters' => ['component_embed' => ['status' => TRUE, 'weight' => 100]],
    ])->save();
    Editor::create([
      'format' => self::FORMAT,
      'editor' => 'ckeditor5',
      'settings' => ['toolbar' => ['items' => ['drupalComponent']], 'plugins' => []],
      'image_upload' => ['status' => FALSE],
    ])->save();

    $this->drupalLogin($this->drupalCreateUser([
      'administer blocks',
      'administer node display',
      'configure any layout',
      'administer views',
      'use text format ' . self::FORMAT,
    ]));
  }

  /**
   * Zero, one, then two displays, on every host.
   */
  public function testHosts(): void {
    $hosts = [
      'block' => [
        fn () => $this->drupalGet(Url::fromRoute('block.admin_add', [
          'plugin_id' => 'ui_patterns:' . self::COMPONENT,
          'theme' => 'stark',
        ])),
        'settings[ui_patterns]',
      ],
      'layout builder section' => [
        fn () => $this->drupalGet('layout_builder/configure/section/defaults/node.page.teaser/0'),
        'layout_settings[ui_patterns]',
      ],
      'field formatter' => [
        function (): void {
          $this->drupalGet('admin/structure/types/manage/page/display/default');
          $this->submitForm([], 'field_ref_settings_edit');
        },
        'fields[field_ref][settings_edit_form][settings][ui_patterns]',
      ],
      'views style' => [
        fn () => $this->drupalGet('admin/structure/views/nojs/display/frontpage/default/style_options'),
        'style_options[ui_patterns][ui_patterns]',
      ],
      'ckeditor5 dialog' => [
        fn () => $this->openDialog(),
        'component',
      ],
    ];
    foreach ([0, 1, 2] as $count) {
      if ($count > 0) {
        $this->displays[] = $this->createDisplay(self::COMPONENT, 'mode' . $count, ['string' => $this->row('content', 'textfield')])->id();
      }
      foreach ($hosts as $label => [$open, $prefix]) {
        $open();
        $this->assertHost("{$label} with {$count} display(s)", $prefix, $count);
      }
    }
  }

  /**
   * Opens the editor dialog on the component, without JavaScript.
   */
  private function openDialog(): void {
    $url = 'editor/dialog/ui_patterns_ckeditor5/' . self::FORMAT;
    $this->drupalGet($url);
    $page = $this->getSession()->getPage();
    $driver = $this->getSession()->getDriver();
    \assert($driver instanceof BrowserKitDriver);
    $driver->getClient()->request('POST', $this->getAbsoluteUrl($url), [
      'form_id' => 'ui_patterns_ckeditor5_dialog_form',
      'form_build_id' => $page->find('css', 'input[name="form_build_id"]')->getValue(),
      'form_token' => $page->find('css', 'input[name="form_token"]')->getValue(),
      // The selector triggers the rebuild, not the Embed button.
      '_triggering_element_name' => 'component[component_id]',
      'component' => ['component_id' => self::COMPONENT],
    ]);
  }

  /**
   * The form of a host, for the displays created so far.
   */
  private function assertHost(string $label, string $prefix, int $count): void {
    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);
    $plain = $prefix . '[props][string][source][value]';
    if ($count === 0) {
      $assert->elementNotExists('css', '[name="' . $prefix . '[display][display_id]"]');
      $assert->fieldExists($plain);
      return;
    }
    // The first display by label is the one opened.
    $assert->hiddenFieldValueEquals($prefix . '[display][display_id]', $this->displays[0]);
    $assert->fieldExists($prefix . '[display][value_' . Html::getId($this->displays[0]) . '][string][source][value]');
    $assert->fieldNotExists($plain);
    $select = $this->getSession()->getPage()->find('css', 'select[name="' . $prefix . '[display_id]"]');
    if ($count === 1) {
      self::assertNull($select, $label);
      return;
    }
    self::assertNotNull($select, $label);
    $options = \array_map(static fn ($option) => $option->getValue(), $select->findAll('css', 'option'));
    self::assertSame($this->displays, $options, $label);
  }

}
