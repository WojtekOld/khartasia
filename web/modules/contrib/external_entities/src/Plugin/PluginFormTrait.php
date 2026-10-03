<?php

namespace Drupal\external_entities\Plugin;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\external_entities\Form\AjaxFormTrait;

/**
 * Provides default implementations for plugin configuration forms.
 *
 * @see \Drupal\Core\Plugin\PluginFormInterface
 */
trait PluginFormTrait {

  use AjaxFormTrait;

  /**
   * Form validation handler.
   *
   * @param array $form
   *   An associative array containing the structure of the plugin form as built
   *   by static::buildConfigurationForm().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   *
   * @see \Drupal\Core\Plugin\PluginFormInterface::validateConfigurationForm()
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the plugin form as built
   *   by static::buildConfigurationForm().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   *
   * @see \Drupal\Core\Plugin\PluginFormInterface::submitConfigurationForm()
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if ($this instanceof ConfigurableInterface) {
      $this->setConfiguration($form_state->getValues());
    }
  }

  /**
   * Override the first form with the second one.
   *
   * This method can be used when a base plugin implementation provides a
   * default form that can be overriden by derived plugin types. Sometimes, the
   * base plugin implementation evolves and have new items not taken into
   * account by derived plugin types. In such case, to avoid having unsupported
   * form elements appearing, it is possible to provide a default override to
   * hide or disable them:
   * @code
   *   // To disable new elements:
   *   $default_override = ['#disabled' => TRUE]
   *   // To hide new elements:
   *   $default_override = ['#type' => 'hidden']
   * @endcode
   *
   * @param array $source_form
   *   The base form.
   * @param array $override_form
   *   The override form. Elements that are not really overridden but known and
   *   used by the derived plugin should be set to empty arrays: their default
   *   settings will be kept while the default override will not be applied.
   *   Elements to remove from the original form should not appear in
   *   $override_form.
   *   Note: to override checkbox elements with hidden elements, use default
   *   value 1 for "checked" and '' for "not checked" rather than boolean or
   *   other default values ortherwise boolean values will not be taken into
   *   account.
   *   Note: new elements can be only specified in $override_form and will
   *   appear.
   * @param array $default_override
   *   Default form element override to apply to elements existing in
   *   $source_form but not specified in $override_form.
   *
   * @return array
   *   The form override/merge result.
   */
  public function overrideForm(array $source_form, array $override_form, array $default_override = []) :array {
    $form = NestedArray::mergeDeep(
      $source_form,
      $override_form
    );

    // Cleanup result form: when overriding, some elements may be undisplayed.
    // If their type are cleared or set to hidden, those element properties must
    // be removed to avoid issues with properties like '#states'. However, for
    // hidden elements, their values must be kept.
    // Finally, for elements present in source form but not overridden, it may
    // be because those elements are newer than than the version of the
    // override: the default override should be applied.
    $cleanupForm = function (array &$element, array $existing_override, array $default_override) use (&$cleanupForm) {
      $clear_properties = FALSE;
      $value = NULL;
      // Check if current element has a type.
      if (empty($element['#type'])) {
        // No type, clear element properties.
        $clear_properties = TRUE;
      }
      elseif ('hidden' == $element['#type']) {
        // Hidden type, clear element properties and only keep value.
        $value = $element['#value'] ?? $element['#default_value'] ?? NULL;
        $clear_properties = TRUE;
      }
      // Process properties or sub-elements.
      foreach ($element as $property => &$sub_element) {
        if ((!is_string($property) || ($property[0] != '#'))
            && is_array($sub_element)
        ) {
          // Check if sub-element has been already overriden.
          if (!array_key_exists($property, $existing_override)) {
            // No, apply default override.
            $sub_element = NestedArray::mergeDeep(
              $sub_element,
              $default_override
            );
          }
          // Process sub-element.
          $cleanupForm($sub_element, $existing_override[$property] ?? [], $default_override);
          // Current element has sub-elements and therefore can not have a
          // value. Make sure it is the case.
          unset($value);
        }
        elseif ($clear_properties) {
          // Clear properties.
          unset($element[$property]);
        }
      }
      // Put back hidden value when there is one.
      if (isset($value)) {
        $element['#type'] = 'hidden';
        $element['#default_value'] = $value;
      }
    };

    $cleanupForm($form, $override_form, $default_override);

    return $form;
  }

}
