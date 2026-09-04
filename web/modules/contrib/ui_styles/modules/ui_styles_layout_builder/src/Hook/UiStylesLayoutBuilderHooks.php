<?php

declare(strict_types=1);

namespace Drupal\ui_styles_layout_builder\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\ui_styles_layout_builder\HookHandler\ElementInfoAlter;
use Drupal\ui_styles_layout_builder\HookHandler\EntityViewAlter;
use Drupal\ui_styles_layout_builder\HookHandler\FormLayoutBuilderBlockAlter;
use Drupal\ui_styles_layout_builder\HookHandler\FormLayoutBuilderConfigureSectionAlter;
use Drupal\ui_styles_layout_builder\HookHandler\PreprocessBlock;

/**
 * Hook implementations for ui_styles_layout_builder.
 */
class UiStylesLayoutBuilderHooks {

  /**
   * Implements hook_element_info_alter().
   */
  #[Hook('element_info_alter')]
  public function elementInfoAlter(array &$info): void {
    /** @var \Drupal\ui_styles_layout_builder\HookHandler\ElementInfoAlter $instance */
    $instance = \Drupal::service('class_resolver')->getInstanceFromDefinition(ElementInfoAlter::class);
    $instance->alter($info);
  }

  /**
   * Implements hook_entity_view_alter().
   */
  #[Hook('entity_view_alter')]
  public function entityViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display): void {
    /** @var \Drupal\ui_styles_layout_builder\HookHandler\EntityViewAlter $instance */
    $instance = \Drupal::service('class_resolver')->getInstanceFromDefinition(EntityViewAlter::class);
    $instance->alter($build, $entity, $display);
  }

  /**
   * Implements hook_form_FORM_ID_alter() for 'layout_builder_add_block'.
   */
  #[Hook('form_layout_builder_add_block_alter')]
  public function formLayoutBuilderAddBlockAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\ui_styles_layout_builder\HookHandler\FormLayoutBuilderBlockAlter $instance */
    $instance = \Drupal::service('class_resolver')->getInstanceFromDefinition(FormLayoutBuilderBlockAlter::class);
    $instance->formAlter($form, $form_state);
  }

  /**
   * Implements hook_form_FORM_ID_alter() for 'layout_builder_configure_section'.
   */
  #[Hook('form_layout_builder_configure_section_alter')]
  public function formLayoutBuilderConfigureSectionAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\ui_styles_layout_builder\HookHandler\FormLayoutBuilderConfigureSectionAlter $instance */
    $instance = \Drupal::service('class_resolver')->getInstanceFromDefinition(FormLayoutBuilderConfigureSectionAlter::class);
    $instance->formAlter($form, $form_state);
  }

  /**
   * Implements hook_form_FORM_ID_alter() for 'layout_builder_update_block'.
   */
  #[Hook('form_layout_builder_update_block_alter')]
  public function formLayoutBuilderUpdateBlockAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\ui_styles_layout_builder\HookHandler\FormLayoutBuilderBlockAlter $instance */
    $instance = \Drupal::service('class_resolver')->getInstanceFromDefinition(FormLayoutBuilderBlockAlter::class);
    $instance->formAlter($form, $form_state);
  }

  /**
   * Implements hook_preprocess_HOOK() for 'block'.
   */
  #[Hook('preprocess_block')]
  public function preprocessBlock(array &$variables): void {
    /** @var \Drupal\ui_styles_layout_builder\HookHandler\PreprocessBlock $instance */
    $instance = \Drupal::service('class_resolver')->getInstanceFromDefinition(PreprocessBlock::class);
    $instance->preprocess($variables);
  }

}
