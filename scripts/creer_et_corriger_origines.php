<?php
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

echo "=== Création directe de Japan et Korea ===\n";
foreach (['Japan', 'Korea'] as $nom) {
  $existant = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'pays', 'name' => $nom]);
  if (!$existant) {
    $t = Term::create(['vid' => 'pays', 'name' => $nom]);
    $t->save();
    echo "$nom créé, tid=" . $t->id() . "\n";
  } else {
    echo "$nom déjà là, tid=" . reset($existant)->id() . "\n";
  }
}

// Vérification immédiate, dans le même script, avant de continuer
$verif_japan = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'pays', 'name' => 'Japan']);
$verif_korea = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'pays', 'name' => 'Korea']);
if (!$verif_japan || !$verif_korea) {
  echo "ÉCHEC : la création n'a pas pris, arrêt avant correction.\n";
  exit(1);
}
echo "Confirmé présents — poursuite avec la correction.\n";

// === Correction rétroactive ===
try {
  $pdo = new PDO('mysql:host=db2;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
  echo "ERREUR : " . $e->getMessage() . "\n";
  exit(1);
}

function resoudre_terme_par_nom($pdo, $d7_tid, $d11_vid, &$cache) {
  if (!$d7_tid) return NULL;
  $cle = "$d11_vid:$d7_tid";
  if (isset($cache[$cle])) return $cache[$cle];
  $nom_d7 = $pdo->query("SELECT name FROM taxonomy_term_data WHERE tid = $d7_tid")->fetchColumn();
  if (!$nom_d7) { $cache[$cle] = NULL; return NULL; }
  $terme_d11 = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => $d11_vid, 'name' => trim($nom_d7)]);
  $resultat = $terme_d11 ? reset($terme_d11)->id() : NULL;
  $cache[$cle] = $resultat;
  return $resultat;
}
$cache_termes = [];

$ids_papers = \Drupal::entityQuery('node')
  ->condition('type', 'papers')
  ->condition('field_origin_taxo', NULL, 'IS NULL')
  ->accessCheck(FALSE)->execute();
echo "\nPapiers sans origine avant correction : " . count($ids_papers) . "\n";

$corriges = 0;
$toujours_echec = [];
foreach ($ids_papers as $nid) {
  $tid_origine_d7 = $pdo->query("SELECT field_origin_taxo_tid FROM field_data_field_origin_taxo WHERE entity_id = $nid")->fetchColumn();
  if (!$tid_origine_d7) continue;

  $resolu = resoudre_terme_par_nom($pdo, $tid_origine_d7, 'pays', $cache_termes);
  if ($resolu) {
    $node = Node::load($nid);
    $node->set('field_origin_taxo', ['target_id' => $resolu]);
    $node->save();
    $corriges++;
  } else {
    $toujours_echec[] = $nid;
  }
}

echo "Corrigés : $corriges\n";
echo "Toujours en échec : " . count($toujours_echec) . "\n";
if ($toujours_echec) {
  echo "nid en échec : " . implode(', ', array_slice($toujours_echec, 0, 10)) . "\n";
}
