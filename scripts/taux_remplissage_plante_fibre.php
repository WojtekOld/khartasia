<?php
$ids = \Drupal::entityQuery('node')
  ->condition('type', 'plante_fibre')
  ->accessCheck(FALSE)
  ->execute();

$total = count($ids);
echo "Total plante_fibre : $total\n\n";

$champs = ['field_pf_type', 'field_pf_long_min', 'field_pf_larg_min', 'field_pf_extremites',
  'field_pf_striations', 'field_pf_cellules', 'field_pf_particularites', 'field_pf_herzberg',
  'field_pf_graff_c', 'field_pf_notes', 'field_pf_source'];

$remplis = array_fill_keys($champs, 0);
$vides_partout = 0;

foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $node) {
  $au_moins_un = false;
  foreach ($champs as $champ) {
    if ($node->hasField($champ) && !$node->get($champ)->isEmpty()) {
      $remplis[$champ]++;
      $au_moins_un = true;
    }
  }
  if (!$au_moins_un) {
    $vides_partout++;
  }
}

echo "Taux de remplissage par champ :\n";
foreach ($remplis as $champ => $n) {
  $pct = round($n / $total * 100, 1);
  echo "  $champ : $n / $total ($pct%)\n";
}

echo "\nFiches totalement vides (aucun champ field_pf_* rempli) : $vides_partout / $total\n";
