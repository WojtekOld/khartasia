<?php
/**
 * ajouter_translitteration_gabarit.php — fait afficher la translittération savante sous le titre des fiches de noms vernaculaires.
 *
 * Usage, depuis la racine du projet :
 *   ddev exec php scripts/correctifs_appliques/ajouter_translitteration_gabarit.php              (simulation : n'écrit rien)
 *   ddev exec php scripts/correctifs_appliques/ajouter_translitteration_gabarit.php --appliquer  (écrit, après sauvegarde)
 *
 * Ce qu'il fait : dans templates/node/node--common-names.html.twig, ajoute, juste après la ligne de l'écriture locale, une ligne
 * « <translittération> (translittération) » affichée seulement si le champ field_ver_translitteration est rempli.
 * Sans ce correctif, le champ est enregistré par le chargeur mais jamais montré au lecteur.
 *
 * DÉFINITIONS
 * translittération savante : romanisation conventionnelle d'une forme (Wylie pour le tibétain, pinyin pour le chinois…), distincte de
 *   l'écriture locale (la graphie d'origine) et du titre (la romanisation d'usage).
 * tiret de la balise ({%- if) : supprime l'espace qui précède la balise, pour que le HTML produit soit strictement identique quand le champ est vide.
 * gabarit : fichier .twig qui décrit comment une fiche s'affiche. Un champ absent y vaut « vide » : l'ajout est sans risque si le champ n'existe pas.
 * simulation / garde / idempotent / BOM / fin de ligne (EOL) : voir appliquer_filtre_publication.php.
 */

$appliquer = in_array('--appliquer', $argv ?? [], TRUE);
$gabarit = 'web/themes/custom/khartasia_ui/templates/node/node--common-names.html.twig';
$dossier_sauvegardes = '_sauvegardes_locales';

echo "=== Affichage de la translittération (gabarit des noms) — " . ($appliquer ? 'APPLICATION' : 'SIMULATION') . " ===\n";
if (!is_file($gabarit)) { echo "ERREUR : fichier introuvable : $gabarit (lancez ce script depuis la racine du projet).\nAucun fichier n'a été modifié.\n"; exit(1); }
$c = file_get_contents($gabarit);
$bom = substr($c, 0, 3) === "\xEF\xBB\xBF";
if ($bom) { $c = substr($c, 3); }
$eol = strpos($c, "\r\n") !== FALSE ? "\r\n" : "\n";

if (strpos($c, 'field_ver_translitteration') !== FALSE) { echo "\nDéjà fait : le gabarit affiche déjà la translittération. Rien à faire.\n"; exit(0); }
if (strpos($c, 'plantes_liees') === FALSE) { echo "ERREUR : ce n'est pas le gabarit attendu (il devrait contenir « plantes_liees »).\nAucun fichier n'a été modifié.\n"; exit(1); }

$motif = '/(?P<i>[ \t]*)\{% if node\.field_ver_local_scripture\.value %\}\s*<div class="kh-plante__synonymes">\{\{ node\.field_ver_local_scripture\.value \}\}<\/div>\s*\{% endif %\}/';
$n = preg_match_all($motif, $c, $m);
if ($n !== 1) { echo "ERREUR : le bloc de l'écriture locale est trouvé $n fois (une seule attendue) ; je ne sais pas où insérer sans risque.\nAucun fichier n'a été modifié.\n"; exit(1); }

$ajout = ['{%- if node.field_ver_translitteration.value %}', '  <div class="kh-plante__synonymes">{{ node.field_ver_translitteration.value }} <span class="kh-fibre__notes">(translittération)</span></div>', '{% endif %}'];
$nouveau = preg_replace_callback($motif, function ($mm) use ($ajout, $eol) {
  $i = $mm['i'];
  return $mm[0] . $eol . $i . implode($eol . $i, $ajout);
}, $c, 1);
echo "\nbloc de l'écriture locale trouvé ; 3 lignes à insérer juste après.\n";
if (!$appliquer) { echo "\nSimulation : rien n'a été écrit. Relancez avec --appliquer pour appliquer.\n"; exit(0); }

if (!is_dir($dossier_sauvegardes)) { mkdir($dossier_sauvegardes, 0775, TRUE); }
$copie = $dossier_sauvegardes . '/' . basename($gabarit) . '.avant_translitteration_' . date('Ymd_His');
if (!copy($gabarit, $copie)) { echo "ERREUR : sauvegarde impossible : $copie\nAucun fichier n'a été modifié.\n"; exit(1); }
echo "sauvegarde : $copie\n";
file_put_contents($gabarit, ($bom ? "\xEF\xBB\xBF" : '') . $nouveau);
echo "écrit : $gabarit\n\nFait. Vider les caches (ddev drush cr) puis regarder une fiche de nom.\n";
