<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\TestSite;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\TestSite\TestSetupInterface;

/**
 * Setup file used by tests/src/Playwright/Tests/.
 *
 * @see \Drupal\Tests\Scripts\TestSiteApplicationTest
 */
class PlaywrightTestSetup implements TestSetupInterface {

  /**
   * {@inheritdoc}
   */
  public function setup(): void {
    $module_installer = \Drupal::service('module_installer');
    \assert($module_installer instanceof ModuleInstallerInterface);
    // One batch: each install() call rebuilds the container and the router.
    $module_installer->install([
      'block',
      'menu_link_content',
      'dblog',
      'node',
      'taxonomy',
      'field',
      'field_ui',
      'ui_patterns',
      'ui_patterns_blocks',
      'ui_patterns_field_formatters',
      'layout_builder',
      'ui_patterns_layouts',
      'views',
      'views_ui',
      'ui_patterns_views',
      'ckeditor5',
      'ui_patterns_ckeditor5',
    ]);
    // Test modules last, their config import needs the modules above.
    $module_installer->install(['ui_patterns_test', 'ui_patterns_test_content']);
  }

}
