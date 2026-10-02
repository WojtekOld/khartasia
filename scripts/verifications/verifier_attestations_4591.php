<?php
// verifier_attestations_4591.php — v2, corrige le bug d'interpolation.

$nid = 4591;

$ids = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'attestation')
  ->condition('field_att_papier', $nid)
  ->execute();

echo "Attestations trouvées référençant papers/{$nid} : " . count($ids) . "\n\n";

foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
  $statut = $n->hasField('field_att_statut') && !$n->get('field_att_statut')->isEmpty()
    ? $n->get('field_att_statut')->value : '(vide)';
  $valeur = $n->hasField('field_att_valeur') && !$n->get('field_att_valeur')->isEmpty()
    ? mb_substr($n->get('field_att_valeur')->value, 0, 70) : $n->getTitle();
  $axe = ($n->hasField('field_att_axe') && !$n->get('field_att_axe')->isEmpty())
    ? $n->get('field_att_axe')->entity?->label() : '(vide)';
  $type = ($n->hasField('field_att_type') && !$n->get('field_att_type')->isEmpty())
    ? $n->get('field_att_type')->entity?->label() : '(vide)';
  $publie = $n->isPublished() ? 'PUBLIÉ' : 'NON PUBLIÉ';
  $cree = date('Y-m-d H:i:s', (int) $n->getCreatedTime());

  echo "attestation/{$n->id()}  [{$publie}]  créé={$cree}  statut={$statut}  axe={$axe}  type={$type}\n";
  echo "  {$valeur}...\n\n";
}

if (empty($ids)) {
  echo "Aucune attestation trouvée du tout.\n";
}
