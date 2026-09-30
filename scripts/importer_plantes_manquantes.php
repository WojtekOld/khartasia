<?php
use Drupal\node\Entity\Node;

try {
  $pdo = new PDO('mysql:host=db2;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
  echo "ERREUR de connexion : " . $e->getMessage() . "\n";
  exit(1);
}

// === Trouver les 152 nid manquants ===
$stmt = $pdo->query("SELECT nid, title FROM node WHERE type = 'article'");
$d7_plantes = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $d7_plantes[$row['nid']] = $row['title'];
}
$ids_d11 = \Drupal::entityQuery('node')->condition('type', 'article')->accessCheck(FALSE)->execute();
$existants_d11 = array_flip($ids_d11);

$manquants = [];
foreach ($d7_plantes as $nid => $titre) {
  if (!isset($existants_d11[$nid])) $manquants[$nid] = $titre;
}
echo "Plantes manquantes à importer : " . count($manquants) . "\n";

// === Fonction : résoudre un terme D7 (tid) vers son équivalent D11 (par NOM, pas par ID) ===
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

// === Préparer les données pour chaque plante manquante ===
$a_creer = [];
$echecs_resolution = [];

foreach ($manquants as $nid => $titre) {
  $donnees = ['nid' => $nid, 'titre' => $titre];

  // Champs directs (texte)
  foreach (['body' => 'body', 'field_aire_croissance' => 'field_aire_croissance',
            'field_ident_fibre' => 'field_ident_fibre', 'field_paper_name' => 'field_paper_name',
            'field_synonymes_bot' => 'field_synonymes_bot'] as $d7_champ => $d11_champ) {
    $table = "field_data_$d7_champ";
    $val = $pdo->query("SELECT {$d7_champ}_value FROM `$table` WHERE entity_id = $nid")->fetchColumn();
    $donnees[$d11_champ] = $val ?: NULL;
  }

  // Taxonomie : résolution par nom
  $tid_order = $pdo->query("SELECT field_tax_order_tid FROM field_data_field_tax_order WHERE entity_id = $nid")->fetchColumn();
  $tid_family = $pdo->query("SELECT field_tax_family_tid FROM field_data_field_tax_family WHERE entity_id = $nid")->fetchColumn();
  $tid_genus = $pdo->query("SELECT field_tax_genus_tid FROM field_data_field_tax_genus WHERE entity_id = $nid")->fetchColumn();

  $donnees['field_tax_order'] = resoudre_terme_par_nom($pdo, $tid_order, 'classification_botanique', $cache_termes);
  $donnees['field_tax_family'] = resoudre_terme_par_nom($pdo, $tid_family, 'classification_botanique', $cache_termes);
  $donnees['field_tax_genus'] = resoudre_terme_par_nom($pdo, $tid_genus, 'classification_botanique', $cache_termes);

  if ($tid_genus && !$donnees['field_tax_genus']) {
    $echecs_resolution[] = "nid=$nid ($titre) : genre non résolu (tid D7=$tid_genus)";
  }

  // field_used_part_plant et field_use_paper_making (multi-valeurs)
  foreach (['field_used_part_plant' => 'partie_utilis_e_de_la_plante', 'field_use_paper_making' => 'utilisation_dans_la_fabricatio'] as $champ => $vid_cible) {
    $table = "field_data_$champ";
    $tids = $pdo->query("SELECT {$champ}_tid FROM `$table` WHERE entity_id = $nid")->fetchAll(PDO::FETCH_COLUMN);
    $resolus = [];
    foreach ($tids as $t) {
      $r = resoudre_terme_par_nom($pdo, $t, $vid_cible, $cache_termes);
      if ($r) $resolus[] = $r;
      else $echecs_resolution[] = "nid=$nid ($titre) : $champ non résolu (tid D7=$t)";
    }
    $donnees[$champ] = $resolus;
  }

  // Fiches plante_texte associées — noms de champs exacts vérifiés par audit,
  // AUCUN motif de suffixe uniforme n'existe réellement en D7 (désordre confirmé :
  // intr_paper n'a pas de version française, japonais tantôt _ja tantôt _jap,
  // le thaï de gen_cult porte même une coquille dans son propre nom de champ).
  $textes = [];
  $mapping_langues = [
    'Français' => ['aire' => 'field_aire_utilis', 'gen' => 'field_gen_culture', 'intr' => NULL, 'mode' => 'field_mode_prep'],
    'Chinois'  => ['aire' => 'field_aire_utilis_ch', 'gen' => 'field_gen_cult_ch', 'intr' => 'field_intr_paper_ch', 'mode' => 'field_mode_prep_ch'],
    'Japonais' => ['aire' => 'field_aire_utilis_ja', 'gen' => 'field_gen_cult_jap', 'intr' => 'field_intr_paper_jap', 'mode' => 'field_mode_prep_ja'],
    'Coréen'   => ['aire' => 'field_aire_utilis_ko', 'gen' => 'field_gen_cult_ko', 'intr' => 'field_intr_paper_ko', 'mode' => 'field_mode_prep_ko'],
    'Thai'     => ['aire' => 'field_aire_utilis_thai', 'gen' => 'field_fiel_gen_cult_thai', 'intr' => 'field_intr_paper_thai', 'mode' => 'field_mode_prep_thai'],
  ];

  foreach ($mapping_langues as $nom_langue => $champs) {
    $champ_aire = $champs['aire'];
    $val_aire = $pdo->query("SELECT {$champ_aire}_value FROM field_data_$champ_aire WHERE entity_id = $nid")->fetchColumn();
    if (!$val_aire) continue; // pas de contenu dans cette langue, on saute

    $val_gen = NULL;
    if ($champs['gen']) {
      $c = $champs['gen'];
      $val_gen = $pdo->query("SELECT {$c}_value FROM field_data_$c WHERE entity_id = $nid")->fetchColumn();
    }
    $val_intr = NULL;
    if ($champs['intr']) {
      $c = $champs['intr'];
      $val_intr = $pdo->query("SELECT {$c}_value FROM field_data_$c WHERE entity_id = $nid")->fetchColumn();
    }
    $val_mode = NULL;
    if ($champs['mode']) {
      $c = $champs['mode'];
      $val_mode = $pdo->query("SELECT {$c}_value FROM field_data_$c WHERE entity_id = $nid")->fetchColumn();
    }

    $textes[] = [
      'langue' => $nom_langue,
      'field_aire_utilis_ml' => $val_aire,
      'field_gen_culture_ml' => $val_gen,
      'field_intr_paper_ml' => $val_intr,
      'field_mode_prep_ml' => $val_mode,
    ];
  }
  $donnees['textes'] = $textes;

  $a_creer[] = $donnees;
}

echo "\n=== Aperçu ===\n";
echo "Plantes prêtes à créer : " . count($a_creer) . "\n";
echo "Échecs de résolution de taxonomie : " . count($echecs_resolution) . "\n";
if ($echecs_resolution) {
  echo "Détail (10 premiers) :\n";
  foreach (array_slice($echecs_resolution, 0, 10) as $e) echo "  - $e\n";
}
$total_textes = array_sum(array_map(fn($p) => count($p['textes']), $a_creer));
echo "Fiches plante_texte à créer : $total_textes\n";

echo "\nTaper CONTINUER pour créer réellement, autre chose pour annuler : ";
if (trim(fgets(STDIN)) !== 'CONTINUER') {
  echo "Annulé — rien créé.\n";
  exit(0);
}

// === Création réelle ===
$compteur_plantes = 0;
$compteur_textes = 0;

foreach ($a_creer as $d) {
  $node = Node::create([
    'nid' => $d['nid'],
    'type' => 'article',
    'title' => $d['titre'],
    'langcode' => 'fr',
    'body' => ['value' => $d['body'], 'format' => 'full_html'],
    'field_aire_croissance' => ['value' => $d['field_aire_croissance'], 'format' => 'full_html'],
    'field_ident_fibre' => ['value' => $d['field_ident_fibre'], 'format' => 'full_html'],
    'field_paper_name' => ['value' => $d['field_paper_name'], 'format' => 'full_html'],
    'field_synonymes_bot' => ['value' => $d['field_synonymes_bot'], 'format' => 'full_html'],
  ]);
  if ($d['field_tax_order']) $node->set('field_tax_order', ['target_id' => $d['field_tax_order']]);
  if ($d['field_tax_family']) $node->set('field_tax_family', ['target_id' => $d['field_tax_family']]);
  if ($d['field_tax_genus']) $node->set('field_tax_genus', ['target_id' => $d['field_tax_genus']]);
  if ($d['field_used_part_plant']) $node->set('field_used_part_plant', array_map(fn($t) => ['target_id' => $t], $d['field_used_part_plant']));
  if ($d['field_use_paper_making']) $node->set('field_use_paper_making', array_map(fn($t) => ['target_id' => $t], $d['field_use_paper_making']));
  $node->save();
  $compteur_plantes++;

  foreach ($d['textes'] as $t) {
    $langue_terme = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'languages', 'name' => $t['langue']]);
    $pt = Node::create([
      'type' => 'plante_texte',
      'title' => $d['titre'] . ' [' . $t['langue'] . ']',
      'field_plante_ref' => ['target_id' => $d['nid']],
      'field_aire_utilis_ml' => ['value' => $t['field_aire_utilis_ml'], 'format' => 'full_html'],
      'field_gen_culture_ml' => ['value' => $t['field_gen_culture_ml'], 'format' => 'full_html'],
      'field_intr_paper_ml' => ['value' => $t['field_intr_paper_ml'], 'format' => 'full_html'],
      'field_mode_prep_ml' => ['value' => $t['field_mode_prep_ml'], 'format' => 'full_html'],
    ]);
    if ($langue_terme) $pt->set('field_langue_texte', ['target_id' => reset($langue_terme)->id()]);
    $pt->save();
    $compteur_textes++;
  }
}

echo "\n=== Terminé : $compteur_plantes plantes créées, $compteur_textes fiches plante_texte créées ===\n";
