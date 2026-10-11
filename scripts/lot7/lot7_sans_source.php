<?php
/**
 * lot7_sans_source.php — attestations « attesté » SANS SOURCE.
 *
 * DÉFINITIONS
 *  - Attestation « attesté sans source » : attestation dont le statut est « atteste » et dont
 *    le champ source (field_att_source) est vide. Un énoncé « attesté » affirme que la source le
 *    dit ; sans source, l'affirmation n'est pas vérifiable.
 *  - Rétrograder : passer le statut de « atteste » à « inconnu » (affiché « à vérifier » sur la
 *    fiche publique). Aucune autre donnée n'est touchée.
 *  - Fichier de restauration : JSON écrit AVANT toute modification, qui contient pour chaque
 *    attestation son statut, sa source et le rôle de la source d'origine. Le mode « restaurer »
 *    les remet tels quels.
 *  - Motif source_absente : posé automatiquement par EntityReviewCalculator à l'enregistrement
 *    d'une attestation de papier sans source ; ce script n'y touche pas.
 *
 * DEUX VOIES (combinables)
 *  1. Rattacher la source retrouvée (voie à privilégier : elle règle le fond). Un fichier CSV
 *     « nid;source_id;role » indique, POUR CHAQUE ATTESTATION, la référence bibcite et le rôle de
 *     la source (atteste_directement, reference_generique, contexte_historique ou contredit ;
 *     facultatif). Raccourci : une ligne SANS nid mais avec papier_nid sert de valeur par défaut
 *     à toutes les attestations restantes de ce papier. Un papier à plusieurs sources (ex. Liansi)
 *     se règle donc attestation par attestation.
 *  Les chemins relatifs sont lus depuis la RACINE DU PROJET (le dossier qui contient web/).
 *  2. Rétrograder les attestations qui restent sans source (option « demote »).
 *
 * USAGE (toutes les options sont après « -- » ; SIMULATION par défaut)
 *   drush php:script scripts/lot7/lot7_sans_source.php
 *       → liste les attestations concernées, groupées par papier (lecture seule).
 *   drush php:script scripts/lot7/lot7_sans_source.php -- modele=/chemin/modele.csv
 *       → écrit un CSV à remplir (une ligne par papier concerné).
 *   drush php:script scripts/lot7/lot7_sans_source.php -- sources=/chemin/sources.csv demote
 *       → simulation de ce qui serait fait.
 *   drush php:script scripts/lot7/lot7_sans_source.php -- sources=… demote apply sortie=/chemin/restauration.json
 *       → applique (le fichier de restauration est écrit d'abord).
 *   drush php:script scripts/lot7/lot7_sans_source.php -- restaurer=/chemin/restauration.json apply
 *       → remet l'état d'origine.
 *
 * Chaque attestation est validée (validate()) avant l'enregistrement ; celles qui échouent sont
 * signalées et laissées intactes. Aucune nouvelle révision n'est créée.
 */

use Drupal\node\Entity\Node;

// ── Chemins : relatifs à la racine du projet (drush se place dans web/) ─────
$racine = dirname(DRUPAL_ROOT);
$chemin = fn(string $p) => (str_starts_with($p, '/') || preg_match('/^[A-Za-z]:[\\\/]/', $p)) ? $p : $racine . '/' . $p;

// ── Arguments ─────────────────────────────────────────────────────────────
$argv_ = $_SERVER['argv'] ?? [];
$appliquer = in_array('apply', $argv_, TRUE);
$retrograder = in_array('demote', $argv_, TRUE);
$opt = [];
foreach ($argv_ as $a) {
  if (preg_match('/^(sources|sortie|modele|restaurer)=(.+)$/', $a, $m)) {
    $opt[$m[1]] = $chemin($m[2]);
  }
}
echo $appliquer ? "MODE : APPLICATION\n\n" : "MODE : SIMULATION (ajouter « apply » pour écrire)\n\n";

$etm = \Drupal::entityTypeManager();
$ns = $etm->getStorage('node');
$roles_ok = ['atteste_directement', 'reference_generique', 'contexte_historique', 'contredit'];

$valeur = fn(Node $n, string $f) => $n->hasField($f) && !$n->get($f)->isEmpty() ? (string) $n->get($f)->value : '';
$cible = fn(Node $n, string $f) => $n->hasField($f) && !$n->get($f)->isEmpty() ? (int) $n->get($f)->target_id : 0;

// ── Mode restauration ─────────────────────────────────────────────────────
if (isset($opt['restaurer'])) {
  if (!is_file($opt['restaurer'])) { echo "Fichier introuvable : {$opt['restaurer']}\n"; return; }
  $data = json_decode(file_get_contents($opt['restaurer']), TRUE);
  $n_ok = 0; $n_ko = 0;
  foreach ($data['attestations'] ?? [] as $l) {
    $n = $ns->load($l['nid']);
    if (!$n) { echo "  ! nid {$l['nid']} introuvable\n"; $n_ko++; continue; }
    echo "  " . ($appliquer ? "restaure" : "restaurerait") . " nid {$l['nid']} : statut={$l['statut']} source=" . ($l['source'] ?: '∅') . " rôle=" . ($l['role'] ?: '∅') . "\n";
    if ($appliquer) {
      $n->set('field_att_statut', $l['statut']);
      $n->set('field_att_source', $l['source'] ? [['target_id' => $l['source']]] : []);
      if ($n->hasField('field_att_role_source')) { $n->set('field_att_role_source', $l['role'] ?: NULL); }
      $n->setNewRevision(FALSE);
      $n->save();
    }
    $n_ok++;
  }
  echo "\n$n_ok attestation(s) " . ($appliquer ? "restaurée(s)" : "à restaurer") . ", $n_ko introuvable(s).\nFin.\n";
  return;
}

// ── Sélection : « attesté » sans source ───────────────────────────────────
$ids = $ns->getQuery()->accessCheck(FALSE)
  ->condition('type', 'attestation')
  ->condition('field_att_statut', 'atteste')
  ->notExists('field_att_source')
  ->sort('nid')->execute();
$noeuds = $ns->loadMultiple($ids);
echo count($noeuds) . " attestation(s) « attesté » sans source.\n\n";
if (!$noeuds) { echo "Rien à faire.\nFin.\n"; return; }

$par_papier = [];
foreach ($noeuds as $n) {
  $par_papier[$cible($n, 'field_att_papier')][] = $n;
}
ksort($par_papier);

// Sources déjà utilisées par les AUTRES attestations d'un papier (indice pour retrouver la source).
$sources_du_papier = function (int $papier_nid) use ($ns, $etm, $cible) {
  if (!$papier_nid) { return []; }
  $q = $ns->getQuery()->accessCheck(FALSE)->condition('type', 'attestation')
    ->condition('field_att_papier', $papier_nid)->exists('field_att_source')->execute();
  $out = [];
  foreach ($ns->loadMultiple($q) as $a) {
    $sid = $cible($a, 'field_att_source');
    if ($sid && ($r = $etm->getStorage('bibcite_reference')->load($sid))) {
      $out[$sid] = "#$sid " . mb_substr((string) $r->label(), 0, 70);
    }
  }
  return array_values($out);
};

$etiquette_papier = function (int $papier_nid) use ($ns) {
  if (!$papier_nid) { return '(sans papier)'; }
  $p = $ns->load($papier_nid);
  return $p ? $p->label() : "papier #$papier_nid";
};

// ── Mode modèle CSV : une ligne par attestation ───────────────────────────
if (isset($opt['modele'])) {
  $fh = @fopen($opt['modele'], 'w');
  if (!$fh) { echo "Impossible d'écrire : {$opt['modele']} (le dossier existe-t-il ?)\n"; return; }
  fwrite($fh, "\xEF\xBB\xBF");
  fputcsv($fh, ['nid', 'papier_nid', 'papier', 'type', 'publiee', 'titre', 'enonce', 'source_id', 'role', 'indice_sources_deja_sur_ce_papier'], ';');
  foreach ($par_papier as $pid => $liste) {
    $indice = implode(' | ', $sources_du_papier($pid));
    foreach ($liste as $n) {
      $type = $n->hasField('field_att_type') && !$n->get('field_att_type')->isEmpty() ? $n->get('field_att_type')->entity?->label() : '';
      $enonce = '';
      foreach (['field_att_valeur', 'field_att_enonce'] as $champ) {
        if ($n->hasField($champ) && !$n->get($champ)->isEmpty()) { $enonce = mb_substr((string) $n->get($champ)->value, 0, 200); break; }
      }
      fputcsv($fh, [$n->id(), $pid, $etiquette_papier($pid), $type, $n->isPublished() ? 'OUI' : 'non', $n->label(), $enonce, '', '', $indice], ';');
    }
  }
  fclose($fh);
  echo "Modèle écrit : {$opt['modele']} (" . count($noeuds) . " lignes)\nRemplir « source_id » (identifiant bibcite) et, si possible, « role » (" . implode(', ', $roles_ok) . ").\nFin.\n";
  return;
}

// ── Lecture du CSV des sources ────────────────────────────────────────────
$par_nid = [];      // nid => ['source' =>, 'role' =>]
$par_defaut = [];   // papier_nid => idem (lignes sans nid)
if (isset($opt['sources'])) {
  if (!is_file($opt['sources'])) { echo "Fichier introuvable : {$opt['sources']}\n"; return; }
  $fh = fopen($opt['sources'], 'r');
  $entete = fgetcsv($fh, 0, ';');
  if ($entete) { $entete[0] = preg_replace('/^\xEF\xBB\xBF/', '', $entete[0]); }
  while (($row = fgetcsv($fh, 0, ';')) !== FALSE) {
    $l = array_combine($entete, array_pad(array_slice($row, 0, count($entete)), count($entete), ''));
    $nid = (int) trim($l['nid'] ?? '');
    $pid = (int) trim($l['papier_nid'] ?? '');
    $sid = (int) trim($l['source_id'] ?? '');
    $role = trim($l['role'] ?? '');
    if (!$sid || (!$nid && !$pid)) { continue; }
    if ($role !== '' && !in_array($role, $roles_ok, TRUE)) { echo "  ! ligne nid=$nid papier=$pid : rôle « $role » invalide, ignorée\n"; continue; }
    if (!$etm->getStorage('bibcite_reference')->load($sid)) { echo "  ! ligne nid=$nid papier=$pid : source #$sid introuvable, ignorée\n"; continue; }
    if ($nid) { $par_nid[$nid] = ['source' => $sid, 'role' => $role]; }
    else { $par_defaut[$pid] = ['source' => $sid, 'role' => $role]; }
  }
  fclose($fh);
}

// ── Plan ──────────────────────────────────────────────────────────────────
$plan = [];   // nid => ['action' => 'source'|'retrograder'|'rien', ...]
foreach ($par_papier as $pid => $liste) {
  echo "== " . $etiquette_papier($pid) . " (papier #$pid) : " . count($liste) . " attestation(s)\n";
  $indice = $sources_du_papier($pid);
  echo "   sources déjà sur ce papier : " . ($indice ? implode(' | ', $indice) : '(aucune)') . "\n";
  foreach ($liste as $n) {
    $nid = (int) $n->id();
    $type = $n->hasField('field_att_type') && !$n->get('field_att_type')->isEmpty() ? $n->get('field_att_type')->entity?->label() : '?';
    $choix = $par_nid[$nid] ?? $par_defaut[$pid] ?? NULL;
    if ($choix) {
      $plan[$nid] = ['action' => 'source'] + $choix;
      $txt = "→ source #{$choix['source']}" . ($choix['role'] ? " (rôle {$choix['role']})" : " (rôle non précisé)");
    }
    elseif ($retrograder) {
      $plan[$nid] = ['action' => 'retrograder'];
      $txt = "→ statut « atteste » → « inconnu »";
    }
    else {
      $plan[$nid] = ['action' => 'rien'];
      $txt = "→ inchangée";
    }
    echo "   - #$nid [$type] " . ($n->isPublished() ? 'PUBLIÉE ⚠' : 'non publiée') . " « " . mb_substr((string) $n->label(), 0, 60) . " » $txt\n";
  }
  echo "\n";
}

$n_source = count(array_filter($plan, fn($p) => $p['action'] === 'source'));
$n_retro = count(array_filter($plan, fn($p) => $p['action'] === 'retrograder'));
$n_rien = count(array_filter($plan, fn($p) => $p['action'] === 'rien'));
echo "Bilan : $n_source source(s) rattachée(s), $n_retro rétrogradée(s), $n_rien inchangée(s).\n";

if (!$appliquer) { echo "Rien n'a été écrit.\nFin.\n"; return; }
if (!$n_source && !$n_retro) { echo "Rien à écrire.\nFin.\n"; return; }

// ── Fichier de restauration (AVANT toute écriture) ────────────────────────
$sortie = $opt['sortie'] ?? (sys_get_temp_dir() . '/lot7_restauration_' . date('Ymd_His') . '.json');
$sauve = [];
foreach ($plan as $nid => $p) {
  if ($p['action'] === 'rien') { continue; }
  $n = $noeuds[$nid];
  $sauve[] = ['nid' => $nid, 'statut' => $valeur($n, 'field_att_statut'), 'source' => $cible($n, 'field_att_source'), 'role' => $valeur($n, 'field_att_role_source')];
}
if (file_put_contents($sortie, json_encode(['date' => date('c'), 'attestations' => $sauve], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === FALSE) {
  echo "Impossible d'écrire le fichier de restauration ($sortie) : arrêt, rien n'est modifié.\n"; return;
}
echo "Fichier de restauration : $sortie (" . count($sauve) . " attestation(s))\n";

// ── Écriture ──────────────────────────────────────────────────────────────
$ok = 0; $ko = 0;
foreach ($plan as $nid => $p) {
  if ($p['action'] === 'rien') { continue; }
  $n = $noeuds[$nid];
  if ($p['action'] === 'source') {
    $n->set('field_att_source', [['target_id' => $p['source']]]);
    if ($p['role'] !== '' && $n->hasField('field_att_role_source')) { $n->set('field_att_role_source', $p['role']); }
  }
  else {
    $n->set('field_att_statut', 'inconnu');
  }
  $violations = $n->validate();
  if (count($violations)) {
    echo "  ! #$nid non modifiée : " . $violations[0]->getMessage() . "\n";
    $ko++;
    continue;
  }
  $n->setNewRevision(FALSE);
  $n->save();
  $ok++;
}
echo "\n$ok attestation(s) modifiée(s), $ko refusée(s) par les contraintes.\nFin. Conserver le fichier de restauration.\n";
