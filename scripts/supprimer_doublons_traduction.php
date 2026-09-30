<?php
use Drupal\node\Entity\Node;

$pdo = new PDO('mysql:host=db2;port=3306;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');
$stmt = $pdo->query("SELECT nid, tnid, language FROM node WHERE type = 'article'");
$groupes = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $groupes[$row['tnid']][$row['language']] = $row['nid'];
}

$a_supprimer = [];
foreach ($groupes as $tnid => $langues) {
  $canonique = Node::load($tnid);
  if (!$canonique) continue;

  foreach ($langues as $langue => $nid_d7) {
    if ($nid_d7 == $tnid) continue; // le canonique lui-même

    // Le canonique a-t-il déjà cette traduction ?
    if ($canonique->hasTranslation($langue)) {
      // Alors un nœud isolé sous ce nid est un pur doublon, à supprimer
      $isole = Node::load($nid_d7);
      if ($isole) {
        $a_supprimer[] = [
          'nid' => $nid_d7,
          'titre' => $isole->label(),
          'langue' => $langue,
          'canonique_nid' => $tnid,
        ];
      }
    }
  }
}

echo "=== Aperçu ===\n";
echo "Nœuds isolés dupliqués à supprimer : " . count($a_supprimer) . "\n\n";
echo "Échantillon (10 premiers) :\n";
foreach (array_slice($a_supprimer, 0, 10) as $s) {
  echo "  nid={$s['nid']} [{$s['langue']}] {$s['titre']} — doublon du nœud canonique {$s['canonique_nid']}\n";
}

echo "\nTaper CONTINUER pour supprimer réellement, autre chose pour annuler : ";
if (trim(fgets(STDIN)) !== 'CONTINUER') {
  echo "Annulé — rien supprimé.\n";
  exit(0);
}

$compteur = 0;
foreach ($a_supprimer as $s) {
  $n = Node::load($s['nid']);
  if ($n) {
    $n->delete();
    $compteur++;
  }
}
echo "\n=== Terminé : $compteur nœuds isolés supprimés ===\n";

$total_final = \Drupal::entityQuery('node')->condition('type', 'article')->accessCheck(FALSE)->count()->execute();
echo "Total article restant : $total_final (attendu 90)\n";
