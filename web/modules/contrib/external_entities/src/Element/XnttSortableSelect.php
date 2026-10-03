<?php

namespace Drupal\external_entities\Element;

use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element\Select;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a sortable select form element.
 *
 * @FormElement("xnttsortableselect")
 */
#[FormElement('xnttsortableselect')]
class XnttSortableSelect extends Select {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();
    $info['#process'] = [
      [get_called_class(), 'processXnttSortableSelect'],
    ];
    $info['#pre_render'] = [
      [get_called_class(), 'preRenderXnttSortableSelect'],
    ];
    $info['#multiple'] = TRUE;
    return $info;
  }

  /**
   * Process callback for the xnttsortableselect element.
   */
  public static function processXnttSortableSelect(&$element, FormStateInterface $form_state, &$complete_form) {
    $element['#name'] = $element['#name'] . '[]';
    $element['#attached']['library'][] = 'external_entities/xntt_sortable_select';
    $element['#attributes']['class'][] = 'xntt-sortable-select';
    return $element;
  }

  /**
   * Pre-render callback for the xnttsortableselect element.
   */
  public static function preRenderXnttSortableSelect($element) {
    $element['#multiple'] = TRUE;
    $element['#attributes']['multiple'] = 'multiple';
    $element['#attributes']['size'] = $element['#size'] ?? 5;
    return $element;
  }

}
