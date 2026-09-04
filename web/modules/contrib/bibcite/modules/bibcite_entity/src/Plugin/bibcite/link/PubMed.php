<?php

namespace Drupal\bibcite_entity\Plugin\bibcite\link;

use Drupal\bibcite_entity\Attribute\BibciteLink;
use Drupal\bibcite_entity\Entity\ReferenceInterface;
use Drupal\bibcite_entity\Plugin\BibciteLinkPluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Build PubMed lookup link.
 *
 * @BibciteLink(
 *   id = "pubmed",
 *   label = @Translation("PubMed"),
 * )
 */
#[
  BibciteLink(
    id: "pubmed",
    label: new TranslatableMarkup("PubMed"),
  )
]
class PubMed extends BibciteLinkPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildUrl(ReferenceInterface $reference) {
    $pmid_field = $reference->get('bibcite_pmid');

    if (!$pmid_field->isEmpty()) {
      return Url::fromUri("https://www.ncbi.nlm.nih.gov/pubmed/{$pmid_field->value}?dopt=Abstract");
    }

    return NULL;
  }

}
