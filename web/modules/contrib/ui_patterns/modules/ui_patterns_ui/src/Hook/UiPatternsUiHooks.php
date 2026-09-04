<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_ui\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for ui_patterns_ui.
 */
class UiPatternsUiHooks {

  /**
   * Implements hook_ui_patterns_form_alter().
   *
   * @SuppressWarnings("PHPMD.UnusedFormalParameter")
   */
  #[Hook('ui_patterns_form_alter')]
  public function uiPatternsFormAlter(array &$form, FormStateInterface $form_state): void {
    if ($form['#allow_override'] ?? TRUE) {
      $form['#type'] = 'uip_displays_form';
    }
  }

  /**
   * Implements hook_config_schema_info_alter().
   */
  #[Hook('config_schema_info_alter')]
  public function configSchemaInfoAlter(array &$definitions): void {
    $definitions['ui_patterns_component']['mapping']['display_id'] = [
      'type' => 'string',
      'label' => 'UI Patterns UI Display ID',
    ];
  }

}
