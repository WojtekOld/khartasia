<?php
use Drupal\views\Entity\View;

// === 1. Corriger le style de ligne sur glossaire_plantes (toujours en attente) ===
$source = View::load('glossaire_plantes');
if (!$source) {
  echo "ERREUR CRITIQUE : glossaire_plantes introuvable, arrêt.\n";
  exit(1);
}

$display = &$source->getDisplay('default');
echo "Style de ligne actuel sur glossaire_plantes : " . ($display['display_options']['row']['type'] ?? '(non défini)') . "\n";
$display['display_options']['row'] = ['type' => 'fields'];
$display['display_options']['style'] = ['type' => 'default'];
$source->save();
echo "Corrigé à 'fields'.\n\n";

// === 2. Extraire la configuration complète (fonctionnelle) pour cloner ===
$source = View::load('glossaire_plantes'); // recharger après save
$config_source = $source->toArray();

// === 3. Cloner vers noms et papiers, en changeant uniquement bundle/chemin/libellé ===
$clones = [
  'glossaire_noms' => ['bundle' => 'common_names', 'chemin' => 'glossaire/noms-communs', 'label' => 'Glossaire Noms vernaculaires'],
  'glossaire_papiers' => ['bundle' => 'papers', 'chemin' => 'glossaire/papiers', 'label' => 'Glossaire Papiers'],
];

foreach ($clones as $id => $info) {
  if (View::load($id)) {
    View::load($id)->delete();
  }

  $nouvelle_config = $config_source;
  $nouvelle_config['id'] = $id;
  $nouvelle_config['label'] = $info['label'];

  // Ajuster le filtre de bundle (type de contenu)
  if (isset($nouvelle_config['display']['default']['display_options']['filters']['type'])) {
    $nouvelle_config['display']['default']['display_options']['filters']['type']['value'] = [$info['bundle'] => $info['bundle']];
  }

  // Ajuster le chemin de la page
  if (isset($nouvelle_config['display']['page_1']['display_options']['path'])) {
    $nouvelle_config['display']['page_1']['display_options']['path'] = $info['chemin'];
  }

  // Remplacer toute référence au chemin "glossaire/plantes" dans le contenu de l'en-tête, s'il y en a
  foreach (['default', 'page_1'] as $disp_id) {
    if (isset($nouvelle_config['display'][$disp_id]['display_options']['header']['area_text']['content'])) {
      $nouvelle_config['display'][$disp_id]['display_options']['header']['area_text']['content'] = str_replace(
        'glossaire/plantes', $info['chemin'], $nouvelle_config['display'][$disp_id]['display_options']['header']['area_text']['content']
      );
    }
  }

  unset($nouvelle_config['uuid']);

  $nouvelle_vue = View::create($nouvelle_config);
  $nouvelle_vue->save();
  echo "$id créé par clonage, accessible sur /" . $info['chemin'] . "\n";
}

\Drupal::service('cache.discovery')->deleteAll();
echo "\nTerminé.\n";
