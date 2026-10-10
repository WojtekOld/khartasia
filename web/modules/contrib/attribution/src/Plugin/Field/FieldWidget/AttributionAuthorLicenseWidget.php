<?php

namespace Drupal\attribution\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'Author & License' widget.
 */
#[FieldWidget(
  id: 'attribution_author_license',
  label: new TranslatableMarkup('Author & License'),
  field_types: ['attribution'],
)]
class AttributionAuthorLicenseWidget extends AttributionWidgetBase {

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
    $element['author'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline'],
      ],
    ];
    $element['author']['author_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Author', [], ['context' => 'Attribution author']),
      '#default_value' => $items[$delta]->author_name ?? NULL,
      '#size' => 20,
      '#placeholder' => $this->t('First Middle Lastname'),
    ];
    $element['author']['author_link'] = [
      '#type' => 'url',
      '#title' => $this->t('Link'),
      '#default_value' => $items[$delta]->author_link ?? NULL,
      '#size' => 20,
      '#placeholder' => $this->t('https://author.example.org'),
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
      'author_name' => ['author', 'author_name'],
      'author_link' => ['author', 'author_link'],
    ];
  }

}
