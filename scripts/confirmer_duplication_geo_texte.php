<?php
$geo_chine = \Drupal\node\Entity\Node::load(3593); // plante_geo Broussonetia — Chine
$texte_chinois = \Drupal\node\Entity\Node::load(3312); // plante_texte Broussonetia [Chinois]

echo "=== plante_geo (Chine), field_pg_culture ===\n";
echo $geo_chine->get('field_pg_culture')->value . "\n\n";

echo "=== plante_texte (Chinois), field_gen_culture_ml ===\n";
echo $texte_chinois->get('field_gen_culture_ml')->value . "\n\n";

echo "=== Identiques ? ===\n";
$a = trim(strip_tags($geo_chine->get('field_pg_culture')->value ?? ''));
$b = trim(strip_tags($texte_chinois->get('field_gen_culture_ml')->value ?? ''));
echo (mb_substr($a, 0, 200) === mb_substr($b, 0, 200)) ? "OUI, contenu identique.\n" : "NON, différent.\n";

echo "\n=== field_zones_geo sur Broussonetia papyrifera (nid=22) : combien de fiches liées ? ===\n";
$node = \Drupal\node\Entity\Node::load(22);
if ($node->hasField('field_zones_geo')) {
  foreach ($node->get('field_zones_geo') as $item) {
    $g = $item->entity;
    echo "  nid={$g->id()} : {$g->label()}\n";
  }
}

echo "\n=== field_zones_geo pointe-t-il vers zone_historique_production quelque part ? ===\n";
foreach (['field_pg_pays', 'field_pg_region', 'field_pg_region_hist'] as $c) {
  $fc = \Drupal::entityTypeManager()->getStorage('field_config')->load("node.plante_geo.$c");
  if ($fc) {
    echo "  $c : type=" . $fc->getType() . "\n";
  }
}
