<?php

namespace Drupal\external_entities\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;

/**
 * Generates a subform.
 */
class XnttSubformState {

  /**
   * Creates a new instance for a subform.
   *
   * The Drupal implementation of SubformState::createForSubform() does not
   * automatically set the '#parents' arrays and then complains about it when
   * a value is fetched using SubformState::getValue(). This static method
   * automatically sets parent arrays to avoid that.
   *
   * @param array $parents
   *   The array of parent element names.
   * @param array &$form
   *   The subform's parent form.
   * @param \Drupal\Core\Form\FormStateInterface $parent_form_state
   *   The parent form state.
   *
   * @return \Drupal\Core\Form\SubformState
   *   A new sub-form state object.
   */
  public static function createForSubform(
    array $parents,
    array &$form,
    FormStateInterface $parent_form_state,
  ) :SubformState {
    $form['#parents'] ??= [];
    NestedArray::setValue(
      $form,
      array_merge($parents, ['#parents']),
      array_merge($form['#parents'], $parents)
    );
    return SubformState::createForSubform(
      NestedArray::getValue($form, $parents),
      $form,
      $parent_form_state
    );
  }

}
