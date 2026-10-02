<?php
/**
 * modele_nomenclature.php — modèle de la nomenclature : type « Désignation », locateur, portée du nom,
 * champs rendus facultatifs, champs affichés dans les formulaires.
 *
 * Usage (sur le serveur) : cd /var/www/khartasia && vendor/bin/drush php-script modele_nomenclature.php
 *   simulation par défaut ; touch APPLY_CONFIRME.txt pour appliquer ; touch RESTAURER_CONFIRME.txt pour restaurer.
 *
 * DÉFINITIONS
 * configuration active : la configuration du site telle qu'elle est en base (champs, formulaires, vocabulaires).
 *   Elle doit rester identique au dossier config/sync : après ce script, les fichiers modifiés sont rapatriés
 *   depuis le serveur dans config/sync (voir les commandes données avec le script).
 * stockage de champ (field storage) : définition d'un champ à l'échelle du site (son type, ses valeurs possibles).
 * instance de champ (field config) : présence de ce champ sur UN type de contenu (son libellé, s'il est obligatoire).
 * affichage de formulaire (form display) : la liste des champs qui apparaissent dans le formulaire d'un type de contenu.
 *   Un champ « masqué » n'y apparaît pas : l'exiger rendrait le formulaire impossible à valider.
 * composant : un champ placé dans un affichage de formulaire, avec son widget (select, texte, autocomplétion).
 * terme / vocabulaire : valeur d'une liste de la taxonomie, et la liste qui la contient.
 * « sensu » : « au sens de » ; référentiel taxonomique (POWO, GRIN, Flora of China) qui fixe les limites d'un taxon.
 * portée d'un nom : à quoi se rapporte un nom qui n'a pas de plante liée (taxon supérieur, fonction, sans référent papetier).
 * simulation : exécution à blanc, qui affiche ce qui serait fait sans rien écrire.
 * garde : test préalable qui fait REFUSER l'écriture si l'état n'est pas celui attendu ; un refus ne modifie rien.
 * idempotent : relancer le script ne change rien quand tout est déjà fait.
 * restauration : remise de chaque élément dans son état d'avant, d'après le fichier de sauvegarde, en bloc ou pas du tout.
 */

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

$vid_types = 'attestation_type';
$nom_terme = 'Désignation';
$description_terme = "Un nom désigne un référent (plante ou papier), à un endroit, selon une source. Distinct de « Synonyme » (deux noms pour un même référent) et d'« Écriture vernaculaire » (la forme graphique d'un nom).";

$champs_a_creer = [
  'field_att_locateur' => [
    'bundle' => 'attestation', 'type' => 'string', 'label' => 'Localisation dans la source',
    'description' => "Page, planche ou entrée précise de la source (par exemple « p. 127 »). Laisser vide si la source est citée dans son ensemble.",
    'cardinalite' => 1, 'reglages_stockage' => ['max_length' => 255],
  ],
  'field_nom_portee' => [
    'bundle' => 'common_names', 'type' => 'list_string', 'label' => 'Portée du nom',
    'description' => "À renseigner seulement si le nom ne désigne aucune fiche Plante : taxon supérieur (genre ou famille), appellatif générique (une fonction) ou nom sans référent papetier (faux nom).",
    'cardinalite' => 1,
    'reglages_stockage' => ['allowed_values' => [
      'espece' => 'Espèce (fiche Plante)',
      'taxon_superieur' => 'Taxon supérieur (genre, famille)',
      'fonction' => 'Appellatif générique (fonction)',
      'sans_referent_papetier' => 'Sans référent papetier (faux nom)',
    ]],
  ],
];
$instances_a_modifier = [
  ['bundle' => 'attestation', 'champ' => 'field_att_concept_taxo', 'required' => FALSE,
   'description' => "Référentiel taxonomique suivi pour cette ligne (« sensu » : au sens de POWO, de GRIN, de Flora of China ou autre), car ces référentiels peuvent délimiter un taxon différemment. Exigé pour toute attestation liée à une plante."],
  ['bundle' => 'common_names', 'champ' => 'field_genus_com_entity', 'required' => FALSE,
   'description' => "Plante(s) désignée(s) par ce nom. À laisser vide seulement pour un nom qui ne désigne aucune fiche Plante : renseigner alors sa portée."],
];
$composants_cibles = [
  'node.attestation.default' => [
    'field_att_concept_taxo' => ['type' => 'options_select', 'settings' => []],
    'field_att_nom_vernaculaire' => ['type' => 'entity_reference_autocomplete', 'settings' => ['match_operator' => 'CONTAINS', 'match_limit' => 10, 'size' => 60, 'placeholder' => '']],
    'field_att_locateur' => ['type' => 'string_textfield', 'settings' => ['size' => 60, 'placeholder' => '']],
  ],
  'node.common_names.default' => [
    'field_nom_portee' => ['type' => 'options_select', 'settings' => []],
  ],
];
$cle_fautive = 'phonetique|'; $cle_juste = 'phonetique';

$apply = file_exists(__DIR__ . '/APPLY_CONFIRME.txt');
$restore = file_exists(__DIR__ . '/RESTAURER_CONFIRME.txt');
if ($apply) { @unlink(__DIR__ . '/APPLY_CONFIRME.txt'); }
if ($restore) { @unlink(__DIR__ . '/RESTAURER_CONFIRME.txt'); $apply = FALSE; }
$fichier = __DIR__ . '/sauvegarde_modele_nomenclature.json';
echo "=== Modèle de la nomenclature — mode : " . ($restore ? 'RESTAURATION' : ($apply ? 'APPLY' : 'SIMULATION')) . " ===\n";

$etm = \Drupal::entityTypeManager();
$termes = $etm->getStorage('taxonomy_term');
$aff_stockage = $etm->getStorage('entity_form_display');
$nb_lignes = function ($table) {
  $db = \Drupal::database();
  return $db->schema()->tableExists($table) ? (int) $db->query('SELECT COUNT(*) FROM {' . $table . '}')->fetchField() : 0;
};
$ecrire = function ($etat) use ($fichier) { file_put_contents($fichier, json_encode($etat, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); };

// ===================== RESTAURATION =====================
if ($restore) {
  if (!file_exists($fichier)) { echo "Aucune sauvegarde (sauvegarde_modele_nomenclature.json introuvable).\n"; return; }
  $s = json_decode(file_get_contents($fichier), TRUE);
  if (!is_array($s)) { echo "ERREUR : sauvegarde illisible.\n"; return; }
  $ko = 0;
  // Vérifications préalables : tout doit être comme l'application l'a laissé.
  foreach (($s['modifications'] ?? []) as $m) {
    if ($m['sorte'] === 'instance') {
      $i = FieldConfig::loadByName('node', $m['bundle'], $m['champ']);
      if (!$i || $i->isRequired() !== $m['apres']['required'] || $i->getDescription() !== $m['apres']['description']) { echo "  [ATTENTION] {$m['bundle']}.{$m['champ']} : modifié depuis l'application ; rien n'est restauré.\n"; $ko++; }
    }
    elseif ($m['sorte'] === 'valeurs_liste') {
      $st = FieldStorageConfig::loadByName('node', $m['champ']);
      if (!$st || $st->getSetting('allowed_values') !== $m['apres']) { echo "  [ATTENTION] {$m['champ']} : valeurs modifiées depuis l'application ; rien n'est restauré.\n"; $ko++; }
    }
  }
  foreach (($s['creations']['champs'] ?? []) as $c) {
    $i = FieldConfig::loadByName('node', $c['bundle'], $c['champ']);
    if ($i && $nb_lignes('node__' . $c['champ']) > 0) { echo "  [ATTENTION] {$c['champ']} contient déjà des données ; il ne sera pas supprimé, et rien n'est restauré.\n"; $ko++; }
  }
  if ($ko) { echo "restaurés : 0 ; bloqués : $ko\n"; return; }
  // Affichages de formulaire.
  foreach (($s['affichages'] ?? []) as $id => $comps) {
    $aff = $aff_stockage->load($id);
    if (!$aff) { continue; }
    foreach ($comps as $nom => $d) {
      if ($d['avant'] !== NULL) { $aff->setComponent($nom, $d['avant']); continue; }
      $aff->removeComponent($nom);
      if (!$d['etait_cache']) {   // le champ n'était pas dans la liste des champs masqués : on ne l'y laisse pas
        $masques = $aff->get('hidden'); unset($masques[$nom]); $aff->set('hidden', $masques);
      }
    }
    $aff->save();
    echo "  affichage $id restauré\n";
  }
  foreach (($s['modifications'] ?? []) as $m) {
    if ($m['sorte'] === 'instance') {
      $i = FieldConfig::loadByName('node', $m['bundle'], $m['champ']);
      $i->setRequired($m['avant']['required'])->setDescription($m['avant']['description'])->save();
      echo "  {$m['bundle']}.{$m['champ']} restauré\n";
    }
    elseif ($m['sorte'] === 'valeurs_liste') {
      $st = FieldStorageConfig::loadByName('node', $m['champ']);
      $st->setSetting('allowed_values', $m['avant'])->save();
      echo "  {$m['champ']} : valeurs d'origine remises\n";
    }
  }
  foreach (($s['creations']['champs'] ?? []) as $c) {
    $i = FieldConfig::loadByName('node', $c['bundle'], $c['champ']);
    if ($i) { $i->delete(); }
    $st = FieldStorageConfig::loadByName('node', $c['champ']);
    if ($st) { $st->delete(); }
    echo "  champ {$c['champ']} supprimé (créé par ce lot, vide)\n";
  }
  foreach (($s['creations']['termes'] ?? []) as $tid) {
    $t = $termes->load($tid);
    $n = (int) \Drupal::database()->query('SELECT COUNT(*) FROM {node__field_att_type} WHERE field_att_type_target_id = :t', [':t' => $tid])->fetchField();
    if ($t && $n === 0) { $t->delete(); echo "  terme $tid supprimé (créé par ce lot, plus utilisé)\n"; }
    elseif ($t) { echo "  terme $tid conservé (encore utilisé par $n attestation(s))\n"; }
  }
  rename($fichier, $fichier . '.restaure_' . date('YmdHis'));
  echo "restauration terminée ; sauvegarde archivée.\n";
  return;
}

// ===================== ANALYSE =====================
$anomalies = 0;
$plan = [];

// --- gardes générales ---
$existants = $termes->loadByProperties(['vid' => $vid_types]);
$noms_existants = array_map(function ($t) { return $t->label(); }, $existants);
echo "\nvocabulaire « $vid_types » : " . count($existants) . " terme(s)\n";
if (count($existants) < 9 || !in_array('Synonyme', $noms_existants, TRUE) || !in_array('Écriture vernaculaire', $noms_existants, TRUE)) {
  echo "  ANOMALIE : ce n'est pas le vocabulaire attendu (au moins 9 termes dont « Synonyme » et « Écriture vernaculaire »).\n"; $anomalies++;
}
foreach ([['attestation', 'field_att_valeur'], ['common_names', 'field_ver_local_scripture']] as $p) {
  if (!FieldConfig::loadByName('node', $p[0], $p[1])) { echo "  ANOMALIE : le type « {$p[0]} » n'a pas le champ {$p[1]} : ce n'est pas le type attendu.\n"; $anomalies++; }
}

// --- 1. terme « Désignation » ---
echo "\n--- 1. terme « $nom_terme »\n";
$deja_terme = in_array($nom_terme, $noms_existants, TRUE);
echo $deja_terme ? "  = existe déjà : réutilisé\n" : "  + sera créé\n";
$plan['terme'] = !$deja_terme;

// --- 2. champs à créer ---
$plan['champs'] = [];
foreach ($champs_a_creer as $nom => $c) {
  echo "\n--- 2. champ $nom sur « {$c['bundle']} »\n";
  $st = FieldStorageConfig::loadByName('node', $nom);
  $inst = FieldConfig::loadByName('node', $c['bundle'], $nom);
  if ($st && $st->getType() !== $c['type']) { echo "  ANOMALIE : le nom $nom existe déjà avec un autre type (" . $st->getType() . ") ; je ne le touche pas.\n"; $anomalies++; continue; }
  echo $st ? "  = stockage existe déjà\n" : "  + stockage à créer ({$c['type']})\n";
  echo $inst ? "  = instance existe déjà\n" : "  + instance à créer (« {$c['label']} »)\n";
  $plan['champs'][$nom] = ['stockage' => !$st, 'instance' => !$inst];
}

// --- 3. instances à modifier ---
$plan['instances'] = [];
foreach ($instances_a_modifier as $m) {
  echo "\n--- 3. {$m['bundle']}.{$m['champ']}\n";
  $i = FieldConfig::loadByName('node', $m['bundle'], $m['champ']);
  if (!$i) { echo "  ANOMALIE : instance introuvable.\n"; $anomalies++; continue; }
  $etat = [];
  if ($i->isRequired() !== $m['required']) { echo "  ~ obligatoire : " . ($i->isRequired() ? 'oui' : 'non') . " -> " . ($m['required'] ? 'oui' : 'non') . "\n"; $etat[] = 'required'; }
  else { echo "  = obligatoire déjà : " . ($m['required'] ? 'oui' : 'non') . "\n"; }
  if ($i->getDescription() !== $m['description']) { echo "  ~ description : « " . mb_substr((string) $i->getDescription(), 0, 70) . " » -> nouvelle\n"; $etat[] = 'description'; }
  else { echo "  = description déjà à jour\n"; }
  if ($etat) { $plan['instances'][] = $m; }
}

// --- 4. clé fautive du type de nom ---
echo "\n--- 4. valeurs du champ field_nom_type\n";
$plan['nom_type'] = FALSE;
$st_type = FieldStorageConfig::loadByName('node', 'field_nom_type');
if (!$st_type) { echo "  ANOMALIE : field_nom_type introuvable.\n"; $anomalies++; }
else {
  $vals = $st_type->getSetting('allowed_values');
  if (array_key_exists($cle_fautive, $vals)) {
    $n = $nb_lignes('node__field_nom_type');
    if ($n > 0) { echo "  ANOMALIE : $n valeur(s) enregistrée(s) dans ce champ ; je ne renomme pas la clé sans les migrer.\n"; $anomalies++; }
    elseif (array_key_exists($cle_juste, $vals)) { echo "  ANOMALIE : « $cle_juste » existe déjà à côté de « $cle_fautive ».\n"; $anomalies++; }
    else { echo "  ~ clé « $cle_fautive » -> « $cle_juste » (aucune donnée ne l'utilise)\n"; $plan['nom_type'] = TRUE; }
  }
  else { echo "  = clé déjà correcte\n"; }
}

// --- 5. composants des formulaires ---
echo "\n--- 5. formulaires\n";
$plan['affichages'] = [];
foreach ($composants_cibles as $id => $comps) {
  $aff = $aff_stockage->load($id);
  if (!$aff) { echo "  ANOMALIE : affichage de formulaire $id introuvable.\n"; $anomalies++; continue; }
  foreach ($comps as $nom => $cible) {
    $actuel = $aff->getComponent($nom);
    if ($actuel && ($actuel['type'] ?? NULL) === $cible['type']) { echo "  = $id : $nom déjà affiché ({$cible['type']})\n"; continue; }
    echo "  + $id : $nom sera affiché ({$cible['type']})" . ($actuel ? '' : ' — actuellement masqué ou absent') . "\n";
    $plan['affichages'][$id][$nom] = $cible;
  }
}

$a_faire = (int) $plan['terme'] + count(array_filter($plan['champs'], function ($c) { return $c['stockage'] || $c['instance']; })) + count($plan['instances']) + (int) $plan['nom_type'] + array_sum(array_map('count', $plan['affichages']));
echo "\nactions à faire : $a_faire | anomalies : $anomalies\n";
if (!$apply) { echo "\nSimulation : rien n'a été modifié.\n"; return; }

// ===================== APPLICATION =====================
if ($anomalies) { echo "\nERREUR : $anomalies anomalie(s) ; abandon (relire la simulation).\n"; return; }
if ($a_faire === 0) { echo "\nRien à faire : tout est déjà en place.\n"; return; }
if (file_exists($fichier)) { echo "\nERREUR : sauvegarde_modele_nomenclature.json existe déjà ; abandon pour ne pas l'écraser.\n"; return; }
$etat = ['date' => date('c'), 'creations' => ['termes' => [], 'champs' => []], 'modifications' => [], 'affichages' => []];
$ecrire($etat);
echo "\nfichier de restauration écrit : sauvegarde_modele_nomenclature.json\n";

if ($plan['terme']) {
  $t = $termes->create(['vid' => $vid_types, 'name' => $nom_terme, 'langcode' => 'en', 'description' => ['value' => $description_terme, 'format' => 'plain_text']]);
  $t->save();
  $etat['creations']['termes'][] = (int) $t->id(); $ecrire($etat);
  echo "  terme créé : « $nom_terme » (tid " . $t->id() . ")\n";
}
foreach ($plan['champs'] as $nom => $p) {
  $c = $champs_a_creer[$nom];
  $st = FieldStorageConfig::loadByName('node', $nom);
  if ($p['stockage']) {
    $st = FieldStorageConfig::create(['field_name' => $nom, 'entity_type' => 'node', 'type' => $c['type'], 'cardinality' => $c['cardinalite'], 'settings' => $c['reglages_stockage']]);
    $st->save();
  }
  if ($p['instance']) {
    FieldConfig::create(['field_storage' => $st, 'bundle' => $c['bundle'], 'label' => $c['label'], 'description' => $c['description'], 'required' => FALSE, 'translatable' => FALSE])->save();
    $etat['creations']['champs'][] = ['champ' => $nom, 'bundle' => $c['bundle']]; $ecrire($etat);
    echo "  champ créé : $nom sur {$c['bundle']}\n";
  }
}
foreach ($plan['instances'] as $m) {
  $i = FieldConfig::loadByName('node', $m['bundle'], $m['champ']);
  $avant = ['required' => $i->isRequired(), 'description' => $i->getDescription()];
  $i->setRequired($m['required'])->setDescription($m['description'])->save();
  $etat['modifications'][] = ['sorte' => 'instance', 'bundle' => $m['bundle'], 'champ' => $m['champ'], 'avant' => $avant, 'apres' => ['required' => $m['required'], 'description' => $m['description']]]; $ecrire($etat);
  echo "  modifié : {$m['bundle']}.{$m['champ']} (facultatif, description à jour)\n";
}
if ($plan['nom_type']) {
  $st = FieldStorageConfig::loadByName('node', 'field_nom_type');
  $avant = $st->getSetting('allowed_values'); $apres = [];
  foreach ($avant as $k => $l) { $apres[$k === $cle_fautive ? $cle_juste : $k] = $l; }
  $st->setSetting('allowed_values', $apres)->save();
  $etat['modifications'][] = ['sorte' => 'valeurs_liste', 'champ' => 'field_nom_type', 'avant' => $avant, 'apres' => $apres]; $ecrire($etat);
  echo "  corrigé : clé « $cle_fautive » -> « $cle_juste »\n";
}
foreach ($plan['affichages'] as $id => $comps) {
  $aff = $aff_stockage->load($id);
  $poids = 0; foreach ($aff->getComponents() as $o) { $poids = max($poids, (int) ($o['weight'] ?? 0)); }
  foreach ($comps as $nom => $cible) {
    $masques = $aff->get('hidden');
    $etat['affichages'][$id][$nom] = ['avant' => $aff->getComponent($nom), 'etait_cache' => isset($masques[$nom])];   // avant NULL = masqué ou absent
    $poids++;
    $aff->setComponent($nom, ['type' => $cible['type'], 'weight' => $poids, 'region' => 'content', 'settings' => $cible['settings'], 'third_party_settings' => []]);
    echo "  affiché : $id → $nom (poids $poids, en fin de formulaire)\n";
  }
  $aff->save(); $ecrire($etat);
}

// ===================== RELECTURE =====================
echo "\nRelecture de l'état obtenu :\n";
$ok = 0; $tot = 0;
$verif = function ($libelle, $condition) use (&$ok, &$tot) { $tot++; if ($condition) { $ok++; echo "  ✓ $libelle\n"; } else { echo "  ✗ $libelle\n"; } };
$verif("terme « $nom_terme » présent", count($termes->loadByProperties(['vid' => $vid_types, 'name' => $nom_terme])) === 1);
foreach ($champs_a_creer as $nom => $c) { $verif("champ $nom présent sur {$c['bundle']}", FieldConfig::loadByName('node', $c['bundle'], $nom) !== NULL); }
foreach ($instances_a_modifier as $m) { $i = FieldConfig::loadByName('node', $m['bundle'], $m['champ']); $verif("{$m['bundle']}.{$m['champ']} facultatif", $i && $i->isRequired() === FALSE); }
$vals = FieldStorageConfig::loadByName('node', 'field_nom_type')->getSetting('allowed_values');
$verif("clé « $cle_juste » présente et « $cle_fautive » absente", array_key_exists($cle_juste, $vals) && !array_key_exists($cle_fautive, $vals));
foreach ($composants_cibles as $id => $comps) { $aff = $aff_stockage->load($id); foreach ($comps as $nom => $cible) { $verif("$id affiche $nom", $aff && ($aff->getComponent($nom)['type'] ?? NULL) === $cible['type']); } }
echo "\nrelecture : $ok/$tot conformes\n";
echo "\nFait. Penser à : vendor/bin/drush cr\n";
