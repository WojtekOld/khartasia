<?php
use Drupal\node\Entity\Node;

$pdo = new PDO('mysql:host=db2;port=3306;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');
$stmt = $pdo->query("SELECT nid, tnid, language FROM node WHERE type = 'papers'");
$groupes = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $cle = ($row['tnid'] == 0) ? $row['nid'] : $row['tnid'];
  $groupes[$cle][$row['language']] = $row['nid'];
}
echo "Groupes : " . count($groupes) . "\n";

$a_supprimer = [];
$canonique_absent = 0;
$traduction_manquante = [];

foreach ($groupes as $cle => $langues) {
  $canonique = Node::load($cle);
  if (!$canonique) { $canonique_absent++; continue; }

  foreach ($langues as $langue => $nid_d7) {
    if ($nid_d7 == $cle) continue;
    if ($canonique->hasTranslation($langue)) {
      $isole = Node::load($nid_d7);
      if ($isole) {
        $a_supprimer[] = ['nid' => $nid_d7, 'titre' => $isole->label(), 'langue' => $langue, 'canonique' => $cle];
      }
    } else {
      $traduction_manquante[] = ['canonique' => $cle, 'langue' => $langue];
    }
  }
}

echo "Canonique introuvable : $canonique_absent\n";
echo "À supprimer : " . count($a_supprimer) . "\n";
echo "Traduction manquante sur canonique (cas à part) : " . count($traduction_manquante) . "\n";

file_put_contents('/tmp/papers_a_supprimer.json', json_encode($a_supprimer));

echo "\nTaper CONTINUER pour supprimer réellement : ";
if (trim(fgets(STDIN)) !== 'CONTINUER') {
  echo "Annulé.\n";
  exit(0);
}

$compteur = 0;
foreach ($a_supprimer as $s) {
  $n = Node::load($s['nid']);
  if ($n) { $n->delete(); $compteur++; }
}
echo "\n=== Terminé : $compteur nœuds supprimés ===\n";

$total_final = \Drupal::entityQuery('node')->condition('type', 'papers')->accessCheck(FALSE)->count()->execute();
echo "Total papers restant : $total_final\n";
