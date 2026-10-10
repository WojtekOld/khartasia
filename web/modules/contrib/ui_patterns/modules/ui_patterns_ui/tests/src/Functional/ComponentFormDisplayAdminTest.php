<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_ui\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\ui_patterns_ui\Traits\ComponentFormDisplayCreationTrait;
use Drupal\ui_patterns_ui\Entity\ComponentFormDisplay;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The admin pages: list, add, edit rows and their settings, delete.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_ui')]
#[RunTestsInSeparateProcesses]
final class ComponentFormDisplayAdminTest extends BrowserTestBase {

  use ComponentFormDisplayCreationTrait;

  private const COMPONENT = 'ui_patterns_test:test-component';

  private const DISPLAY = 'ui_patterns_test.test-component.editor';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'ui_patterns',
    'ui_patterns_test',
    'ui_patterns_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalLogin($this->drupalCreateUser(['administer component_form_display']));
  }

  /**
   * The declared permission opens every page; anonymous users get none.
   */
  public function testAccess(): void {
    $assert = $this->assertSession();
    $this->drupalGet('admin/structure/component');
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('UI Patterns Test component');
    $assert->linkExists('Manage form display');
    $this->drupalGet($this->url('entity.component_form_display.' . self::COMPONENT));
    $assert->statusCodeEquals(200);
    $assert->addressEquals($this->url('entity.component_form_display.' . self::COMPONENT . '.add_form'));

    $this->drupalLogout();
    $this->drupalGet('admin/structure/component');
    $assert->statusCodeEquals(403);
    $this->drupalGet($this->url('entity.component_form_display.' . self::COMPONENT . '.add_form'));
    $assert->statusCodeEquals(403);
  }

  /**
   * Add a display, move rows, tune a widget and a fixed value, delete it.
   */
  public function testAdminFlow(): void {
    $assert = $this->assertSession();
    $this->drupalGet($this->url('entity.component_form_display.' . self::COMPONENT . '.add_form'));
    $assert->optionNotExists('fields[slot][source_id]', '');
    $this->submitForm([
      'label' => 'Editor',
      'form_mode_name' => 'editor',
      'fields[string][region]' => 'content',
      'fields[string][source_id]' => 'textfield',
      'fields[variant][region]' => 'configure',
      'fields[variant][source_id]' => 'select',
      'fields[slot][region]' => 'content',
    ], 'Save');
    $display = ComponentFormDisplay::load(self::DISPLAY);
    self::assertNotNull($display);
    self::assertSame('textfield', $display->getPropSlotOption('string')['source_id']);
    self::assertSame('configure', $display->getPropSlotOption('variant')['region']);
    // Moved to Content, the row gets a source; the selector is offered next.
    self::assertNotSame('', $display->getPropSlotOption('slot')['source_id']);
    self::assertTrue($display->get('hidden')['integer']);
    $assert->addressEquals($this->url('entity.component_form_display.' . self::COMPONENT . '.edit_form', ['form_mode_name' => 'editor']));
    $assert->linkExists('Add form display');
    // Tabs show up once there is more than one display.
    $assert->linkNotExists('Editor');
    $this->createDisplay(self::COMPONENT, 'alpha', []);
    $this->drupalGet($this->reload()->toUrl());
    $assert->linkExists('Editor');
    $assert->linkExists('Alpha');

    // Once in Content, a row can hand the source choice to the editor.
    $this->submitForm(['fields[slot][source_id]' => ''], 'Save');
    self::assertSame('', $this->reload()->getPropSlotOption('slot')['source_id']);

    // Widget settings of a Content row.
    $this->submitForm([], 'string_settings_edit');
    $this->submitForm([
      'fields[string][settings_edit_form][settings][title]' => 'Custom title',
      'fields[string][settings_edit_form][settings][required]' => TRUE,
    ], 'Update');
    $this->submitForm([], 'Save');
    $widget_settings = $this->reload()->getPropSlotOption('string')['widget_settings'];
    self::assertSame('Custom title', $widget_settings['title']);
    self::assertSame(1, $widget_settings['required']);
    $assert->pageTextContains('Title: Custom title');

    // The fixed value of a Configured row.
    $this->submitForm([], 'variant_settings_edit');
    $this->submitForm(['fields[variant][settings_edit_form][settings][value]' => 'other'], 'Update');
    $this->submitForm([], 'Save');
    self::assertSame(['value' => 'other'], $this->reload()->getPropSlotOption('variant')['source']);

    // The machine name is unique per component.
    $this->drupalGet($this->url('entity.component_form_display.' . self::COMPONENT . '.add_form'));
    $this->submitForm(['label' => 'Again', 'form_mode_name' => 'editor'], 'Save');
    $assert->pageTextContains('The machine-readable name is already in use.');

    $this->drupalGet($this->reload()->toUrl('delete-form'));
    $this->submitForm([], 'Delete');
    self::assertNull(ComponentFormDisplay::load(self::DISPLAY));
  }

  /**
   * The display as saved, bypassing the static cache.
   */
  private function reload(): ComponentFormDisplay {
    $storage = $this->container->get('entity_type.manager')->getStorage('component_form_display');
    $storage->resetCache([self::DISPLAY]);
    $display = $storage->load(self::DISPLAY);
    \assert($display instanceof ComponentFormDisplay);
    return $display;
  }

  /**
   * A route of the module as a URL.
   */
  private function url(string $route, array $parameters = []): Url {
    return Url::fromRoute($route, $parameters);
  }

}
