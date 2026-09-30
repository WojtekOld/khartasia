<?php
// Chercher les noms communs en chinois liés à Broussonetia papyrifera (nid=22),
// notre cas de test récurrent cette nuit — pour voir si une vraie variante
// d'écriture (simplifié/traditionnel/pinyin) est déjà présente dans le corpus.
$db = \Drupal::database();

$ids = $db->query("
  SELECT n.nid
  FROM node__field_genus_com_entity g
  INNER JOIN node_field_data n ON n.nid = g.entity_id
  WHERE g.field_genus_com_entity_target_id = 22
  AND n.langcode = 'fr'
")->fetchCol();

echo "Noms communs liés à Broussonetia papyrifera : " . count($ids) . "\n\n";

foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
  $langue = $n->hasField('field_language_taxo_vern') && !$n->get('field_language_taxo_vern')->isEmpty()
    ? $n->get('field_language_taxo_vern')->entity->label() : '?';
  $scripture = $n->hasField('field_ver_local_scripture') ? ($n->get('field_ver_local_scripture')->value ?? '') : '';
  echo "  [{$langue}] {$n->label()} — écriture locale : " . ($scripture ?: '(vide)') . " (nid={$n->id()})\n";
}
