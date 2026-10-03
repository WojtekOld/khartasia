<?php
/**
 * modele_nomenclature_3.php — troisième lot du modèle : deux champs de traduction anglaise sur l'attestation.
 *
 *   « Énoncé (anglais) »          traduction de l'énoncé (champ « Valeur »)
 *   « Note de travail (anglais) » traduction de la note de travail (champ « Note interne »)
 *
 * Usage (sur le serveur ou en local, depuis le dossier du script) :
 *   vendor/bin/drush php-script modele_nomenclature_3.php
 *   simulation par défaut ; touch APPLY_CONFIRME.txt pour appliquer ; touch RESTAURER_CONFIRME.txt pour restaurer.
 *
 * Ce que fait ce lot : ajoute à l'attestation deux champs facultatifs, du MÊME type que leur champ français. Le champ français
 * reste la RÉFÉRENCE : le champ anglais ne le remplace jamais. Chaque champ est traité à part : si l'un existe déjà, seul
 * l'autre est créé. Ce qu'il ne fait pas : aucune traduction automatique, aucune donnée créée, aucun autre type touché.
 *
 * DÉFINITIONS
 * énoncé : le texte d'une attestation, qui dit ce qu'une source affirme (champ « Valeur »).
 * note de travail : texte interne de l'attestation (provenance, validation) ; jamais publié.
 * référence : le texte qui fait foi. La traduction est un confort de lecture, enregistrée par l'équipe, pas par la source.
 * simulation / garde / idempotent / restauration / affichage de formulaire / composant : voir modele_nomenclature.php.
 */

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

$bundle = 'attestation';
$affichage_id = 'node.attestation.default';
$champs = [
  'field_att_valeur_en' => ['reference' => 'field_att_valeur', 'type' => 'string_long', 'label' => 'Énoncé (anglais)',
    'description' => "Traduction anglaise de l'énoncé, pour les dossiers destinés aux lecteurs anglophones. Facultative. Le champ « Valeur » (le texte français) reste la référence : en cas d'écart, c'est lui qui fait foi. Traduction de l'équipe, non de la source.",
    'widget' => ['type' => 'string_textarea', 'settings' => ['rows' => 5, 'placeholder' => '']]],
  'field_att_note_interne_en' => ['reference' => 'field_att_note_interne', 'type' => 'text_long', 'label' => 'Note de travail (anglais)',
    'description' => "Traduction anglaise de la note de travail, pour les dossiers de relecture destinés aux lecteurs anglophones. Facultative. Jamais publiée. La note française (« Note interne ») reste la référence. Traduction de l'équipe, non de la source.",
    'widget' => ['type' => 'text_textarea', 'settings' => ['rows' => 5, 'placeholder' => '']]],
];

$apply = file_exists(__DIR__ . '/APPLY_CONFIRME.txt');
$restore = file_exists(__DIR__ . '/RESTAURER_CONFIRME.txt');
if ($apply) { @unlink(__DIR__ . '/APPLY_CONFIRME.txt'); }
if ($restore) { @unlink(__DIR__ . '/RESTAURER_CONFIRME.txt'); $apply = FALSE; }
$fichier = __DIR__ . '/sauvegarde_modele_nomenclature_3.json';
echo "=== Modèle de la nomenclature, lot 3 — mode : " . ($restore ? 'RESTAURATION' : ($apply ? 'APPLY' : 'SIMULATION')) . " ===\n";

$aff_stockage = \Drupal::entityTypeManager()->getStorage('entity_form_display');
$nb_lignes = function ($table) { $db = \Drupal::database(); return $db->schema()->tableExists($table) ? (int) $db->query('SELECT COUNT(*) FROM {' . $table . '}')->fetchField() : 0; };
$ecrire = function ($etat) use ($fichier) { file_put_contents($fichier, json_encode($etat, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); };

// ===================== RESTAURATION =====================
if ($restore) {
  if (!file_exists($fichier)) { echo "Aucune sauvegarde (sauvegarde_modele_nomenclature_3.json introuvable).\n"; return; }
  $s = json_decode(file_get_contents($fichier), TRUE);
  if (!is_array($s)) { echo "ERREUR : sauvegarde illisible.\n"; return; }
  $bloques = 0;
  foreach (($s['champs_crees'] ?? []) as $nom) {
    $n = $nb_lignes('node__' . $nom);
    if ($n > 0) { echo "  [ATTENTION] $nom contient déjà $n valeur(s) ; il ne sera pas supprimé, et rien n'est restauré.\n"; $bloques++; }
  }
  if ($bloques) { echo "restaurés : 0 ; bloqués : $bloques\n"; return; }
  $aff = $aff_stockage->load($affichage_id);
  if ($aff) {
    foreach (($s['affichages'] ?? []) as $nom => $x) {
      if ($x['avant'] !== NULL) { $aff->setComponent($nom, $x['avant']); }
      else { $aff->removeComponent($nom); if (!$x['etait_cache']) { $m = $aff->get('hidden'); unset($m[$nom]); $aff->set('hidden', $m); } }
      echo "  affichage $affichage_id : $nom restauré\n";
    }
    $aff->save();
  }
  foreach (($s['champs_crees'] ?? []) as $nom) {
    $i = FieldConfig::loadByName('node', $bundle, $nom); if ($i) { $i->delete(); }
    $st = FieldStorageConfig::loadByName('node', $nom); if ($st) { $st->delete(); }
    echo "  champ $nom supprimé (créé par ce lot, vide)\n";
  }
  rename($fichier, $fichier . '.restaure_' . date('YmdHis'));
  echo "restauration terminée ; sauvegarde archivée.\n";
  return;
}

// ===================== ANALYSE =====================
$anomalies = 0; $plan = [];
$aff = $aff_stockage->load($affichage_id);
echo "\n--- gardes\n";
if (!$aff) { echo "  ANOMALIE : affichage de formulaire $affichage_id introuvable.\n"; $anomalies++; } else { echo "  = formulaire des attestations trouvé\n"; }
foreach ($champs as $nom => $c) {
  echo "\n--- champ $nom sur node/$bundle\n";
  $ref_st = FieldStorageConfig::loadByName('node', $c['reference']);
  $ref_inst = FieldConfig::loadByName('node', $bundle, $c['reference']);
  if (!$ref_st || !$ref_inst) { echo "  ANOMALIE : le champ {$c['reference']} est absent du type « $bundle » : ce n'est pas le type attendu.\n"; $anomalies++; continue; }
  if ($ref_st->getType() !== $c['type']) { echo "  ANOMALIE : {$c['reference']} n'est pas de type {$c['type']} (" . $ref_st->getType() . ") ; le champ anglais doit avoir le même type.\n"; $anomalies++; continue; }
  echo "  = champ de référence {$c['reference']} reconnu ({$c['type']})\n";
  $st = FieldStorageConfig::loadByName('node', $nom);
  $inst = FieldConfig::loadByName('node', $bundle, $nom);
  if ($st && $st->getType() !== $c['type']) { echo "  ANOMALIE : le nom $nom existe déjà avec un autre type (" . $st->getType() . ") ; je ne le touche pas.\n"; $anomalies++; continue; }
  echo $st ? "  = stockage existe déjà\n" : "  + stockage à créer ({$c['type']})\n";
  echo $inst ? "  = instance existe déjà\n" : "  + instance à créer (« {$c['label']} »)\n";
  $afficher = $aff && !$aff->getComponent($nom);
  echo $afficher ? "  + $nom sera affiché (en fin de formulaire)\n" : ($aff ? "  = $nom déjà affiché\n" : '');
  $plan[$nom] = ['stockage' => !$st, 'instance' => !$inst, 'afficher' => $afficher];
}
$a_faire = 0;
foreach ($plan as $p) { $a_faire += ($p['stockage'] || $p['instance'] ? 1 : 0) + ($p['afficher'] ? 1 : 0); }
echo "\nactions à faire : $a_faire | anomalies : $anomalies\n";
if (!$apply) { echo "\nSimulation : rien n'a été modifié.\n"; return; }

// ===================== APPLICATION =====================
if ($anomalies) { echo "\nERREUR : $anomalies anomalie(s) ; abandon (relire la simulation).\n"; return; }
if ($a_faire === 0) { echo "\nRien à faire : tout est déjà en place.\n"; return; }
if (file_exists($fichier)) { echo "\nERREUR : sauvegarde_modele_nomenclature_3.json existe déjà ; abandon pour ne pas l'écraser.\n"; return; }
$etat = ['date' => date('c'), 'champs_crees' => [], 'affichages' => []];
$ecrire($etat);
echo "\nfichier de restauration écrit : sauvegarde_modele_nomenclature_3.json\n";

foreach ($plan as $nom => $p) {
  $c = $champs[$nom];
  $st = FieldStorageConfig::loadByName('node', $nom);
  if ($p['stockage']) { $st = FieldStorageConfig::create(['field_name' => $nom, 'entity_type' => 'node', 'type' => $c['type'], 'cardinality' => 1, 'settings' => []]); $st->save(); }
  if ($p['instance']) {
    FieldConfig::create(['field_storage' => $st, 'bundle' => $bundle, 'label' => $c['label'], 'description' => $c['description'], 'required' => FALSE, 'translatable' => FALSE])->save();
    $etat['champs_crees'][] = $nom; $ecrire($etat);
    echo "  champ créé : $nom sur node/$bundle\n";
  }
  if ($p['afficher']) {
    $aff = $aff_stockage->load($affichage_id);
    $poids = 0; foreach ($aff->getComponents() as $o) { $poids = max($poids, (int) ($o['weight'] ?? 0)); }
    $m = $aff->get('hidden');
    $etat['affichages'][$nom] = ['avant' => $aff->getComponent($nom), 'etait_cache' => isset($m[$nom])];
    $poids++;
    $aff->setComponent($nom, ['type' => $c['widget']['type'], 'weight' => $poids, 'region' => 'content', 'settings' => $c['widget']['settings'], 'third_party_settings' => []]);
    $aff->save(); $ecrire($etat);
    echo "  affiché : $affichage_id → $nom (poids $poids, en fin de formulaire)\n";
  }
}

// ===================== RELECTURE =====================
echo "\nRelecture de l'état obtenu :\n";
$ok = 0; $tot = 0;
$verif = function ($libelle, $condition) use (&$ok, &$tot) { $tot++; if ($condition) { $ok++; echo "  ✓ $libelle\n"; } else { echo "  ✗ $libelle\n"; } };
$a2 = $aff_stockage->load($affichage_id);
foreach ($champs as $nom => $c) {
  $verif("stockage $nom présent", FieldStorageConfig::loadByName('node', $nom) !== NULL);
  $verif("champ $nom présent sur node/$bundle", FieldConfig::loadByName('node', $bundle, $nom) !== NULL);
  $verif("$affichage_id affiche $nom", $a2 && $a2->getComponent($nom) !== NULL);
}
echo "\nrelecture : $ok/$tot conformes\n";
echo "\nFait. Penser à : vendor/bin/drush cr\n";
