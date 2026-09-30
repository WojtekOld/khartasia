<?php
$n1 = \Drupal\node\Entity\Node::load(3586);
$n2 = \Drupal\node\Entity\Node::load(3879);

foreach (['field_gen_culture_ml', 'field_intr_paper_ml', 'field_mode_prep_ml'] as $champ) {
  $v1 = $n1->get($champ)->getValue()[0] ?? [];
  $v2 = $n2->get($champ)->getValue()[0] ?? [];

  echo "=== $champ ===\n";
  echo "format 3586 : " . ($v1['format'] ?? '(aucun)') . "\n";
  echo "format 3879 : " . ($v2['format'] ?? '(aucun)') . "\n";

  $val1 = $v1['value'] ?? '';
  $val2 = $v2['value'] ?? '';

  echo "longueur 3586 : " . strlen($val1) . " octets\n";
  echo "longueur 3879 : " . strlen($val2) . " octets\n";
  echo "value strictement identique : " . ($val1 === $val2 ? 'OUI' : 'NON') . "\n";

  if ($val1 !== $val2) {
    // Trouver le premier point de divergence
    $min_len = min(strlen($val1), strlen($val2));
    for ($i = 0; $i < $min_len; $i++) {
      if ($val1[$i] !== $val2[$i]) {
        echo "Première différence à l'octet $i :\n";
        echo "  3586: ..." . substr($val1, max(0, $i - 20), 40) . "...\n";
        echo "  3879: ..." . substr($val2, max(0, $i - 20), 40) . "...\n";
        break;
      }
    }
  }
  echo "\n";
}
