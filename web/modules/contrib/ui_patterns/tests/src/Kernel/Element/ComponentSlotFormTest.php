<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Element;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\Core\Render\Element;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Slot maxItems and expected in the component_slot_form element.
 *
 * MaxItems disables the add select once reached and never drops a saved
 * source. expected filters the nested Component selector.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
final class ComponentSlotFormTest extends KernelTestBase {

  private const COMPONENT = 'ui_patterns_test:test-slot-constraints';

  private const EXPECTED_BY_ID = 'ui_patterns_test:test-form-component';

  private const EXPECTED_BY_TAG = 'ui_patterns_test:test-wrapper-component';

  private const HIDDEN_WITH_TAG = 'ui_patterns_test:no-ui-component';

  private const OTHER = 'ui_patterns_test:test-component';

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
   * The add select is disabled once the slot holds maxItems sources.
   */
  public function testAddSelectDisabledAtMaxItems(): void {
    self::assertFalse($this->addSelect('slot_max', 0)['#disabled'] ?? FALSE);
    self::assertFalse($this->addSelect('slot_max', 1)['#disabled'] ?? FALSE);
    self::assertTrue($this->addSelect('slot_max', 2)['#disabled']);
  }

  /**
   * A saved slot over maxItems keeps every source, only adding is blocked.
   */
  public function testOverfullSlotKeepsSources(): void {
    $slot = $this->slotForm('slot_max', 3);
    self::assertCount(3, Element::children($slot['sources']));
    self::assertTrue($slot['add_more_button']['#disabled']);
  }

  /**
   * A slot without maxItems never disables the add select.
   */
  public function testNoLimitWithoutMaxItems(): void {
    self::assertFalse($this->addSelect('slot_free', 5)['#disabled'] ?? FALSE);
  }

  /**
   * An explicit #max_items beats the slot definition.
   */
  public function testExplicitMaxItemsWins(): void {
    self::assertTrue($this->addSelect('slot_free', 1, ['#max_items' => 1])['#disabled']);
    self::assertFalse($this->addSelect('slot_max', 2, ['#max_items' => 3])['#disabled'] ?? FALSE);
  }

  /**
   * A single-cardinality slot keeps hiding the add select after one source.
   */
  public function testSingleCardinalityUnchanged(): void {
    $slot = $this->slotForm('slot_max', 1, ['#cardinality_multiple' => FALSE]);
    self::assertArrayNotHasKey('add_more_button', $slot);
  }

  /**
   * The nested Component selector offers the expected IDs and tags only.
   *
   * A hidden component keeps hidden even when it carries an expected tag.
   */
  public function testNestedSelectorOffersExpected(): void {
    self::assertEqualsCanonicalizing([self::EXPECTED_BY_ID, self::EXPECTED_BY_TAG], $this->nestedOptions('slot_expected'));
    $all = $this->nestedOptions('slot_free');
    self::assertContains(self::OTHER, $all);
    self::assertContains(self::EXPECTED_BY_ID, $all);
    self::assertNotContains(self::HIDDEN_WITH_TAG, $all);
  }

  /**
   * A saved component outside the expected list stays selectable.
   */
  public function testNestedSelectorKeepsSaved(): void {
    self::assertEqualsCanonicalizing([self::EXPECTED_BY_ID, self::EXPECTED_BY_TAG, self::OTHER], $this->nestedOptions('slot_expected', self::OTHER));
  }

  /**
   * The add select of a built slot form.
   */
  private function addSelect(string $slot_id, int $n_sources, array $overrides = []): array {
    $slot = $this->slotForm($slot_id, $n_sources, $overrides);
    self::assertSame('select', $slot['add_more_button']['#type']);
    return $slot['add_more_button'];
  }

  /**
   * The component IDs the nested Component selector of the first source offers.
   *
   * @return string[]
   *   The component IDs.
   */
  private function nestedOptions(string $slot_id, ?string $selected = NULL): array {
    $slot = $this->slotForm($slot_id, 1, [], $selected);
    $options = OptGroup::flattenOptions($slot['sources'][0]['source']['component']['component_id']['#options']);
    return \array_values(\array_filter(\array_keys($options), static fn ($key): bool => $key !== ''));
  }

  /**
   * Builds a slot form holding $n_sources Component sources.
   */
  private function slotForm(string $slot_id, int $n_sources, array $overrides = [], ?string $selected = NULL): array {
    $source = ['source_id' => 'component', 'source' => []];
    if ($selected !== NULL) {
      $source['source']['component'] = ['component_id' => $selected];
    }
    $element = $overrides + [
      '#type' => 'component_slot_form',
      '#title' => $slot_id,
      '#component_id' => self::COMPONENT,
      '#slot_id' => $slot_id,
      '#default_value' => ['sources' => \array_fill(0, $n_sources, $source)],
    ];
    $form_state = new FormState();
    $form = $this->container->get('form_builder')->buildForm($this->formObject($element), $form_state);
    return $form['slot'];
  }

  /**
   * A form holding one component_slot_form element.
   */
  private function formObject(array $element): FormInterface {
    return new class($element) extends FormBase {

      public function __construct(
        protected readonly array $element,
      ) {}

      /**
       * {@inheritdoc}
       */
      public function getFormId(): string {
        return 'ui_patterns_test_component_slot_form';
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
  }

}
