<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_styles_page\Functional;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\ui_styles_page\UiStylesPageInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test uninstall ui_styles_page module.
 *
 * @group ui_styles
 * @group ui_styles_page
 */
#[Group('ui_styles')]
#[Group('ui_styles_page')]
#[RunTestsInSeparateProcesses]
class UiStylesPageUninstallTest extends UiStylesPageFunctionalTestBase {

  /**
   * The module installer service.
   *
   * @var \Drupal\Core\Extension\ModuleInstallerInterface
   */
  protected ModuleInstallerInterface $moduleInstaller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->moduleInstaller = $this->container->get('module_installer');
  }

  /**
   * Test hook_uninstall.
   */
  public function testUninstall(): void {
    $themeSettings = $this->configFactory->getEditable($this->defaultTheme . '.settings');
    $themeSettings->set(UiStylesPageInterface::REGION_STYLES_KEY_THEME_SETTINGS, [
      'sidebar_first' => [
        'selected' => [
          'fake' => 'fake',
        ],
        'extra' => 'free-value',
      ],
    ]);
    $themeSettings->save();

    $this->assertNotNull($themeSettings->get(UiStylesPageInterface::REGION_STYLES_KEY_THEME_SETTINGS));
    $this->assertNotNull($themeSettings->get('third_party_settings.ui_styles_page'));
    $this->assertNotNull($themeSettings->get('third_party_settings'));

    $this->moduleInstaller->uninstall(['ui_styles_page']);

    $themeSettings = $this->configFactory->getEditable($this->defaultTheme . '.settings');
    $this->assertNull($themeSettings->get(UiStylesPageInterface::REGION_STYLES_KEY_THEME_SETTINGS));
    $this->assertNull($themeSettings->get('third_party_settings.ui_styles_page'));
    $this->assertNull($themeSettings->get('third_party_settings'));
  }

}
