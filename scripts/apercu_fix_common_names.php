<?php
use Drupal\node\Entity\Node;

$pdo = new PDO('mysql:host=db2;port=3306;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');
$stmt = $pdo->query("SELECT nid, tnid, language FROM node WHERE type = 'common_names'");
$groupes = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $cle = ($row['tnid'] == 0) ? $row['nid'] : $row['tnid'];
  $groupes[$cle][$row['language']] = $row['nid'];
}
echo "Groupes de traduction trouvés : " . count($groupes) . "\n";

$a_supprimer = [];
$canonique_absent = 0;
$traduction_manquante_sur_canonique = [];

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
      $traduction_manquante_sur_canonique[] = ['canonique' => $cle, 'langue' => $langue, 'nid_source' => $nid_d7];
    }
  }
}

echo "Nœud canonique introuvable : $canonique_absent\n";
echo "Nœuds isolés dupliqués à supprimer : " . count($a_supprimer) . "\n";
echo "Traductions manquantes sur le canonique (cas différent, à traiter à part) : " . count($traduction_manquante_sur_canonique) . "\n\n";

if ($traduction_manquante_sur_canonique) {
  echo "Échantillon de traductions manquantes (5) :\n";
  foreach (array_slice($traduction_manquante_sur_canonique, 0, 5) as $t) {
    echo "  canonique={$t['canonique']}, langue manquante={$t['langue']}, source D7 nid={$t['nid_source']}\n";
  }
}

file_put_contents('/tmp/cn_a_supprimer.json', json_encode($a_supprimer));
echo "\nPlan de suppression sauvegardé (" . count($a_supprimer) . " entrées).\n";
