<?php

namespace Drupal\attribution\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'Source & License' widget.
 */
#[FieldWidget(
  id: 'attribution_source_license',
  label: new TranslatableMarkup('Source & License'),
  field_types: ['attribution'],
)]
class AttributionSourceLicenseWidget extends AttributionWidgetBase {

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
    $element['source'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline'],
      ],
    ];
    $element['source']['source_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Source'),
      '#default_value' => $items[$delta]->source_name ?? NULL,
      '#size' => 20,
      '#placeholder' => $this->t('Untitled'),
    ];
    $element['source']['source_link'] = [
      '#type' => 'url',
      '#title' => $this->t('Link'),
      '#default_value' => $items[$delta]->source_link ?? NULL,
      '#size' => 20,
      '#placeholder' => $this->t('https://example.org/source'),
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

  /**
   * {@inheritdoc}
   */
  protected function getNestedProperties(): array {
    return [
      'source_name' => ['source', 'source_name'],
      'source_link' => ['source', 'source_link'],
    ];
  }

}
