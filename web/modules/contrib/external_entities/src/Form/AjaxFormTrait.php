<?php

namespace Drupal\external_entities\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides generic ajax handler for ajax forms.
 */
trait AjaxFormTrait {

  /**
   * Handles AJAX subform update.
   *
   * When using AJAX callbacks in subform, the returned $form object is not
   * the original subform but the full form. Therefore, it is not possible to
   * simply use the knowledge of the subform structure to find out what part of
   * $form should be returned.
   *
   * The trick here is to use the triggering element that comes from the subform
   * and climb back up in the form tree until we find the wrapper element to
   * update and return it.
   *
   * Note: When using AJAX on form elements which trigger the AJAX call on a
   * value change (such as radios or select elements), it is advised to set the
   * form element attribute 'autocomplete' to 'off' to avoid discrepencies
   * between the selected value and the form content if the user refreshes the
   * web browser page.
   * @code
   *   '#attributes' => ['autocomplete' => 'off'],
   * @endcode
   *
   * Usage:
   * @code
   *   $form['maincntr']['subcontainer']['#attributes']['id'] = 'some_predictible_unique_id';
   *   $form['maincntr']['subcontainer']['somewrapper']['some_button'] = [
   *     '#type' => 'submit',
   *     '#value' => $this->t('Do something'),
   *     '#name' => 'do_something',
   *     '#ajax' => [
   *       'callback' => [
   *         get_class($this),
   *         'buildAjaxParentSubForm',
   *       ],
   *       'wrapper' => ($form['maincntr']['subcontainer']['#attributes']['id'] ??= uniqid('x', TRUE)),
   *       'method' => 'replaceWith',
   *       'effect' => 'fade',
   *     ],
   *   ];
   * @endcode
   *
   * @param array $form
   *   The current form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request object.
   *
   * @return array
   *   The part of the form to return as AJAX.
   */
  public static function buildAjaxParentSubForm(
    array &$form,
    FormStateInterface &$form_state,
    Request $request,
  ) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#array_parents'];
    if (!empty($triggering_element['#ajax']['wrapper'])) {
      // Try to retrieve the container parent.
      $container = $form;
      // Is it this parent?
      if (($container['#attributes']['id'] ?? '') == $triggering_element['#ajax']['wrapper']) {
        return $container;
      }
      while (!empty($parents) && is_array($container)) {
        $parent = array_shift($parents);
        $container = $container[$parent];
        // Check children.
        foreach ($container as $key => $children_container) {
          if ((is_numeric($key) || ('#' != $key[0]))
            && (is_array($children_container))
            && (($children_container['#attributes']['id'] ?? '') == $triggering_element['#ajax']['wrapper'])
          ) {
            return $children_container;
          }
        }
      }
    }

    // Otherwise, fallback to triggering element's parent.
    $parents = array_slice($triggering_element['#array_parents'], 0, -1);
    return NestedArray::getValue($form, $parents);
  }

}
