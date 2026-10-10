<?php

declare(strict_types=1);

namespace Drupal\Tests\attribution\Kernel\Form;

use Drupal\attribution\Plugin\Field\FieldType\AttributionItem;
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
 * Kernel tests for AttributionItem::fieldSettingsForm().
 *
 * @group attribution
 */
#[CoversClass(AttributionItem::class)]
#[Group('attribution')]
#[RunTestsInSeparateProcesses]
class AttributionFieldSettingsFormTest extends KernelTestBase {

  use HttpKernelUiHelperTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['attribution', 'entity_test', 'field', 'field_ui', 'user', 'system'];

  /**
   * Tests that the licenses setting is preselected with its stored value.
   */
  public function testFieldSettingsFormShowsConfiguredLicensesAsSelected(): void {
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
      'settings' => ['licenses' => ['cc_by_4_0']],
    ])->save();

    $this->setUpCurrentUser([], ['administer entity_test fields']);

    $url = Url::fromRoute('entity.field_config.entity_test_field_edit_form', [
      'bundle' => 'entity_test',
      'field_config' => 'entity_test.entity_test.field_attribution',
    ]);
    $this->drupalGet($url);

    $selected = $this->getSession()->getPage()->findAll('xpath', "//select[@name='settings[licenses][]']/option[@selected]");
    $this->assertCount(1, $selected);
    $this->assertSame('cc_by_4_0', $selected[0]->getAttribute('value'));
  }

}
