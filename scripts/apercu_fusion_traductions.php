<?php
$pdo = new PDO('mysql:host=db2;port=3306;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');

// Récupérer la structure complète tnid/language de D7
$stmt = $pdo->query("SELECT nid, tnid, language FROM node WHERE type = 'article'");
$groupes = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $groupes[$row['tnid']][$row['language']] = $row['nid'];
}

echo "Groupes de traduction trouvés : " . count($groupes) . "\n\n";

$a_fusionner = [];
$deja_ok = 0;
$canonique_absent = 0;

foreach ($groupes as $tnid => $langues) {
  // Le nœud canonique en D11 est censé exister sous l'ID D7 == tnid (le nœud source FR)
  $canonique = \Drupal\node\Entity\Node::load($tnid);
  if (!$canonique) {
    $canonique_absent++;
    continue;
  }

  foreach ($langues as $langue => $nid_d7) {
    if ($nid_d7 == $tnid) continue; // c'est le canonique lui-même, rien à faire

    if ($canonique->hasTranslation($langue)) {
      $deja_ok++;
      continue; // déjà une vraie traduction, rien à corriger
    }

    // Existe-t-il un nœud D11 séparé sous cet ID, mal créé en 'fr' hier soir ?
    $noeud_isole = \Drupal\node\Entity\Node::load($nid_d7);
    if ($noeud_isole) {
      $a_fusionner[] = [
        'canonique_nid' => $tnid,
        'canonique_titre' => $canonique->label(),
        'isole_nid' => $nid_d7,
        'langue_reelle' => $langue,
        'isole_langcode_actuel' => $noeud_isole->language()->getId(),
      ];
    }
  }
}

echo "=== Aperçu ===\n";
echo "Déjà correctement traduits (rien à faire) : $deja_ok\n";
echo "Nœud canonique introuvable en D11 : $canonique_absent\n";
echo "À fusionner (nœud isolé -> vraie traduction sur le canonique) : " . count($a_fusionner) . "\n\n";

echo "Échantillon (15 premiers) :\n";
foreach (array_slice($a_fusionner, 0, 15) as $f) {
  echo "  {$f['canonique_titre']} : nœud isolé {$f['isole_nid']} (actuellement langcode={$f['isole_langcode_actuel']}) -> deviendra la traduction [{$f['langue_reelle']}] du nœud {$f['canonique_nid']}\n";
}

file_put_contents('/tmp/fusion_plan.json', json_encode($a_fusionner));
echo "\nPlan complet sauvegardé pour l'étape suivante.\n";
