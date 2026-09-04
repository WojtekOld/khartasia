<?php

declare(strict_types=1);

namespace Drupal\ui_skins\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Template\AttributeHelper;
use Drupal\ui_skins\Theme\ThemePluginManagerInterface;
use Drupal\ui_skins\UiSkinsInterface;

/**
 * Inject theme.
 */
class PreprocessHtml {

  public function __construct(
    protected ThemePluginManagerInterface $themePluginManager,
    protected ThemeSettingsProvider $themeSettings,
  ) {}

  /**
   * Implements hook_preprocess_html().
   *
   * Inject attributes.
   */
  #[Hook('preprocess_html')]
  public function preprocess(array &$variables): void {
    $ui_skins_theme_setting = $this->themeSettings
      ->getSetting(UiSkinsInterface::THEME_THEME_SETTING_KEY);
    if (!\is_string($ui_skins_theme_setting) || empty($ui_skins_theme_setting)) {
      return;
    }

    $definitions = $this->themePluginManager->getDefinitionWithDependencies($ui_skins_theme_setting);
    foreach ($definitions as $definition) {
      $target = $definition->getComputedTarget();
      $key = $definition->getKey();
      $value = $definition->getValue();
      if ($key == 'class') {
        $value = [
          Html::getClass($value),
        ];
      }

      $variables[$target] = AttributeHelper::mergeCollections(
        // @phpstan-ignore-next-line
        $variables[$target] ?? [],
        [
          $key => $value,
        ]
      );

      $library = $definition->getLibrary();
      if (!empty($library)) {
        $variables['#attached']['library'][] = $library;
      }
    }
  }

}
