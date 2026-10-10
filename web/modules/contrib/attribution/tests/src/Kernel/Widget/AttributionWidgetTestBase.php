<?php

declare(strict_types=1);

namespace Drupal\Tests\attribution\Kernel\Widget;

use Drupal\attribution\Plugin\Field\FieldType\AttributionItem;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormState;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;

/**
 * Shared kernel test base for all Attribution field widgets.
 */
abstract class AttributionWidgetTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'attribution',
    'entity_test',
    'field',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'attribution']);

    FieldStorageConfig::create([
      'field_name' => 'field_attribution',
      'entity_type' => 'entity_test',
      'type' => 'attribution',
      'cardinality' => 2,
    ])->save();

    FieldConfig::create([
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'field_name' => 'field_attribution',
      'label' => 'Attribution',
    ])->save();
  }

  /**
   * Returns the widget plugin ID under test.
   */
  abstract protected function getWidgetPluginId(): string;

  /**
   * Runs all widget assertions in a single process.
   */
  public function testWidgetBehavior(): void {
    $this->assertDetailsWrapperPresent();
    $this->assertCreationTypeElementsPresent();
    $this->assertStatesSelectorsMatchDeltaAndFieldName();
    $this->assertMassageFormValuesConvertsEmptyStringsToNull();
    $this->assertMassageFormValuesClearsStaleAiFields();
    $this->assertWidgetSpecificElementsPresent();
  }

  /**
   * Asserts the widget-specific elements (source/author/license) present.
   *
   * Each concrete widget nests a different combination of 'source', 'author'
   * and 'license' elements; only the concrete widget test class knows which
   * ones apply.
   */
  abstract protected function assertWidgetSpecificElementsPresent(): void;

  // -------------------------------------------------------------------------
  // Sub-assertions
  // -------------------------------------------------------------------------

  /**
   * Asserts the shared 'details' wrapper every concrete widget builds.
   */
  protected function assertDetailsWrapperPresent(): void {
    $element = $this->buildFormElement($this->getEmptyItems(), 0);
    $this->assertSame('details', $element['#type']);
    $this->assertSame([], $element['#summary_attributes']);
    $this->assertTrue($element['#open']);
  }

  /**
   * Asserts that creation_type and the conditional AI fields are present.
   */
  protected function assertCreationTypeElementsPresent(): void {
    $element = $this->buildFormElement($this->getEmptyItems(), 0);

    $this->assertSame('select', $element['creation_type']['#type']);
    $this->assertArrayHasKey(AttributionItem::CREATION_TYPE_AI_GENERATED, $element['creation_type']['#options']);
    $this->assertArrayHasKey(AttributionItem::CREATION_TYPE_AI_MODIFIED, $element['creation_type']['#options']);

    $this->assertSame('textfield', $element['ai_tool']['#type']);
    $this->assertArrayHasKey('#states', $element['ai_tool']);
    $this->assertSame('textarea', $element['ai_prompt']['#type']);
    $this->assertArrayHasKey('#states', $element['ai_prompt']);
    $this->assertSame('container', $element['prompt_editor']['#type']);
    $this->assertContains('container-inline', $element['prompt_editor']['#attributes']['class']);
    $this->assertArrayHasKey('#states', $element['prompt_editor']);
    $this->assertSame('textfield', $element['prompt_editor']['prompt_editor_name']['#type']);
    $this->assertSame('url', $element['prompt_editor']['prompt_editor_link']['#type']);
  }

  /**
   * Asserts the #states selectors reference the correct field name and delta.
   */
  protected function assertStatesSelectorsMatchDeltaAndFieldName(): void {
    foreach ([0, 1] as $delta) {
      $element = $this->buildFormElement($this->getEmptyItems(), $delta);

      $expected_selector = ':input[name="field_attribution[' . $delta . '][creation_type]"]';

      $ai_tool_conditions = $element['ai_tool']['#states']['visible'];
      $this->assertSame($expected_selector, array_key_first($ai_tool_conditions[0]));
      $this->assertSame(AttributionItem::CREATION_TYPE_AI_GENERATED, $ai_tool_conditions[0][$expected_selector]['value']);
      $this->assertSame(AttributionItem::CREATION_TYPE_AI_MODIFIED, $ai_tool_conditions[2][$expected_selector]['value']);

      $prompt_editor_conditions = $element['prompt_editor']['#states']['visible'];
      $this->assertSame($expected_selector, array_key_first($prompt_editor_conditions[0]));
      $this->assertSame(AttributionItem::CREATION_TYPE_AI_GENERATED, $prompt_editor_conditions[0][$expected_selector]['value']);
      $this->assertSame(AttributionItem::CREATION_TYPE_AI_MODIFIED, $prompt_editor_conditions[2][$expected_selector]['value']);
    }
  }

  /**
   * Asserts that empty strings are converted to NULL for all flat properties.
   */
  protected function assertMassageFormValuesConvertsEmptyStringsToNull(): void {
    $widget = $this->getWidgetInstance();
    $values = [0 => $this->rawSubmittedValues([])];

    $massaged = $widget->massageFormValues($values, [], new FormState());

    $this->assertNull($massaged[0]['license']);
    $this->assertNull($massaged[0]['creation_type']);
    $this->assertNull($massaged[0]['ai_tool']);
    $this->assertNull($massaged[0]['ai_prompt']);
    $this->assertNull($massaged[0]['prompt_editor_name']);
    $this->assertNull($massaged[0]['prompt_editor_link']);
  }

  /**
   * Asserts stale AI values are cleared when not warranted by creation_type.
   */
  protected function assertMassageFormValuesClearsStaleAiFields(): void {
    $widget = $this->getWidgetInstance();

    $values = [
      0 => $this->rawSubmittedValues([
        'creation_type' => AttributionItem::CREATION_TYPE_HUMAN,
        'ai_tool' => 'Stale tool',
        'ai_prompt' => 'Stale prompt',
        'prompt_editor' => ['prompt_editor_name' => 'Stale editor', 'prompt_editor_link' => 'https://stale.example.org'],
      ]),
    ];
    $massaged = $widget->massageFormValues($values, [], new FormState());
    $this->assertSame(AttributionItem::CREATION_TYPE_HUMAN, $massaged[0]['creation_type']);
    $this->assertNull($massaged[0]['ai_tool']);
    $this->assertNull($massaged[0]['ai_prompt']);
    $this->assertNull($massaged[0]['prompt_editor_name']);
    $this->assertNull($massaged[0]['prompt_editor_link']);

    $values = [
      0 => $this->rawSubmittedValues([
        'creation_type' => AttributionItem::CREATION_TYPE_AI_GENERATED,
        'ai_tool' => 'Midjourney',
        'ai_prompt' => 'A watercolor fox',
        'prompt_editor' => ['prompt_editor_name' => 'Jane Doe', 'prompt_editor_link' => 'https://editor.example.org'],
      ]),
    ];
    $massaged = $widget->massageFormValues($values, [], new FormState());
    $this->assertSame('Midjourney', $massaged[0]['ai_tool']);
    $this->assertSame('A watercolor fox', $massaged[0]['ai_prompt']);
    $this->assertSame('Jane Doe', $massaged[0]['prompt_editor_name']);
    $this->assertSame('https://editor.example.org', $massaged[0]['prompt_editor_link']);

    $values = [
      0 => $this->rawSubmittedValues([
        'creation_type' => AttributionItem::CREATION_TYPE_AI_MODIFIED,
        'ai_tool' => 'Midjourney',
        'ai_prompt' => 'A watercolor fox',
        'prompt_editor' => ['prompt_editor_name' => 'Jane Doe', 'prompt_editor_link' => 'https://editor.example.org'],
      ]),
    ];
    $massaged = $widget->massageFormValues($values, [], new FormState());
    $this->assertSame('Midjourney', $massaged[0]['ai_tool']);
    $this->assertSame('Jane Doe', $massaged[0]['prompt_editor_name']);
    $this->assertSame('https://editor.example.org', $massaged[0]['prompt_editor_link']);
  }

  // -------------------------------------------------------------------------
  // Helpers
  // -------------------------------------------------------------------------

  /**
   * Returns an empty attribution field item list to build form elements from.
   */
  protected function getEmptyItems(): FieldItemListInterface {
    $entity = EntityTest::create();
    $entity->set('field_attribution', []);
    return $entity->get('field_attribution');
  }

  /**
   * Instantiates the widget under test.
   */
  protected function getWidgetInstance(): WidgetInterface {
    assert($this->container !== NULL);
    $field_definition = FieldConfig::loadByName('entity_test', 'entity_test', 'field_attribution');
    $widget = $this->container->get('plugin.manager.field.widget')->getInstance([
      'field_definition' => $field_definition,
      'configuration' => [
        'type' => $this->getWidgetPluginId(),
        'settings' => [],
        'third_party_settings' => [],
      ],
    ]);
    $this->assertInstanceOf(WidgetInterface::class, $widget);
    return $widget;
  }

  /**
   * Builds the widget's form element for a single delta.
   */
  protected function buildFormElement(FieldItemListInterface $items, int $delta): array {
    $widget = $this->getWidgetInstance();
    $element = [
      '#field_parents' => [],
    ];
    $form = ['#parents' => []];
    return $widget->formElement($items, $delta, $element, $form, new FormState());
  }

  /**
   * Builds a raw submitted-values array as massageFormValues() would receive.
   *
   * Includes keys for every possible sub-field across all 4 widget variants;
   * massageFormValues() only reads the keys relevant to the widget under
   * test, so the extra keys are harmless for widgets that don't use them.
   */
  protected function rawSubmittedValues(array $overrides): array {
    return $overrides + [
      'source' => ['source_name' => '', 'source_link' => ''],
      'author' => ['author_name' => '', 'author_link' => ''],
      'license' => '',
      'creation_type' => '',
      'ai_tool' => '',
      'ai_prompt' => '',
      'prompt_editor' => ['prompt_editor_name' => '', 'prompt_editor_link' => ''],
    ];
  }

}
