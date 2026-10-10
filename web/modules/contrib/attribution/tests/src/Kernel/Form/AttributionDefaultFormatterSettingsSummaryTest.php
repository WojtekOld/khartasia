<?php

declare(strict_types=1);

namespace Drupal\Tests\attribution\Kernel\Form;

use Drupal\attribution\Plugin\Field\FieldFormatter\AttributionDefaultFormatter;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\HttpKernelUiHelperTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for AttributionDefaultFormatter::settingsSummary().
 *
 * SettingsForm() itself is not reachable this way: field_ui only builds
 * it after the "Edit" button for a field's formatter has been submitted,
 * and HttpKernelUiHelperTrait cannot submit forms.
 *
 * @group attribution
 */
#[CoversClass(AttributionDefaultFormatter::class)]
#[Group('attribution')]
#[RunTestsInSeparateProcesses]
class AttributionDefaultFormatterSettingsSummaryTest extends KernelTestBase {

  use HttpKernelUiHelperTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['attribution', 'entity_test', 'field', 'field_ui', 'user', 'system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'attribution']);

    FieldStorageConfig::create([
      'field_name' => 'field_attribution',
      'entity_type' => 'entity_test',
      'type' => 'attribution',
    ])->save();

    FieldConfig::create([
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'field_name' => 'field_attribution',
      'label' => 'Attribution',
    ])->save();

    $this->setUpCurrentUser([], ['administer entity_test display']);
  }

  /**
   * Runs all settings summary assertions in a single process.
   */
  public function testSettingsSummary(): void {
    $this->assertDefaultSettingsSummary();
    $this->assertIdentifierInsteadOfNameSummary();
    $this->assertCcIconSummary();
    $this->assertDividerInsteadOfConnectorWordsSummary();
    $this->assertAiIconSummary();
    $this->assertPromptSummary();
    $this->assertLabelToggleSummary();
  }

  /**
   * Asserts the summary shown for the formatter's default settings.
   */
  protected function assertDefaultSettingsSummary(): void {
    $html = $this->getSettingsSummaryHtml([]);
    $this->assertStringContainsString('License name shown', $html);
    $this->assertStringContainsString('Connector words shown', $html);
    $this->assertStringNotContainsString('License identifier shown instead of name', $html);
    $this->assertStringNotContainsString('Divider markup instead of connector words', $html);
    $this->assertStringNotContainsString('Creative Commons icon shown when applicable', $html);
    $this->assertStringNotContainsString('AI type icon shown when applicable', $html);
    $this->assertStringNotContainsString('AI prompt shown when available', $html);
    $this->assertStringNotContainsString('Field label used as expand/collapse toggle', $html);
  }

  /**
   * Asserts the summary when the license identifier replaces the name.
   */
  protected function assertIdentifierInsteadOfNameSummary(): void {
    $html = $this->getSettingsSummaryHtml(['use_identifier' => TRUE]);
    $this->assertStringContainsString('License identifier shown instead of name', $html);
    $this->assertStringNotContainsString('License name shown', $html);
  }

  /**
   * Asserts the summary when the Creative Commons icon setting is enabled.
   */
  protected function assertCcIconSummary(): void {
    $html = $this->getSettingsSummaryHtml(['cc_icon' => TRUE]);
    $this->assertStringContainsString('Creative Commons icon shown when applicable', $html);
  }

  /**
   * Asserts the summary when divider markup replaces connector words.
   */
  protected function assertDividerInsteadOfConnectorWordsSummary(): void {
    $html = $this->getSettingsSummaryHtml(['divider' => TRUE]);
    $this->assertStringContainsString('Divider markup instead of connector words', $html);
    $this->assertStringNotContainsString('Connector words shown', $html);
  }

  /**
   * Asserts the summary when the AI type icon setting is enabled.
   */
  protected function assertAiIconSummary(): void {
    $html = $this->getSettingsSummaryHtml(['ai_icon' => TRUE]);
    $this->assertStringContainsString('AI type icon shown when applicable', $html);
  }

  /**
   * Asserts the summary when the AI prompt setting is enabled.
   */
  protected function assertPromptSummary(): void {
    $html = $this->getSettingsSummaryHtml(['show_prompt' => TRUE]);
    $this->assertStringContainsString('AI prompt shown when available', $html);
  }

  /**
   * Asserts the summary when the label toggle setting is enabled.
   */
  protected function assertLabelToggleSummary(): void {
    $html = $this->getSettingsSummaryHtml(['label_toggle' => TRUE]);
    $this->assertStringContainsString('Field label used as expand/collapse toggle', $html);
  }

  /**
   * Loads the manage display page for the given formatter settings.
   *
   * @param array<string, mixed> $settings
   *   Formatter settings overriding the formatter's own defaultSettings().
   *
   * @return string
   *   The rendered manage display page.
   */
  protected function getSettingsSummaryHtml(array $settings): string {
    $display = \Drupal::entityTypeManager()
      ->getStorage('entity_view_display')
      ->load('entity_test.entity_test.default')
      ?? EntityViewDisplay::create([
        'targetEntityType' => 'entity_test',
        'bundle' => 'entity_test',
        'mode' => 'default',
        'status' => TRUE,
      ]);
    $display->setComponent('field_attribution', [
      'type' => 'attribution_default',
      'settings' => $settings,
    ])->save();

    $url = Url::fromRoute('entity.entity_view_display.entity_test.default', [
      'bundle' => 'entity_test',
    ]);

    return $this->drupalGet($url);
  }

}
