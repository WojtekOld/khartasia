<?php
$corrections = [
  'field_att_type' => [
    'usage' => 'Usage', 'geographie' => 'Géographie', 'composition' => 'Composition',
    'circuit' => 'Circuit commercial', 'degradation' => 'Dégradation matérielle',
    'critere_morphologique' => 'Critère morphologique',
  ],
  'field_att_type_source' => [
    'analyse' => 'Analyse (laboratoire, microscopie...)', 'texte_historique' => 'Texte historique',
    'temoignage' => 'Témoignage d\'artisan', 'enquete_terrain' => 'Enquête de terrain',
    'expertise' => 'Expertise professionnelle',
  ],
  'field_att_statut' => [
    'atteste' => 'Attesté', 'probable' => 'Probable', 'hypothetique' => 'Hypothétique',
    'refute' => 'Réfuté', 'inconnu' => 'Inconnu / à vérifier',
  ],
  'field_att_relation_ecart' => [
    'convergence' => 'Convergence', 'divergence' => 'Divergence',
    'contradiction' => 'Contradiction', 'silence' => 'Silence (absence attendue)',
  ],
];

foreach ($corrections as $name => $valeurs) {
  $storage = \Drupal\field\Entity\FieldStorageConfig::loadByName('node', $name);
  if (!$storage) {
    echo "$name : stockage introuvable\n";
    continue;
  }
  $storage->setSetting('allowed_values', $valeurs);
  $storage->save();
  echo "$name : " . count($valeurs) . " valeurs déplacées vers le stockage\n";
}

\Drupal::service('cache.discovery')->deleteAll();
echo "\nTerminé.\n";
