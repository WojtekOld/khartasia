<?php

namespace Drupal\xntt_file_field\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\Element\ManagedFile;
use Drupal\xntt_file_field\ExternalFileStorageInterface;

/**
 * Provides an wrapper to 'managed_file' form element to support external files.
 *
 * @FormElement("external_file")
 */
#[FormElement('external_file')]
class ExternalFile extends ManagedFile {

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    // Get regular managed files.
    $return = parent::valueCallback($element, $input, $form_state);

    // Now add external files.
    $fids = !empty($input['fids']) ? explode(' ', $input['fids']) : [];
    foreach ($fids as $key => $fid) {
      if (preg_match(ExternalFileStorageInterface::XNTT_FILE_ID_REGEX, $fid)) {
        $return['fids'][$key] = $fid;
      }
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public static function validateManagedFile(&$element, FormStateInterface $form_state, &$complete_form) {
    if (!empty($element['fids']['#value'])
      && (preg_match(ExternalFileStorageInterface::XNTT_FILE_ID_REGEX, $element['fids']['#value'][0]))
      && (!empty($element['real_uri']['#value']))
    ) {
      // @todo Validate URI (real_uri). Take into account uri regexp filter.
    }
    parent::validateManagedFile($element, $form_state, $complete_form);
  }

  /**
   * Form API callback: Processes an image_image field element.
   *
   * Expands the image_image type to include the alt and title fields.
   *
   * This method is assigned as a #process callback in formElement() method.
   */
  public static function processExternalFile($element, FormStateInterface $form_state, $form) {
    $item = $element['#value'];
    $item['fids'] = $element['fids']['#value'];

    // Add the additional alt and title fields.
    $element['real_uri'] = [
      '#title' => new TranslatableMarkup('External URI'),
      '#type' => 'textfield',
      '#default_value' => $item['real_uri'] ?? '',
      '#description' => new TranslatableMarkup('Real file URI.'),
      '#maxlength' => 2048,
      '#weight' => -14,
    ];

    // Remove file management interface.
    unset($element['upload']);
    unset($element['upload_button']);
    unset($element['remove_button']);
    // @todo Manage URI adding and removal.
    return $element;
  }

}
