<?php
/**
 * reparer_accents.php — répare les accents doublement encodés du thème khartasia_ui.
 *
 * Usage, depuis la racine du projet :
 *   ddev exec php reparer_accents.php              (simulation : n'écrit rien)
 *   ddev exec php reparer_accents.php --appliquer  (écrit, après sauvegarde)
 *
 * Le défaut : dans web/themes/custom/khartasia_ui/khartasia_ui.theme, certains textes ont été
 * enregistrés avec leurs accents « doublement encodés » (« Ã© » au lieu de « é »). Conséquence
 * fonctionnelle : le code compare un libellé de la base (« Critère morphologique ») à une chaîne
 * abîmée (« CritÃ¨re morphologique ») ; la comparaison ne réussit jamais, donc les sections « critères
 * morphologiques » et « statut géographique » des fiches Plante ne s'affichent jamais, et les libellés
 * visibles (« Pays non spÃ©cifiÃ© », boutons de pagination) sont illisibles.
 *
 * ATTENTION — ordre impératif. Cette réparation RÉVEILLE du code qui ne fonctionnait pas : après elle,
 * ces sections afficheront les attestations publiées. Elle ne doit donc venir QU'APRÈS le filtre de
 * publication (appliquer_filtre_publication.php), faute de quoi des attestations non publiées
 * pourraient s'afficher. Le script le vérifie et refuse sinon.
 *
 * DÉFINITIONS
 * doublement encodé (mojibake) : texte UTF-8 lu à tort comme du Windows-1252 puis réenregistré en UTF-8.
 *   « é » (octets C3 A9) devient « Ã© » (C3 83 C2 A9).
 * réparer : refaire le chemin inverse. On ne répare qu'une séquence qui, relue en Windows-1252, forme
 *   des octets UTF-8 VALIDES ; un texte normal n'a pratiquement jamais cette forme.
 * faux positif : séquence saine que le script réparerait à tort. Les tests en vérifient l'absence ; la
 *   simulation liste toute chaîne qu'elle modifierait pour que vous puissiez la contrôler.
 * faux négatif : séquence abîmée que le script ne répare pas. Le contrôle final des libellés attendus
 *   (13 libellés de la base) sert à le détecter pour ce qui compte.
 * élément (token) : unité de code PHP découpée par l'analyseur. Seuls les commentaires et les chaînes de
 *   caractères sont réparés ; le code (noms de fonctions, variables) n'est jamais touché.
 * simulation / garde / idempotent / BOM / fin de ligne : voir appliquer_filtre_publication.php.
 */

$appliquer = in_array('--appliquer', $argv ?? [], TRUE);
$theme = 'web/themes/custom/khartasia_ui/khartasia_ui.theme';
$dossier_sauvegardes = '_sauvegardes_locales';

// Libellés de la base que le code compare ou affiche (relevés dans les vocabulaires attestation_type et attestation_axe).
$libelles_attendus = [
  "'Critère morphologique'", "'Géographie'",
  "'Type de fibre'", "'Longueur'", "'Largeur'", "'Parois cellulaires'", "'Surface de la fibre'",
  "'Extrémités'", "'Stries et dislocations'", "'Cellules associées'", "'Caractéristiques particulières'",
  "'Coloration Herzberg'", "'Coloration Graff C'",
];
// Marqueurs des six requêtes filtrées par appliquer_filtre_publication.php, par fonction.
$marqueurs_filtre = [
  'khartasia_ui_preprocess_node_article' => ['FROM node__field_genus_pap_entity f', 'FROM node__field_genus_taxo f', 'FROM node__field_att_plante f', 'FROM node__field_plante_ref f'],
  'khartasia_ui_preprocess_node_papers' => ['FROM node__field_variante_de f', 'FROM node__field_genus_taxo f'],
];

function lire($chemin) {
  if (!is_file($chemin)) { echo "ERREUR : fichier introuvable : $chemin (lancez ce script depuis la racine du projet)\n"; exit(1); }
  $c = file_get_contents($chemin);
  $bom = substr($c, 0, 3) === "\xEF\xBB\xBF";
  if ($bom) { $c = substr($c, 3); }
  return [$c, $bom];
}
function arreter($msg) { echo "ERREUR : $msg\nAucun fichier n'a été modifié.\n"; exit(1); }

function u_chr($cp) {
  if ($cp < 0x80) { return chr($cp); }
  if ($cp < 0x800) { return chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 0x3F)); }
  if ($cp < 0x10000) { return chr(0xE0 | ($cp >> 12)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F)); }
  return chr(0xF0 | ($cp >> 18)) . chr(0x80 | (($cp >> 12) & 0x3F)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
}

/** Table : caractère Unicode -> octet Windows-1252 (0xA0-0xFF identiques à Latin-1 ; 0x80-0x9F : caractères spéciaux). */
function carte_cp1252() {
  $m = [];
  for ($b = 0xA0; $b <= 0xFF; $b++) { $m[u_chr($b)] = $b; }
  $speciaux = [0x80 => 0x20AC, 0x82 => 0x201A, 0x83 => 0x0192, 0x84 => 0x201E, 0x85 => 0x2026, 0x86 => 0x2020, 0x87 => 0x2021, 0x88 => 0x02C6,
    0x89 => 0x2030, 0x8A => 0x0160, 0x8B => 0x2039, 0x8C => 0x0152, 0x8E => 0x017D, 0x91 => 0x2018, 0x92 => 0x2019, 0x93 => 0x201C, 0x94 => 0x201D,
    0x95 => 0x2022, 0x96 => 0x2013, 0x97 => 0x2014, 0x98 => 0x02DC, 0x99 => 0x2122, 0x9A => 0x0161, 0x9B => 0x203A, 0x9C => 0x0153, 0x9E => 0x017E, 0x9F => 0x0178];
  foreach ($speciaux as $octet => $cp) { $m[u_chr($cp)] = $octet; }
  foreach ([0x81, 0x8D, 0x8F, 0x90, 0x9D] as $octet) { $m[u_chr($octet)] = $octet; }   // contrôles C1, quand le texte les a conservés
  return $m;
}

/** Répare un texte ; $journal reçoit « avant » => « après » et leur nombre. Un texte qui n'est pas de l'UTF-8 valide est laissé tel quel. */
function reparer($texte, $carte, &$journal) {
  $chars = preg_split('//u', $texte, -1, PREG_SPLIT_NO_EMPTY);
  if ($chars === FALSE) { return $texte; }
  $n = count($chars); $sortie = '';
  for ($i = 0; $i < $n; ) {
    $b = $carte[$chars[$i]] ?? NULL; $long = 0;
    if ($b !== NULL) { if ($b >= 0xC2 && $b <= 0xDF) { $long = 2; } elseif ($b >= 0xE0 && $b <= 0xEF) { $long = 3; } elseif ($b >= 0xF0 && $b <= 0xF4) { $long = 4; } }
    if ($long && $i + $long <= $n) {
      $octets = chr($b); $ok = TRUE;
      for ($k = 1; $k < $long; $k++) {
        $bk = $carte[$chars[$i + $k]] ?? NULL;
        if ($bk === NULL || $bk < 0x80 || $bk > 0xBF) { $ok = FALSE; break; }
        $octets .= chr($bk);
      }
      if ($ok && preg_match('//u', $octets) === 1) {
        $cle = implode('', array_slice($chars, $i, $long)) . ' → ' . $octets;
        $journal[$cle] = ($journal[$cle] ?? 0) + 1;
        $sortie .= $octets; $i += $long; continue;
      }
    }
    $sortie .= $chars[$i]; $i++;
  }
  return $sortie;
}

function region_fonction($code, $nom) {
  $tokens = token_get_all($code); $offsets = []; $pos = 0;
  foreach ($tokens as $i => $t) { $offsets[$i] = $pos; $pos += strlen(is_array($t) ? $t[1] : $t); }
  $n = count($tokens);
  for ($i = 0; $i < $n; $i++) {
    if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) { continue; }
    $j = $i + 1;
    while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) { $j++; }
    if ($j < $n && $tokens[$j] === '&') { $j++; while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) { $j++; } }
    if (!($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING && $tokens[$j][1] === $nom)) { continue; }
    $p = 0; $corps = FALSE;
    for ($m = $j; $m < $n; $m++) {
      $t = $tokens[$m];
      if ($t === '{' || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], TRUE))) { $p++; $corps = TRUE; }
      elseif ($t === '}') { $p--; if ($corps && $p === 0) { return substr($code, $offsets[$i], $offsets[$m] + 1 - $offsets[$i]); } }
    }
  }
  return NULL;
}

echo "=== Réparation des accents doublement encodés — " . ($appliquer ? 'APPLICATION' : 'SIMULATION') . " ===\n";
list($code, $bom) = lire($theme);

// --- Garde : le filtre de publication doit être en place AVANT de réveiller ce code ---
echo "\nGarde : filtre de publication en place ?\n";
$manque = [];
foreach ($marqueurs_filtre as $fonction => $marqueurs) {
  $region = region_fonction($code, $fonction);
  if ($region === NULL) { arreter("fonction $fonction introuvable."); }
  foreach ($marqueurs as $m) { if (strpos($region, $m) === FALSE) { $manque[] = "$fonction : $m"; } }
}
if ($manque) {
  echo "  ✗ filtre absent pour :\n"; foreach ($manque as $x) { echo "      - $x\n"; }
  arreter("le filtre de publication n'est pas (entièrement) en place. Lancez d'abord appliquer_filtre_publication.php : sinon cette réparation ferait apparaître des attestations non publiées.");
}
echo "  ✓ les six requêtes sont filtrées.\n";

// --- Réparation, élément par élément ---
$carte = carte_cp1252();
$tokens = token_get_all($code);
$nouveau = ''; $journal_commentaires = []; $journal_chaines = []; $chaines_modifiees = []; $nb_comm = 0; $nb_chaines = 0;
foreach ($tokens as $t) {
  if (!is_array($t)) { $nouveau .= $t; continue; }
  list($id, $texte, $ligne) = $t;
  if (in_array($id, [T_COMMENT, T_DOC_COMMENT], TRUE)) {
    $j = []; $r = reparer($texte, $carte, $j);
    if ($r !== $texte) { $nb_comm++; foreach ($j as $k => $v) { $journal_commentaires[$k] = ($journal_commentaires[$k] ?? 0) + $v; } }
    $nouveau .= $r;
  }
  elseif (in_array($id, [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], TRUE)) {
    $j = []; $r = reparer($texte, $carte, $j);
    if ($r !== $texte) { $nb_chaines++; foreach ($j as $k => $v) { $journal_chaines[$k] = ($journal_chaines[$k] ?? 0) + $v; } $chaines_modifiees[] = [$ligne, $texte, $r]; }
    $nouveau .= $r;
  }
  else { $nouveau .= $texte; }
}

echo "\ncommentaires modifiés : $nb_comm | chaînes de caractères modifiées : $nb_chaines\n";
$tous = $journal_commentaires; foreach ($journal_chaines as $k => $v) { $tous[$k] = ($tous[$k] ?? 0) + $v; }
arsort($tous);
echo "\nremplacements (avant → après : nombre) :\n";
foreach ($tous as $k => $v) { echo "  $k : $v\n"; }
if ($chaines_modifiees) {
  echo "\nCHAÎNES DE CARACTÈRES modifiées (ce sont elles qui changent le comportement ; à lire) :\n";
  $court = function ($s) { $s = trim(preg_replace('/\s+/u', ' ', $s)); return strlen($s) > 95 ? substr($s, 0, 92) . '...' : $s; };
  foreach ($chaines_modifiees as list($ligne, $a, $b)) { echo "  l.$ligne  " . $court($a) . "\n          → " . $court($b) . "\n"; }
}
if (!$tous) { echo "  (aucun : rien à réparer)\n"; echo "\nRien à faire.\n"; exit(0); }

// --- Contrôle : les libellés réparés correspondent-ils à ceux de la base ? ---
echo "\nContrôle des libellés attendus (ceux de la base) dans le résultat :\n";
$absents = [];
foreach ($libelles_attendus as $l) { if (strpos($nouveau, $l) === FALSE) { $absents[] = $l; } }
echo "  libellés présents : " . (count($libelles_attendus) - count($absents)) . "/" . count($libelles_attendus) . "\n";
foreach ($absents as $l) { echo "  ✗ absent : $l\n"; }
if ($absents) { arreter("des libellés attendus manquent après réparation : la réparation ne produit pas ce que la base contient."); }

$tmp = tempnam(sys_get_temp_dir(), 'kh_theme_') . '.php';
file_put_contents($tmp, $nouveau);
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $sortie, $retour);
@unlink($tmp);
if ($retour !== 0) { arreter("le .theme obtenu n'a pas une syntaxe PHP valide : " . implode(' ', $sortie)); }
echo "  syntaxe PHP du résultat : OK\n";
echo "\n⚠ Après cette réparation, les sections « critères morphologiques » et « statut géographique » des fiches Plante\n  afficheront les attestations PUBLIÉES. Vérifier avant de publier les 436 critères du CRCC (aujourd'hui non publiés).\n";

if (!$appliquer) { echo "\nSimulation : rien n'a été écrit. Relancez avec --appliquer pour appliquer.\n"; exit(0); }
if (!is_dir($dossier_sauvegardes)) { mkdir($dossier_sauvegardes, 0775, TRUE); }
$copie = $dossier_sauvegardes . '/' . basename($theme) . '.avant_reparation_accents_' . date('Ymd_His');
if (!copy($theme, $copie)) { arreter("sauvegarde impossible : $copie"); }
echo "\nsauvegarde : $copie\n";
file_put_contents($theme, ($bom ? "\xEF\xBB\xBF" : '') . $nouveau);
echo "écrit : $theme\n\nFait. Vider les caches (ddev drush cr) puis regarder une fiche Plante.\n";
