<?php

/**
 * @file
 * API file.
 */

declare(strict_types=1);

use Drupal\Core\Form\FormStateInterface;
use Drupal\ui_patterns\Plugin\UiPatterns\PropType\SlotPropType;
use Drupal\ui_patterns\SourceInterface;

/**
 * Alter Hook for SDC Component definition.
 *
 * @param array $definitions
 *   SDC Component definitions.
 *
 * @see \Drupal\ui_patterns\ComponentPluginManager
 */
function hook_component_info_alter(array &$definitions) {
  $definitions['COMPONENT_ID']['slots']['slot_name']['title'] = 'demo';
  // Restrict a slot: the listed components (IDs or tags) only, two at most.
  $definitions['COMPONENT_ID']['slots']['slot_name']['expected'] = ['OTHER_COMPONENT_ID', 'a_tag'];
  $definitions['COMPONENT_ID']['slots']['slot_name']['maxItems'] = 2;
}

/**
 * Alter Hook for UI Patterns source values.
 *
 * @param mixed $value
 *   Value produced by the source.
 * @param \Drupal\ui_patterns\SourceInterface $source
 *   The source object which has produced the value.
 * @param array $source_configuration
 *   The full raw configuration used to build the source.
 *
 * @SuppressWarnings("PHPMD.UnusedFormalParameter")
 */
function hook_ui_patterns_source_value_alter(mixed &$value, SourceInterface $source, array &$source_configuration): void {
  $type_definition = $source->getPropDefinition()['ui_patterns']['type_definition'];
  if ($type_definition instanceof SlotPropType) {
    if (\is_array($value)) {
      $value['#cache']['tags'][] = 'custom_cache_tag';
    }
  }
}

/**
 * Alter Hook for a component form, before the Form API processes it.
 *
 * Fired by every host assembling a component_form element:
 * ComponentFormBuilderTrait, the Component source, the CKEditor 5 dialog.
 * A host sets '#allow_override' to FALSE to keep the plain form.
 *
 * @param array $form
 *   The component_form element.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The form state of the host.
 *
 * @SuppressWarnings("PHPMD.UnusedFormalParameter")
 */
function hook_ui_patterns_form_alter(array &$form, FormStateInterface $form_state): void {
  $form['#component_filter'] = ['my_theme:card'];
}

/**
 * Prepare Hook for UI Patterns render element.
 *
 * Use this hook to prepare the render element
 * before it's passed to the renderer pipeline.
 *
 * @param array $element
 *   The render element.
 *
 * @SuppressWarnings("PHPMD.UnusedFormalParameter")
 */
function hook_ui_patterns_component_pre_build_alter(array &$element): void {}
