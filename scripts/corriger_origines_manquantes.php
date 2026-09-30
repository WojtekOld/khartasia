<?php
use Drupal\node\Entity\Node;

echo "=== Japan/Korea existent-ils vraiment maintenant ? ===\n";
foreach (['Japan', 'Korea'] as $nom) {
  $t = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'pays', 'name' => $nom]);
  echo "$nom : " . ($t ? "existe (tid=" . reset($t)->id() . ")" : "N'EXISTE TOUJOURS PAS") . "\n";
}

echo "\n=== Correction rétroactive des papiers sans origine, parmi les 454 tout juste créés ===\n";
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

// Trouver les papiers sans origine parmi ceux qu'on vient de créer
$ids_papers = \Drupal::entityQuery('node')
  ->condition('type', 'papers')
  ->condition('field_origin_taxo', NULL, 'IS NULL')
  ->accessCheck(FALSE)->execute();

echo "Papiers sans origine actuellement : " . count($ids_papers) . "\n";

$corriges = 0;
$toujours_echec = 0;
foreach ($ids_papers as $nid) {
  $tid_origine_d7 = $pdo->query("SELECT field_origin_taxo_tid FROM field_data_field_origin_taxo WHERE entity_id = $nid")->fetchColumn();
  if (!$tid_origine_d7) continue; // n'avait vraiment pas d'origine en D7 non plus, normal

  $resolu = resoudre_terme_par_nom($pdo, $tid_origine_d7, 'pays', $cache_termes);
  if ($resolu) {
    $node = Node::load($nid);
    $node->set('field_origin_taxo', ['target_id' => $resolu]);
    $node->save();
    $corriges++;
  } else {
    $toujours_echec++;
  }
}

echo "Corrigés maintenant : $corriges\n";
echo "Toujours en échec (nom D7 introuvable même après ajout Japan/Korea) : $toujours_echec\n";
