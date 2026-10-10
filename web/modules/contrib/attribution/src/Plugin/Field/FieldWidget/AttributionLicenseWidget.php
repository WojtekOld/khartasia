<?php

namespace Drupal\attribution\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'License only' widget.
 */
#[FieldWidget(
  id: 'attribution_license',
  label: new TranslatableMarkup('License only'),
  field_types: ['attribution'],
)]
class AttributionLicenseWidget extends AttributionWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $field_settings = $this->getFieldSettings();
    $element += [
      '#type' => 'details',
      '#summary_attributes' => [],
      '#open' => TRUE,
    ];
    $element['license'] = [
      '#type' => 'select',
      '#title' => $this->t('License'),
      '#default_value' => $items[$delta]->license ?? NULL,
      '#options' => $this->buildLicenseOptions($field_settings),
    ];
    $element = $this->buildCreationTypeElements($element, $delta, $items);
    return $this->attachElementDefaults($element);
  }

}
