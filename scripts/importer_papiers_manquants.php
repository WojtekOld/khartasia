<?php
use Drupal\node\Entity\Node;

try {
  $pdo = new PDO('mysql:host=db2;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
  echo "ERREUR de connexion : " . $e->getMessage() . "\n";
  exit(1);
}

// === Trouver les manquants ===
$stmt = $pdo->query("SELECT nid, title FROM node WHERE type = 'papers'");
$d7_papiers = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $d7_papiers[$row['nid']] = $row['title'];
}
$ids_d11 = \Drupal::entityQuery('node')->condition('type', 'papers')->accessCheck(FALSE)->execute();
$existants_d11 = array_flip($ids_d11);

$manquants = [];
foreach ($d7_papiers as $nid => $titre) {
  if (!isset($existants_d11[$nid])) $manquants[$nid] = $titre;
}
echo "Papiers manquants à importer : " . count($manquants) . "\n";

// === Résolution par nom (termes de taxonomie) ===
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

// === Résolution des Plantes référencées : par nid direct, mais vérifié existant ===
function resoudre_plante($d7_nid, &$cache_plantes) {
  if (!$d7_nid) return NULL;
  if (isset($cache_plantes[$d7_nid])) return $cache_plantes[$d7_nid];
  $existe = \Drupal\node\Entity\Node::load($d7_nid);
  $resultat = ($existe && $existe->bundle() === 'article') ? $d7_nid : NULL;
  $cache_plantes[$d7_nid] = $resultat;
  return $resultat;
}
$cache_plantes = [];

$a_creer = [];
$echecs = ['plante' => 0, 'genre' => 0, 'origine' => 0];

foreach ($manquants as $nid => $titre) {
  $donnees = ['nid' => $nid, 'titre' => $titre];

  foreach (['body' => 'body', 'field_pap_local_scripture' => 'field_pap_local_scripture', 'field_pap_synonyme' => 'field_pap_synonyme'] as $champ) {
    $table = "field_data_$champ";
    $val = $pdo->query("SELECT {$champ}_value FROM `$table` WHERE entity_id = $nid")->fetchColumn();
    $donnees[$champ] = $val ?: NULL;
  }

  // field_genus_pap_entity (multi-valeurs, référence vers Plantes)
  $nids_plantes_d7 = $pdo->query("SELECT field_genus_pap_entity_target_id FROM field_data_field_genus_pap_entity WHERE entity_id = $nid ORDER BY delta")->fetchAll(PDO::FETCH_COLUMN);
  $plantes_resolues = [];
  foreach ($nids_plantes_d7 as $pnid) {
    $r = resoudre_plante($pnid, $cache_plantes);
    if ($r) $plantes_resolues[] = $r;
    else $echecs['plante']++;
  }
  $donnees['field_genus_pap_entity'] = $plantes_resolues;

  // field_genus_taxo_pap (multi-valeurs, référence vers classification_botanique)
  $tids_genre_d7 = $pdo->query("SELECT field_genus_taxo_pap_tid FROM field_data_field_genus_taxo_pap WHERE entity_id = $nid ORDER BY delta")->fetchAll(PDO::FETCH_COLUMN);
  $genres_resolus = [];
  foreach ($tids_genre_d7 as $t) {
    $r = resoudre_terme_par_nom($pdo, $t, 'classification_botanique', $cache_termes);
    if ($r) $genres_resolus[] = $r;
    else $echecs['genre']++;
  }
  $donnees['field_genus_taxo_pap'] = $genres_resolus;

  // field_origin_taxo (référence simple vers 'pays')
  $tid_origine = $pdo->query("SELECT field_origin_taxo_tid FROM field_data_field_origin_taxo WHERE entity_id = $nid")->fetchColumn();
  $donnees['field_origin_taxo'] = resoudre_terme_par_nom($pdo, $tid_origine, 'pays', $cache_termes);
  if ($tid_origine && !$donnees['field_origin_taxo']) $echecs['origine']++;

  $a_creer[] = $donnees;
}

echo "\n=== Aperçu ===\n";
echo "Papiers prêts à créer : " . count($a_creer) . "\n";
echo "Échecs résolution Plante : {$echecs['plante']}\n";
echo "Échecs résolution genre (classification_botanique) : {$echecs['genre']}\n";
echo "Échecs résolution origine (pays) : {$echecs['origine']}\n";

echo "\nTaper CONTINUER pour créer réellement, autre chose pour annuler : ";
if (trim(fgets(STDIN)) !== 'CONTINUER') {
  echo "Annulé — rien créé.\n";
  exit(0);
}

$compteur = 0;
foreach ($a_creer as $d) {
  $node = Node::create([
    'nid' => $d['nid'],
    'type' => 'papers',
    'title' => $d['titre'],
    'langcode' => 'fr',
    'body' => ['value' => $d['body'], 'format' => 'full_html'],
  ]);
  if ($d['field_pap_local_scripture']) $node->set('field_pap_local_scripture', $d['field_pap_local_scripture']);
  if ($d['field_pap_synonyme']) $node->set('field_pap_synonyme', $d['field_pap_synonyme']);
  if ($d['field_genus_pap_entity']) $node->set('field_genus_pap_entity', array_map(fn($t) => ['target_id' => $t], $d['field_genus_pap_entity']));
  if ($d['field_genus_taxo_pap']) $node->set('field_genus_taxo_pap', array_map(fn($t) => ['target_id' => $t], $d['field_genus_taxo_pap']));
  if ($d['field_origin_taxo']) $node->set('field_origin_taxo', ['target_id' => $d['field_origin_taxo']]);
  $node->save();
  $compteur++;
}

echo "\n=== Terminé : $compteur papiers créés ===\n";
