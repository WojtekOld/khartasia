<?php
/**
 * lot2_termes.php — crée les TERMES du lot 2 (les termes ne sont PAS de la
 * configuration : ils ne passent pas par « drush cex », il faut donc rejouer
 * ce script en production).
 *
 * Usage (SIMULATION par défaut, n'écrit rien) :
 *   ddev drush php:script scripts/lot2/lot2_termes.php
 * Application :
 *   ddev drush php:script scripts/lot2/lot2_termes.php -- apply
 *
 * PRÉREQUIS : lot2_config_locale.php appliqué (les vocabulaires doivent exister).
 * Idempotent : un terme déjà présent (même nom, même vocabulaire) est ignoré.
 *
 * DÉFINITIONS
 *  - Terme : une valeur d'un vocabulaire de taxonomie (ex. « Colorant »).
 *  - Idempotent : relancer le script ne crée pas de doublons.
 *  - Description du terme : texte d'aide HTML affiché à l'utilisateur ; ici
 *    construit depuis fonction_intrant.json (définition, exemples,
 *    « à distinguer de », précautions).
 */

$appliquer = in_array('apply', $_SERVER['argv'] ?? [], TRUE);
echo $appliquer ? "MODE : APPLICATION\n\n" : "MODE : SIMULATION (ajouter « apply » pour écrire)\n\n";

$etm = \Drupal::entityTypeManager();
$stockage = $etm->getStorage('taxonomy_term');

// Format de texte : filtered_html, sinon basic_html, sinon plain_text.
$format = 'plain_text';
foreach (['filtered_html', 'basic_html'] as $f) {
  if ($etm->getStorage('filter_format')->load($f)) { $format = $f; break; }
}
echo "Format des descriptions : $format\n\n";

function k_terme(string $vid, string $nom, ?int $parent_tid, string $desc, string $format, bool $appliquer, $stockage): ?int {
  $ids = $stockage->getQuery()->accessCheck(FALSE)
    ->condition('vid', $vid)->condition('name', $nom)->execute();
  if ($ids) {
    $tid = (int) reset($ids);
    // Mise à jour de la description si elle a changé dans le JSON (nom et parent inchangés).
    $t = $stockage->load($tid);
    $actuelle = (string) ($t->get('description')->value ?? '');
    if ($desc !== '' && $actuelle !== $desc) {
      echo "  ~ décrire [$vid] $nom (description mise à jour)\n";
      if ($appliquer) { $t->set('description', ['value' => $desc, 'format' => $format])->save(); }
    }
    else {
      echo "  = existe  [$vid] $nom\n";
    }
    return $tid;
  }
  echo "  + créer   [$vid] $nom" . ($parent_tid ? " (parent tid $parent_tid)" : '') . "\n";
  if (!$appliquer) { return NULL; }
  $v = ['vid' => $vid, 'name' => $nom, 'description' => ['value' => $desc, 'format' => $format]];
  if ($parent_tid) { $v['parent'] = [$parent_tid]; }
  $t = $stockage->create($v);
  $t->save();
  return (int) $t->id();
}

$existe = fn($vid) => (bool) $etm->getStorage('taxonomy_vocabulary')->load($vid);

// --- 1. fonction_intrant ----------------------------------------------------
echo "== fonction_intrant ==\n";
if (!$existe('fonction_intrant')) {
  echo "  ! vocabulaire absent : lancer d'abord lot2_config_locale.php apply\n";
}
else {
  $json = json_decode(file_get_contents(__DIR__ . '/fonction_intrant.json'), TRUE);
  $li = fn(array $a) => $a ? '<ul>' . implode('', array_map(fn($x) => '<li>' . htmlspecialchars($x) . '</li>', $a)) . '</ul>' : '';
  foreach ($json['termes'] as $t) {
    $h = $t['definition'] ? '<p><strong>' . htmlspecialchars($t['court']) . '</strong></p><p>' . htmlspecialchars($t['definition']) . '</p>' : '';
    if ($t['exemples'])    { $h .= '<p><em>Exemples</em></p>' . $li($t['exemples']); }
    if ($t['distinguer'])  { $h .= '<p><em>À distinguer de</em></p>' . $li($t['distinguer']); }
    if ($t['precautions']) { $h .= '<p><em>Précautions</em></p>' . $li($t['precautions']); }
    k_terme('fonction_intrant', $t['nom'], NULL, $h, $format, $appliquer, $stockage);
  }
}

// --- 2. aire_culturelle -----------------------------------------------------
// Aire culturelle : zone définie par la culture papetière, pas par les
// frontières d'État actuelles. Hiérarchie à deux niveaux.
echo "\n== aire_culturelle ==\n";
if (!$existe('aire_culturelle')) {
  echo "  ! vocabulaire absent : lancer d'abord lot2_config_locale.php apply\n";
}
else {
  $arbre = [
    'Tibet culturel' => ['Amdo', 'Kham', 'Ü-Tsang'],
    'Himalaya népalais' => [],
    'Bhoutan' => [],
    'Chine des plaines' => [],
    'Sichuan-Chongqing' => [],
    'Asie du Nord-Est' => ['Japon', 'Corée'],
    'Asie du Sud-Est continentale' => ['Thaïlande', 'Lanna'],
  ];
  foreach ($arbre as $parent => $enfants) {
    $ptid = k_terme('aire_culturelle', $parent, NULL, '', $format, $appliquer, $stockage);
    foreach ($enfants as $e) {
      k_terme('aire_culturelle', $e, $ptid, '', $format, $appliquer, $stockage);
    }
  }
}
echo "\nFin.\n";
