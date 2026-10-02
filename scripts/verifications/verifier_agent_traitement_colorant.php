<?php
// verifier_agent_traitement_colorant.php — via `drush php-script`.
// Que couvrent RÉELLEMENT les axes "Agent de traitement" et "Colorant" ?
// Décisif avant de proposer un nouveau mécanisme pour dispersants/charges/adhésifs.

$storage = \Drupal::entityTypeManager()->getStorage('node');

foreach (['Agent de traitement', 'Colorant'] as $axe_nom) {
  echo "=== axe: $axe_nom ===\n";
  $ids = $storage->getQuery()->accessCheck(FALSE)
    ->condition('type', 'attestation')
    ->condition('field_att_axe.entity.name', $axe_nom)
    ->execute();

  foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
    $papier_id = ($n->hasField('field_att_papier') && !$n->get('field_att_papier')->isEmpty())
      ? $n->get('field_att_papier')->target_id : null;
    $papier_titre = $papier_id ? (\Drupal\node\Entity\Node::load($papier_id)?->getTitle() ?? '?') : '(aucun)';
    $valeur = ($n->hasField('field_att_valeur') && !$n->get('field_att_valeur')->isEmpty())
      ? mb_substr($n->get('field_att_valeur')->value, 0, 100) : $n->getTitle();
    $type = ($n->hasField('field_att_type') && !$n->get('field_att_type')->isEmpty())
      ? $n->get('field_att_type')->entity?->label() : '(vide)';
    $plante = ($n->hasField('field_att_plante') && !$n->get('field_att_plante')->isEmpty())
      ? \Drupal\node\Entity\Node::load($n->get('field_att_plante')->target_id)?->getTitle() : null;

    echo "  attestation/{$n->id()}  papier={$papier_titre}  type={$type}" . ($plante ? "  plante={$plante}" : "") . "\n";
    echo "    {$valeur}...\n";
  }
  echo "\n";
}
