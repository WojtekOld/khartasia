<?php

declare(strict_types=1);

namespace Drupal\ui_styles_block\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\block\BlockInterface;
use Drupal\ui_styles_block\HookHandler\BlockPresave;
use Drupal\ui_styles_block\HookHandler\FormBlockFormAlter;
use Drupal\ui_styles_block\HookHandler\PreprocessBlock;

/**
 * Hook implementations for ui_styles_block.
 */
class UiStylesBlockHooks {

  /**
   * Implements hook_ENTITY_TYPE_presave() for 'block'.
   */
  #[Hook('block_presave')]
  public function blockPresave(BlockInterface $entity): void {
    /** @var \Drupal\ui_styles_block\HookHandler\BlockPresave $instance */
    $instance = \Drupal::service('class_resolver')->getInstanceFromDefinition(BlockPresave::class);
    $instance->setThirdPartySettings($entity);
  }

  /**
   * Implements hook_form_FORM_ID_alter() for 'block_form'.
   */
  #[Hook('form_block_form_alter')]
  public function formBlockFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    /** @var \Drupal\ui_styles_block\HookHandler\FormBlockFormAlter $instance */
    $instance = \Drupal::service('class_resolver')->getInstanceFromDefinition(FormBlockFormAlter::class);
    $instance->blockFormAlter($form, $form_state);
  }

  /**
   * Implements hook_preprocess_HOOK() for 'block'.
   */
  #[Hook('preprocess_block')]
  public function preprocessBlock(array &$variables): void {
    /** @var \Drupal\ui_styles_block\HookHandler\PreprocessBlock $instance */
    $instance = \Drupal::service('class_resolver')->getInstanceFromDefinition(PreprocessBlock::class);
    $instance->preprocess($variables);
  }

}
