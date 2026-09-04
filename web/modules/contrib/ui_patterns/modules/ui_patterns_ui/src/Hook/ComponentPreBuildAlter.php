<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_ui\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\ui_patterns_ui\Entity\ComponentFormDisplay;

/**
 * Hook implementations for ui_patterns_ui.
 */
class ComponentPreBuildAlter {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_ui_patterns_component_pre_build_alter().
   */
  #[Hook('ui_patterns_component_pre_build_alter')]
  public function uiPatternsComponentPreBuildAlter(array &$element): void {
    if (!isset($element['#ui_patterns']['display_id']) || !isset($element['#component'])) {
      return;
    }
    $display_id = $element['#ui_patterns']['display_id'];

    $display = $this->entityTypeManager->getStorage('component_form_display')
      ->load($display_id);
    if ($display !== NULL) {
      \assert($display instanceof ComponentFormDisplay);
      $elementMetadata = BubbleableMetadata::createFromRenderArray($element);
      $elementMetadata->merge(BubbleableMetadata::createFromObject($display));
      $elementMetadata->addCacheTags(['config:' . $display->getConfigDependencyName()]);
      $elementMetadata->applyTo($element);
      $options = $display->getPropSlotOptions();
      foreach ($options as $prop_slot_id => $option) {
        if (isset($option['source_id'])) {
          if ($option['region'] === 'configure') {
            if ($prop_slot_id === 'variant') {
              $element['#ui_patterns']['variant_id'] = [
                'source' => $option['source'] ?? [],
                'source_id' => $option['source_id'],
              ];
              continue;
            }
            if ($display->isSlot($prop_slot_id)) {
              $element['#ui_patterns']['slots'][$prop_slot_id] = [
                'sources' => [[
                  'source' => $option['source'] ?? [],
                  'source_id' => $option['source_id'],
                ],
                ],
              ];
            }
            else {
              $element['#ui_patterns']['props'][$prop_slot_id] = [
                'source' => $option['source'] ?? [],
                'source_id' => $option['source_id'],
              ];
            }
          }
        }
      }
    }
  }

}
