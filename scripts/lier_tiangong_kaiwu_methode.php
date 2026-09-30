<?php
$ref_ids = \Drupal::entityQuery('bibcite_reference')->condition('title', 'Tiangong Kaiwu', 'CONTAINS')->accessCheck(FALSE)->execute();
if (!$ref_ids) {
  echo "Référence Tiangong Kaiwu introuvable.\n";
  exit(1);
}
$ref_id = reset($ref_ids);
echo "Référence trouvée, id=$ref_id\n";

$att_ids = \Drupal::entityQuery('node')
  ->condition('type', 'attestation')
  ->condition('field_att_papier', 4578)
  ->condition('title', 'Composition (Méthode', 'CONTAINS')
  ->accessCheck(FALSE)->execute();

$compteur = 0;
foreach (\Drupal\node\Entity\Node::loadMultiple($att_ids) as $att) {
  $att->set('field_att_source', ['target_id' => $ref_id]);
  $att->save();
  echo "  {$att->label()} → source liée.\n";
  $compteur++;
}
echo "\nTotal lié : $compteur\n";
