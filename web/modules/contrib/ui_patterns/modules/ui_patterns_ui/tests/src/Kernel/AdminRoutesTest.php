<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_ui\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Every component gets its admin routes, tab and action, behind one permission.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_ui')]
#[RunTestsInSeparateProcesses]
final class AdminRoutesTest extends UiPatternsUiKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * The routes of a component require a permission the module declares.
   */
  public function testRoutesPermission(): void {
    $permissions = $this->container->get('user.permissions')->getPermissions();
    $provider = $this->container->get('router.route_provider');
    $base = 'entity.component_form_display.' . self::COMPONENT;
    $names = [
      $base,
      $base . '.edit_form',
      $base . '.add_form',
      'entity.component_form_display.delete_form',
      'component.collection',
    ];
    foreach ($names as $name) {
      $permission = $provider->getRouteByName($name)->getRequirement('_permission');
      self::assertArrayHasKey($permission, $permissions, $name);
    }
    $admin_permission = $this->container->get('entity_type.manager')->getDefinition('component_form_display')->getAdminPermission();
    self::assertArrayHasKey($admin_permission, $permissions);
  }

  /**
   * The paths of a display: edit, collection and delete.
   */
  public function testUrls(): void {
    $display = $this->createDisplay(self::COMPONENT, 'mode', []);
    $path = 'admin/structure/component/ui_patterns_test:test-component/form-display';
    self::assertSame($path . '/mode', $display->toUrl()->getInternalPath());
    self::assertSame($path . '/mode', $display->toUrl('edit-form')->getInternalPath());
    self::assertSame($path, $display->toUrl('collection')->getInternalPath());
    self::assertSame($path . '/mode/delete', $display->toUrl('delete-form')->getInternalPath());
  }

  /**
   * Saving a display adds its tab under the component overview.
   */
  public function testLocalTasksAndAction(): void {
    $tasks = $this->container->get('plugin.manager.menu.local_task');
    $actions = $this->container->get('plugin.manager.menu.local_action');
    $base = 'ui_patterns_ui.form_displays:component_form_display.' . self::COMPONENT . '.base';
    self::assertArrayHasKey($base, $tasks->getDefinitions());
    self::assertArrayHasKey('ui_patterns_ui.add_manage_display:component_form_display.' . self::COMPONENT, $actions->getDefinitions());

    $display = $this->createDisplay(self::COMPONENT, 'mode', []);
    $tab = $tasks->getDefinitions()['ui_patterns_ui.form_displays:component_form_display.' . $display->id()] ?? NULL;
    self::assertNotNull($tab);
    self::assertSame($base, $tab['parent_id']);
    self::assertSame('entity.component_form_display.' . self::COMPONENT . '.edit_form', $tab['route_name']);
    self::assertSame(['component_id' => self::COMPONENT, 'form_mode_name' => 'mode'], $tab['route_parameters']);

    $display->delete();
    self::assertArrayNotHasKey('ui_patterns_ui.form_displays:component_form_display.' . $display->id(), $tasks->getDefinitions());
  }

}
