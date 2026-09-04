<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_layouts\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;

/**
 * Hook implementations for ui_patterns_layouts.
 */
class UiPatternsLayoutsHooks {

  /**
   * Implements hook_element_info_alter().
   */
  #[Hook('element_info_alter')]
  public function elementInfoAlter(array &$types): void {
    if (isset($types['component'])) {
      \array_unshift($types['component']['#pre_render'], [
        'Drupal\ui_patterns_layouts\Element\ComponentAlterer',
        'processLayoutBuilderRegions',
      ]);
    }
  }

  /**
   * Implements hook_entity_view_alter().
   *
   * Field layout is not adding entity information to the layout.
   * We need to add it in another step.
   *
   * @SuppressWarnings("PHPMD.UnusedFormalParameter")
   */
  #[Hook('entity_view_alter')]
  public function entityViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display): void {
    if (isset($build['_field_layout']['#ui_patterns'], $build['_field_layout']['#source_contexts'])) {
      $build['_field_layout']['#source_contexts']['entity'] = EntityContext::fromEntity($entity);
      $build['_field_layout']['#source_contexts']['bundle'] = new Context(ContextDefinition::create('string'), $entity->bundle() ?? '');
    }
  }

}
