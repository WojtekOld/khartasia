<?php

declare(strict_types=1);

namespace Drupal\ui_skins\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ui_skins\Theme\ThemePluginManagerInterface;
use Drupal\ui_skins\UiSkinsInterface;

/**
 * Alter theme settings form.
 */
class FormSystemThemeSettingsAlter {

  use StringTranslationTrait;

  public function __construct(
    protected ThemePluginManagerInterface $themePluginManager,
  ) {}

  /**
   * Implements hook_form_FORM_ID_alter() for 'system_theme_settings'.
   *
   * Add theme form in system theme settings.
   */
  #[Hook('form_system_theme_settings_alter')]
  public function alter(array &$form, FormStateInterface $form_state): void {
    $form_theme_name = '';
    // Extract theme name from $form.
    if (isset($form['config_key']['#value']) && \is_string($form['config_key']['#value'])) {
      $config_key = $form['config_key']['#value'];
      $config_key_parts = \explode('.', $config_key);
      $form_theme_name = $config_key_parts[0];
    }
    // Impossible to determine on which theme settings form we are.
    if (empty($form_theme_name)) {
      return;
    }

    $plugin_definitions = $this->themePluginManager->getDefinitionsForTheme($form_theme_name);
    if (empty($plugin_definitions)) {
      return;
    }

    $options = [];
    foreach ($plugin_definitions as $plugin_definition) {
      $options[$plugin_definition->id()] = $plugin_definition->getLabel();
    }
    // It works thanks to #config_target.
    $form['third_party_settings']['ui_skins']['theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Theme'),
      '#options' => $options,
      '#empty_option' => $this->t('Select theme'),
      '#config_target' => $form_theme_name . '.settings:' . UiSkinsInterface::THEME_THEME_SETTING_KEY,
    ];
  }

}
