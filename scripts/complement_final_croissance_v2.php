<?php
use Drupal\node\Entity\Node;

function normaliser($s) {
  $s = mb_strtolower(trim($s));
  $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
  return trim($s);
}

// Retourne [texte_nettoyé, a_ete_modifie]
function nettoyer_directionnel($item) {
  $original = $item;
  $avant = null;
  while ($avant !== $item) {
    $avant = $item;
    $item = preg_replace('/^(Nord[\s-]?est|Nord[\s-]?ouest|Sud[\s-]?est|Sud[\s-]?ouest|Nord|Sud|Est|Ouest)(\s+(du|de|des|d\'|d\x{2019}))?\s*/uir', '', $item);
    $item = trim($item);
  }
  $item = preg_replace('/^[ld][\'\x{2019}]\s*/ui', '', $item);
  return [trim($item), $item !== $original];
}

$chine = reset(\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'zone_croissance', 'name' => 'Chine', 'parent' => 0]));
$existing_cq = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'zone_croissance', 'name' => 'Chongqing', 'parent' => $chine->id()]);
if (!$existing_cq) {
  $t = \Drupal\taxonomy\Entity\Term::create(['vid' => 'zone_croissance', 'name' => 'Chongqing', 'parent' => $chine->id()]);
  $t->set('field_niveau_zone', 'province_prefecture_do');
  $t->save();
  echo "Ajouté : Chine > Chongqing\n";
}

$pays_termes = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'zone_croissance', 'parent' => 0]);
$noms_pays = [];
foreach ($pays_termes as $t) { $noms_pays[$t->label()] = $t->id(); }
uksort($noms_pays, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

$tous_termes = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'zone_croissance']);
$terme_par_norme = [];
foreach ($tous_termes as $t) { $terme_par_norme[normaliser($t->label())] = $t->id(); }

$pattern_pays = implode('|', array_map('preg_quote', array_keys($noms_pays)));
$resultats = [];
$total_tags = 0;
$total_partiels = 0;

$ids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->condition('field_aire_croissance', '', '<>')
  ->accessCheck(FALSE)
  ->execute();

foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $node) {
  $texte = strip_tags($node->get('field_aire_croissance')->value);
  $deja_tags = $node->hasField('field_zone_croissance_taxo') ? array_column($node->get('field_zone_croissance_taxo')->getValue(), 'target_id') : [];
  $tags = array_fill_keys($deja_tags, TRUE);
  $partiels = [];
  $nouveaux = 0;

  if (preg_match_all('/(' . $pattern_pays . ')\s*\(([^)]*)\)/u', $texte, $matches, PREG_SET_ORDER)) {
    foreach ($matches as $m) {
      $pays_nom = $m[1];
      $contenu = $m[2];
      if (preg_match('/\b(sauf|partout|excepté)\b/ui', $contenu)) { continue; }
      foreach (explode(',', $contenu) as $item) {
        $item = trim($item);
        [$nettoye, $modifie] = nettoyer_directionnel($item);
        $cle = normaliser($nettoye);

        if (!isset($terme_par_norme[$cle])) continue;

        if ($modifie) {
          $partiels[] = "\"$item\" -> correspond partiellement a \"$nettoye\" (qualificatif directionnel non represente)";
        } elseif (!isset($tags[$terme_par_norme[$cle]])) {
          $tags[$terme_par_norme[$cle]] = TRUE;
          $nouveaux++;
        }
      }
    }
  }

  if ($nouveaux > 0 || $partiels) {
    $resultats[$node->id()] = ['label' => $node->label(), 'tags' => array_keys($tags), 'nouveaux' => $nouveaux, 'partiels' => $partiels];
    $total_tags += $nouveaux;
    $total_partiels += count($partiels);
  }
}

echo "Nouveaux tags pleins (sans qualificatif) : $total_tags\n";
echo "Correspondances partielles signalees, non taguees : $total_partiels\n\n";
echo "--- Echantillon des correspondances partielles ---\n";
$c = 0;
foreach ($resultats as $r) {
  foreach ($r['partiels'] as $p) {
    if ($c++ >= 15) break 2;
    echo "  [{$r['label']}] $p\n";
  }
}

echo "\nTaper CONTINUER pour sauvegarder uniquement les tags pleins ($total_tags), autre chose pour annuler : ";
if (trim(fgets(STDIN)) !== 'CONTINUER') {
  echo "Annule.\n";
  exit(0);
}

$compteur = 0;
foreach ($resultats as $nid => $r) {
  if ($r['nouveaux'] === 0) continue;
  $node = Node::load($nid);
  $values = array_map(fn($tid) => ['target_id' => $tid], $r['tags']);
  $node->set('field_zone_croissance_taxo', $values);
  $node->save();
  $compteur++;
}
echo "Termine : $compteur fiches mises a jour. Les mentions directionnelles restent en attente d'un traitement dedie (modificateur separe), pas taguees par exces.\n";
