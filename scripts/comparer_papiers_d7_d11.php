<?php

// === Connexion directe à la base D7 (db2), depuis l'intérieur du conteneur ===
try {
  $pdo = new PDO('mysql:host=db2;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
  echo "ERREUR de connexion à db2 : " . $e->getMessage() . "\n";
  exit(1);
}

// === Récupérer tous les nid + titres Papiers en D7 ===
$stmt = $pdo->query("SELECT nid, title FROM node WHERE type = 'papers'");
$d7_papiers = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $d7_papiers[$row['nid']] = $row['title'];
}
echo "Total D7 : " . count($d7_papiers) . "\n";

// === Récupérer tous les nid + titres Papiers en D11 ===
$ids_d11 = \Drupal::entityQuery('node')->condition('type', 'papers')->accessCheck(FALSE)->execute();
$d11_papiers = [];
foreach (\Drupal\node\Entity\Node::loadMultiple($ids_d11) as $n) {
  $d11_papiers[$n->id()] = $n->label();
}
echo "Total D11 : " . count($d11_papiers) . "\n";

// === Comparaison ===
$manquants = [];
$presents_meme_id = [];
$presents_titre_different = [];

foreach ($d7_papiers as $nid => $titre) {
  if (!isset($d11_papiers[$nid])) {
    $manquants[$nid] = $titre;
  } elseif (trim($d11_papiers[$nid]) === trim($titre)) {
    $presents_meme_id[$nid] = $titre;
  } else {
    $presents_titre_different[$nid] = ['d7' => $titre, 'd11' => $d11_papiers[$nid]];
  }
}

echo "\n=== Résumé ===\n";
echo "Réellement MANQUANTS en D11 (nid D7 absent) : " . count($manquants) . "\n";
echo "Présents avec le même nid et même titre : " . count($presents_meme_id) . "\n";
echo "Présents avec le même nid mais titre différent : " . count($presents_titre_different) . "\n";

echo "\n=== Échantillon des 20 premiers manquants ===\n";
$echantillon = array_slice($manquants, 0, 20, TRUE);
foreach ($echantillon as $nid => $titre) {
  echo "  nid=$nid : $titre\n";
}

if ($presents_titre_different) {
  echo "\n=== Cas à titre différent (à vérifier séparément, potentiel conflit) ===\n";
  $ech2 = array_slice($presents_titre_different, 0, 5, TRUE);
  foreach ($ech2 as $nid => $v) {
    echo "  nid=$nid : D7=\"{$v['d7']}\" / D11=\"{$v['d11']}\"\n";
  }
}
