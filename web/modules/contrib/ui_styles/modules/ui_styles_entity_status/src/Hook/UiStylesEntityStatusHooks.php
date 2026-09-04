<?php

declare(strict_types=1);

namespace Drupal\ui_styles_entity_status\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\ui_styles_entity_status\HookHandler\EntityView;
use Drupal\ui_styles_entity_status\HookHandler\FormSystemThemeSettingsAlter;

/**
 * Hook implementations for ui_styles_entity_status.
 */
class UiStylesEntityStatusHooks {

  /**
   * Implements hook_entity_view().
   */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    /** @var \Drupal\ui_styles_entity_status\HookHandler\EntityView $instance */
    $instance = \Drupal::service('class_resolver')->getInstanceFromDefinition(EntityView::class);
    $instance->alter($build, $entity, $display, $view_mode);
  }

  /**
   * Implements hook_form_FORM_ID_alter() for 'system_theme_settings'.
   */
  #[Hook('form_system_theme_settings_alter')]
  public function formSystemThemeSettingsAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\ui_styles_entity_status\HookHandler\FormSystemThemeSettingsAlter $instance */
    $instance = \Drupal::service('class_resolver')->getInstanceFromDefinition(FormSystemThemeSettingsAlter::class);
    $instance->alter($form, $form_state);
  }

}
