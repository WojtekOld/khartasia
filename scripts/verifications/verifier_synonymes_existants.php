<?php
// verifier_synonymes_existants.php — via `drush php-script`, aucun argument.
// Les 7 attestations de type "Synonyme" : que contiennent-elles, et sur
// quelles fiches papers ? Décisif pour trancher le sort de nom_papier.

$storage = \Drupal::entityTypeManager()->getStorage('node');
$ids = $storage->getQuery()->accessCheck(FALSE)
  ->condition('type', 'attestation')
  ->condition('field_att_type.entity.name', 'Synonyme')
  ->execute();

foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
  $papier_id = ($n->hasField('field_att_papier') && !$n->get('field_att_papier')->isEmpty())
    ? $n->get('field_att_papier')->target_id : null;
  $papier_titre = $papier_id ? (\Drupal\node\Entity\Node::load($papier_id)?->getTitle() ?? '?') : '(aucun papier lié)';
  $valeur = ($n->hasField('field_att_valeur') && !$n->get('field_att_valeur')->isEmpty())
    ? mb_substr($n->get('field_att_valeur')->value, 0, 90) : $n->getTitle();
  $statut = ($n->hasField('field_att_statut') && !$n->get('field_att_statut')->isEmpty())
    ? $n->get('field_att_statut')->value : '(vide)';

  echo "attestation/{$n->id()}  papier={$papier_titre} (papers/{$papier_id})  statut={$statut}\n";
  echo "  {$valeur}...\n\n";
}
