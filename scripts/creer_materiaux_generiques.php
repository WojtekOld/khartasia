<?php
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

$genres = [
  'Broussonetia' => ['tid' => 2461, 'noms' => ['kōzo (générique)', 'dak (générique)']],
  'Phyllostachys' => ['tid' => 2462, 'noms' => []],
  'Edgeworthia' => ['tid' => 2463, 'noms' => ['mitsumata (générique)']],
  'Wikstroemia' => ['tid' => 2464, 'noms' => []],
];

foreach ($genres as $genre => $info) {
  $titre = "$genre sp. (indéterminé)";

  $existant = \Drupal::entityQuery('node')
    ->condition('type', 'materiau_papetier')
    ->condition('title', $titre)
    ->accessCheck(FALSE)
    ->execute();

  if ($existant) {
    echo "$titre : déjà existant.\n";
    continue;
  }

  // Vérifier que le terme de genre existe bel et bien avant de créer le nœud
  $terme = Term::load($info['tid']);
  if (!$terme || $terme->label() !== $genre) {
    echo "ERREUR : terme de genre pour $genre introuvable ou incohérent (tid={$info['tid']}) — ignoré.\n";
    continue;
  }

  $node = Node::create([
    'type' => 'materiau_papetier',
    'title' => $titre,
    'field_taxon_parent' => ['target_id' => $info['tid']],
    'field_est_generique' => TRUE,
  ]);
  if (!empty($info['noms'])) {
    $node->set('field_nom_vernaculaire', $info['noms']);
  }
  $node->save();
  echo "Créé : $titre (nid=" . $node->id() . "), taxon parent = " . $terme->label() . "\n";
}

echo "\n=== Vérification finale ===\n";
$ids = \Drupal::entityQuery('node')->condition('type', 'materiau_papetier')->accessCheck(FALSE)->execute();
echo "Total materiau_papetier : " . count($ids) . "\n";
