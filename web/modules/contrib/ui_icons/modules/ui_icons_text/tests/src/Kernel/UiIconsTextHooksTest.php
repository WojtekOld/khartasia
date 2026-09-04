<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_text\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons_text\Hook\UiIconsTextHooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the filter format form validation added by ui_icons_text.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(UiIconsTextHooks::class)]
#[Group('ui_icons')]
class UiIconsTextHooksTest extends KernelTestBase {

  /**
   * Every attribute `<drupal-icon>` must be allowed to carry.
   */
  private const REQUIRED_ATTRIBUTES = 'data-icon-id data-icon-settings class aria-label aria-hidden role';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'filter',
    'ui_icons',
    'ui_icons_text',
    'ui_icons_test',
  ];

  /**
   * The hook implementations.
   */
  private UiIconsTextHooks $hooks;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['filter']);
    $this->hooks = $this->container->get(UiIconsTextHooks::class);

    FilterFormat::create([
      'format' => 'test_format',
      'name' => 'Test format',
      'filters' => [
        'filter_html' => ['status' => TRUE, 'weight' => -10],
        'icon_embed' => ['status' => TRUE, 'weight' => 0],
      ],
    ])->save();
  }

  /**
   * Tests both form alters register the validate callback.
   */
  public function testFormAlterAddsValidateCallback(): void {
    $expected = UiIconsTextHooks::class . ':filterFormatEditFormValidate';
    $form_state = new FormState();

    $form = [];
    $this->hooks->formFilterFormatEditFormAlter($form, $form_state, 'filter_format_edit_form');
    $this->assertSame([$expected], $form['#validate']);

    $form = [];
    $this->hooks->formFilterFormatAddFormAlter($form, $form_state, 'filter_format_add_form');
    $this->assertSame([$expected], $form['#validate']);
  }

  /**
   * Builds the pieces of the filter format form the validator reads.
   *
   * @param string $allowed_html
   *   The `filter_html` allowed tags string.
   * @param array $filters
   *   Filter status and weight, merged over sane defaults.
   *
   * @return array
   *   The form and its form state.
   */
  private function prepare(string $allowed_html, array $filters = []): array {
    $filters += [
      'filter_html' => ['status' => 1, 'weight' => -10],
      'filter_autop' => ['status' => 0, 'weight' => -9],
      'icon_embed' => ['status' => 1, 'weight' => 0],
    ];

    $form = [
      'filters' => [
        'order' => [],
        'settings' => [
          'filter_html' => [
            'allowed_html' => ['#parents' => ['filters', 'filter_html', 'settings', 'allowed_html']],
          ],
        ],
      ],
    ];
    foreach (array_keys($filters) + ['filter_html_escape' => 0] as $filter_id) {
      $form['filters']['order'][$filter_id]['filter']['#markup'] = $filter_id;
    }

    $form_state = new FormState();
    $form_state->setTriggeringElement(['#name' => 'op']);
    $form_state->setValues([
      'filters' => $filters + [
        'filter_html' => ['status' => 1, 'weight' => -10, 'settings' => ['allowed_html' => $allowed_html]],
      ],
    ]);
    $form_state->setValue(['filters', 'filter_html', 'settings', 'allowed_html'], $allowed_html);
    $form_state->setFormObject(
      $this->container->get('entity_type.manager')
        ->getFormObject('filter_format', 'edit')
        ->setEntity(FilterFormat::load('test_format'))
    );

    return [$form, $form_state];
  }

  /**
   * Runs the validator and returns the error messages as plain strings.
   *
   * @param string $allowed_html
   *   The `filter_html` allowed tags string.
   * @param array $filters
   *   Filter status and weight overrides.
   *
   * @return array
   *   The validation errors.
   */
  private function validate(string $allowed_html, array $filters = []): array {
    [$form, $form_state] = $this->prepare($allowed_html, $filters);
    $this->hooks->filterFormatEditFormValidate($form, $form_state);

    return array_map('strval', $form_state->getErrors());
  }

  /**
   * Tests a correctly configured format raises nothing.
   */
  public function testValidFormatHasNoError(): void {
    $errors = $this->validate('<p> <drupal-icon ' . self::REQUIRED_ATTRIBUTES . '>');

    $this->assertSame([], $errors);
  }

  /**
   * Tests the wildcard attribute form is accepted.
   */
  public function testWildcardAttributeIsAccepted(): void {
    $errors = $this->validate('<p> <drupal-icon *>');

    $this->assertSame([], $errors);
  }

  /**
   * Tests a missing `<drupal-icon>` tag is reported.
   */
  public function testMissingTagIsReported(): void {
    $errors = $this->validate('<p>');

    $this->assertCount(1, $errors);
    $this->assertStringContainsString('requires', reset($errors));
    $this->assertStringContainsString('drupal-icon', reset($errors));
  }

  /**
   * Tests a tag allowed with no attribute at all lists them all as missing.
   */
  public function testTagWithoutAnyAttributeIsReported(): void {
    $errors = $this->validate('<p> <drupal-icon>');

    $error = implode("\n", $errors);
    $this->assertStringContainsString('missing the following attributes', $error);
    foreach (explode(' ', self::REQUIRED_ATTRIBUTES) as $attribute) {
      $this->assertStringContainsString($attribute, $error);
    }
  }

  /**
   * Tests only the attributes actually missing are listed.
   */
  public function testPartiallyAllowedAttributesAreReported(): void {
    $errors = $this->validate('<p> <drupal-icon data-icon-id class role>');

    $error = implode("\n", $errors);
    $this->assertStringContainsString('data-icon-settings', $error);
    $this->assertStringContainsString('aria-label', $error);
    $this->assertStringContainsString('aria-hidden', $error);
    $this->assertStringNotContainsString('data-icon-id', $error);
  }

  /**
   * Tests the icon filter must run after `filter_html`.
   */
  public function testFilterOrderIsReported(): void {
    $errors = $this->validate('<p> <drupal-icon ' . self::REQUIRED_ATTRIBUTES . '>', [
      'filter_html' => ['status' => 1, 'weight' => 10],
      'icon_embed' => ['status' => 1, 'weight' => 0],
    ]);

    $this->assertCount(1, $errors);
    $this->assertStringContainsString('needs to be placed after', reset($errors));
  }

  /**
   * Tests both precedent filters are listed when both run too late.
   */
  public function testMultipleFilterOrderErrorsArePluralized(): void {
    $errors = $this->validate('<p> <drupal-icon ' . self::REQUIRED_ATTRIBUTES . '>', [
      'filter_html' => ['status' => 1, 'weight' => 10],
      'filter_autop' => ['status' => 1, 'weight' => 10],
      'icon_embed' => ['status' => 1, 'weight' => 0],
    ]);

    $error = implode("\n", $errors);
    $this->assertStringContainsString('the following filters', $error);
    $this->assertStringContainsString('filter_autop', $error);
  }

  /**
   * Tests `filter_html_escape` is reported as cancelling the icon filter.
   */
  public function testHtmlEscapeIsReported(): void {
    $errors = $this->validate('<p> <drupal-icon ' . self::REQUIRED_ATTRIBUTES . '>', [
      'filter_html_escape' => ['status' => 1, 'weight' => 20],
    ]);

    $this->assertCount(1, $errors);
    $this->assertStringContainsString('will not work and should be removed', reset($errors));
  }

  /**
   * Tests validation is skipped when the icon filter is disabled.
   */
  public function testDisabledIconFilterSkipsValidation(): void {
    $errors = $this->validate('<p>', [
      'icon_embed' => ['status' => 0, 'weight' => 0],
    ]);

    $this->assertSame([], $errors);
  }

  /**
   * Tests validation is skipped when `filter_html` is disabled.
   */
  public function testDisabledFilterHtmlSkipsValidation(): void {
    $errors = $this->validate('<p>', [
      'filter_html' => ['status' => 0, 'weight' => -10],
    ]);

    $this->assertSame([], $errors);
  }

  /**
   * Tests validation only runs for the form submit button.
   */
  public function testNonSubmitTriggerSkipsValidation(): void {
    [$form, $form_state] = $this->prepare('<p>');
    $form_state->setTriggeringElement(['#name' => 'filters[filter_html][status]']);

    $this->hooks->filterFormatEditFormValidate($form, $form_state);

    $this->assertSame([], $form_state->getErrors());
  }

}
