<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_ui\Kernel\Element;

use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\Tests\ui_patterns_ui\Kernel\UiPatternsUiKernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The rows a display shows to the editor, and the value they give back.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_ui')]
#[RunTestsInSeparateProcesses]
final class ComponentFormDisplayFormTest extends UiPatternsUiKernelTestBase {

  /**
   * Only Content rows with a source build, in weight order.
   */
  public function testRows(): void {
    $form = $this->buildElement($this->element($this->createTestDisplay()->id(), $this->contexts()));
    self::assertSame(['integer', 'string_plain', 'string', 'boolean', 'slot'], Element::children($form));
  }

  /**
   * A fixed widget row carries the widget settings of the display.
   */
  public function testFixedWidgetRow(): void {
    $form = $this->buildElement($this->element($this->createTestDisplay()->id(), $this->contexts()));
    $integer = $form['integer'];
    self::assertSame('container', $integer['#type']);
    $value = $integer['source']['value'];
    self::assertSame('number', $value['#type']);
    self::assertSame('Int title', $value['#title']);
    self::assertSame('after', $value['#title_display']);
    self::assertSame('Int description', $value['#description']);
    self::assertSame('before', $value['#description_display']);
    self::assertTrue((bool) $value['#required']);
    self::assertSame('number', $integer['source_id']['#value']);
    self::assertSame('', $integer['node_id']['#value']);
    self::assertArrayNotHasKey('third_party_settings', $integer);

    $string = $form['string']['source']['value'];
    self::assertSame('Str title', $string['#title']);
    self::assertSame('before', $string['#title_display']);
    self::assertFalse((bool) $string['#required']);
    self::assertSame('invisible', $form['string_plain']['source']['value']['#title_display']);
  }

  /**
   * A fixed source builds its form with the contexts of the host.
   */
  public function testFixedRowGetsContexts(): void {
    $display_id = $this->createDisplay(self::COMPONENT, 'context', ['string_plain' => $this->row('content', 'context_exists')])->id();
    $form = $this->buildElement($this->element($display_id, $this->contexts()));
    self::assertStringContainsString('entity exists: 1', (string) $form['string_plain']['source']['context_exists']['#markup']);
    $form = $this->buildElement($this->element($display_id));
    self::assertStringContainsString('entity exists: <', (string) $form['string_plain']['source']['context_exists']['#markup']);
  }

  /**
   * A source selector row is the prop or slot form, with the stored value.
   */
  public function testSelectorRows(): void {
    $stored = [
      'props' => ['boolean' => ['source_id' => 'checkbox', 'source' => ['value' => TRUE]]],
      'slots' => ['slot' => ['sources' => [['source_id' => 'textfield', 'source' => ['value' => 'stored']]]]],
    ];
    $form = $this->buildElement($this->element($this->createTestDisplay()->id(), $this->contexts(), $stored));
    $boolean = $form['boolean'];
    self::assertSame('component_prop_form', $boolean['#type']);
    self::assertSame('boolean', $boolean['#prop_id']);
    self::assertSame(self::COMPONENT, $boolean['#component_id']);
    self::assertArrayHasKey('entity', $boolean['#source_contexts']);
    self::assertSame('checkbox', $boolean['#default_value']['source_id']);
    self::assertSame('/dialog', $boolean['#ajax_url']->toString());
    $slot = $form['slot'];
    self::assertSame('component_slot_form', $slot['#type']);
    self::assertSame('slot', $slot['#slot_id']);
    self::assertSame(self::COMPONENT, $slot['#component_id']);
    self::assertArrayHasKey('entity', $slot['#source_contexts']);
    self::assertSame('textfield', $slot['#default_value']['sources'][0]['source_id']);
    self::assertSame('/dialog', $slot['#ajax_url']->toString());
  }

  /**
   * A fixed slot row keeps its third party settings and gives one source.
   */
  public function testFixedSlot(): void {
    $display = $this->createDisplay(self::COMPONENT, 'fixed_slot', ['slot' => $this->row('content', 'textfield')]);
    $source = ['source_id' => 'textfield', 'source' => ['value' => 'stored'], 'third_party_settings' => ['x' => 'y']];
    $slot = $this->buildElement($this->element($display->id(), [], ['slots' => ['slot' => ['sources' => [$source]]]]))['slot'];
    self::assertSame('container', $slot['#type']);
    self::assertSame('stored', $slot['source']['value']['#default_value']);
    self::assertSame('value', $slot['third_party_settings']['#type']);
    self::assertSame(['x' => 'y'], $slot['third_party_settings']['#value']);

    $source = ['source_id' => 'textfield', 'source' => ['value' => 'x'], 'node_id' => ''];
    $value = $this->submitElement($this->element($display->id()), ['slot' => $source]);
    self::assertSame([], $value['props']);
    self::assertEquals($source + ['third_party_settings' => []], $value['slots']['slot']['sources'][0]);
    $this->assertComponentSchema($value);
  }

  /**
   * The value is a component value, plus the display ID, valid for the schema.
   */
  public function testValue(): void {
    $display = $this->createTestDisplay();
    $input = [
      'integer' => ['source_id' => 'number', 'source' => ['value' => '7'], 'node_id' => 'n1'],
      'string_plain' => ['source_id' => 'textfield', 'source' => ['value' => 'plain'], 'node_id' => ''],
      'string' => ['source_id' => 'textfield', 'source' => ['value' => 'hello'], 'node_id' => ''],
      'boolean' => ['source_id' => 'checkbox', 'source' => ['value' => TRUE]],
      'slot' => ['sources' => [['source_id' => 'textfield', 'source' => ['value' => 'in slot']]]],
    ];
    $value = $this->submitElement($this->element($display->id(), $this->contexts()), $input);
    self::assertSame(['component_id', 'display_id', 'variant_id', 'props', 'slots'], \array_keys($value));
    self::assertSame(self::COMPONENT, $value['component_id']);
    self::assertSame($display->id(), $value['display_id']);
    self::assertNull($value['variant_id']);
    self::assertSame(['integer', 'string_plain', 'string', 'boolean'], \array_keys($value['props']));
    self::assertSame('7', $value['props']['integer']['source']['value']);
    self::assertSame('n1', $value['props']['integer']['node_id']);
    self::assertSame('in slot', $value['slots']['slot']['sources'][0]['source']['value']);
    // A programmed submission keeps the button values.
    unset($value['slots']['slot']['sources'][0]['_remove']);
    $this->assertComponentSchema($value);
  }

  /**
   * A display covering every kind of row.
   */
  private function createTestDisplay() {
    $widget_settings = [
      'title' => 'Int title',
      'title_display' => 'after',
      'description' => 'Int description',
      'description_display' => 'before',
      'required' => 1,
    ];
    return $this->createDisplay(self::COMPONENT, 'test', [
      'ghost' => $this->row('content', 'textfield', weight: 0),
      'integer' => $this->row('content', 'number', [], $widget_settings, 1),
      'string_plain' => $this->row('content', 'textfield', [], ['title_display' => 'invisible'], 2),
      'string' => $this->row('content', 'textfield', [], ['title' => 'Str title'], 3),
      'boolean' => $this->row('content', '', [], [], 4),
      'slot' => $this->row('content', '', [], [], 5),
      'links' => $this->row('content', weight: 6),
      'variant' => $this->row('configure', 'select', ['value' => 'other'], [], 7),
    ], ['url' => TRUE]);
  }

  /**
   * An entity context, as a field or layout host would give.
   */
  private function contexts(): array {
    $user = User::create(['name' => 'context']);
    $user->save();
    return ['entity' => EntityContext::fromEntity($user)];
  }

  /**
   * The element under test, as a host in a dialog would build it.
   */
  private function element(string $display_id, array $contexts = [], array $stored = []): array {
    return [
      '#type' => 'uip_display_form',
      '#ajax_url' => Url::fromUserInput('/dialog'),
      '#display_id' => $display_id,
      '#component_id' => self::COMPONENT,
      '#source_contexts' => $contexts,
      '#default_value' => $stored + ['component_id' => self::COMPONENT],
    ];
  }

}
