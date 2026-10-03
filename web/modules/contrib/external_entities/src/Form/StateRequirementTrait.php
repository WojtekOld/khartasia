<?php

namespace Drupal\external_entities\Form;

/**
 * Provides a method to transfer static requirements to states API.
 */
trait StateRequirementTrait {

  /**
   * Transfer static requirements to states API.
   *
   * When using states API, some sub-forms are often hidden or disabled by the
   * state of other form elements. However, such sub-forms may contain
   * required elements. If those elements are not filled properly, their
   * '#required' property is set, and their sub-form can not be changed by the
   * user, they will prevent form submission while the user can not fix the
   * element.
   *
   * To avoid that behavior, this method processes the given sub-form and
   * replace '#required' properties by a '#states' 'required' condition based on
   * the provided $condition which should be the same as the one used to hide or
   * disable the sub-form.
   *
   * Example:
   * @code
   *   $form['use_extra'] = [
   *     '#type' => 'checkbox',
   *     '#title' => $this->t('Use extra fields'),
   *     '#ajax' => [
   *       'callback' => '::updateExtraFields',
   *       'wrapper' => 'extra-fields-wrapper',
   *     ],
   *   ];
   *
   *   $form['extra_fields'] = [
   *     '#type' => 'container',
   *     '#attributes' => ['id' => 'extra-fields-wrapper'],
   *     '#states' => [
   *       'visible' => [
   *         ':input[name="use_extra"]' => ['checked' => TRUE],
   *       ],
   *     ],
   *   ];
   *
   *   $form['extra_fields']['my_required_field'] = [
   *     '#type' => 'textfield',
   *     '#title' => $this->t('Extra field'),
   *     // This will prevent form validation if the field is left empty while
   *     // 'use_extra' is not checked.
   *     '#required' => TRUE,
   *   ];
   * @endcode
   *
   * Solution:
   * @code
   *   // Add this line after the sub-form 'extra_fields' has been fully built:
   *   $this->requireOnState(
   *     $form['extra_fields'],
   *     $form['extra_fields']['#states']['visible'],
   *   );
   * @endcode
   *
   * Changes made:
   * @code
   *   $form['extra_fields']['my_required_field'] = [
   *     '#type' => 'textfield',
   *     '#title' => $this->t('Extra field'),
   *     '#states' => [
   *       'required' => [
   *         ':input[name="use_extra"]' => ['checked' => TRUE],
   *       ],
   *     ],
   *   ];
   * @endcode
   *
   * @param array $subform
   *   The subform array to process.
   * @param array $condition
   *   The states API condition to use to enable requirements. If $condition is
   *   empty, requirements are removed.
   */
  public function requireOnState(array &$subform, array $condition) {
    // Transfer requirement to states API if needed.
    if (!empty($subform['#required'])
      && empty($subform['#states']['required'])
    ) {
      if (!empty($condition)) {
        $subform['#states']['required'] = $condition;
      }
      unset($subform['#required']);
    }
    // Process sub-elements.
    foreach ($subform as $key => $element) {
      if ((is_numeric($key) || ($key[0] !== '#')) && is_array($subform[$key])) {
        $this->requireOnState($subform[$key], $condition);
      }
    }
  }

}
