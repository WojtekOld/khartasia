<?php

declare(strict_types=1);

namespace Drupal\ui_styles_entity_status\HookHandler;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ui_styles_entity_status\UiStylesEntityStatusInterface;

/**
 * Alter theme settings form.
 */
class FormSystemThemeSettingsAlter {

  use StringTranslationTrait;

  /**
   * Add unpublished entity styles form in system theme settings.
   */
  public function alter(array &$form, FormStateInterface $form_state): void {
    $theme = '';
    // Extract theme name from $form.
    if (isset($form['config_key']['#value']) && \is_string($form['config_key']['#value'])) {
      $config_key = $form['config_key']['#value'];
      $config_key_parts = \explode('.', $config_key);
      $theme = $config_key_parts[0];
    }
    // Impossible to determine on which theme settings form we are.
    if (empty($theme)) {
      return;
    }

    /** @var array $settings */
    $settings = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', static fn () => \Drupal::service(ThemeSettingsProvider::class)->getSetting(UiStylesEntityStatusInterface::UNPUBLISHED_CLASSES_THEME_SETTING_KEY, $theme), static fn () => \theme_get_setting(UiStylesEntityStatusInterface::UNPUBLISHED_CLASSES_THEME_SETTING_KEY, $theme)) ?? [];
    // #config_target is not usable because using #tree.
    $form['third_party_settings_ui_styles_entity_status_unpublished'] = [
      '#type' => 'ui_styles_styles',
      '#title' => $this->t('Unpublished entity styles'),
      '#drupal_theme' => $theme,
      '#default_value' => [
        'selected' => $settings['selected'] ?? [],
        'extra' => $settings['extra'] ?? '',
      ],
      '#tree' => TRUE,
    ];

    $form['#validate'][] = [$this, 'validateForm'];
  }

  /**
   * Converts form element key so that it is saved in third party settings.
   */
  public function validateForm(array $form, FormStateInterface $formState): void {
    $unpublished = $formState->getValue('third_party_settings_ui_styles_entity_status_unpublished');
    $formState->unsetValue('third_party_settings_ui_styles_entity_status_unpublished');
    $formState->setValue(UiStylesEntityStatusInterface::UNPUBLISHED_CLASSES_THEME_SETTING_KEY, $unpublished);
  }

}
