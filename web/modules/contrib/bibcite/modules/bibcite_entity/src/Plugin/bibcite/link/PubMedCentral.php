<?php

namespace Drupal\bibcite_entity\Plugin\bibcite\link;

use Drupal\bibcite_entity\Attribute\BibciteLink;
use Drupal\bibcite_entity\Entity\ReferenceInterface;
use Drupal\bibcite_entity\Plugin\BibciteLinkPluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Build PubMedCentral lookup link.
 *
 * @BibciteLink(
 *   id = "pubmedcentral",
 *   label = @Translation("PubMed Central"),
 * )
 */
#[
  BibciteLink(
    id: "pubmedcentral",
    label: new TranslatableMarkup("PubMed Central"),
  )
]
class PubMedCentral extends BibciteLinkPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildUrl(ReferenceInterface $reference) {
    $pmcid_field = $reference->get('bibcite_pmcid');

    if (!$pmcid_field->isEmpty()) {
      return Url::fromUri("https://www.ncbi.nlm.nih.gov/pmc/articles/{$pmcid_field->value}");
    }

    return NULL;
  }

}
