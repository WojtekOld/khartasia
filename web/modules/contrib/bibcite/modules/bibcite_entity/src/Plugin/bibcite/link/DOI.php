<?php

namespace Drupal\bibcite_entity\Plugin\bibcite\link;

use Drupal\bibcite_entity\Attribute\BibciteLink;
use Drupal\bibcite_entity\Entity\ReferenceInterface;
use Drupal\bibcite_entity\Plugin\BibciteLinkPluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Build DOI lookup link.
 *
 * @BibciteLink(
 *   id = "doi",
 *   label = @Translation("DOI"),
 * )
 */
#[
  BibciteLink(
    id: "doi",
    label: new TranslatableMarkup("DOI"),
  )
]
class DOI extends BibciteLinkPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildUrl(ReferenceInterface $reference) {
    $doi_field = $reference->get('bibcite_doi');

    if (!$doi_field->isEmpty()) {
      return Url::fromUri("http://dx.doi.org/{$doi_field->value}");
    }

    return NULL;
  }

}
