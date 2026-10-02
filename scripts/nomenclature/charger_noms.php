<?php
/**
 * charger_noms.php — CHARGEMENT INITIAL de noms vernaculaires et de leurs attestations, depuis un fichier de données.
 *
 * Le script ne contient AUCUN contenu : tout ce qui relève de l'érudition (noms, sources, pages, statuts, textes, plantes)
 * est dans le fichier de données JSON. Corriger un contenu = corriger ce fichier, jamais ce script.
 *
 * Usage (sur le serveur) :
 *   cd /var/www/khartasia && DONNEES=mon_fichier.json vendor/bin/drush php-script charger_noms.php
 * Variables d'environnement (facultatives) :
 *   DONNEES=<fichier>              fichier de données (défaut : donnees_noms.json, à côté du script)
 *   REFS=<clé>=<n>,<clé>=<n>       force le numéro de la notice bibliographique d'une source (si la recherche automatique échoue)
 *   SEULEMENT_VALIDES=1            ne charge que les enregistrements dont la validation est « valide » ou « corrige »
 * Simulation par défaut ; touch APPLY_CONFIRME.txt pour charger ; touch RESTAURER_CONFIRME.txt pour défaire.
 *
 * INVARIANTS (garantis par le script)
 *   - tout est créé NON PUBLIÉ ; le script ne publie jamais (une clé « publie » dans le fichier est ignorée et signalée) ;
 *   - rien n'est créé hors des noms et attestations du fichier : ni terme, ni fiche Plante, ni notice bibliographique ;
 *   - un prérequis manquant ou une valeur non permise arrête tout AVANT la moindre écriture ;
 *   - c'est un chargement initial : pas de mise à jour. Les corrections se font ensuite dans l'interface.
 *
 * DÉFINITIONS
 * chargement initial : création de contenus neufs, en une fois. Le script ne met pas à jour un contenu déjà chargé.
 * enregistrement : un nom et ses attestations, avec l'état de leur validation scientifique.
 * clé : identifiant d'un enregistrement DANS le fichier (sert à désigner un parent) ; elle n'est pas stockée dans le site.
 * parent : nom principal dont un nom est une variante (champ « forme canonique parente »). Il est désigné par sa clé.
 * portée : à quoi se rapporte un nom sans plante (taxon supérieur, fonction, sans référent papetier). Obligatoire sans plante.
 * attestation : une affirmation d'UNE source, à UNE page, sur UN référent. Un nom polysémique a une attestation par plante.
 * validation : état de la relecture scientifique d'un enregistrement : a_valider, valide, corrige ou rejete. Il est recopié dans la
 *   note interne de chaque attestation, pour rester attaché au contenu. « rejete » n'est jamais chargé.
 * valeurs permises : celles que la configuration du site définit (statut, concept, portée). Le script les lit sur le site ;
 *   une valeur du fichier qui n'en fait pas partie est refusée.
 * concept taxonomique : référentiel « sensu » (au sens de POWO, GRIN, Flora of China ou autre) ; exigé pour toute attestation liée à une plante.
 * validation de Drupal : contrôle des règles d'un contenu SANS l'enregistrer (entity->validate()). C'est le vrai test du modèle.
 * cas témoin : cas qui DOIT violer une règle ; s'il ne la viole pas, c'est le test qui est en défaut (faux négatif).
 * simulation / garde / restauration : voir modele_nomenclature.php.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;

$arg = getenv('DONNEES') ?: 'donnees_noms.json';
$chemin_donnees = ($arg[0] === '/') ? $arg : __DIR__ . '/' . $arg;
$seulement_valides = (bool) getenv('SEULEMENT_VALIDES');
$refs_forcees = [];
foreach (array_filter(explode(',', getenv('REFS') ?: '')) as $paire) { $p = explode('=', $paire, 2); if (count($p) === 2) { $refs_forcees[trim($p[0])] = (int) $p[1]; } }
$etats_validation = ['a_valider', 'valide', 'corrige', 'rejete'];
$periode = date('Y-m-d');

$apply = file_exists(__DIR__ . '/APPLY_CONFIRME.txt');
$restore = file_exists(__DIR__ . '/RESTAURER_CONFIRME.txt');
if ($apply) { @unlink(__DIR__ . '/APPLY_CONFIRME.txt'); }
if ($restore) { @unlink(__DIR__ . '/RESTAURER_CONFIRME.txt'); $apply = FALSE; }
$fichier = __DIR__ . '/sauvegarde_chargement_noms.json';
echo "=== Chargement initial de noms et d'attestations — mode : " . ($restore ? 'RESTAURATION' : ($apply ? 'APPLY' : 'SIMULATION')) . " ===\n";

$etm = \Drupal::entityTypeManager();
$noeuds = $etm->getStorage('node');
$termes = $etm->getStorage('taxonomy_term');
$ecrire = function ($etat) use ($fichier) { file_put_contents($fichier, json_encode($etat, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); };

// ===================== RESTAURATION =====================
if ($restore) {
  if (!file_exists($fichier)) { echo "Aucune sauvegarde (sauvegarde_chargement_noms.json introuvable).\n"; return; }
  $s = json_decode(file_get_contents($fichier), TRUE);
  if (!is_array($s) || empty($s['crees'])) { echo "ERREUR : sauvegarde illisible.\n"; return; }
  $ko = 0; $charges = [];
  foreach ($s['crees'] as $nid => $e) {
    $n = $noeuds->load($nid); $charges[$nid] = $n;
    if (!$n) { echo "  [ATTENTION] $nid ({$e['titre']}) : introuvable ; rien n'est supprimé.\n"; $ko++; continue; }
    if ($n->isPublished()) { echo "  [ATTENTION] $nid ({$e['titre']}) : publié depuis le chargement ; rien n'est supprimé.\n"; $ko++; }
    elseif ((int) $n->getChangedTime() !== (int) $e['changed']) { echo "  [ATTENTION] $nid ({$e['titre']}) : modifié depuis le chargement ; rien n'est supprimé.\n"; $ko++; }
  }
  if ($ko) { echo "supprimés : 0 ; bloqués : $ko\n"; return; }
  foreach (array_reverse(array_keys($s['crees'])) as $nid) { $charges[$nid]->delete(); echo "  supprimé : $nid ({$s['crees'][$nid]['titre']})\n"; }
  rename($fichier, $fichier . '.restaure_' . date('YmdHis'));
  echo "restauration terminée : " . count($s['crees']) . " contenu(s) supprimé(s) ; sauvegarde archivée.\n";
  return;
}

// ===================== LECTURE ET STRUCTURE DU FICHIER =====================
if (!is_file($chemin_donnees)) { echo "ERREUR : fichier de données introuvable : $chemin_donnees\n"; return; }
$donnees = json_decode(file_get_contents($chemin_donnees), TRUE);
if (!is_array($donnees)) { echo "ERREUR : fichier de données illisible (JSON invalide : " . json_last_error_msg() . ").\n"; return; }
$meta = $donnees['meta'] ?? []; $jeu = (string) ($meta['jeu'] ?? '(sans nom)'); $version = (string) ($meta['version'] ?? '?');
$sources_def = $donnees['sources'] ?? []; $plantes_def = $donnees['plantes'] ?? []; $tous = $donnees['enregistrements'] ?? [];
echo "fichier : " . basename($chemin_donnees) . " — jeu « $jeu », version $version\n";

$anomalies = 0;
$verif = function ($condition, $texte) use (&$anomalies) { echo ($condition ? "  ✓ " : "  ✗ ") . $texte . "\n"; if (!$condition) { $anomalies++; } return (bool) $condition; };
$erreur = function ($texte) use (&$anomalies) { echo "  ✗ $texte\n"; $anomalies++; };

echo "\nStructure du fichier :\n";
if (!is_array($tous) || !$tous) { $erreur("aucun enregistrement dans le fichier"); $tous = []; }
$par_cle = [];
foreach ($tous as $i => $r) {
  $cle = $r['cle'] ?? NULL;
  if (!is_string($cle) || $cle === '') { $erreur("enregistrement n°" . ($i + 1) . " : « cle » manquante"); continue; }
  if (isset($par_cle[$cle])) { $erreur("clé en double : « $cle »"); continue; }
  $par_cle[$cle] = $r;
}
$retenus = []; $ecartes = [];
foreach ($par_cle as $cle => $r) {
  $etat_v = $r['validation']['etat'] ?? 'a_valider';
  if (!in_array($etat_v, $etats_validation, TRUE)) { $erreur("« $cle » : état de validation « $etat_v » inconnu (permis : " . implode(', ', $etats_validation) . ")"); continue; }
  if ($etat_v === 'rejete') { $ecartes[$cle] = 'rejeté par la validation'; continue; }
  if ($seulement_valides && !in_array($etat_v, ['valide', 'corrige'], TRUE)) { $ecartes[$cle] = "validation « $etat_v » (SEULEMENT_VALIDES)"; continue; }
  $retenus[$cle] = $r;
}
foreach ($retenus as $cle => $r) {
  if (empty($r['nom']['titre']) || empty($r['nom']['langue'])) { $erreur("« $cle » : nom.titre et nom.langue sont obligatoires"); }
  $plantes_nom = $r['nom']['plantes'] ?? [];
  if (!$plantes_nom && empty($r['nom']['portee'])) { $erreur("« $cle » : un nom sans plante doit avoir une portée (nom.portee)"); }
  if (empty($r['attestations']) || !is_array($r['attestations'])) { $erreur("« $cle » : au moins une attestation est obligatoire"); continue; }
  foreach ($r['attestations'] as $k => $a) {
    foreach (['type', 'statut', 'source', 'texte'] as $obligatoire) { if (empty($a[$obligatoire])) { $erreur("« $cle » attestation " . ($k + 1) . " : « $obligatoire » manquant"); } }
    if (!empty($a['source']) && !isset($sources_def[$a['source']])) { $erreur("« $cle » attestation " . ($k + 1) . " : source « {$a['source']} » non déclarée dans « sources »"); }
    foreach (($a['plantes'] ?? $plantes_nom) as $al) { if (!isset($plantes_def[$al])) { $erreur("« $cle » : plante « $al » non déclarée dans « plantes »"); } }
    if (!empty(($a['plantes'] ?? $plantes_nom)) && empty($a['concept'])) { $erreur("« $cle » attestation " . ($k + 1) . " : une attestation liée à une plante exige un « concept »"); }
  }
  if (array_key_exists('publie', $r) || array_key_exists('publie', $r['nom'] ?? [])) { echo "  ⚠ « $cle » : la clé « publie » est ignorée (le script ne publie jamais)\n"; }
  $parent = $r['nom']['parent'] ?? NULL;
  if ($parent !== NULL && !isset($retenus[$parent])) { $erreur("« $cle » : parent « $parent » inconnu, écarté ou absent des enregistrements retenus"); }
}
// ordre de création : les parents d'abord
$ordre = []; $places = [];
while (count($ordre) < count($retenus)) {
  $progres = FALSE;
  foreach ($retenus as $cle => $r) {
    if (isset($places[$cle])) { continue; }
    $parent = $r['nom']['parent'] ?? NULL;
    if ($parent === NULL || isset($places[$parent]) || !isset($retenus[$parent])) { $places[$cle] = TRUE; $ordre[] = $cle; $progres = TRUE; }
  }
  if (!$progres) { $erreur("cycle dans les parents (" . implode(', ', array_diff(array_keys($retenus), array_keys($places))) . ")"); break; }
}
$verif(TRUE, count($retenus) . " enregistrement(s) retenu(s)" . ($ecartes ? ", " . count($ecartes) . " écarté(s) : " . implode(' ; ', array_map(function ($c, $m) { return "$c ($m)"; }, array_keys($ecartes), $ecartes)) : ''));

// ===================== PRÉREQUIS SUR LE SITE =====================
echo "\nPrérequis sur le site :\n";
$aliases_utilises = [];
foreach ($retenus as $r) { foreach (($r['nom']['plantes'] ?? []) as $al) { $aliases_utilises[$al] = TRUE; } foreach (($r['attestations'] ?? []) as $a) { foreach (($a['plantes'] ?? []) as $al) { $aliases_utilises[$al] = TRUE; } } }
$plantes = [];
foreach (array_keys($aliases_utilises) as $al) {
  $d = $plantes_def[$al] ?? NULL; if (!$d) { continue; }
  $n = !empty($d['nid']) ? $noeuds->load($d['nid']) : NULL;
  $contient = $d['titre_contient'] ?? '';
  $ok = $n && $n->bundle() === 'article' && $n->isPublished() && ($contient === '' || stripos($n->label(), $contient) !== FALSE);
  $tid = ($n && $n->hasField('field_tax_genus') && !$n->get('field_tax_genus')->isEmpty()) ? (int) $n->get('field_tax_genus')->target_id : 0;
  $verif($ok && $tid > 0, "plante « $al » : nœud " . ($d['nid'] ?? '?') . " « " . ($n ? $n->label() : 'introuvable') . " » (article publié" . ($contient !== '' ? " contenant « $contient »" : '') . ") ; pointeur de taxonomie " . ($tid ?: 'ABSENT'));
  if ($ok && $tid) { $plantes[$al] = ['nid' => (int) $d['nid'], 'tid' => $tid, 'titre' => $n->label()]; }
}
$refs = []; $sources_utilisees = [];
foreach ($retenus as $r) { foreach ($r['attestations'] ?? [] as $a) { if (!empty($a['source'])) { $sources_utilisees[$a['source']] = TRUE; } } }
foreach (array_keys($sources_utilisees) as $al) {
  $d = $sources_def[$al] ?? NULL; if (!$d) { continue; }
  $ref = NULL;
  if (isset($refs_forcees[$al])) { $ref = $etm->getStorage('bibcite_reference')->load($refs_forcees[$al]); }
  else {
    $cand = [];
    foreach ($etm->getStorage('bibcite_reference')->loadMultiple() as $rr) { foreach (($d['motifs'] ?? []) as $m) { if ($m !== '' && stripos($rr->label(), $m) !== FALSE) { $cand[$rr->id()] = $rr; break; } } }
    if (count($cand) === 1) { $ref = reset($cand); } else { foreach ($cand as $id => $rr) { echo "      candidate : $id — " . $rr->label() . "\n"; } }
  }
  $verif($ref !== NULL, "source « $al » : " . ($ref ? $ref->id() . " — " . $ref->label() : "notice introuvable ou ambiguë (la saisir dans bibcite, ou relancer avec REFS=$al=<numéro>)"));
  if ($ref) { $refs[$al] = (int) $ref->id(); }
}
$vocab_de = function ($bundle, $champ) { $i = FieldConfig::loadByName('node', $bundle, $champ); $t = $i ? array_keys($i->getSetting('handler_settings')['target_bundles'] ?? []) : []; return $t ? $t[0] : NULL; };
$premier = function ($vid, $nom) use ($termes) { $r = $termes->loadByProperties(['vid' => $vid, 'name' => $nom]); return $r ? (int) key($r) : 0; };
$vid_langue = $vocab_de('common_names', 'field_language_taxo_vern');
$langues = []; $connues = [];
if ($vid_langue) { foreach ($termes->loadByProperties(['vid' => $vid_langue]) as $t) { $connues[] = $t->label(); $langues[mb_strtolower($t->label())] = (int) $t->id(); } }
foreach (array_unique(array_map(function ($r) { return (string) ($r['nom']['langue'] ?? ''); }, $retenus)) as $lg) {
  $verif(isset($langues[mb_strtolower($lg)]), "langue « $lg » dans le vocabulaire « $vid_langue » : " . (isset($langues[mb_strtolower($lg)]) ? $langues[mb_strtolower($lg)] : "absente (existantes : " . implode(', ', array_slice($connues, 0, 15)) . ")"));
}
$types = []; $types_source = [];
foreach ($retenus as $r) { foreach ($r['attestations'] ?? [] as $a) {
  $t = $a['type'] ?? ''; $ts = $a['type_source'] ?? 'Publication scientifique';
  if ($t !== '' && !isset($types[$t])) { $types[$t] = $premier('attestation_type', $t); $verif($types[$t] > 0, "type d'attestation « $t » : " . ($types[$t] ?: 'absent du vocabulaire')); }
  if (!isset($types_source[$ts])) { $types_source[$ts] = $premier('attestation_type_source', $ts); $verif($types_source[$ts] > 0, "type de source « $ts » : " . ($types_source[$ts] ?: 'absent du vocabulaire')); }
} }
$vid_lieu = $vocab_de('attestation', 'field_att_lieu');
$defs = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('node');
$permises = function ($champ) use ($defs) { return isset($defs[$champ]) ? array_keys((array) $defs[$champ]->getSetting('allowed_values')) : NULL; };
$verif(isset($defs['field_att_valeur'], $defs['field_att_locateur'], $defs['field_att_statut'], $defs['field_att_concept_taxo']), "champs d'attestation présents (valeur, locateur, statut, concept)");
$statuts_ok = $permises('field_att_statut') ?: []; $concepts_ok = $permises('field_att_concept_taxo') ?: []; $portees_ok = $permises('field_nom_portee') ?: [];
foreach ($retenus as $cle => $r) {
  foreach ($r['attestations'] ?? [] as $k => $a) {
    if (!empty($a['statut']) && !in_array($a['statut'], $statuts_ok, TRUE)) { $erreur("« $cle » attestation " . ($k + 1) . " : statut « {$a['statut']} » non permis (permis : " . implode(', ', $statuts_ok) . ")"); }
    if (!empty($a['concept']) && !in_array($a['concept'], $concepts_ok, TRUE)) { $erreur("« $cle » attestation " . ($k + 1) . " : concept « {$a['concept']} » non permis (permis : " . implode(', ', $concepts_ok) . ")"); }
  }
  if (!empty($r['nom']['portee']) && !in_array($r['nom']['portee'], $portees_ok, TRUE)) { $erreur("« $cle » : portée « {$r['nom']['portee']} » non permise (permise : " . implode(', ', $portees_ok) . ")"); }
}
$type_valeur = isset($defs['field_att_valeur']) ? $defs['field_att_valeur']->getType() : 'string_long';
$db = \Drupal::database();
$lc_noms = $db->query("SELECT langcode FROM {node_field_data} WHERE type = :t AND default_langcode = 1 ORDER BY nid LIMIT 1", [':t' => 'common_names'])->fetchField() ?: 'en';
$lc_att = $db->query("SELECT langcode FROM {node_field_data} WHERE type = :t AND default_langcode = 1 ORDER BY nid LIMIT 1", [':t' => 'attestation'])->fetchField() ?: 'en';
echo "  langue des contenus : noms « $lc_noms », attestations « $lc_att » (reprise des contenus existants)\n";
foreach ($retenus as $cle => $r) {
  $lg = mb_strtolower((string) ($r['nom']['langue'] ?? ''));
  $existe = isset($langues[$lg]) ? $noeuds->getQuery()->accessCheck(FALSE)->condition('type', 'common_names')->condition('title', $r['nom']['titre'] ?? '')->condition('field_language_taxo_vern', $langues[$lg])->execute() : [];
  if ($existe) { $erreur("« {$r['nom']['titre']} » existe déjà comme nom en {$r['nom']['langue']} (nœud " . implode(',', $existe) . ") : doublon"); }
}

// ===================== CONSTRUCTION EN MÉMOIRE =====================
$valeur_champ = function ($texte) use ($type_valeur) { return $type_valeur === 'text_long' ? ['value' => $texte, 'format' => 'plain_text'] : ['value' => $texte]; };
$phrase_validation = function ($v) {
  $etat = $v['etat'] ?? 'a_valider';
  $libelles = ['a_valider' => 'à valider', 'valide' => 'validé', 'corrige' => 'corrigé'];
  $s = 'Validation : ' . ($libelles[$etat] ?? $etat);
  if (!empty($v['par'])) { $s .= ' par ' . $v['par']; }
  if (!empty($v['date'])) { $s .= ' le ' . $v['date']; }
  if (!empty($v['commentaire'])) { $s .= ' — ' . $v['commentaire']; }
  return $s . '.';
};
$plantes_de = function ($r, $a) use ($plantes) { $al = $a['plantes'] ?? ($r['nom']['plantes'] ?? []); return array_values(array_filter(array_map(function ($x) use ($plantes) { return $plantes[$x] ?? NULL; }, $al))); };
$construire_nom = function ($r, $parent_nid = NULL) use ($langues, $plantes, $lc_noms) {
  $aliases = $r['nom']['plantes'] ?? [];
  $v = ['type' => 'common_names', 'title' => $r['nom']['titre'], 'status' => 0, 'langcode' => $lc_noms,
    'field_language_taxo_vern' => [['target_id' => $langues[mb_strtolower($r['nom']['langue'])] ?? 0]]];
  if (!empty($r['nom']['ecriture_locale'])) { $v['field_ver_local_scripture'] = $r['nom']['ecriture_locale']; }
  if ($aliases) {
    $v['field_genus_com_entity'] = array_map(function ($al) use ($plantes) { return ['target_id' => $plantes[$al]['nid'] ?? 0]; }, $aliases);
    $v['field_genus_taxo'] = array_map(function ($tid) { return ['target_id' => $tid]; }, array_values(array_unique(array_map(function ($al) use ($plantes) { return $plantes[$al]['tid'] ?? 0; }, $aliases))));
  }
  if (!empty($r['nom']['portee'])) { $v['field_nom_portee'] = $r['nom']['portee']; }
  if ($parent_nid) { $v['field_nom_parent'] = [['target_id' => $parent_nid]]; }
  return Node::create($v);
};
$construire_att = function ($r, $k, $nom_nid = NULL, $avec_concept = TRUE) use ($refs, $types, $types_source, $plantes_de, $valeur_champ, $lc_att, $periode, $jeu, $version, $phrase_validation, $premier, $vid_lieu) {
  $a = $r['attestations'][$k]; $pl = $plantes_de($r, $a); $n_att = count($r['attestations']);
  $titre = $a['type'] . ' — ' . $r['nom']['titre'] . ' (' . $r['nom']['langue'] . ')' . ($n_att > 1 && $pl ? ' → ' . $pl[0]['titre'] : '');
  $note = "Chargement initial $periode (jeu « $jeu », version $version, enregistrement {$r['cle']}). " . $phrase_validation($r['validation'] ?? []) . (empty($a['note_interne']) ? '' : ' ' . $a['note_interne']);
  $v = ['type' => 'attestation', 'title' => $titre, 'status' => 0, 'langcode' => $lc_att,
    'field_att_type' => [['target_id' => $types[$a['type']] ?? 0]], 'field_att_valeur' => $valeur_champ($a['texte']), 'field_att_statut' => $a['statut'],
    'field_att_source' => [['target_id' => $refs[$a['source']] ?? 0]], 'field_att_type_source' => [['target_id' => $types_source[$a['type_source'] ?? 'Publication scientifique'] ?? 0]],
    'field_att_note_interne' => $note];
  if (!empty($a['locateur'])) { $v['field_att_locateur'] = $a['locateur']; }
  if ($pl) { $v['field_att_plante'] = array_map(function ($p) { return ['target_id' => $p['nid']]; }, $pl); }
  if ($avec_concept && !empty($a['concept'])) { $v['field_att_concept_taxo'] = $a['concept']; }
  if ($nom_nid) { $v['field_att_nom_vernaculaire'] = [['target_id' => $nom_nid]]; }
  if (!empty($a['lieu']) && $vid_lieu && ($tl = $premier($vid_lieu, $a['lieu']))) { $v['field_att_lieu'] = [['target_id' => $tl]]; }
  return Node::create($v);
};
$violations_de = function ($entite) { $m = []; foreach ($entite->validate() as $viol) { $m[] = $viol->getPropertyPath() . ' : ' . $viol->getMessage(); } return $m; };

echo "\nContenus prévus et validation de Drupal (en mémoire, rien n'est enregistré) :\n";
$nb_violations = 0; $nb_noms = 0; $nb_att = 0;
foreach ($ordre as $cle) {
  $r = $retenus[$cle]; $nb_noms++;
  $vn = $violations_de($construire_nom($r)); $lignes = [];
  foreach ($r['attestations'] as $k => $a) { $nb_att++; $vl = $violations_de($construire_att($r, $k)); $lignes[] = $vl; $nb_violations += count($vl); }
  $nb_violations += count($vn);
  $resume = implode('/', array_map(function ($a) { return $a['statut']; }, $r['attestations']));
  echo "  • $cle : {$r['nom']['titre']} (" . $r['nom']['langue'] . ")" . (($r['nom']['parent'] ?? NULL) ? " variante de " . $r['nom']['parent'] : '') . " · plantes : " . (implode(', ', $r['nom']['plantes'] ?? []) ?: 'aucune, portée ' . ($r['nom']['portee'] ?? '?')) . " · " . count($r['attestations']) . " attestation(s) [" . $resume . "] · validation " . ($r['validation']['etat'] ?? 'a_valider') . "\n";
  $viols = array_merge($vn, ...($lignes ?: [[]]));
  echo "      " . ($viols ? count($viols) . " violation(s)" : 'valide') . "\n";
  foreach ($viols as $m) { echo "        - $m\n"; }
}
$verif($nb_violations === 0, "aucune violation de validation sur les $nb_noms nom(s) et $nb_att attestation(s)");

echo "\nCas témoins (doivent violer) :\n";
$a_viole = function ($entite, $classe) { foreach ($entite->validate() as $viol) { if (strpos(get_class($viol->getConstraint()), $classe) !== FALSE) { return TRUE; } } return FALSE; };
$premier_cle = $ordre ? $ordre[0] : NULL;
if ($premier_cle !== NULL) {
  $r0 = $retenus[$premier_cle];
  $t_nom = Node::create(['type' => 'common_names', 'title' => 'témoin', 'langcode' => $lc_noms, 'field_language_taxo_vern' => [['target_id' => $langues[mb_strtolower($r0['nom']['langue'])] ?? 0]]]);
  $verif($a_viole($t_nom, 'NomSansPlante'), "nom sans plante ni portée : viole la règle des noms");
  if ($plantes) { $p1 = reset($plantes); $a0 = $r0['attestations'][0]; $a0['plantes'] = [array_key_first($plantes)]; $r0t = $r0; $r0t['attestations'] = [$a0];
    $t_att = $construire_att($r0t, 0, NULL, FALSE);
    $verif($a_viole($t_att, 'AttestationConcept'), "attestation de plante sans concept : viole la règle des attestations"); }
  else { echo "  - attestation de plante sans concept : non applicable (aucune plante dans ce fichier)\n"; }
}

echo "\nanomalies : $anomalies\n";
if (!$apply) { echo "\nSimulation : rien n'a été enregistré.\n"; return; }

// ===================== APPLICATION =====================
if ($anomalies) { echo "\nERREUR : $anomalies anomalie(s) ; abandon (relire la simulation).\n"; return; }
if (file_exists($fichier)) { echo "\nERREUR : sauvegarde_chargement_noms.json existe déjà ; abandon pour ne pas l'écraser.\n"; return; }
$etat = ['date' => date('c'), 'jeu' => $jeu, 'version' => $version, 'crees' => []];
$ecrire($etat);
echo "\nfichier de restauration écrit : sauvegarde_chargement_noms.json\n";
$nids_noms = [];
foreach ($ordre as $cle) {
  $r = $retenus[$cle]; $parent = $r['nom']['parent'] ?? NULL;
  $n = $construire_nom($r, $parent ? ($nids_noms[$parent] ?? NULL) : NULL);
  if ($v = $violations_de($n)) { echo "  ERREUR : « $cle » invalide : " . implode(' ; ', $v) . "\n  Arrêt : voir la sauvegarde pour ce qui a déjà été créé.\n"; return; }
  $n->save(); $nids_noms[$cle] = (int) $n->id();
  $etat['crees'][$n->id()] = ['titre' => $r['nom']['titre'], 'sorte' => 'nom', 'changed' => (int) $n->getChangedTime()]; $ecrire($etat);
  echo "  nom créé : {$r['nom']['titre']} (nœud " . $n->id() . ", non publié)\n";
}
$nids_att = [];
foreach ($ordre as $cle) {
  $r = $retenus[$cle];
  foreach ($r['attestations'] as $k => $a) {
    $at = $construire_att($r, $k, $nids_noms[$cle]);
    if ($v = $violations_de($at)) { echo "  ERREUR : attestation " . ($k + 1) . " de « $cle » invalide : " . implode(' ; ', $v) . "\n  Arrêt : voir la sauvegarde pour ce qui a déjà été créé.\n"; return; }
    $at->save(); $nids_att[$cle][$k] = (int) $at->id();
    $etat['crees'][$at->id()] = ['titre' => $at->label(), 'sorte' => 'attestation', 'changed' => (int) $at->getChangedTime()]; $ecrire($etat);
    echo "  attestation créée : " . $at->label() . " (nœud " . $at->id() . ", non publiée)\n";
  }
}

// ===================== RELECTURE =====================
echo "\nRelecture des contenus enregistrés :\n";
$ok = 0; $tot = 0;
$lire = function ($libelle, $c) use (&$ok, &$tot) { $tot++; if ($c) { $ok++; echo "  ✓ $libelle\n"; } else { echo "  ✗ $libelle\n"; } };
$ids = function ($champ) { $o = array_map('intval', array_column($champ->getValue(), 'target_id')); sort($o); return $o; };
foreach ($ordre as $cle) {
  $r = $retenus[$cle]; $n = $noeuds->load($nids_noms[$cle]);
  $att_plantes = array_map(function ($al) use ($plantes) { return $plantes[$al]['nid']; }, $r['nom']['plantes'] ?? []); sort($att_plantes);
  $parent = $r['nom']['parent'] ?? NULL;
  $lire("$cle : nom non publié, plantes [" . implode(',', $att_plantes) . "]" . ($parent ? ", parent $parent" : '') . (!empty($r['nom']['portee']) ? ", portée {$r['nom']['portee']}" : ''),
    $n && !$n->isPublished() && $ids($n->get('field_genus_com_entity')) === $att_plantes && (!$parent || (int) $n->get('field_nom_parent')->target_id === $nids_noms[$parent]) && (empty($r['nom']['portee']) || $n->get('field_nom_portee')->value === $r['nom']['portee']));
  foreach ($r['attestations'] as $k => $a) {
    $at = $noeuds->load($nids_att[$cle][$k]);
    $lire("$cle : attestation " . ($k + 1) . " non publiée, statut {$a['statut']}, locateur, lien vers le nom",
      $at && !$at->isPublished() && $at->get('field_att_statut')->value === $a['statut'] && (empty($a['locateur']) || $at->get('field_att_locateur')->value === $a['locateur']) && (int) $at->get('field_att_nom_vernaculaire')->target_id === $nids_noms[$cle]);
  }
}
echo "\nrelecture : $ok/$tot conformes\n";
echo "\nFait. Contenus NON PUBLIÉS, visibles de l'éditeur seul. Les corrections se font maintenant dans l'interface. Penser à : vendor/bin/drush cr\n";
