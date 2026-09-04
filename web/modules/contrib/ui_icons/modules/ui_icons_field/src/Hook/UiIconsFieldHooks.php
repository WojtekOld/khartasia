<?php

declare(strict_types=1);

namespace Drupal\ui_icons_field\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for ui_icons_field.
 */
class UiIconsFieldHooks {

  /**
   * Implements hook_config_schema_info_alter().
   */
  #[Hook('config_schema_info_alter')]
  public function configSchemaInfoAlter(array &$definitions): void {
    if (!isset($definitions['field.value.link']['mapping']['options']['mapping'])) {
      return;
    }

    $definitions['field.value.link']['mapping']['options']['mapping']['icon'] = [
      'type' => 'field.value.ui_icon',
    ];
    $definitions['field.value.link']['mapping']['options']['mapping']['icon_display'] = [
      'type' => 'string',
      'label' => 'Icon display position',
    ];
  }

}
