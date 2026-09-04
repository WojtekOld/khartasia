<?php

namespace Drupal\tmgmt_deepl_glossary\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the glossary item widget.
 */
#[FieldWidget(
  id: 'deepl_glossary_item_widget',
  label: new TranslatableMarkup('Glossary item'),
  field_types: ['deepl_glossary_item'],
)]
class DeeplGlossaryItemWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $values = $items[$delta]?->getValue() ?? [];
    assert(is_array($values));

    // Subject.
    $element['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Source text'),
      '#placeholder' => $this->t('Enter the source text of the glossary item.'),
      '#default_value' => $values['subject'] ?? NULL,
      '#maxlength' => 255,
    ];

    // Definition.
    $element['definition'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Target text'),
      '#placeholder' => $this->t('Enter the target text of the glossary item.'),
      '#default_value' => $values['definition'] ?? NULL,
      '#maxlength' => 255,
    ];

    return $element;
  }

}
