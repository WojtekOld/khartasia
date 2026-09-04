<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_field_formatters\Hook;

use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for ui_patterns_field_formatters.
 */
class UiPatternsFieldFormattersHooks {

  public function __construct(
    protected FieldTypePluginManagerInterface $fieldTypePluginManager,
  ) {}

  /**
   * Implements hook_field_formatter_info_alter().
   */
  #[Hook('field_formatter_info_alter')]
  public function fieldFormatterInfoAlter(array &$info): void {
    $field_types = \array_keys($this->fieldTypePluginManager->getDefinitions());
    // Allow any field to be formatted with ui patterns field formatters.
    // Because it is impossible to assign a field formatter to every field types
    // using the plugins attributes.
    $info['ui_patterns_component']['field_types'] = $field_types;
    $info['ui_patterns_component_per_item']['field_types'] = $field_types;
  }

}
