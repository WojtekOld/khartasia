<?php

namespace Drupal\khartasia_core\Service;

use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

final class ParcoursPapier {

  const SECTIONS = [
    'base' => ['label' => 'Identité', 'route' => 'entity.node.edit_form'], // Vérification §6 : fallback vers route standard
    'noms' => ['label' => 'Noms', 'route' => 'khartasia_noms.add_wizard'],
    'attestations' => ['label' => 'Caractéristiques', 'route' => 'khartasia_guided_forms.papier_attestations'],
  ];

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager
  ) {}

  public function etat(NodeInterface $papier): array {
    $nid = $papier->id();
    $node_storage = $this->entityTypeManager->getStorage('node');
    $etat = [];

    // --- NOMS ---
    $nomsQueryBase = $node_storage->getQuery()
      ->condition('type', 'nom_papier') // 'nom_papier' est un bundle de node, pas un entity type
      ->condition('field_np_papier', $nid)
      ->accessCheck(FALSE);

    $etat['noms']['total'] = (clone $nomsQueryBase)->count()->execute();
    $etat['noms']['a_revoir'] = (clone $nomsQueryBase)
      ->condition('field_np_a_revoir', TRUE)
      ->count()->execute();

    // --- ATTESTATIONS ---
    // G tranché : source_sans_type et dispute_signalee sont désormais
    // persistés dans field_att_revue_motifs (via hook_node_presave +
    // EntityReviewCalculator) — deux requêtes de comptage direct
    // remplacent le chargement + boucle sur chaque attestation.
    $attQueryBase = $node_storage->getQuery()
      ->condition('type', 'attestation')
      ->condition('field_att_papier', $nid)
      ->accessCheck(FALSE);

    $etat['attestations']['total'] = (clone $attQueryBase)->count()->execute();
    $etat['attestations']['sources_sans_type_source'] = (clone $attQueryBase)
      ->condition('field_att_revue_motifs', 'source_sans_type')
      ->count()->execute();
    $etat['attestations']['contradictions_non_resolues'] = (clone $attQueryBase)
      ->condition('field_att_revue_motifs', 'dispute_signalee')
      ->count()->execute();

    return $etat;
  }

}
