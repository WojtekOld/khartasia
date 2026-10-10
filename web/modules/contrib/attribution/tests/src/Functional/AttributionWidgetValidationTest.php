<?php

declare(strict_types=1);

namespace Drupal\Tests\attribution\Functional;

use Drupal\attribution\Plugin\Field\FieldType\AttributionItem;
use Drupal\attribution\Plugin\Field\FieldWidget\AttributionWidgetBase;
use Drupal\attribution_test_constraint\Hook\AttributionTestConstraintHooks;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that entity validation errors are flagged on the widget elements.
 *
 * @group attribution
 */
#[CoversClass(AttributionWidgetBase::class)]
#[Group('attribution')]
#[RunTestsInSeparateProcesses]
class AttributionWidgetValidationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'attribution',
    'attribution_test_constraint',
    'entity_test',
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Form element names of the constrained properties.
   */
  private const ELEMENT_NAMES = [
    'source_name' => 'field_attribution[0][source][source_name]',
    'author_name' => 'field_attribution[0][author][author_name]',
    'ai_tool' => 'field_attribution[0][ai_tool]',
    'prompt_editor_name' => 'field_attribution[0][prompt_editor][prompt_editor_name]',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

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
    $this->setWidget('attribution_source_author_license');

    $this->drupalLogin($this->drupalCreateUser(['administer entity_test content']));
  }

  /**
   * Tests that a property violation marks only the matching element.
   */
  public function testViolationIsFlaggedOnPropertyElement(): void {
    $assert_session = $this->assertSession();

    foreach (array_keys(self::ELEMENT_NAMES) as $property) {
      $this->drupalGet('entity_test/add');
      $this->submitForm([
        'field_attribution[0][creation_type]' => AttributionItem::CREATION_TYPE_AI_GENERATED,
        self::ELEMENT_NAMES[$property] => AttributionTestConstraintHooks::INVALID_VALUE,
      ], 'Save');

      $assert_session->pageTextContains("Invalid $property.");
      foreach (self::ELEMENT_NAMES as $other_property => $name) {
        $classes = (string) $assert_session->fieldExists($name)->getAttribute('class');
        if ($other_property === $property) {
          $this->assertStringContainsString('error', $classes, "Element of '$property' is not flagged.");
        }
        else {
          $this->assertStringNotContainsString('error', $classes, "Element of '$other_property' is flagged for a '$property' violation.");
        }
      }
    }
  }

  /**
   * Sets the form widget of the attribution field.
   */
  private function setWidget(string $widget): void {
    $this->container->get('entity_display.repository')
      ->getFormDisplay('entity_test', 'entity_test')
      ->setComponent('field_attribution', ['type' => $widget])
      ->save();
  }

}
