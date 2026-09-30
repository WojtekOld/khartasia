<?php
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;

// === Créer le champ structuré s'il n'existe pas ===
if (!FieldStorageConfig::loadByName('node', 'field_zone_croissance_taxo')) {
  FieldStorageConfig::create([
    'field_name' => 'field_zone_croissance_taxo',
    'entity_type' => 'node',
    'type' => 'entity_reference',
    'settings' => ['target_type' => 'taxonomy_term'],
    'cardinality' => -1,
  ])->save();
}
if (!FieldConfig::loadByName('node', 'article', 'field_zone_croissance_taxo')) {
  FieldConfig::create([
    'field_name' => 'field_zone_croissance_taxo',
    'entity_type' => 'node',
    'bundle' => 'article',
    'label' => 'Zone de croissance (structurée)',
    'description' => 'Extraction automatique de field_aire_croissance vers le vocabulaire zone_croissance. Le champ texte original n\'est pas modifié ni supprimé.',
    'settings' => ['handler_settings' => ['target_bundles' => ['zone_croissance' => 'zone_croissance']]],
  ])->save();
  echo "Champ field_zone_croissance_taxo créé sur Plantes.\n\n";
}

// === Charger le vocabulaire ===
$pays_termes = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'zone_croissance', 'parent' => 0]);
$noms_pays = [];
foreach ($pays_termes as $t) {
  $noms_pays[$t->label()] = $t->id();
}
uksort($noms_pays, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

// Charger TOUS les termes non-racine avec leur pays d'appartenance (recherche à plat, tous niveaux)
$tous_termes = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'zone_croissance']);
$terme_par_nom = [];
foreach ($tous_termes as $t) {
  $terme_par_nom[$t->label()] = $t->id();
}

function nettoyer_directionnel($item) {
  return trim(preg_replace('/^(Nord|Sud|Est|Ouest|Nord[\s-]?est|Nord[\s-]?ouest|Sud[\s-]?est|Sud[\s-]?ouest)(\s+(du|de|des|d\'))?\s+/ui', '', $item));
}

$pattern_pays = implode('|', array_map('preg_quote', array_keys($noms_pays)));

$resultats = []; // nid => ['tags' => [tid,...], 'a_verifier' => [texte,...]]
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
      $tags[$noms_pays[$pays_nom]] = TRUE; // toujours taguer le pays lui-même
      $contenu = $m[2];

      // Syntaxe d'exclusion : jamais traitée automatiquement
      if (preg_match('/\b(sauf|partout|excepté)\b/ui', $contenu)) {
        $a_verifier[] = "$pays_nom(...) [exclusion, non traité] : $contenu";
        continue;
      }

      foreach (explode(',', $contenu) as $item) {
        $item = trim($item);
        $item_nettoye = nettoyer_directionnel($item);
        if (isset($terme_par_nom[$item_nettoye])) {
          $tags[$terme_par_nom[$item_nettoye]] = TRUE;
        } elseif (isset($terme_par_nom[$item])) {
          $tags[$terme_par_nom[$item]] = TRUE;
        } else {
          $a_verifier[] = "$pays_nom(...) : \"$item\" non reconnu";
        }
      }
    }
  }

  // Pays mentionnés sans parenthèses (ex: ", Corée, Japon.")
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

echo "=== APERÇU AVANT SAUVEGARDE ===\n";
echo "Fiches concernées : " . count($resultats) . "\n";
echo "Total de tags à créer : $total_tags\n";
echo "Total d'éléments à vérifier manuellement (non taggés) : $total_a_verifier\n\n";

echo "--- Échantillon des 'à vérifier' (les 20 premiers) ---\n";
$compte = 0;
foreach ($resultats as $nid => $r) {
  foreach ($r['a_verifier'] as $av) {
    if ($compte++ >= 20) break 2;
    echo "  [{$r['label']}] $av\n";
  }
}

echo "\nTaper CONTINUER pour sauvegarder les $total_tags tags sur " . count($resultats) . " fiches, autre chose pour annuler : ";
if (trim(fgets(STDIN)) !== 'CONTINUER') {
  echo "Annulé — rien sauvegardé. La liste complète des 'à vérifier' n'a pas été affichée en entier ;\n";
  echo "relancer avec sortie complète si besoin avant de décider.\n";
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

echo "\n=== TERMINÉ : $compteur fiches Plantes mises à jour ===\n";
echo "Rappel : $total_a_verifier éléments n'ont pas pu être reconnus automatiquement — laissés de côté, pas devinés.\n";
