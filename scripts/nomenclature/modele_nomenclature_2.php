<?php
/**
 * modele_nomenclature_2.php — deuxième lot du modèle : champ « translittération » sur les noms vernaculaires, et vocabulaire
 * « Matériau » (intrants non végétaux et préparations) avec ses champs « nature » et « état ».
 *
 * Usage (sur le serveur) : cd /var/www/khartasia && vendor/bin/drush php-script modele_nomenclature_2.php
 *   simulation par défaut ; touch APPLY_CONFIRME.txt pour appliquer ; touch RESTAURER_CONFIRME.txt pour restaurer.
 *
 * Ce que ce lot NE fait PAS, volontairement : pas de liste d'écritures ni de drapeau (les vocabulaires type_ecriture et
 * type_romanisation existent déjà), pas de vocabulaire de lieux ni d'aire culturelle (différés tant qu'aucune requête ne
 * les exige), pas de type de contenu « Matériau », et aucun terme créé dans le vocabulaire (les termes sont saisis par l'éditeur).
 * Le vocabulaire « Matériau » n'est pas encore référencé par une attestation : c'est une liste de référence qui attend son usage.
 *
 * DÉFINITIONS
 * translittération savante : romanisation conventionnelle d'une forme écrite (Wylie pour le tibétain, pinyin pour le chinois,
 *   Hepburn pour le japonais). Distincte de l'écriture locale (la graphie d'origine) et de la romanisation d'usage (le titre).
 * matériau : substance citée dans une recette de fabrication qui n'est PAS une plante à l'état brut (colle animale, suie,
 *   kaolin, chaux, amidon préparé, mucilage préparé). Un intrant végétal brut reste une fiche Plante.
 * nature / état : deux axes indépendants d'un matériau. Nature : végétal, animal, minéral, mixte. État : brut ou préparé.
 *   « Végétal » n'a de sens qu'avec « préparé » : un végétal brut est une plante, pas un matériau.
 * fonction de l'intrant : ce que la substance fait dans CETTE recette (adhésif, colorant, charge…). Elle n'est jamais une
 *   propriété du matériau : elle se saisit sur l'attestation.
 * simulation / garde / idempotent / restauration / affichage de formulaire / composant : voir modele_nomenclature.php.
 */

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\taxonomy\Entity\Vocabulary;

$vid = 'materiau';
$nom_vocabulaire = 'Matériau (intrants non végétaux et préparations)';
$description_vocabulaire = "Substances citées dans les recettes de fabrication qui ne sont pas une plante à l'état brut : colle animale, suie, kaolin, chaux, amidon préparé, mucilage préparé… Un intrant végétal BRUT n'a pas sa place ici : il reste une fiche Plante. La FONCTION de l'intrant (fibre, adhésif, colorant, charge…) ne se saisit jamais ici : elle appartient à l'attestation, car une même substance peut être charge dans un papier et adhésif dans un autre.";
$champs = [
  'field_ver_translitteration' => ['entity_type' => 'node', 'bundle' => 'common_names', 'type' => 'string', 'cardinalite' => 1,
    'reglages' => ['max_length' => 255], 'label' => 'Translittération savante',
    'description' => "Romanisation savante de la forme (Wylie, pinyin, Hepburn…), distincte de l'« Écriture locale » qui reçoit la graphie d'origine. La romanisation d'usage va dans le titre, pas ici.",
    'widget' => ['type' => 'string_textfield', 'settings' => ['size' => 60, 'placeholder' => '']]],
  'field_mat_nature' => ['entity_type' => 'taxonomy_term', 'bundle' => $vid, 'type' => 'list_string', 'cardinalite' => 1,
    'reglages' => ['allowed_values' => ['vegetal' => 'Végétal (préparation seulement)', 'animal' => 'Animal', 'mineral' => 'Minéral', 'mixte' => 'Mixte']],
    'label' => 'Nature', 'description' => "Origine de la substance. « Végétal » n'a de sens qu'avec l'état « préparé » : un végétal brut reste une plante.",
    'widget' => ['type' => 'options_select', 'settings' => []]],
  'field_mat_etat' => ['entity_type' => 'taxonomy_term', 'bundle' => $vid, 'type' => 'list_string', 'cardinalite' => 1,
    'reglages' => ['allowed_values' => ['brut' => 'Brut', 'prepare' => 'Préparé']],
    'label' => 'État', 'description' => "Brut : tel que prélevé (kaolin, suie). Préparé : transformé avant emploi (amidon cuit, colle de peau, mucilage préparé).",
    'widget' => ['type' => 'options_select', 'settings' => []]],
];
$affichage_noeud = 'node.common_names.default';
$affichage_terme = 'taxonomy_term.' . $vid . '.default';
$composants_terme_base = [
  'name' => ['type' => 'string_textfield', 'weight' => -5, 'region' => 'content', 'settings' => ['size' => 60, 'placeholder' => ''], 'third_party_settings' => []],
  'description' => ['type' => 'text_textarea', 'weight' => 0, 'region' => 'content', 'settings' => ['rows' => 5, 'placeholder' => ''], 'third_party_settings' => []],
];

$apply = file_exists(__DIR__ . '/APPLY_CONFIRME.txt');
$restore = file_exists(__DIR__ . '/RESTAURER_CONFIRME.txt');
if ($apply) { @unlink(__DIR__ . '/APPLY_CONFIRME.txt'); }
if ($restore) { @unlink(__DIR__ . '/RESTAURER_CONFIRME.txt'); $apply = FALSE; }
$fichier = __DIR__ . '/sauvegarde_modele_nomenclature_2.json';
echo "=== Modèle de la nomenclature, lot 2 — mode : " . ($restore ? 'RESTAURATION' : ($apply ? 'APPLY' : 'SIMULATION')) . " ===\n";

$etm = \Drupal::entityTypeManager();
$aff_stockage = $etm->getStorage('entity_form_display');
$repo = \Drupal::service('entity_display.repository');
$termes = $etm->getStorage('taxonomy_term');
$nb_lignes = function ($table) { $db = \Drupal::database(); return $db->schema()->tableExists($table) ? (int) $db->query('SELECT COUNT(*) FROM {' . $table . '}')->fetchField() : 0; };
$table_de = function ($c) { return $c['entity_type'] . '__' . '%s'; };
$ecrire = function ($etat) use ($fichier) { file_put_contents($fichier, json_encode($etat, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); };
$poser = function ($aff, $nom, $options) { $aff->setComponent($nom, $options); };

// ===================== RESTAURATION =====================
if ($restore) {
  if (!file_exists($fichier)) { echo "Aucune sauvegarde (sauvegarde_modele_nomenclature_2.json introuvable).\n"; return; }
  $s = json_decode(file_get_contents($fichier), TRUE);
  if (!is_array($s)) { echo "ERREUR : sauvegarde illisible.\n"; return; }
  $ko = 0;
  foreach (($s['creations']['champs'] ?? []) as $c) {
    $n = $nb_lignes($c['entity_type'] . '__' . $c['champ']);
    if ($n > 0) { echo "  [ATTENTION] {$c['champ']} contient déjà $n valeur(s) ; il ne sera pas supprimé, et rien n'est restauré.\n"; $ko++; }
  }
  if (!empty($s['creations']['vocabulaire'])) {
    $nt = count($termes->loadByProperties(['vid' => $s['creations']['vocabulaire']]));
    if ($nt > 0) { echo "  [ATTENTION] le vocabulaire « {$s['creations']['vocabulaire']} » contient déjà $nt terme(s) ; il ne sera pas supprimé, et rien n'est restauré.\n"; $ko++; }
  }
  if ($ko) { echo "restaurés : 0 ; bloqués : $ko\n"; return; }
  foreach (($s['affichages'] ?? []) as $id => $d) {
    $aff = $aff_stockage->load($id);
    if (!$aff) { continue; }
    if (!empty($d['cree'])) { $aff->delete(); echo "  affichage $id supprimé (créé par ce lot)\n"; continue; }
    foreach ($d['composants'] as $nom => $x) {
      if ($x['avant'] !== NULL) { $aff->setComponent($nom, $x['avant']); continue; }
      $aff->removeComponent($nom);
      if (!$x['etait_cache']) { $m = $aff->get('hidden'); unset($m[$nom]); $aff->set('hidden', $m); }
    }
    $aff->save(); echo "  affichage $id restauré\n";
  }
  foreach (($s['creations']['champs'] ?? []) as $c) {
    $i = FieldConfig::loadByName($c['entity_type'], $c['bundle'], $c['champ']); if ($i) { $i->delete(); }
    $st = FieldStorageConfig::loadByName($c['entity_type'], $c['champ']); if ($st) { $st->delete(); }
    echo "  champ {$c['champ']} supprimé (créé par ce lot, vide)\n";
  }
  if (!empty($s['creations']['vocabulaire'])) { $v = Vocabulary::load($s['creations']['vocabulaire']); if ($v) { $v->delete(); echo "  vocabulaire « {$s['creations']['vocabulaire']} » supprimé (créé par ce lot, vide)\n"; } }
  rename($fichier, $fichier . '.restaure_' . date('YmdHis'));
  echo "restauration terminée ; sauvegarde archivée.\n";
  return;
}

// ===================== ANALYSE =====================
$anomalies = 0; $plan = ['champs' => [], 'vocabulaire' => FALSE, 'affichage_noeud' => [], 'affichage_terme' => []];
echo "\n--- gardes\n";
if (!FieldConfig::loadByName('node', 'common_names', 'field_ver_local_scripture')) { echo "  ANOMALIE : le type « common_names » n'a pas field_ver_local_scripture : ce n'est pas le type attendu.\n"; $anomalies++; }
else { echo "  = type « common_names » reconnu\n"; }
$aff_n = $aff_stockage->load($affichage_noeud);
if (!$aff_n) { echo "  ANOMALIE : affichage de formulaire $affichage_noeud introuvable.\n"; $anomalies++; } else { echo "  = formulaire des noms trouvé\n"; }

echo "\n--- 1. vocabulaire « $vid »\n";
$voc = Vocabulary::load($vid);
if ($voc) { echo "  = existe déjà (« " . $voc->label() . " ») : réutilisé, non modifié\n"; } else { echo "  + sera créé : « $nom_vocabulaire »\n"; $plan['vocabulaire'] = TRUE; }

foreach ($champs as $nom => $c) {
  echo "\n--- champ $nom sur {$c['entity_type']}/{$c['bundle']}\n";
  $st = FieldStorageConfig::loadByName($c['entity_type'], $nom);
  $inst = FieldConfig::loadByName($c['entity_type'], $c['bundle'], $nom);
  if ($st && $st->getType() !== $c['type']) { echo "  ANOMALIE : le nom $nom existe déjà avec un autre type (" . $st->getType() . ") ; je ne le touche pas.\n"; $anomalies++; continue; }
  echo $st ? "  = stockage existe déjà\n" : "  + stockage à créer ({$c['type']})\n";
  echo $inst ? "  = instance existe déjà\n" : "  + instance à créer (« {$c['label']} »)\n";
  if (!$st || !$inst) { $plan['champs'][$nom] = ['stockage' => !$st, 'instance' => !$inst]; }
}

echo "\n--- formulaire des noms\n";
$a_n = $aff_n ? $aff_n->getComponent('field_ver_translitteration') : NULL;
if ($aff_n && !$a_n) { echo "  + field_ver_translitteration sera affiché (en fin de formulaire)\n"; $plan['affichage_noeud'][] = 'field_ver_translitteration'; }
elseif ($a_n) { echo "  = field_ver_translitteration déjà affiché\n"; }

echo "\n--- formulaire des termes « $vid »\n";
$aff_t_existe = $aff_stockage->load($affichage_terme);
echo $aff_t_existe ? "  = l'affichage existe déjà : on n'y ajoute que ce qui manque\n" : "  + l'affichage sera créé (nom, description, nature, état)\n";
$voulus = array_merge(array_keys($composants_terme_base), ['field_mat_nature', 'field_mat_etat']);
foreach ($voulus as $nom) { if (!$aff_t_existe || !$aff_t_existe->getComponent($nom)) { $plan['affichage_terme'][] = $nom; } }
if ($aff_t_existe && !$plan['affichage_terme']) { echo "  = tous les composants sont déjà présents\n"; }

$a_faire = (int) $plan['vocabulaire'] + count($plan['champs']) + count($plan['affichage_noeud']) + ($plan['affichage_terme'] ? 1 : 0);
echo "\nactions à faire : $a_faire | anomalies : $anomalies\n";
if (!$apply) { echo "\nSimulation : rien n'a été modifié.\n"; return; }

// ===================== APPLICATION =====================
if ($anomalies) { echo "\nERREUR : $anomalies anomalie(s) ; abandon (relire la simulation).\n"; return; }
if ($a_faire === 0) { echo "\nRien à faire : tout est déjà en place.\n"; return; }
if (file_exists($fichier)) { echo "\nERREUR : sauvegarde_modele_nomenclature_2.json existe déjà ; abandon pour ne pas l'écraser.\n"; return; }
$etat = ['date' => date('c'), 'creations' => ['champs' => [], 'vocabulaire' => NULL], 'affichages' => []];
$ecrire($etat);
echo "\nfichier de restauration écrit : sauvegarde_modele_nomenclature_2.json\n";

if ($plan['vocabulaire']) {
  Vocabulary::create(['vid' => $vid, 'name' => $nom_vocabulaire, 'description' => $description_vocabulaire, 'langcode' => 'fr'])->save();
  $etat['creations']['vocabulaire'] = $vid; $ecrire($etat);
  echo "  vocabulaire créé : « $nom_vocabulaire »\n";
}
foreach ($plan['champs'] as $nom => $p) {
  $c = $champs[$nom];
  $st = FieldStorageConfig::loadByName($c['entity_type'], $nom);
  if ($p['stockage']) { $st = FieldStorageConfig::create(['field_name' => $nom, 'entity_type' => $c['entity_type'], 'type' => $c['type'], 'cardinality' => $c['cardinalite'], 'settings' => $c['reglages']]); $st->save(); }
  if ($p['instance']) {
    FieldConfig::create(['field_storage' => $st, 'bundle' => $c['bundle'], 'label' => $c['label'], 'description' => $c['description'], 'required' => FALSE, 'translatable' => FALSE])->save();
    $etat['creations']['champs'][] = ['champ' => $nom, 'entity_type' => $c['entity_type'], 'bundle' => $c['bundle']]; $ecrire($etat);
    echo "  champ créé : $nom sur {$c['entity_type']}/{$c['bundle']}\n";
  }
}
if ($plan['affichage_noeud']) {
  $aff = $aff_stockage->load($affichage_noeud);
  $poids = 0; foreach ($aff->getComponents() as $o) { $poids = max($poids, (int) ($o['weight'] ?? 0)); }
  foreach ($plan['affichage_noeud'] as $nom) {
    $m = $aff->get('hidden');
    $etat['affichages'][$affichage_noeud]['composants'][$nom] = ['avant' => $aff->getComponent($nom), 'etait_cache' => isset($m[$nom])];
    $poids++;
    $aff->setComponent($nom, ['type' => $champs[$nom]['widget']['type'], 'weight' => $poids, 'region' => 'content', 'settings' => $champs[$nom]['widget']['settings'], 'third_party_settings' => []]);
    echo "  affiché : $affichage_noeud → $nom (poids $poids, en fin de formulaire)\n";
  }
  $aff->save(); $ecrire($etat);
}
if ($plan['affichage_terme']) {
  $aff = $repo->getFormDisplay('taxonomy_term', $vid, 'default');
  $etat['affichages'][$affichage_terme] = ['cree' => !$aff_t_existe, 'composants' => []];
  $poids = 10;
  foreach ($plan['affichage_terme'] as $nom) {
    $m = $aff->get('hidden');
    $etat['affichages'][$affichage_terme]['composants'][$nom] = ['avant' => $aff->getComponent($nom), 'etait_cache' => isset($m[$nom])];
    if (isset($composants_terme_base[$nom])) { $aff->setComponent($nom, $composants_terme_base[$nom]); }
    else { $poids++; $aff->setComponent($nom, ['type' => $champs[$nom]['widget']['type'], 'weight' => $poids, 'region' => 'content', 'settings' => $champs[$nom]['widget']['settings'], 'third_party_settings' => []]); }
    echo "  affiché : $affichage_terme → $nom\n";
  }
  $aff->save(); $ecrire($etat);
}

// ===================== RELECTURE =====================
echo "\nRelecture de l'état obtenu :\n";
$ok = 0; $tot = 0;
$verif = function ($libelle, $condition) use (&$ok, &$tot) { $tot++; if ($condition) { $ok++; echo "  ✓ $libelle\n"; } else { echo "  ✗ $libelle\n"; } };
$verif("vocabulaire « $vid » présent", Vocabulary::load($vid) !== NULL);
foreach ($champs as $nom => $c) { $verif("champ $nom présent sur {$c['entity_type']}/{$c['bundle']}", FieldConfig::loadByName($c['entity_type'], $c['bundle'], $nom) !== NULL); }
$an = $aff_stockage->load($affichage_noeud); $verif("$affichage_noeud affiche field_ver_translitteration", $an && $an->getComponent('field_ver_translitteration') !== NULL);
$at = $aff_stockage->load($affichage_terme);
foreach ($voulus as $nom) { $verif("$affichage_terme affiche $nom", $at && $at->getComponent($nom) !== NULL); }
echo "\nrelecture : $ok/$tot conformes\n";
echo "\nFait. Penser à : vendor/bin/drush cr, puis ouvrir « Ajouter un terme » dans le vocabulaire Matériau pour contrôler le formulaire.\n";
