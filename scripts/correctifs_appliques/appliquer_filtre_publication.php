<?php
/**
 * appliquer_filtre_publication.php — fait ne lister que les contenus PUBLIÉS sur les fiches Plante et Papier.
 *
 * Usage, depuis la racine du projet :
 *   ddev exec php appliquer_filtre_publication.php              (simulation : n'écrit rien)
 *   ddev exec php appliquer_filtre_publication.php --appliquer  (écrit, après sauvegarde)
 *
 * Ce qu'il fait : dans web/themes/custom/khartasia_ui/khartasia_ui.theme, il ajoute un filtre
 * « statut = publié » à six requêtes qui chargeaient des contenus sans le vérifier :
 *   fiche Plante (khartasia_ui_preprocess_node_article) : papiers, noms vernaculaires,
 *     attestations (critères morphologiques et géographie), textes multilingues ;
 *   fiche Papier (khartasia_ui_preprocess_node_papers) : variantes connues, noms vernaculaires.
 * Il ne touche à rien d'autre : ni aux chaînes de caractères, ni à l'encodage (traité à part).
 *
 * DÉFINITIONS
 * contenu publié : contenu dont le statut est « publié » ; seul celui-là doit apparaître sur une page
 *   publique. Un brouillon, un contenu de test ou un dossier en relecture ne doit jamais s'afficher.
 * requête filtrée : requête qui joint la table node_field_data et exige n.status = 1.
 * simulation : exécution à blanc. Le script lit le fichier, vérifie qu'il sait où agir et que le résultat
 *   est un PHP valide, affiche ce qu'il ferait, et n'écrit rien.
 * garde : test préalable qui fait REFUSER l'écriture quand quelque chose n'a pas la forme attendue
 *   (fonction absente, requête introuvable ou en double). Un refus ne modifie aucun fichier.
 * idempotent : relancer le script ne change rien (il constate que c'est déjà fait).
 * requête non reconnue : requête sur un contenu que ce script ne sait pas corriger. Il la SIGNALE
 *   sans la modifier ; c'est à vous de la lire. (Faux négatif à éviter : croire qu'elle est sûre.)
 * analyseur PHP (tokenizer) : l'outil de PHP qui découpe le code ; il sert à trouver où une fonction finit.
 * BOM / fin de ligne (EOL) : marque d'octets de début de fichier et saut de ligne (LF ou CRLF) ; conservés.
 */

$appliquer = in_array('--appliquer', $argv ?? [], TRUE);
$theme = 'web/themes/custom/khartasia_ui/khartasia_ui.theme';
$dossier_sauvegardes = '_sauvegardes_locales';

/**
 * Règles : par fonction, des requêtes à filtrer.
 * 'ancien'   : motif (les espaces et sauts de ligne sont libres) de la requête actuelle.
 * 'nouveau'  : lignes SQL de remplacement ; {i} sera remplacé par l'indentation de la requête d'origine.
 * 'marqueur' : texte qui prouve que la requête est déjà corrigée.
 */
$regles = [
  'khartasia_ui_preprocess_node_article' => [
    ['nom' => 'papiers de la plante',
     'ancien' => "SELECT\s+DISTINCT\s+entity_id\s+FROM\s+node__field_genus_pap_entity\s+WHERE\s+field_genus_pap_entity_target_id\s*=\s*:nid\s+AND\s+bundle\s*=\s*'papers'\s+AND\s+deleted\s*=\s*0",
     'nouveau' => ["SELECT DISTINCT f.entity_id", "FROM node__field_genus_pap_entity f", "INNER JOIN node_field_data n ON n.nid = f.entity_id AND n.status = 1", "WHERE f.field_genus_pap_entity_target_id = :nid", "AND f.bundle = 'papers'", "AND f.deleted = 0"],
     'marqueur' => 'FROM node__field_genus_pap_entity f'],
    ['nom' => 'noms vernaculaires de la plante',
     'ancien' => "SELECT\s+DISTINCT\s+entity_id\s+FROM\s+node__field_genus_taxo\s+WHERE\s+field_genus_taxo_target_id\s*=\s*:tid\s+AND\s+bundle\s*=\s*'common_names'\s+AND\s+deleted\s*=\s*0",
     'nouveau' => ["SELECT DISTINCT f.entity_id", "FROM node__field_genus_taxo f", "INNER JOIN node_field_data n ON n.nid = f.entity_id AND n.status = 1", "WHERE f.field_genus_taxo_target_id = :tid", "AND f.bundle = 'common_names'", "AND f.deleted = 0"],
     'marqueur' => 'FROM node__field_genus_taxo f'],
    ['nom' => 'attestations de la plante (critères morphologiques, géographie)',
     'ancien' => "SELECT\s+DISTINCT\s+entity_id\s+FROM\s+node__field_att_plante\s+WHERE\s+field_att_plante_target_id\s*=\s*:nid\s+AND\s+bundle\s*=\s*'attestation'\s+AND\s+deleted\s*=\s*0",
     'nouveau' => ["SELECT DISTINCT f.entity_id", "FROM node__field_att_plante f", "INNER JOIN node_field_data n ON n.nid = f.entity_id AND n.status = 1", "WHERE f.field_att_plante_target_id = :nid", "AND f.bundle = 'attestation'", "AND f.deleted = 0"],
     'marqueur' => 'FROM node__field_att_plante f'],
    ['nom' => 'textes multilingues de la plante',
     'ancien' => "SELECT\s+entity_id\s+FROM\s+node__field_plante_ref\s+WHERE\s+field_plante_ref_target_id\s*=\s*:nid",
     'nouveau' => ["SELECT DISTINCT f.entity_id", "FROM node__field_plante_ref f", "INNER JOIN node_field_data n ON n.nid = f.entity_id AND n.status = 1", "WHERE f.field_plante_ref_target_id = :nid"],
     'marqueur' => 'FROM node__field_plante_ref f'],
  ],
  'khartasia_ui_preprocess_node_papers' => [
    ['nom' => 'variantes connues du papier',
     'ancien' => "SELECT\s+entity_id\s+FROM\s+node__field_variante_de\s+WHERE\s+field_variante_de_target_id\s*=\s*:nid",
     'nouveau' => ["SELECT DISTINCT f.entity_id", "FROM node__field_variante_de f", "INNER JOIN node_field_data n ON n.nid = f.entity_id AND n.status = 1", "WHERE f.field_variante_de_target_id = :nid"],
     'marqueur' => 'FROM node__field_variante_de f'],
    ['nom' => 'noms vernaculaires du papier',
     'ancien' => "SELECT\s+DISTINCT\s+entity_id\s+FROM\s+node__field_genus_taxo\s+WHERE\s+field_genus_taxo_target_id\s+IN\s*\(\\\$placeholders\)\s+AND\s+bundle\s*=\s*'common_names'\s+AND\s+deleted\s*=\s*0",
     'nouveau' => ["SELECT DISTINCT f.entity_id", "FROM node__field_genus_taxo f", "INNER JOIN node_field_data n ON n.nid = f.entity_id AND n.status = 1", "WHERE f.field_genus_taxo_target_id IN (\$placeholders)", "AND f.bundle = 'common_names'", "AND f.deleted = 0"],
     'marqueur' => 'FROM node__field_genus_taxo f'],
  ],
];

function lire($chemin) {
  if (!is_file($chemin)) { echo "ERREUR : fichier introuvable : $chemin (lancez ce script depuis la racine du projet)\n"; exit(1); }
  $c = file_get_contents($chemin);
  $bom = substr($c, 0, 3) === "\xEF\xBB\xBF";
  if ($bom) { $c = substr($c, 3); }
  return [$c, $bom, strpos($c, "\r\n") !== FALSE ? "\r\n" : "\n"];
}
function arreter($msg) { echo "ERREUR : $msg\nAucun fichier n'a été modifié.\n"; exit(1); }

/** Bornes [début, fin) d'une fonction (avec son commentaire), ou NULL. Analyseur PHP : sûr face aux accolades dans les chaînes. */
function trouver_fonction($code, $nom) {
  $tokens = token_get_all($code);
  $offsets = []; $pos = 0;
  foreach ($tokens as $i => $t) { $offsets[$i] = $pos; $pos += strlen(is_array($t) ? $t[1] : $t); }
  $trouvees = []; $n = count($tokens);
  for ($i = 0; $i < $n; $i++) {
    if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) { continue; }
    $j = $i + 1;
    while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) { $j++; }
    if ($j < $n && $tokens[$j] === '&') { $j++; while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) { $j++; } }
    if (!($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING && $tokens[$j][1] === $nom)) { continue; }
    $profondeur = 0; $corps = FALSE; $fin = NULL;
    for ($m = $j; $m < $n; $m++) {
      $t = $tokens[$m];
      $ouvre = $t === '{' || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], TRUE));
      if ($ouvre) { $profondeur++; $corps = TRUE; }
      elseif ($t === '}') { $profondeur--; if ($corps && $profondeur === 0) { $fin = $offsets[$m] + 1; break; } }
    }
    if ($fin === NULL) { return [NULL, NULL, 0]; }
    $trouvees[] = [$offsets[$i], $fin];
  }
  if (!$trouvees) { return NULL; }
  return [$trouvees[0][0], $trouvees[0][1], count($trouvees)];
}

echo "=== Filtre de publication des fiches Plante et Papier — " . ($appliquer ? 'APPLICATION' : 'SIMULATION') . " ===\n";
list($code, $bom, $eol) = lire($theme);
$nouveau_code = $code;
$a_faire = 0; $deja = 0; $non_reconnues = [];

foreach ($regles as $fonction => $liste) {
  $trouve = trouver_fonction($nouveau_code, $fonction);
  if ($trouve === NULL) { arreter("fonction $fonction introuvable dans $theme."); }
  list($debut, $fin, $nombre) = $trouve;
  if ($nombre !== 1 || $debut === NULL) { arreter("la fonction $fonction apparaît $nombre fois (une seule attendue)."); }
  $region = substr($nouveau_code, $debut, $fin - $debut);
  echo "\n$fonction (" . (substr_count($region, "\n") + 1) . " lignes)\n";
  foreach ($liste as $r) {
    if (strpos($region, $r['marqueur']) !== FALSE) { echo "  = déjà filtrée : " . $r['nom'] . "\n"; $deja++; continue; }
    $motif = '/(?P<i>[ \t]*)' . $r['ancien'] . '/';
    $n = preg_match_all($motif, $region, $m);
    if ($n !== 1) { arreter("requête « " . $r['nom'] . " » trouvée $n fois dans $fonction (une seule attendue) ; je ne sais pas la corriger sans risque."); }
    $region = preg_replace_callback($motif, function ($mm) use ($r, $eol) {
      $i = $mm['i'];
      return $i . implode($eol . $i, $r['nouveau']);
    }, $region, 1);
    echo "  + à filtrer : " . $r['nom'] . "\n"; $a_faire++;
  }
  // Requêtes sur des contenus (node__field_*) de cette fonction : filtrées ou non ?
  $nettoyee = $region;
  if (preg_match_all('/FROM\s+(node__field_[a-z0-9_]+)([^"]{0,260})/s', $nettoyee, $mm, PREG_SET_ORDER)) {
    foreach ($mm as $q) { if (strpos($q[2], 'node_field_data n ON n.nid') === FALSE) { $non_reconnues[] = "$fonction : FROM " . $q[1]; } }
  }
  $nouveau_code = substr($nouveau_code, 0, $debut) . $region . substr($nouveau_code, $fin);
}

echo "\nrequêtes à filtrer : $a_faire | déjà filtrées : $deja\n";
if ($non_reconnues) {
  echo "\n⚠ REQUÊTES NON RECONNUES (sur des contenus, sans filtre de publication, laissées telles quelles) — à lire :\n";
  foreach ($non_reconnues as $q) { echo "   - $q\n"; }
}
else { echo "aucune requête sur des contenus restée sans filtre dans ces deux fonctions.\n"; }

if ($a_faire === 0) { echo "\nRien à faire : tout est déjà filtré.\n"; exit(0); }
$tmp = tempnam(sys_get_temp_dir(), 'kh_theme_') . '.php';
file_put_contents($tmp, $nouveau_code);
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $sortie, $retour);
@unlink($tmp);
if ($retour !== 0) { arreter("le .theme obtenu n'a pas une syntaxe PHP valide : " . implode(' ', $sortie)); }
echo "syntaxe PHP du résultat : OK\n";

if (!$appliquer) { echo "\nSimulation : rien n'a été écrit. Relancez avec --appliquer pour appliquer.\n"; exit(0); }
if (!is_dir($dossier_sauvegardes)) { mkdir($dossier_sauvegardes, 0775, TRUE); }
$copie = $dossier_sauvegardes . '/' . basename($theme) . '.avant_filtre_publication_' . date('Ymd_His');
if (!copy($theme, $copie)) { arreter("sauvegarde impossible : $copie"); }
echo "\nsauvegarde : $copie\n";
file_put_contents($theme, ($bom ? "\xEF\xBB\xBF" : '') . $nouveau_code);
echo "écrit : $theme\n\nFait. Vider les caches (ddev drush cr) puis regarder une fiche Plante et une fiche Papier.\n";
