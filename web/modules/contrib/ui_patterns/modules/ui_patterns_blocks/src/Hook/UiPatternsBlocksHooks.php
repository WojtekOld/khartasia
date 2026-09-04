<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_blocks\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Plugin\Context\EntityContext;

/**
 * Hook implementations for ui_patterns_blocks.
 */
class UiPatternsBlocksHooks {

  /**
   * Implements hook_plugin_filter_TYPE__CONSUMER_alter().
   */
  #[Hook('plugin_filter_block__layout_builder_alter')]
  public function pluginFilterBlockLayoutBuilderAlter(array &$definitions, array $extra): void {
    /** @var \Drupal\layout_builder\SectionStorageInterface $section_storage */
    $section_storage = $extra['section_storage'] ?? NULL;
    $sectionStorageContexts = $section_storage ? $section_storage->getContexts() : NULL;
    $display = $sectionStorageContexts ? $sectionStorageContexts['display'] ?? NULL : NULL;
    $entity_context_exists = $display && $display instanceof EntityContext;
    $this->removeUnsuitableBlocks($definitions, !$entity_context_exists);
  }

  /**
   * Implements hook_plugin_filter_TYPE_alter().
   *
   * @SuppressWarnings("PHPMD.UnusedFormalParameter")
   */
  #[Hook('plugin_filter_block_alter')]
  public function pluginFilterBlockAlter(array &$definitions, array $extra, ?string $consumer): void {
    if ($consumer !== 'layout_builder') {
      // In all User interfaces except Layout builder,
      // we remove blocks defined by ui_patterns_blocks
      // which declare entity in context_definitions.
      $this->removeUnsuitableBlocks($definitions, TRUE);
    }
  }

  /**
   * Remove entity SDC component blocks with or without entity context.
   *
   * @param array $definitions
   *   The block plugin definitions.
   * @param bool $removeBlocksWithContexts
   *   If the block with contexts should be removed.
   */
  protected function removeUnsuitableBlocks(array &$definitions, bool $removeBlocksWithContexts = TRUE): void {
    foreach ($definitions as $id => $definition) {
      if ($definition['provider'] !== 'ui_patterns_blocks') {
        continue;
      }
      $block_has_entity_context = isset($definition['context_definitions'])
        && \is_array($definition['context_definitions'])
        && isset($definition['context_definitions']['entity']);
      if (($removeBlocksWithContexts && $block_has_entity_context) || (!$removeBlocksWithContexts && !$block_has_entity_context)) {
        unset($definitions[$id]);
      }
    }
  }

}
