<?php

namespace Drupal\khartasia_core\Service;

use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

final class ParcoursPapier {

  // 'param' précise le nom réel du paramètre de route attendu — les trois
  // routes ne s'accordent pas toutes sur 'node' (papier_attestations
  // attend 'papier_nid', d'après la signature de PapierAttestationsForm).
  const SECTIONS = [
    'base'         => ['label' => 'Identité', 'route' => 'entity.node.edit_form', 'param' => 'node'],
    'noms'         => ['label' => 'Noms', 'route' => 'khartasia_noms.add_wizard', 'param' => 'node'],
    'attestations' => ['label' => 'Caractéristiques', 'route' => 'khartasia_guided_forms.papier_attestations', 'param' => 'papier_nid'],
  ];

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager
  ) {}

  public function etat(NodeInterface $papier): array {
    $nid = $papier->id();
    $node_storage = $this->entityTypeManager->getStorage('node');
    $etat = [];

    $nomsQueryBase = $node_storage->getQuery()
      ->condition('type', 'nom_papier')
      ->condition('field_np_papier', $nid)
      ->accessCheck(FALSE);

    $etat['noms']['total'] = (clone $nomsQueryBase)->count()->execute();
    $etat['noms']['a_revoir'] = (clone $nomsQueryBase)
      ->condition('field_np_a_revoir', TRUE)
      ->count()->execute();

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
