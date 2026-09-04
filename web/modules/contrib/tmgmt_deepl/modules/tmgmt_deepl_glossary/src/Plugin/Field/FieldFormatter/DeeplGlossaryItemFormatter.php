<?php

namespace Drupal\tmgmt_deepl_glossary\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'field_deepl_glossary_item' formatter.
 */
#[FieldFormatter(
  id: 'deepl_glossary_item_formatter',
  label: new TranslatableMarkup('DeepL glossary item formatter'),
  field_types: ['deepl_glossary_item'],
)]
class DeeplGlossaryItemFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    foreach ($items as $delta => $item) {
      $values = $item->getValue();
      assert(is_array($values));

      $elements[$delta] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Source text: @subject - Target text: @definition', [
          '@subject' => $values['subject'] ?? '',
          '@definition' => $values['definition'] ?? '',
        ]),
      ];
    }

    return $elements;
  }

}
