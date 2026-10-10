<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_ui\Functional;

use Drupal\block\Entity\Block;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\ui_patterns\Traits\ConfigImporterTrait;
use Drupal\Tests\ui_patterns_ui\Traits\ComponentFormDisplayCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * A block edited through a display, saved, reopened and rendered.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_ui')]
#[RunTestsInSeparateProcesses]
final class BlockFormDisplayTest extends BrowserTestBase {

  use ComponentFormDisplayCreationTrait;
  use ConfigImporterTrait;

  private const COMPONENT = 'ui_patterns_test:test-component';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'ui_patterns',
    'ui_patterns_blocks',
    'ui_patterns_test',
    'ui_patterns_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The display ID.
   */
  private string $display;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->display = $this->createDisplay(self::COMPONENT, 'editor', [
      'variant' => $this->row('configure', 'select', ['value' => 'other']),
      'string' => $this->row('content', 'textfield', [], ['title' => 'Custom title', 'required' => 1], 1),
      'string_plain' => $this->row('content', 'textfield', [], [], 2),
    ])->id();
    $this->drupalLogin($this->drupalCreateUser(['administer blocks']));
  }

  /**
   * One display and no permission: the display form alone, saved and rendered.
   */
  public function testSingleDisplay(): void {
    $assert = $this->assertSession();
    $this->drupalGet($this->addUrl());
    $assert->fieldNotExists('settings[ui_patterns][display_id]');
    $assert->hiddenFieldValueEquals('settings[ui_patterns][display][display_id]', $this->display);
    $assert->fieldNotExists('settings[ui_patterns][display][value_' . Html::getId($this->display) . '][variant][source][value]');
    $field = 'settings[ui_patterns][display][value_' . Html::getId($this->display) . '][string][source][value]';
    $assert->elementAttributeContains('css', '[name="' . $field . '"]', 'required', 'required');
    $assert->elementTextContains('css', 'label[for="' . $this->getSession()->getPage()->findField($field)->getAttribute('id') . '"]', 'Custom title');

    $this->submitForm(['id' => 'editor_block', 'region' => 'content', $field => 'typed'], 'Save block');
    $settings = Block::load('editor_block')->get('settings')['ui_patterns'];
    self::assertSame($this->display, $settings['display_id']);
    self::assertSame('typed', $settings['props']['string']['source']['value']);
    self::assertArrayNotHasKey('variant', $settings['props']);
    self::assertArrayNotHasKey('node_id', $settings['props']);
    $this->assertConfigSchema($this->container->get(TypedConfigManagerInterface::class), 'block.block.editor_block', Block::load('editor_block')->toArray());

    $this->drupalGet('admin/structure/block/manage/editor_block');
    $assert->fieldValueEquals($field, 'typed');

    $this->drupalGet('<front>');
    $assert->elementExists('css', '.ui-patterns-test-component-variant-other');
    $assert->elementTextContains('css', '.ui-patterns-props-string', 'typed');
  }

  /**
   * The form placing a component block in the default theme.
   */
  private function addUrl(string $component_id = self::COMPONENT): Url {
    return Url::fromRoute('block.admin_add', ['plugin_id' => 'ui_patterns:' . $component_id, 'theme' => 'stark']);
  }

}
