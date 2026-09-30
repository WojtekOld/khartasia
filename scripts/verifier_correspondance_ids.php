<?php

try {
  $pdo = new PDO('mysql:host=db2;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
  echo "ERREUR de connexion : " . $e->getMessage() . "\n";
  exit(1);
}

// === 1. Nœuds Plantes (article) : D7 vs D11 ===
echo "=== Correspondance des nœuds Plantes (article) ===\n";
$stmt = $pdo->query("SELECT nid, title FROM node WHERE type = 'article'");
$d7_plantes = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $d7_plantes[$row['nid']] = $row['title'];
}
echo "Total D7 : " . count($d7_plantes) . "\n";

$ids_d11 = \Drupal::entityQuery('node')->condition('type', 'article')->accessCheck(FALSE)->execute();
$d11_plantes = [];
foreach (\Drupal\node\Entity\Node::loadMultiple($ids_d11) as $n) {
  $d11_plantes[$n->id()] = $n->label();
}
echo "Total D11 : " . count($d11_plantes) . "\n";

$coherents = 0;
$incoherents = [];
$absents_d11 = 0;
foreach ($d7_plantes as $nid => $titre) {
  if (!isset($d11_plantes[$nid])) {
    $absents_d11++;
    continue;
  }
  if (trim($d11_plantes[$nid]) === trim($titre)) {
    $coherents++;
  } else {
    $incoherents[$nid] = ['d7' => $titre, 'd11' => $d11_plantes[$nid]];
  }
}
echo "Même nid + même titre : $coherents\n";
echo "Même nid, titre DIFFÉRENT (danger) : " . count($incoherents) . "\n";
echo "Nid D7 absent en D11 : $absents_d11\n";
if ($incoherents) {
  echo "Exemples de titres différents :\n";
  foreach (array_slice($incoherents, 0, 10, TRUE) as $nid => $v) {
    echo "  nid=$nid : D7=\"{$v['d7']}\" / D11=\"{$v['d11']}\"\n";
  }
}

// === 2. Vérification ciblée : nid=22 précisément ===
echo "\n=== Cas précis : nid=22 ===\n";
echo "D7 : " . ($d7_plantes[22] ?? 'absent') . "\n";
echo "D11 : " . ($d11_plantes[22] ?? 'absent') . "\n";

// === 3. Termes classification_botanique : D7 vs D11 ===
echo "\n=== Correspondance classification_botanique ===\n";
// En D7, trouver le bon vocabulaire par son nom
$vid_d7 = $pdo->query("SELECT vid FROM taxonomy_vocabulary WHERE name = 'Classification botanique' OR machine_name = 'classification_botanique'")->fetchColumn();
if (!$vid_d7) {
  echo "Vocabulaire introuvable en D7 sous ce nom — vérifier manuellement.\n";
} else {
  echo "Vocabulaire D7 trouvé, vid=$vid_d7\n";
  $stmt2 = $pdo->query("SELECT tid, name FROM taxonomy_term_data WHERE vid = $vid_d7");
  $d7_termes = [];
  while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    $d7_termes[$row['tid']] = $row['name'];
  }
  echo "Total termes D7 : " . count($d7_termes) . "\n";

  $termes_d11 = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'classification_botanique']);
  $d11_termes = [];
  foreach ($termes_d11 as $t) {
    $d11_termes[$t->id()] = $t->label();
  }
  echo "Total termes D11 : " . count($d11_termes) . "\n";

  $t_coherents = 0;
  $t_incoherents = [];
  foreach ($d7_termes as $tid => $nom) {
    if (!isset($d11_termes[$tid])) continue;
    if (trim($d11_termes[$tid]) === trim($nom)) {
      $t_coherents++;
    } else {
      $t_incoherents[$tid] = ['d7' => $nom, 'd11' => $d11_termes[$tid]];
    }
  }
  echo "Même tid + même nom : $t_coherents\n";
  echo "Même tid, nom DIFFÉRENT (danger) : " . count($t_incoherents) . "\n";
  if ($t_incoherents) {
    foreach (array_slice($t_incoherents, 0, 10, TRUE) as $tid => $v) {
      echo "  tid=$tid : D7=\"{$v['d7']}\" / D11=\"{$v['d11']}\"\n";
    }
  }
}
