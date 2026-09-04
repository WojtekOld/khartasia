<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Element;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * The #component_filter option of the component_form element.
 *
 * The selector offers the listed components only and keeps the selected
 * one, so a saved configuration stays editable. The filter is not a guard:
 * a caller needing one checks the value itself.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
final class ComponentFormComponentFilterTest extends KernelTestBase {

  private const ALLOWED = 'ui_patterns_test:test-component';

  private const OTHER = 'ui_patterns_test:test-form-component';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ui_patterns',
    'ui_patterns_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'ui_patterns', 'ui_patterns_test']);
  }

  /**
   * The selector offers the listed components, all for NULL, none for [].
   */
  public function testSelectorOptions(): void {
    self::assertSame([self::ALLOWED], $this->selectorOptions([self::ALLOWED]));
    self::assertSame([], $this->selectorOptions([]));
    $all = $this->selectorOptions(NULL);
    self::assertContains(self::ALLOWED, $all);
    self::assertContains(self::OTHER, $all);
  }

  /**
   * The selected component stays in the options, listed or not.
   */
  public function testKeepsSelected(): void {
    self::assertEqualsCanonicalizing([self::ALLOWED, self::OTHER], $this->selectorOptions([self::ALLOWED], self::OTHER));
    self::assertSame([self::OTHER], $this->selectorOptions([], self::OTHER));
    self::assertSame([], $this->submitForm([self::ALLOWED], self::OTHER));
  }

  /**
   * The component IDs the selector offers.
   *
   * @return string[]
   *   The component IDs.
   */
  private function selectorOptions(?array $component_filter, ?string $selected = NULL): array {
    $form_state = new FormState();
    $form = $this->container->get('form_builder')->buildForm($this->formObject($component_filter, $selected), $form_state);
    $options = OptGroup::flattenOptions($form['component']['component_id']['#options']);
    return \array_values(\array_filter(\array_keys($options), static fn ($key): bool => $key !== ''));
  }

  /**
   * Submits a component ID and returns the validation errors.
   *
   * @return array<string, mixed>
   *   The errors keyed by element name.
   */
  private function submitForm(array $component_filter, string $component_id): array {
    $form_state = (new FormState())->setValues(['component' => ['component_id' => $component_id]]);
    $this->container->get('form_builder')->submitForm($this->formObject($component_filter), $form_state);
    return $form_state->getErrors();
  }

  /**
   * A form holding one component_form element.
   */
  private function formObject(?array $component_filter, ?string $selected = NULL): FormInterface {
    return new class($component_filter, $selected) extends FormBase {

      public function __construct(
        protected readonly ?array $componentFilter,
        protected readonly ?string $selected,
      ) {}

      /**
       * {@inheritdoc}
       */
      public function getFormId(): string {
        return 'ui_patterns_test_component_form';
      }

      /**
       * {@inheritdoc}
       */
      public function buildForm(array $form, FormStateInterface $form_state): array {
        $form['component'] = [
          '#type' => 'component_form',
          '#component_filter' => $this->componentFilter,
          '#default_value' => $this->selected ? ['component_id' => $this->selected] : NULL,
        ];
        return $form;
      }

      /**
       * {@inheritdoc}
       */
      public function submitForm(array &$form, FormStateInterface $form_state): void {}

    };
  }

}
