<?php
$plante = \Drupal\node\Entity\Node::load(22);
$fibre = \Drupal\node\Entity\Node::load(3829);

echo "=== Contenu complet de field_ident_fibre (nœud Plantes 22) ===\n\n";
echo strip_tags($plante->get('field_ident_fibre')->value, '<table><tr><td><th>') . "\n";
echo "\n--- HTML brut (pour voir la vraie structure du tableau) ---\n";
echo $plante->get('field_ident_fibre')->value . "\n";

echo "\n\n=== Champs structurés field_pf_* sur plante_fibre (nid=3829) ===\n\n";
$champs_pf = ['field_pf_type', 'field_pf_long_min', 'field_pf_long_max', 'field_pf_larg_min', 'field_pf_larg_max',
  'field_pf_extremites', 'field_pf_striations', 'field_pf_cellules', 'field_pf_particularites',
  'field_pf_herzberg', 'field_pf_graff_c', 'field_pf_notes', 'field_pf_source'];

foreach ($champs_pf as $champ) {
  if ($fibre->hasField($champ) && !$fibre->get($champ)->isEmpty()) {
    $val = $fibre->get($champ)->value;
    echo "$champ : " . strip_tags($val) . "\n";
  } else {
    echo "$champ : (vide)\n";
  }
}
