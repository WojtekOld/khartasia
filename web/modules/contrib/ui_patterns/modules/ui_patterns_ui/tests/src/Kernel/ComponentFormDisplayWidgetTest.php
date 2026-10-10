<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_ui\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\ui_patterns_ui\Entity\ComponentFormDisplay;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The field widget pinned on a display.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_ui')]
#[RunTestsInSeparateProcesses]
final class ComponentFormDisplayWidgetTest extends UiPatternsUiKernelTestBase {

  private const WIDGET = 'ui_patterns_ui_component_form_display';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'entity_test',
    'ui_patterns_field',
  ];

  /**
   * The ID of the display labelled Alpha.
   */
  private string $alpha;

  /**
   * The ID of the display labelled Beta.
   */
  private string $beta;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
    FieldStorageConfig::create([
      'field_name' => 'field_component',
      'entity_type' => 'entity_test',
      'type' => 'ui_patterns_source',
    ])->save();
    FieldConfig::create(['field_name' => 'field_component', 'entity_type' => 'entity_test', 'bundle' => 'entity_test'])->save();
    $row = ['string' => $this->row('content', 'textfield')];
    $this->beta = $this->createDisplay(self::COMPONENT, 'beta', $row)->id();
    $this->alpha = $this->createDisplay(self::COMPONENT, 'alpha', $row)->id();
    $this->createDisplay(self::COMPONENT, 'off', $row, [], FALSE);
  }

  /**
   * The settings offer the enabled displays, dots escaped for storage.
   */
  public function testSettings(): void {
    $widget = $this->widget(['display_id' => $this->escape($this->beta)]);
    $form = $widget->settingsForm([], new FormState());
    $options = [
      $this->escape($this->alpha) => '[' . self::COMPONENT . '] Alpha',
      $this->escape($this->beta) => '[' . self::COMPONENT . '] Beta',
    ];
    self::assertSame($options, $form['display_id']['#options']);
    self::assertSame($options, $form['additional_display_ids']['#options']);
    self::assertSame('checkbox', $form['display_component_form']['#type']);
    self::assertSame('Display Id: ' . $this->beta, (string) $widget->settingsSummary()[0]);
  }

  /**
   * The element is the display selector, pinned on the component, in context.
   */
  public function testFormElement(): void {
    $settings = [
      'display_id' => $this->escape($this->beta),
      'additional_display_ids' => [$this->escape($this->alpha) => $this->escape($this->alpha)],
      'display_component_form' => TRUE,
    ];
    $element = $this->formElement($settings, []);
    self::assertArrayNotHasKey('#access', $element);
    self::assertSame('component', $element['source_id']['#value']);
    $component = $element['source']['component'];
    self::assertSame('uip_displays_form', $component['#type']);
    self::assertSame(self::COMPONENT, $component['#component_id']);
    self::assertSame([$this->beta, $this->alpha], $component['#include_display_ids']);
    self::assertTrue($component['#display_component_form']);
    self::assertSame($this->beta, $component['#default_value']['display_id']);
    self::assertArrayHasKey('entity', $component['#source_contexts']);
    self::assertArrayHasKey('bundle', $component['#source_contexts']);

    $stored = [
      'component_id' => self::COMPONENT,
      'display_id' => $this->alpha,
      'props' => ['string' => ['source_id' => 'textfield', 'source' => ['value' => 'kept']]],
    ];
    $element = $this->formElement($settings, ['source_id' => 'component', 'source' => ['component' => $stored]]);
    self::assertSame($this->alpha, $element['source']['component']['#default_value']['display_id']);
    self::assertSame('kept', $element['source']['component']['#default_value']['props']['string']['source']['value']);
  }

  /**
   * The widget steps aside when it cannot edit the item.
   */
  public function testHidden(): void {
    $settings = ['display_id' => $this->escape($this->beta)];
    self::assertFalse($this->formElement($settings, ['source_id' => 'textfield', 'source' => ['value' => 'x']])['#access']);
    self::assertFalse($this->formElement(['display_id' => NULL], [])['#access']);
    ComponentFormDisplay::load($this->beta)->delete();
    self::assertFalse($this->formElement($settings, [])['#access']);
  }

  /**
   * The element the widget builds for the first item.
   */
  private function formElement(array $settings, array $item): array {
    $entity = EntityTest::create(['field_component' => $item === [] ? [] : [$item]]);
    $items = $entity->get('field_component');
    if ($items->isEmpty()) {
      // The widget form appends the empty item itself.
      $items->appendItem();
    }
    $form = ['#parents' => []];
    return $this->widget($settings)->formElement($items, 0, [], $form, new FormState());
  }

  /**
   * A widget instance with the given settings.
   */
  private function widget(array $settings) {
    $field = FieldConfig::loadByName('entity_test', 'entity_test', 'field_component');
    $configuration = ['field_definition' => $field, 'settings' => $settings, 'third_party_settings' => []];
    return $this->container->get('plugin.manager.field.widget')->createInstance(self::WIDGET, $configuration);
  }

  /**
   * A display ID as the widget settings store it.
   */
  private function escape(string $display_id): string {
    return \str_replace('.', '||', $display_id);
  }

}
