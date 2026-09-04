<?php

declare(strict_types=1);

namespace Drupal\ui_patterns\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\WysiwygWidget;

/**
 * Hook implementations for ui_patterns.
 */
class UiPatternsHooks {

  /**
   * Implements hook_element_info_alter().
   */
  #[Hook('element_info_alter')]
  public function elementInfoAlter(array &$types): void {
    if (isset($types['component'])) {
      \array_unshift($types['component']['#pre_render'], 'ui_patterns.component_element_alter:alter');
      \array_unshift($types['component']['#pre_render'], 'ui_patterns.component_element_builder:build');
    }
    if (isset($types['text_format'])) {
      $types['text_format']['#pre_render'][] = [
        WysiwygWidget::class,
        'textFormat',
      ];
    }
  }

  /**
   * Implements hook_plugin_filter_TYPE__CONSUMER_alter().
   *
   * Prepare list of block plugins returned when using consumer 'ui_patterns'.
   *
   * @see \Drupal\ui_patterns\Plugin\UiPatterns\Source\BlockSource::listBlockDefinitions()
   *
   * @SuppressWarnings("PHPMD.UnusedFormalParameter")
   */
  #[Hook('plugin_filter_block__ui_patterns_alter')]
  public function pluginFilterBlockUiPatternsAlter(array &$definitions, array $extra): void {
    // We are not allowing 'inline_block' blocks to avoid dependencies from
    // config entities to content entities.
    $definitions = \array_filter($definitions, static function ($definition) {
      return $definition['id'] !== 'inline_block';
    });
    // Those blocks are not allowed in layout builder. Let's do the same.
    unset($definitions['system_main_block'], $definitions['page_title_block']);
    // Add a boolean marker '_ui_patterns_compatible' to all remaining
    // definitions.
    // Other modules can use the same hook to modify this value.
    // This allows to add or remove blocks.
    $forbidden_blocks = [
      'provider' => [
        'layout_builder',
        'ui_patterns_blocks',
      ],
    ];
    foreach ($definitions as $id => &$definition) {
      if (isset($definitions[$id]['_ui_patterns_compatible'])) {
        // When a block plugin already has '_ui_patterns_compatible'
        // It probably means it has been marked by another code.
        // Honor what the other code has done and do not override.
        continue;
      }
      $compatibilityFlag = TRUE;
      if (\in_array($definition['provider'], $forbidden_blocks['provider'], TRUE)) {
        $compatibilityFlag = FALSE;
      }
      // Filter out blocks with _block_ui_hidden ?
      $definitions[$id]['_ui_patterns_compatible'] = $compatibilityFlag;
    }
  }

}
