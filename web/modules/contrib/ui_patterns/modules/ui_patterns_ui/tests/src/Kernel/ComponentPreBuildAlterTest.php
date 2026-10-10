<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_ui\Kernel;

use Drupal\ui_patterns_ui\Hook\ComponentPreBuildAlter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * At render time, the Configured rows of a display replace the stored value.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_ui')]
#[RunTestsInSeparateProcesses]
final class ComponentPreBuildAlterTest extends UiPatternsUiKernelTestBase {

  /**
   * Configured rows win; Content and hidden rows stay as stored.
   */
  public function testConfiguredRowsWin(): void {
    $display = $this->createTestDisplay();
    $element = $this->element($display->id());
    $this->alter($element);
    $configuration = $element['#ui_patterns'];
    self::assertSame(['source' => ['value' => 'other'], 'source_id' => 'select'], $configuration['variant_id']);
    self::assertSame(['source' => ['value' => 'fixed'], 'source_id' => 'textfield'], $configuration['props']['string']);
    self::assertSame(['source_id' => 'textfield', 'source' => ['value' => 'plain']], $configuration['props']['string_plain']);
    self::assertSame(['source_id' => 'number', 'source' => ['value' => 3]], $configuration['props']['integer']);
    self::assertSame([['source' => ['value' => 'fixed slot'], 'source_id' => 'textfield']], $configuration['slots']['slot']['sources']);
    self::assertContains('config:' . $display->getConfigDependencyName(), $element['#cache']['tags']);
  }

  /**
   * The cache metadata of the display reaches the element.
   *
   * On a multilingual site, the display varies by interface language.
   */
  public function testDisplayCacheMetadata(): void {
    $this->enableModules(['language']);
    $this->installConfig(['language']);
    $element = $this->element($this->createTestDisplay()->id());
    $this->alter($element);
    self::assertContains('languages:language_interface', $element['#cache']['contexts'] ?? []);
  }

  /**
   * Without a loadable display, or without a component, nothing changes.
   */
  public function testUntouched(): void {
    $this->createTestDisplay();
    foreach ([NULL, 'ui_patterns_test.test-component.nope'] as $display_id) {
      $element = $this->element($display_id);
      $expected = $element;
      $this->alter($element);
      self::assertSame($expected, $element);
    }
    $element = $this->element($this->createTestDisplay('other')->id());
    unset($element['#component']);
    $expected = $element;
    $this->alter($element);
    self::assertSame($expected, $element);
  }

  /**
   * A disabled display is no longer offered, but what was saved still renders.
   */
  public function testDisabledDisplayStillApplies(): void {
    $display = $this->createTestDisplay();
    $display->setStatus(FALSE)->save();
    $element = $this->element($display->id());
    $this->alter($element);
    self::assertSame('fixed', $element['#ui_patterns']['props']['string']['source']['value']);
  }

  /**
   * The rendered component shows the Configured values.
   */
  public function testRender(): void {
    $display = $this->createTestDisplay();
    $build = ['#type' => 'component', '#component' => self::COMPONENT] + $this->element($display->id());
    $html = (string) $this->container->get('renderer')->renderInIsolation($build);
    self::assertStringContainsString('ui-patterns-test-component-variant-other', $html);
    self::assertMatchesRegularExpression('/ui-patterns-props-string"[^>]*>\s*fixed/', $html);
    self::assertMatchesRegularExpression('/ui-patterns-props-string_plain"[^>]*>\s*plain/', $html);
    self::assertMatchesRegularExpression('/ui-patterns-slots-slot"[^>]*>\s*fixed slot/', $html);
  }

  /**
   * A display fixing the variant, a prop and a slot, showing another prop.
   */
  private function createTestDisplay(string $mode = 'test') {
    return $this->createDisplay(self::COMPONENT, $mode, [
      'variant' => $this->row('configure', 'select', ['value' => 'other']),
      'string' => $this->row('configure', 'textfield', ['value' => 'fixed'], [], 1),
      'slot' => $this->row('configure', 'textfield', ['value' => 'fixed slot'], [], 2),
      'string_plain' => $this->row('content', 'textfield', [], [], 3),
    ], ['integer' => TRUE]);
  }

  /**
   * A stored value trying to set everything the display fixes.
   */
  private function element(?string $display_id): array {
    $textfield = static fn (string $value): array => ['source_id' => 'textfield', 'source' => ['value' => $value]];
    $configuration = [
      'component_id' => self::COMPONENT,
      'variant_id' => ['source_id' => 'select', 'source' => ['value' => 'default']],
      'props' => [
        'string' => $textfield('editor'),
        'string_plain' => $textfield('plain'),
        'integer' => ['source_id' => 'number', 'source' => ['value' => 3]],
      ],
      'slots' => ['slot' => ['sources' => [$textfield('editor slot'), $textfield('second')]]],
    ];
    if ($display_id !== NULL) {
      $configuration['display_id'] = $display_id;
    }
    return ['#component' => self::COMPONENT, '#ui_patterns' => $configuration];
  }

  /**
   * Runs the pre-build alter hook on the element.
   */
  private function alter(array &$element): void {
    $this->container->get(ComponentPreBuildAlter::class)->uiPatternsComponentPreBuildAlter($element);
  }

}
