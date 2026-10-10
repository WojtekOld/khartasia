<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_ui\Kernel\Element;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\Tests\ui_patterns_ui\Kernel\UiPatternsUiKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\ui_patterns\Form\ComponentFormBuilderTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The display selector wrapping the component form, and the value it gives.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_ui')]
#[RunTestsInSeparateProcesses]
final class ComponentFormDisplaysFormTest extends UiPatternsUiKernelTestBase {

  use UserCreationTrait;

  private const OTHER = 'ui_patterns_test:test-form-component';

  /**
   * The ID of the display labelled Alpha.
   */
  private string $alpha;

  /**
   * The ID of the display labelled Beta.
   */
  private string $beta;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $row = ['string' => $this->row('content', 'textfield')];
    $this->beta = $this->createDisplay(self::COMPONENT, 'beta', $row)->id();
    $this->alpha = $this->createDisplay(self::COMPONENT, 'alpha', $row)->id();
    $this->createDisplay(self::COMPONENT, 'off', $row, [], FALSE);
    $this->setUpCurrentUser([], ['access ui patterns component form']);
  }

  /**
   * A component without display keeps its plain form, and its value.
   */
  public function testNoDisplay(): void {
    $form = $this->buildElement($this->element(self::OTHER));
    self::assertArrayNotHasKey('display_id', $form);
    self::assertArrayNotHasKey('display', $form);
    self::assertTrue($form['props']['#access']);

    $slot = ['sources' => [['source_id' => 'textfield', 'source' => ['value' => 'in slot']]], 'add_more_button' => ''];
    $value = $this->submitElement($this->element(self::OTHER), [
      'component_id' => self::OTHER,
      'props' => ['string' => ['source_id' => 'textfield', 'source' => ['value' => 'hi']]],
      'slots' => ['slot' => $slot],
    ]);
    self::assertSame('hi', $value['props']['string']['source']['value']);
    self::assertSame('in slot', $value['slots']['slot']['sources'][0]['source']['value']);
    self::assertArrayNotHasKey('add_more_button', $value['slots']['slot']);
  }

  /**
   * The selector lists the enabled displays by label, then the component form.
   */
  public function testOptions(): void {
    $form = $this->buildElement($this->element(self::COMPONENT));
    self::assertSame([$this->alpha, $this->beta, '_component_form'], \array_keys($form['display_id']['#options']));
    self::assertTrue($form['display_id']['#access']);
    self::assertFalse($form['variant_id']['#access']);
    self::assertFalse($form['props']['#access']);
    self::assertFalse($form['slots']['#access']);
    self::assertTrue($form['#component_validation']);

    self::assertSame([$this->alpha, $this->beta], $this->options(['#display_component_form' => FALSE]));
    self::assertSame([$this->beta, '_component_form'], $this->options(['#include_display_ids' => [$this->beta]]));

    $overrides = ['#include_display_ids' => [$this->beta], '#display_component_form' => FALSE];
    $form = $this->buildElement($this->element(self::COMPONENT, $overrides));
    self::assertFalse($form['display_id']['#access']);
    self::assertSame($this->beta, $form['display']['display_id']['#value']);
  }

  /**
   * The component form needs its permission.
   */
  public function testComponentFormPermission(): void {
    $this->setUpCurrentUser();
    self::assertSame([$this->alpha, $this->beta], $this->options());
  }

  /**
   * The stored display opens, a plain stored value opens the component form.
   */
  public function testDefaultSelection(): void {
    $stored = ['component_id' => self::COMPONENT, 'display_id' => $this->beta];
    $form = $this->buildElement($this->element(self::COMPONENT, ['#default_value' => $stored]));
    self::assertSame($this->beta, $form['display_id']['#default_value']);
    $sub = $form['display']['value_' . Html::getId($this->beta)];
    self::assertSame('uip_display_form', $sub['#type']);
    self::assertSame($this->beta, $sub['#display_id']);
    self::assertSame(self::COMPONENT, $sub['#component_id']);

    $stored = [
      'component_id' => self::COMPONENT,
      'props' => ['string' => ['source_id' => 'textfield', 'source' => ['value' => 'kept']]],
    ];
    $form = $this->buildElement($this->element(self::COMPONENT, ['#default_value' => $stored]));
    self::assertSame('_component_form', $form['display_id']['#default_value']);
    $sub = $form['display']['value_' . Html::getId('_component_form')];
    self::assertSame('component_form', $sub['#type']);
    self::assertSame(self::COMPONENT, $sub['#component_id']);
    self::assertFalse($sub['#component_validation']);
    self::assertSame('kept', $sub['#default_value']['props']['string']['source']['value']);

    self::assertSame($this->alpha, $this->buildElement($this->element(self::COMPONENT))['display_id']['#default_value']);
  }

  /**
   * The display picked in the form wins over the stored one.
   */
  public function testUserChoice(): void {
    $form_state = (new FormState())->setUserInput(['element' => ['display_id' => $this->beta]]);
    $stored = ['component_id' => self::COMPONENT, 'display_id' => $this->alpha];
    $form = $this->buildElement($this->element(self::COMPONENT, ['#default_value' => $stored]), $form_state);
    self::assertSame($this->beta, $form['display_id']['#default_value']);
    self::assertArrayHasKey('value_' . Html::getId($this->beta), $form['display']);
  }

  /**
   * The value is the chosen form's value, with the display ID when it is one.
   */
  public function testValue(): void {
    $row = ['source_id' => 'textfield', 'source' => ['value' => 'hi'], 'node_id' => ''];
    $value = $this->submitElement($this->element(self::COMPONENT), [
      'display_id' => $this->beta,
      'display' => ['value_' . Html::getId($this->beta) => ['string' => $row]],
    ]);
    self::assertSame(self::COMPONENT, $value['component_id']);
    self::assertSame($this->beta, $value['display_id']);
    self::assertSame('hi', $value['props']['string']['source']['value']);

    $value = $this->submitElement($this->element(self::COMPONENT), [
      'display_id' => '_component_form',
      'display' => [
        'value_' . Html::getId('_component_form') => ['component_id' => self::COMPONENT, 'props' => ['string' => $row]],
      ],
    ]);
    self::assertArrayNotHasKey('display_id', $value);
    self::assertSame(self::COMPONENT, $value['component_id']);
    self::assertSame('hi', $value['props']['string']['source']['value']);
  }

  /**
   * In a dialog, the AJAX of the selector and of the rows posts to its URL.
   */
  public function testAjaxUrl(): void {
    $url = Url::fromRoute('<front>', [], ['query' => ['ajax_form' => TRUE]]);
    $form = $this->buildElement($this->element(self::COMPONENT, ['#ajax_url' => $url]));
    self::assertSame($url, $form['display_id']['#ajax']['url']);
    self::assertSame(['query' => ['ajax_form' => TRUE]], $form['display_id']['#ajax']['options']);
    self::assertSame($url, $form['display']['value_' . Html::getId($this->alpha)]['#ajax_url']);
  }

  /**
   * Hosts get the display selector unless they refuse overrides.
   */
  public function testAllowOverride(): void {
    $builder = new class() {

      use ComponentFormBuilderTrait;

      /**
       * The plugin configuration the trait reads and writes.
       */
      protected array $configuration = [];

      /**
       * The component form a host would get.
       */
      public function build(string $component_id, array $overrides): array {
        return $this->buildComponentsForm(new FormState(), [], $component_id, TRUE, TRUE, 'ui_patterns', $overrides);
      }

    };
    self::assertSame('uip_displays_form', $builder->build(self::COMPONENT, [])['#type']);
    self::assertSame('component_form', $builder->build(self::COMPONENT, ['#allow_override' => FALSE])['#type']);
  }

  /**
   * The display options the current user gets.
   *
   * @return string[]
   *   The option keys.
   */
  private function options(array $overrides = []): array {
    return \array_map('strval', \array_keys($this->buildElement($this->element(self::COMPONENT, $overrides))['display_id']['#options']));
  }

  /**
   * The element under test.
   */
  private function element(string $component_id, array $overrides = []): array {
    return $overrides + ['#type' => 'uip_displays_form', '#component_id' => $component_id];
  }

}
