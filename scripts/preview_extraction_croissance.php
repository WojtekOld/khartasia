<?php
// Charger les pays connus du vocabulaire (niveau racine)
$pays_termes = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
  ->loadByProperties(['vid' => 'zone_croissance', 'parent' => 0]);
$noms_pays = [];
foreach ($pays_termes as $t) {
  $noms_pays[$t->label()] = $t->id();
}
// Trier par longueur décroissante pour matcher "Nei Mongol/Mongolie intérieure" avant "Mongolie"
uksort($noms_pays, fn($a, $b) => strlen($b) - strlen($a));

// Charger les provinces connues (par pays)
$provinces_par_pays = [];
foreach ($noms_pays as $nom => $tid) {
  $enfants = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'zone_croissance', 'parent' => $tid]);
  foreach ($enfants as $e) {
    $provinces_par_pays[$nom][] = $e->label();
  }
}

$nids_test = [1741, 1785, 1805, 1835, 1847, 25, 32, 14];

foreach (\Drupal\node\Entity\Node::loadMultiple($nids_test) as $node) {
  if (!$node->hasField('field_aire_croissance') || $node->get('field_aire_croissance')->isEmpty()) {
    continue;
  }
  $texte = strip_tags($node->get('field_aire_croissance')->value);
  echo "--- " . $node->label() . " (nid=" . $node->id() . ") ---\n";

  $pays_trouves = [];
  foreach ($noms_pays as $nom => $tid) {
    if (mb_stripos($texte, $nom) !== false) {
      $pays_trouves[] = $nom;
    }
  }
  echo "Pays détectés : " . (implode(', ', $pays_trouves) ?: '(aucun)') . "\n";

  // Chercher les parenthèses après un nom de pays, et vérifier leur contenu
  if (preg_match_all('/([A-ZÀ-Ý][a-zà-ÿ\s\/]+)\s*\(([^)]+)\)/u', $texte, $matches, PREG_SET_ORDER)) {
    foreach ($matches as $m) {
      $pays_candidat = trim($m[1]);
      $contenu = $m[2];
      $items = array_map('trim', explode(',', $contenu));

      $connu = isset($provinces_par_pays[$pays_candidat]) ? $provinces_par_pays[$pays_candidat] : [];
      $reconnus = [];
      $non_reconnus = [];
      foreach ($items as $item) {
        // Nettoyer les qualificatifs directionnels en préfixe pour tester la province seule
        $item_nettoye = preg_replace('/^(Nord|Sud|Est|Ouest|Nord[\s-]?est|Nord[\s-]?ouest|Sud[\s-]?est|Sud[\s-]?ouest)(\s+(du|de|des|d\'))?\s+/ui', '', $item);
        if (in_array($item_nettoye, $connu) || in_array($item, $connu)) {
          $reconnus[] = $item;
        } else {
          $non_reconnus[] = $item;
        }
      }
      echo "  \"$pays_candidat(...)\" : " . count($reconnus) . " reconnu(s), " . count($non_reconnus) . " NON reconnu(s)\n";
      if ($non_reconnus) {
        echo "    Non reconnus : " . implode(' | ', $non_reconnus) . "\n";
      }
    }
  }
  echo "\n";
}
