<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Source;

use Drupal\Core\Form\FormState;
use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\ComponentSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test ComponentSource.
 *
 * @internal
 */
#[CoversClass(ComponentSource::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class ComponentSourceTest extends SourcePluginsTestBase {

  /**
   * Test ComponentSource Plugin.
   */
  public function testPlugin(): void {
    $this->runSourcePluginTests('component_');
  }

  /**
   * The settings of a choice are read back as that choice.
   *
   * Read back by getChoice(), which names the source in a component summary.
   */
  public function testGetChoiceIsReadBack(): void {
    $source = $this->sourcePluginManager()->getSource('slot', [], [
      'source_id' => 'component',
      'source' => [],
    ]);
    self::assertInstanceOf(ComponentSource::class, $source);

    $settings = $source->getChoiceSettings('ui_patterns_test:test-component');

    self::assertSame('ui_patterns_test:test-component', $source->getChoice($settings));
  }

  /**
   * The slot expected list becomes the #component_filter of the nested form.
   *
   * IDs pass as is, a tag resolves to the components carrying it, a slot
   * without expected list filters nothing.
   */
  public function testExpectedBecomesComponentFilter(): void {
    $slots = $this->componentManager()->getDefinition('ui_patterns_test:test-slot-constraints')['slots'];
    $configuration = ['source_id' => 'component', 'source' => []];

    $form = $this->sourcePluginManager()->getSource('slot_expected', $slots['slot_expected'], $configuration)->settingsForm([], new FormState());
    self::assertEqualsCanonicalizing([
      'ui_patterns_test:test-form-component',
      'ui_patterns_test:test-wrapper-component',
      'ui_patterns_test:no-ui-component',
    ], $form['component']['#component_filter']);

    $form = $this->sourcePluginManager()->getSource('slot_free', $slots['slot_free'], $configuration)->settingsForm([], new FormState());
    self::assertNull($form['component']['#component_filter']);
  }

}
