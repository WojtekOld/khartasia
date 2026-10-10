<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Element;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_patterns\Element\ComponentPropForm;
use PHPUnit\Framework\Attributes\Group;

/**
 * The states of the component_prop_form source selector.
 *
 * Closed, the prop renders its resolved source only, next to the button
 * loading the selector; open — the _open input, or #open_selector for prop
 * forms embedded in a source settings form — it renders the full selector.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
final class ComponentPropFormTest extends KernelTestBase {

  public const COMPONENT = 'ui_patterns_test:test-component';

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
   * Closed and unset: the default widget shows, the selector does not.
   */
  public function testClosedDefaultWidget(): void {
    $element = $this->buildProp([]);
    self::assertSame('hidden', $element['source_id']['#type']);
    self::assertSame('textfield', $element['source_id']['#value']);
    self::assertArrayNotHasKey('#ui_patterns_hidden', $element['source']);
    self::assertArrayHasKey('_configure', $element);
    self::assertArrayNotHasKey('_close', $element);
  }

  /**
   * Closed with a source picked outside the widgets: its summary stands in.
   */
  public function testClosedNonWidgetSummary(): void {
    $element = $this->buildProp(['source_id' => 'token', 'source' => ['value' => '[current-user:name]']]);
    self::assertSame('hidden', $element['source_id']['#type']);
    self::assertSame('token', $element['source_id']['#value']);
    self::assertTrue($element['source']['#ui_patterns_hidden']);
    self::assertArrayHasKey('summary', $element);
    self::assertArrayHasKey('_configure', $element);
  }

  /**
   * The open button names its action, in words shared with slot rows.
   */
  public function testOpenButtonNamesItsAction(): void {
    $widget = $this->buildProp(['source_id' => 'textfield', 'source' => ['value' => 'Hello']]);
    self::assertSame('Change source', (string) $widget['_configure']['#value']);
    self::assertSame('Change source: string', (string) $widget['_configure']['#attributes']['aria-label']);
    $summary = $this->buildProp(['source_id' => 'token', 'source' => ['value' => '[site:name]']]);
    self::assertSame('Edit', (string) $summary['_configure']['#value']);
  }

  /**
   * The _open input, kept across rebuilds, renders the full selector.
   */
  public function testOpenThroughInput(): void {
    $element = $this->buildProp(['_open' => '1']);
    self::assertSame('select', $element['source_id']['#type']);
    self::assertArrayHasKey('textfield', $element['source_id']['#options']);
    self::assertArrayHasKey('_close', $element);
    self::assertArrayNotHasKey('_configure', $element);
  }

  /**
   * With #open_selector, embedded prop forms go straight to the selector.
   */
  public function testOpenSelector(): void {
    $element = $this->buildProp([], TRUE);
    self::assertSame('select', $element['source_id']['#type']);
    self::assertSame('1', $element['_open']['#default_value']);
    self::assertArrayNotHasKey('_configure', $element);
    self::assertArrayNotHasKey('_close', $element);
  }

  /**
   * The box of a wrapped prop unfolds exactly while its selector is open.
   */
  public function testBoxFollowsTheSelector(): void {
    self::assertFalse($this->buildProp([], FALSE, TRUE)['string']['#open']);
    self::assertTrue($this->buildProp(['_open' => '1'], FALSE, TRUE)['string']['#open']);
  }

  /**
   * The box title holds no control: a theme may render it inside a button.
   */
  public function testBoxTitleHoldsNoControl(): void {
    $element = ComponentPropForm::preRenderPropOrSlot($this->buildProp([], FALSE, TRUE));
    self::assertIsNotArray($element['string']['#title']);
    $operations = [];
    foreach (Element::children($element['string']) as $key) {
      $operations[] = $element['string'][$key]['#ui_patterns_selector_operation'] ?? NULL;
    }
    self::assertContains('open', $operations);
  }

  /**
   * A cancel marks the row for the page only when it put other values back.
   *
   * Saving marks nothing: the browser reported the typed values already.
   */
  public function testCancelAnnouncesOnlyChangedValues(): void {
    $row = ['source_id' => 'textfield', 'source' => ['value' => 'kept'], 'node_id' => 'n1'];
    $typed = ['source' => ['value' => 'typed']] + $row;
    self::assertStringNotContainsString('data-uip-announce', $this->refreshRow('cancel', $row, $row));
    self::assertStringContainsString('data-uip-announce', $this->refreshRow('cancel', $typed, $row));
    self::assertStringNotContainsString('data-uip-announce', $this->refreshRow('close', $typed, $row));
  }

  /**
   * A switched source lands a form the browser never saw: it is marked.
   */
  public function testSwitchedSourceFormIsMarked(): void {
    $form_state = new FormState();
    $form = $this->container->get('form_builder')->buildForm($this->formObject([], TRUE), $form_state);
    $form_state->setTriggeringElement($form['prop']['source_id']);
    $source = ComponentPropForm::switchSourceForm($form, $form_state);
    self::assertStringContainsString('data-uip-announce', (string) $source['#prefix']);
  }

  /**
   * A fold tells a screen reader what became of the values; an open, nothing.
   */
  public function testFoldSpeaksItsOutcome(): void {
    $row = ['source_id' => 'textfield', 'source' => ['value' => 'kept'], 'node_id' => 'n1'];
    self::assertSame('string: values kept.', $this->spoken('close', $row, $row));
    self::assertSame('string: values put back.', $this->spoken('cancel', $row, $row));
    self::assertNull($this->spoken('open', $row, $row));
  }

  /**
   * The announcement the toggle refresh carries, NULL when it carries none.
   */
  private function spoken(string $operation, array $typed, array $snapshot): ?string {
    foreach ($this->refreshCommands($operation, $typed, $snapshot) as $command) {
      if ($command['command'] === 'announce') {
        return (string) $command['text'];
      }
    }
    return NULL;
  }

  /**
   * The row the toggle refresh renders, once $typed replaced $snapshot.
   */
  private function refreshRow(string $operation, array $typed, array $snapshot): string {
    return (string) $this->refreshCommands($operation, $typed, $snapshot)[0]['data'];
  }

  /**
   * The AJAX commands of the toggle refresh, once $typed replaced $snapshot.
   */
  private function refreshCommands(string $operation, array $typed, array $snapshot): array {
    $form_state = new FormState();
    $form = $this->container->get('form_builder')->buildForm($this->formObject([], FALSE), $form_state);
    $form_state->setTriggeringElement([
      '#array_parents' => ['prop', '_' . $operation],
      '#ajax' => ['wrapper' => 'row'],
      '#ui_patterns_selector_operation' => $operation,
      '#ui_patterns_prop_parents' => ['prop'],
    ]);
    $form_state->setUserInput(['prop' => $typed + ['_open' => '1', '_snapshot' => (string) \json_encode($snapshot)]]);
    ComponentPropForm::toggleSelectorSubmit($form, $form_state);
    $response = ComponentPropForm::refreshSelectorForm($form, $form_state);
    self::assertInstanceOf(AjaxResponse::class, $response);
    return $response->getCommands();
  }

  /**
   * Builds the form and returns the prop element.
   *
   * @param array $configuration
   *   The prop configuration.
   * @param bool $open_selector
   *   The #open_selector value.
   * @param bool $wrap
   *   The #wrap value.
   *
   * @return array
   *   The built component_prop_form element.
   */
  private function buildProp(array $configuration, bool $open_selector = FALSE, bool $wrap = FALSE): array {
    $form_state = new FormState();
    $form = $this->container->get('form_builder')->buildForm($this->formObject($configuration, $open_selector, $wrap), $form_state);
    return $form['prop'];
  }

  /**
   * A form holding one component_prop_form element.
   */
  private function formObject(array $configuration, bool $open_selector, bool $wrap = FALSE): FormInterface {
    return new class($configuration, $open_selector, $wrap) extends FormBase {

      public function __construct(
        protected readonly array $configuration,
        protected readonly bool $openSelector,
        protected readonly bool $wrap,
      ) {}

      /**
       * {@inheritdoc}
       */
      public function getFormId(): string {
        return 'ui_patterns_test_component_prop_form';
      }

      /**
       * {@inheritdoc}
       */
      public function buildForm(array $form, FormStateInterface $form_state): array {
        $form['prop'] = [
          '#type' => 'component_prop_form',
          '#component_id' => ComponentPropFormTest::COMPONENT,
          '#prop_id' => 'string',
          '#wrap' => $this->wrap,
          '#open_selector' => $this->openSelector,
          '#default_value' => $this->configuration,
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
