<?php

namespace Drupal\khartasia_guided_forms\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\Plugin\EntityReferenceSelection\NodeSelection;

/**
 * Sélection des attestations « concurrentes » d'une attestation.
 *
 * DÉFINITIONS
 * - Attestation concurrente : autre attestation qui contredit ou remplace celle que l'on saisit.
 * - Règle (lot 6) : elle doit porter sur le MÊME papier ; l'attestation en cours d'édition
 *   n'est jamais proposée à elle-même.
 * Les deux réglages (papier_nid, exclure_nid) sont posés par le formulaire d'attestation ;
 * sans papier_nid (attestation sans papier), seule la restriction au type « attestation » s'applique.
 */
#[EntityReferenceSelection(
  id: 'khartasia_concurrente',
  label: new TranslatableMarkup('Attestation concurrente (même papier)'),
  group: 'khartasia_concurrente',
  weight: 0,
  entity_types: ['node'],
)]
class ConcurrenteSelection extends NodeSelection {

  public function defaultConfiguration() {
    return ['papier_nid' => 0, 'exclure_nid' => 0] + parent::defaultConfiguration();
  }

  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);
    $query->condition('type', 'attestation');
    if (!empty($this->configuration['papier_nid'])) {
      $query->condition('field_att_papier', (int) $this->configuration['papier_nid']);
    }
    if (!empty($this->configuration['exclure_nid'])) {
      $query->condition('nid', (int) $this->configuration['exclure_nid'], '<>');
    }
    return $query;
  }

}
