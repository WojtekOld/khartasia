<?php

namespace Drupal\attribution\Plugin\Field\FieldFormatter;

use Drupal\attribution\Entity\AttributionLicense;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'HTML' formatter.
 */
#[FieldFormatter(
  id: 'attribution_html',
  label: new TranslatableMarkup('HTML'),
  field_types: ['attribution'],
)]
class AttributionHtmlFormatter extends AttributionFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];

    foreach ($items as $delta => $item) {
      $values = $item->getValue();
      $element[$delta]['#theme'] = 'attribution_html';
      if ($values['license'] ?? NULL) {
        /** @var \Drupal\attribution\Entity\AttributionLicense $license */
        $license = AttributionLicense::load($values['license']);
        $element[$delta]['#attributes']['class'] = $this->buildLicenseClasses($license);
        $element[$delta]['#license'] = $license;
      }
      if ($source = $this->buildSourceValues($values)) {
        $element[$delta]['#source'] = $source;
      }
      if ($author = $this->buildAuthorValues($values)) {
        $element[$delta]['#author'] = $author;
      }
      if ($creation = $this->buildCreationValues($values)) {
        $element[$delta]['#creation'] = $creation;
        $element[$delta]['#attributes']['class'][] = 'attribution--creation-' . $creation['type'];
        $element[$delta]['#attached']['library'][] = 'attribution/attribution_ai_provenance';
      }
    }

    return $element;
  }

}
