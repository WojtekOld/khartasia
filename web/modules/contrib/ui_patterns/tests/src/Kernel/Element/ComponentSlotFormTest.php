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
use Drupal\ui_patterns\Element\ComponentSlotForm;
use PHPUnit\Framework\Attributes\Group;

/**
 * Slot maxItems and expected in the component_slot_form element.
 *
 * MaxItems disables the add button once reached and never drops a saved
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
   * The add button is disabled once the slot holds maxItems sources.
   */
  public function testAddButtonDisabledAtMaxItems(): void {
    self::assertFalse($this->addButton('slot_max', 0)['#disabled'] ?? FALSE);
    self::assertFalse($this->addButton('slot_max', 1)['#disabled'] ?? FALSE);
    self::assertTrue($this->addButton('slot_max', 2)['#disabled']);
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
   * A slot without maxItems never disables the add button.
   */
  public function testNoLimitWithoutMaxItems(): void {
    self::assertFalse($this->addButton('slot_free', 5)['#disabled'] ?? FALSE);
  }

  /**
   * An explicit #max_items beats the slot definition.
   */
  public function testExplicitMaxItemsWins(): void {
    self::assertTrue($this->addButton('slot_free', 1, ['#max_items' => 1])['#disabled']);
    self::assertFalse($this->addButton('slot_max', 2, ['#max_items' => 3])['#disabled'] ?? FALSE);
  }

  /**
   * A single-cardinality slot keeps hiding the add button after one source.
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
   * A slot row names its open button like a prop row.
   */
  public function testRowButtonNamesItsAction(): void {
    $slot = $this->slotForm('slot_free', 1, [], self::OTHER);
    self::assertSame('Edit', (string) $slot['sources'][0]['_configure']['#value']);
  }

  /**
   * An open row unfolds its own box and the slot box holding it.
   */
  public function testOpenRowUnfoldsItsBoxes(): void {
    $slot = $this->slotForm('slot_free', 1, [], NULL, TRUE);
    self::assertTrue($slot['sources'][0]['_box']['#open']);
    self::assertTrue($slot['slot_free']['#open']);
    $closed = $this->slotForm('slot_free', 1);
    self::assertFalse($closed['sources'][0]['_box']['#open']);
    self::assertFalse($closed['slot_free']['#open']);
  }

  /**
   * The row an action came from keeps its box unfolded, closed as it is.
   */
  public function testTriggeredRowKeepsItsBoxOpen(): void {
    $slot = $this->slotForm('slot_free', 2, [], NULL, FALSE, ['slot', 'sources', 1, '_close']);
    self::assertFalse($slot['sources'][0]['_box']['#open']);
    self::assertTrue($slot['sources'][1]['_box']['#open']);
  }

  /**
   * The box follows the action once built, even when processed before it.
   *
   * A remove validates the whole form; with an error elsewhere there is no
   * rebuild, and the first pass is what the browser gets.
   */
  public function testBoxFollowsTheActionOnceBuilt(): void {
    $slot = $this->slotForm('slot_free', 1);
    self::assertFalse($slot['slot_free']['#open']);
    $form_state = new FormState();
    $form_state->setTriggeringElement(['#array_parents' => ['slot', 'sources', 0, '_remove']]);
    $slot = ComponentSlotForm::afterBuild($slot, $form_state);
    self::assertTrue($slot['slot_free']['#open']);
  }

  /**
   * A row refreshed alone renders no second weight, and keeps its remove.
   */
  public function testRefreshedRowDropsTheWeight(): void {
    $slot = $this->slotForm('slot_free', 1);
    self::assertArrayHasKey('_weight', $slot['sources'][0]);
    $row = ComponentSlotForm::preRenderSourceRow((array) $slot['sources'][0]);
    self::assertArrayNotHasKey('_weight', $row);
    self::assertArrayHasKey('_remove', $row);
    self::assertArrayNotHasKey('#printed', $row['_remove']);
  }

  /**
   * The add button of a built slot form.
   */
  private function addButton(string $slot_id, int $n_sources, array $overrides = []): array {
    $slot = $this->slotForm($slot_id, $n_sources, $overrides);
    self::assertSame('submit', $slot['add_more_button']['#type']);
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
  private function slotForm(string $slot_id, int $n_sources, array $overrides = [], ?string $selected = NULL, bool $open = FALSE, ?array $trigger_parents = NULL): array {
    $source = ['source_id' => 'component', 'source' => []];
    if ($selected !== NULL) {
      $source['source']['component'] = ['component_id' => $selected];
    }
    if ($open) {
      $source['_open'] = '1';
    }
    $element = $overrides + [
      '#type' => 'component_slot_form',
      '#title' => $slot_id,
      '#component_id' => self::COMPONENT,
      '#slot_id' => $slot_id,
      '#default_value' => ['sources' => \array_fill(0, $n_sources, $source)],
    ];
    $form_state = new FormState();
    if ($trigger_parents !== NULL) {
      $form_state->setTriggeringElement(['#array_parents' => $trigger_parents]);
    }
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
