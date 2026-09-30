<?php
$lianqi = \Drupal\node\Entity\Node::load(290);
echo "=== Lianqi zhi ===\n";
echo "Composition : ";
foreach ($lianqi->get('field_genus_pap_entity') as $item) {
  if ($item->entity) echo $item->entity->label() . " ";
}
echo "\n";
echo "Variante de : ";
foreach ($lianqi->get('field_variante_de') as $item) {
  if ($item->entity) echo $item->entity->label() . " (nid={$item->entity->id()})";
}
echo "\n\n=== Liansi (4578) — ne doit PAS avoir Lianqi zhi comme variante connue ===\n";
$db = \Drupal::database();
$verif = $db->query("SELECT entity_id FROM node__field_variante_de WHERE field_variante_de_target_id = 4578")->fetchCol();
echo "Variantes de Liansi : " . (empty($verif) ? "aucune (correct)" : implode(',', $verif) . " (ERREUR, devrait être vide)") . "\n";

echo "\n=== Lianshi zhi (4591) — doit avoir Lianqi zhi comme variante connue ===\n";
$verif2 = $db->query("SELECT entity_id FROM node__field_variante_de WHERE field_variante_de_target_id = 4591")->fetchCol();
echo "Variantes de Lianshi zhi : " . implode(',', $verif2) . "\n";
