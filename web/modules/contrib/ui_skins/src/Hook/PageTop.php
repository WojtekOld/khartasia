<?php

declare(strict_types=1);

namespace Drupal\ui_skins\Hook;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\ui_skins\UiSkinsInterface;
use Drupal\ui_skins\UiSkinsUtility;

/**
 * Inject inline CSS.
 */
class PageTop {

  /**
   * The page top key for CSS variables.
   */
  public const string PAGE_TOP_CSS_VARIABLES_KEY = 'ui_skins_css_variables';

  public function __construct(
    protected ThemeManagerInterface $themeManager,
    protected ThemeSettingsProvider $themeSettings,
  ) {}

  /**
   * Implements hook_page_top().
   *
   * Inject inline CSS variables.
   */
  #[Hook('page_top')]
  public function alter(array &$page_top): void {
    $ui_skins_css_variables_settings = $this->themeSettings
      ->getSetting(UiSkinsInterface::CSS_VARIABLES_THEME_SETTING_KEY);
    if (!\is_array($ui_skins_css_variables_settings)) {
      return;
    }

    // Prepare list of variables grouped by scope.
    $css_variables = [];
    foreach ($ui_skins_css_variables_settings as $plugin_id => $scoped_values) {
      if (!\is_array($scoped_values)) {
        continue;
      }

      $variable_name = UiSkinsUtility::getCssVariableName($plugin_id);
      foreach ($scoped_values as $scope => $value) {
        $css_variables = NestedArray::mergeDeep($css_variables, [
          UiSkinsUtility::getCssScopeName($scope) => [
            $variable_name => $value,
          ],
        ]);
      }
    }

    if (empty($css_variables)) {
      return;
    }

    $page_top[static::PAGE_TOP_CSS_VARIABLES_KEY] = [
      '#type' => 'html_tag',
      '#tag' => 'style',
      '#value' => UiSkinsUtility::getCssVariablesInlineCss($css_variables),
      '#cache' => [
        'tags' => [
          'config:' . $this->themeManager->getActiveTheme()->getName() . '.settings',
        ],
      ],
    ];
  }

}
