<?php
use Drupal\views\Entity\View;

echo "=== papers_lies ===\n";
$v1 = View::load('papers_lies');
if ($v1) {
  $d = $v1->getDisplay('default');
  echo "Base table : " . ($v1->get('base_table') ?? '?') . "\n";
  echo "Filtres : " . implode(', ', array_keys($d['display_options']['filters'] ?? [])) . "\n";
  echo "Champs : " . implode(', ', array_keys($d['display_options']['fields'] ?? [])) . "\n";
  echo "Affichages : " . implode(', ', array_keys($v1->get('display'))) . "\n";
} else {
  echo "Introuvable.\n";
}

echo "\n=== plante_papiers_lies (celle utilisée activement ce soir) ===\n";
$v2 = View::load('plante_papiers_lies');
if ($v2) {
  $d2 = $v2->getDisplay('default');
  echo "Base table : " . ($v2->get('base_table') ?? '?') . "\n";
  echo "Filtres : " . implode(', ', array_keys($d2['display_options']['filters'] ?? [])) . "\n";
} else {
  echo "Introuvable.\n";
}

echo "\n=== Confirmation : papers_noms_communs est-elle encore appelée dans un Twig ? ===\n";
$theme_path = '/var/www/html/web/themes/custom/khartasia_ui/templates';
$trouve = shell_exec("grep -rl 'papers_noms_communs' $theme_path 2>/dev/null");
echo $trouve ? "ENCORE RÉFÉRENCÉE dans : $trouve" : "Plus aucune référence dans les templates — sûre à supprimer.\n";
