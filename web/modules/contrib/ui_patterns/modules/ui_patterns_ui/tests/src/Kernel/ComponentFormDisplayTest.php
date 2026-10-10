<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_ui\Kernel;

use Drupal\ui_patterns_ui\Entity\ComponentFormDisplay;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The component form display entity.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_ui')]
#[RunTestsInSeparateProcesses]
final class ComponentFormDisplayTest extends UiPatternsUiKernelTestBase {

  /**
   * The ID joins the component and the form mode, slots are told from props.
   */
  public function testDefinition(): void {
    $display = $this->createDisplay(self::COMPONENT, 'mode', []);
    self::assertSame('ui_patterns_test.test-component.mode', $display->id());
    self::assertTrue($display->isSlot('slot'));
    self::assertFalse($display->isSlot('string'));
    self::assertFalse($display->isSlot('variant'));
    self::assertFalse($display->isSlot('nope'));
    self::assertSame('string', $display->getPropDefinition('string')['type']);
    self::assertNotNull($display->getPropDefinition('slot'));
    self::assertNull($display->getPropDefinition('nope'));
  }

  /**
   * Rows sink to the bottom, get their defaults, and hide when removed.
   */
  public function testRows(): void {
    $display = $this->createDisplay(self::COMPONENT, 'mode', []);
    $display->setPropSlotOptions('string', ['region' => 'content']);
    $display->setPropSlotOptions('integer', ['region' => 'content']);
    self::assertSame(['region' => 'content', 'weight' => 0, 'widget_settings' => [], 'third_party_settings' => []], $display->getPropSlotOption('string'));
    self::assertSame(1, $display->getPropSlotOption('integer')['weight']);
    self::assertSame(['string', 'integer'], \array_keys($display->getPropSlotOptions()));
    $display->setPropSlotOptions('string', ['region' => 'content', 'weight' => 5]);
    self::assertSame(['integer', 'string'], \array_keys($display->getPropSlotOptions()));
    $display->removePropSlotOption('string');
    self::assertNull($display->getPropSlotOption('string'));
    self::assertSame(['string' => TRUE], $display->get('hidden'));
  }

  /**
   * The admin form stores weights as strings; the next row still sinks.
   */
  public function testHighestWeightAfterSave(): void {
    $display = $this->createDisplay(self::COMPONENT, 'mode', [
      'string' => $this->row('content', 'textfield', weight: 10),
      'integer' => $this->row('content', 'number', weight: 2),
    ]);
    $display = ComponentFormDisplay::load($display->id());
    self::assertSame(10, $display->getHighestWeight());
    $display->setPropSlotOptions('boolean', ['region' => 'content']);
    self::assertSame(11, $display->getPropSlotOption('boolean')['weight']);
    self::assertNull($this->createDisplay(self::COMPONENT, 'empty', [])->getHighestWeight());
  }

  /**
   * The selected source: none, the prop type default, or the given ID.
   */
  public function testSelectedSourcePlugin(): void {
    $display = $this->createDisplay(self::COMPONENT, 'mode', [
      'string' => $this->row('content', ''),
      'integer' => $this->row('content'),
      'url' => $this->row('content', 'token', [], ['title' => 'Token']),
    ]);
    self::assertNull($display->getSelectedSourcePlugin('string'));
    self::assertSame('number', $display->getSelectedSourcePlugin('integer')?->getPluginId());
    $token = $display->getSelectedSourcePlugin('url');
    self::assertSame('token', $token?->getPluginId());
    self::assertSame('Token', $token->getConfiguration()['widget_settings']['title']);
    self::assertSame('before', $token->getConfiguration()['widget_settings']['title_display']);
    self::assertSame('url', $token->getConfiguration()['prop_id']);
  }

  /**
   * Loading by form mode, and the default display of a component.
   */
  public function testLoad(): void {
    $beta = $this->createDisplay(self::COMPONENT, 'beta', []);
    $alpha = $this->createDisplay(self::COMPONENT, 'alpha', []);
    self::assertSame($beta->id(), ComponentFormDisplay::loadByFormMode(self::COMPONENT, 'beta')?->id());
    self::assertNull(ComponentFormDisplay::loadByFormMode(self::COMPONENT, 'nope'));
    self::assertSame($alpha->id(), ComponentFormDisplay::loadDefault(self::COMPONENT)?->id());
    self::assertNull(ComponentFormDisplay::loadDefault('ui_patterns_test:test-form-component'));
  }

  /**
   * A host config keeps its schema with the display ID inside the component.
   */
  public function testHostSchema(): void {
    $this->assertComponentSchema([
      'component_id' => self::COMPONENT,
      'display_id' => 'ui_patterns_test.test-component.mode',
      'variant_id' => NULL,
      'props' => [],
      'slots' => [],
    ]);
  }

}
