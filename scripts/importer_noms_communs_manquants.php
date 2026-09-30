<?php
use Drupal\node\Entity\Node;

try {
  $pdo = new PDO('mysql:host=db2;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
  echo "ERREUR de connexion : " . $e->getMessage() . "\n";
  exit(1);
}

$stmt = $pdo->query("SELECT nid, title FROM node WHERE type = 'common_names'");
$d7_noms = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $d7_noms[$row['nid']] = $row['title'];
}
$ids_d11 = \Drupal::entityQuery('node')->condition('type', 'common_names')->accessCheck(FALSE)->execute();
$existants_d11 = array_flip($ids_d11);

$manquants = [];
foreach ($d7_noms as $nid => $titre) {
  if (!isset($existants_d11[$nid])) $manquants[$nid] = $titre;
}
echo "Noms communs manquants à importer : " . count($manquants) . "\n";

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
$echecs = ['plante' => 0, 'genre' => 0, 'langue' => 0];
$noms_langue_echecs = [];

foreach ($manquants as $nid => $titre) {
  $donnees = ['nid' => $nid, 'titre' => $titre];

  foreach (['body', 'field_vern_syn', 'field_ver_local_scripture'] as $champ) {
    $table = "field_data_$champ";
    $val = $pdo->query("SELECT {$champ}_value FROM `$table` WHERE entity_id = $nid")->fetchColumn();
    $donnees[$champ] = $val ?: NULL;
  }

  $nid_plante_d7 = $pdo->query("SELECT field_genus_com_entity_target_id FROM field_data_field_genus_com_entity WHERE entity_id = $nid")->fetchColumn();
  $donnees['field_genus_com_entity'] = resoudre_plante($nid_plante_d7, $cache_plantes);
  if ($nid_plante_d7 && !$donnees['field_genus_com_entity']) $echecs['plante']++;

  $tid_genre_d7 = $pdo->query("SELECT field_genus_taxo_tid FROM field_data_field_genus_taxo WHERE entity_id = $nid")->fetchColumn();
  $donnees['field_genus_taxo'] = resoudre_terme_par_nom($pdo, $tid_genre_d7, 'classification_botanique', $cache_termes);
  if ($tid_genre_d7 && !$donnees['field_genus_taxo']) $echecs['genre']++;

  // Table de correspondance explicite — le nom D7 n'est PAS le même texte que le
  // terme D11 (D7 mélange anglais et japonais, D11 est toujours en français).
  // Résoudre par nom identique créerait des doublons de langue plutôt que de
  // trouver les 6 termes déjà existants.
  static $mapping_langues = [
    'Chinese' => 'Chinois', 'English' => 'Anglais', 'French' => 'Français',
    'Japanese' => 'Japonais', 'Korean' => 'Coréen', 'Thai' => 'Thai',
    '英語' => 'Anglais', 'フランス語' => 'Français', '中国語' => 'Chinois',
    '日本語' => 'Japonais', '韓国語' => 'Coréen', 'タイ語' => 'Thai',
  ];

  $tid_langue_d7 = $pdo->query("SELECT field_language_taxo_vern_tid FROM field_data_field_language_taxo_vern WHERE entity_id = $nid")->fetchColumn();
  $donnees['field_language_taxo_vern'] = NULL;
  if ($tid_langue_d7) {
    $nom_langue_d7 = trim($pdo->query("SELECT name FROM taxonomy_term_data WHERE tid = $tid_langue_d7")->fetchColumn());
    // Essayer la table de correspondance d'abord, sinon tenter le nom tel quel
    // (certaines entrées D7 utilisent déjà directement le nom français)
    $nom_cible = $mapping_langues[$nom_langue_d7] ?? $nom_langue_d7;
    $terme_d11 = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'languages', 'name' => $nom_cible]);
    $donnees['field_language_taxo_vern'] = $terme_d11 ? reset($terme_d11)->id() : NULL;
    if (!$donnees['field_language_taxo_vern']) {
      $echecs['langue']++;
      $noms_langue_echecs[$nom_langue_d7] = ($noms_langue_echecs[$nom_langue_d7] ?? 0) + 1;
    }
  }

  $a_creer[] = $donnees;
}

echo "\n=== Aperçu ===\n";
echo "Noms prêts à créer : " . count($a_creer) . "\n";
echo "Échecs résolution Plante : {$echecs['plante']}\n";
echo "Échecs résolution genre : {$echecs['genre']}\n";
echo "Échecs résolution langue : {$echecs['langue']}\n";
if ($noms_langue_echecs) {
  echo "Langues manquantes en D11, avec occurrences :\n";
  foreach ($noms_langue_echecs as $nom => $n) echo "  - $nom : $n fois\n";
}

echo "\nTaper CONTINUER pour créer réellement, autre chose pour annuler : ";
if (trim(fgets(STDIN)) !== 'CONTINUER') {
  echo "Annulé — rien créé.\n";
  exit(0);
}

$compteur = 0;
foreach ($a_creer as $d) {
  $node = Node::create([
    'nid' => $d['nid'],
    'type' => 'common_names',
    'title' => $d['titre'],
    'langcode' => 'fr',
  ]);
  if ($d['body']) $node->set('body', ['value' => $d['body'], 'format' => 'full_html']);
  if ($d['field_vern_syn']) $node->set('field_vern_syn', $d['field_vern_syn']);
  if ($d['field_ver_local_scripture']) $node->set('field_ver_local_scripture', $d['field_ver_local_scripture']);
  if ($d['field_genus_com_entity']) $node->set('field_genus_com_entity', ['target_id' => $d['field_genus_com_entity']]);
  if ($d['field_genus_taxo']) $node->set('field_genus_taxo', ['target_id' => $d['field_genus_taxo']]);
  if ($d['field_language_taxo_vern']) $node->set('field_language_taxo_vern', ['target_id' => $d['field_language_taxo_vern']]);
  $node->save();
  $compteur++;
}

echo "\n=== Terminé : $compteur noms communs créés ===\n";
