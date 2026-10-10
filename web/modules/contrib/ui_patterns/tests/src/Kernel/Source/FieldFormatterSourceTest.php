<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Source;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\node\Entity\Node;
use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\FieldFormatterSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test FieldFormatterSource.
 *
 * @internal
 */
#[CoversClass(FieldFormatterSource::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class FieldFormatterSourceTest extends SourcePluginsTestBase {

  /**
   * Test Field Property Plugin.
   */
  public function testPlugin(): void {
    $testData = self::loadTestDataFixture(__DIR__ . '/../../../fixtures/tests.formatter_per_item.yml');
    $testSets = $testData->getTestSets();

    foreach ($testSets as $test_set_name => $test_set) {
      if (!\str_starts_with($test_set_name, 'field_formatter_')) {
        continue;
      }
      $this->runSourcePluginTest($test_set);
    }
  }

  /**
   * The formatter #states point to this form.
   */
  public function testStatesSelectors(): void {
    $time_diff = $this->timestampSettingsForm([])['time_diff'];
    $selector = \array_key_first($time_diff['future_format']['#states']['visible'][0]);
    self::assertSame(":input[name='{$time_diff['enabled']['#name']}']", $selector);
  }

  /**
   * A nested setting missing from the browser input gets its default.
   */
  public function testMissingNestedSettingGetsDefault(): void {
    $settings = $this->timestampSettingsForm(['time_diff' => ['future_format' => '@interval later']]);
    self::assertFalse($settings['time_diff']['enabled']['#default_value']);
    self::assertSame('@interval later', $settings['time_diff']['future_format']['#default_value']);
  }

  /**
   * Builds the settings form of a timestamp formatter in a slot.
   *
   * @return array
   *   The formatter settings form.
   */
  private function timestampSettingsForm(array $settings): array {
    $node = Node::create(['type' => 'page', 'title' => 'Host']);
    $element = [
      '#type' => 'component_slot_form',
      '#title' => 'Slot',
      '#component_id' => 'ui_patterns_test:test-component',
      '#slot_id' => 'slot',
      '#source_contexts' => [
        'entity' => EntityContext::fromEntity($node),
        'bundle' => new Context(new ContextDefinition('string'), 'page'),
        'field_name' => new Context(new ContextDefinition('string'), 'field_timestamp'),
      ],
      '#default_value' => [
        'sources' => [
          [
            'source_id' => 'field_formatter:node:page:field_timestamp',
            'source' => ['type' => 'timestamp', 'settings' => $settings],
            '_open' => '1',
          ],
        ],
      ],
    ];
    $form_object = new class($element) extends FormBase {

      public function __construct(
        protected readonly array $element,
      ) {}

      /**
       * {@inheritdoc}
       */
      public function getFormId(): string {
        return 'ui_patterns_test_formatter_settings';
      }

      /**
       * {@inheritdoc}
       */
      public function buildForm(array $form, FormStateInterface $form_state): array {
        $form['slot'] = $this->element;
        return $form;
      }

      /**
       * {@inheritdoc}
       */
      public function submitForm(array &$form, FormStateInterface $form_state): void {}

    };
    $form_state = new FormState();
    $form = $this->container->get('form_builder')->buildForm($form_object, $form_state);
    return $form['slot']['sources'][0]['source']['settings'];
  }

}
