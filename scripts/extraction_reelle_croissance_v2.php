<?php
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;

if (!FieldStorageConfig::loadByName('node', 'field_zone_croissance_taxo')) {
  FieldStorageConfig::create([
    'field_name' => 'field_zone_croissance_taxo', 'entity_type' => 'node', 'type' => 'entity_reference',
    'settings' => ['target_type' => 'taxonomy_term'], 'cardinality' => -1,
  ])->save();
}
if (!FieldConfig::loadByName('node', 'article', 'field_zone_croissance_taxo')) {
  FieldConfig::create([
    'field_name' => 'field_zone_croissance_taxo', 'entity_type' => 'node', 'bundle' => 'article',
    'label' => 'Zone de croissance (structurée)',
    'description' => 'Extraction automatique de field_aire_croissance. Le champ texte original n\'est pas modifié.',
    'settings' => ['handler_settings' => ['target_bundles' => ['zone_croissance' => 'zone_croissance']]],
  ])->save();
}

function normaliser($s) {
  $s = mb_strtolower(trim($s));
  $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s); // enlève les accents
  return trim($s);
}

function nettoyer_directionnel($item) {
  $avant = null;
  // Boucle jusqu'à stabilité : gère "Sud Est X" (deux mots directionnels collés)
  while ($avant !== $item) {
    $avant = $item;
    $item = preg_replace('/^(Nord[\s-]?est|Nord[\s-]?ouest|Sud[\s-]?est|Sud[\s-]?ouest|Nord|Sud|Est|Ouest)(\s+(du|de|des|d\'))?\s*/ui', '', $item);
    $item = trim($item);
  }
  // Élisions françaises restantes (l'Anhui, d'Anhui...)
  $item = preg_replace('/^[ld]\'\s*/ui', '', $item);
  return trim($item);
}

$pays_termes = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'zone_croissance', 'parent' => 0]);
$noms_pays = [];
foreach ($pays_termes as $t) {
  $noms_pays[$t->label()] = $t->id();
}
uksort($noms_pays, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

$tous_termes = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'zone_croissance']);
$terme_par_norme = []; // clé normalisée (sans accent, minuscule) => tid
foreach ($tous_termes as $t) {
  $terme_par_norme[normaliser($t->label())] = $t->id();
}

$pattern_pays = implode('|', array_map('preg_quote', array_keys($noms_pays)));
$resultats = [];
$total_tags = 0;
$total_a_verifier = 0;

$ids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->condition('field_aire_croissance', '', '<>')
  ->accessCheck(FALSE)
  ->execute();

foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $node) {
  $texte = strip_tags($node->get('field_aire_croissance')->value);
  $tags = [];
  $a_verifier = [];

  if (preg_match_all('/(' . $pattern_pays . ')\s*\(([^)]*)\)/u', $texte, $matches, PREG_SET_ORDER)) {
    foreach ($matches as $m) {
      $pays_nom = $m[1];
      $tags[$noms_pays[$pays_nom]] = TRUE;
      $contenu = $m[2];

      if (preg_match('/\b(sauf|partout|excepté)\b/ui', $contenu)) {
        $a_verifier[] = "$pays_nom(...) [exclusion, non traité] : $contenu";
        continue;
      }

      foreach (explode(',', $contenu) as $item) {
        $item = trim($item);
        $item_nettoye = nettoyer_directionnel($item);
        $cle = normaliser($item_nettoye);
        $cle_brute = normaliser($item);

        if (isset($terme_par_norme[$cle])) {
          $tags[$terme_par_norme[$cle]] = TRUE;
        } elseif (isset($terme_par_norme[$cle_brute])) {
          $tags[$terme_par_norme[$cle_brute]] = TRUE;
        } else {
          $a_verifier[] = "$pays_nom(...) : \"$item\" non reconnu";
        }
      }
    }
  }

  foreach ($noms_pays as $nom => $tid) {
    if (mb_stripos($texte, $nom) !== false) {
      $tags[$tid] = TRUE;
    }
  }

  if ($tags || $a_verifier) {
    $resultats[$node->id()] = ['label' => $node->label(), 'tags' => array_keys($tags), 'a_verifier' => $a_verifier];
    $total_tags += count($tags);
    $total_a_verifier += count($a_verifier);
  }
}

echo "=== APERÇU (v2 — accents normalisés, nettoyage en boucle) ===\n";
echo "Fiches concernées : " . count($resultats) . "\n";
echo "Total de tags à créer : $total_tags\n";
echo "Total à vérifier manuellement : $total_a_verifier\n\n";

echo "--- Échantillon 'à vérifier' (30 premiers) ---\n";
$compte = 0;
foreach ($resultats as $nid => $r) {
  foreach ($r['a_verifier'] as $av) {
    if ($compte++ >= 30) break 2;
    echo "  [{$r['label']}] $av\n";
  }
}

echo "\nTaper CONTINUER pour sauvegarder, autre chose pour annuler : ";
if (trim(fgets(STDIN)) !== 'CONTINUER') {
  echo "Annulé — rien sauvegardé.\n";
  exit(0);
}

$compteur = 0;
foreach ($resultats as $nid => $r) {
  if (empty($r['tags'])) continue;
  $node = Node::load($nid);
  $values = array_map(fn($tid) => ['target_id' => $tid], $r['tags']);
  $node->set('field_zone_croissance_taxo', $values);
  $node->save();
  $compteur++;
}

echo "\n=== TERMINÉ : $compteur fiches mises à jour, $total_a_verifier éléments laissés de côté ===\n";
