<?php
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

echo "=== Vocabulaire 'Type d'écriture' ===\n";
if (!Vocabulary::load('type_ecriture')) {
  Vocabulary::create([
    'vid' => 'type_ecriture',
    'name' => "Type d'écriture",
    'description' => "Système d'écriture d'une variante de nom vernaculaire — distinct de la langue (field_language_taxo_vern). Une langue peut avoir plusieurs écritures légitimes (ex. chinois : simplifié/traditionnel/pinyin).",
  ])->save();
  echo "Créé.\n";
} else {
  echo "Déjà existant.\n";
}

$ecritures = ['Hanzi simplifié', 'Hanzi traditionnel', 'Pinyin', 'Kanji', 'Katakana', 'Hiragana', 'Rōmaji', 'Hangul', 'Romanisation coréenne', 'Alphabet thaï'];
foreach ($ecritures as $e) {
  $existant = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'type_ecriture', 'name' => $e]);
  if (!$existant) {
    Term::create(['vid' => 'type_ecriture', 'name' => $e])->save();
    echo "  + $e\n";
  }
}

echo "\n=== Ajout du type 'Écriture vernaculaire' à attestation_type ===\n";
$existant = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'attestation_type', 'name' => 'Écriture vernaculaire']);
if (!$existant) {
  Term::create(['vid' => 'attestation_type', 'name' => 'Écriture vernaculaire'])->save();
  echo "Ajouté.\n";
} else {
  echo "Déjà présent.\n";
}

echo "\n=== Champ field_att_nom_vernaculaire (cible : node, bundle common_names) ===\n";
if (!FieldStorageConfig::loadByName('node', 'field_att_nom_vernaculaire')) {
  FieldStorageConfig::create([
    'field_name' => 'field_att_nom_vernaculaire',
    'entity_type' => 'node',
    'type' => 'entity_reference',
    'settings' => ['target_type' => 'node'],
    'cardinality' => 1,
  ])->save();
  echo "Stockage créé (target_type=node).\n";
}
if (!FieldConfig::loadByName('node', 'attestation', 'field_att_nom_vernaculaire')) {
  FieldConfig::create([
    'field_name' => 'field_att_nom_vernaculaire',
    'entity_type' => 'node',
    'bundle' => 'attestation',
    'label' => 'Nom vernaculaire concerné',
    'required' => FALSE,
    'description' => "Le nom vernaculaire (fiche common_names) dont cette attestation documente une variante d'écriture.",
    'settings' => ['handler_settings' => ['target_bundles' => ['common_names' => 'common_names']]],
  ])->save();
  echo "Champ rattaché à Attestation.\n";
}

echo "\n=== Champ field_att_type_ecriture (cible : taxonomy_term, bundle type_ecriture) ===\n";
if (!FieldStorageConfig::loadByName('node', 'field_att_type_ecriture')) {
  FieldStorageConfig::create([
    'field_name' => 'field_att_type_ecriture',
    'entity_type' => 'node',
    'type' => 'entity_reference',
    'settings' => ['target_type' => 'taxonomy_term'],
    'cardinality' => 1,
  ])->save();
  echo "Stockage créé (target_type=taxonomy_term).\n";
}
if (!FieldConfig::loadByName('node', 'attestation', 'field_att_type_ecriture')) {
  FieldConfig::create([
    'field_name' => 'field_att_type_ecriture',
    'entity_type' => 'node',
    'bundle' => 'attestation',
    'label' => "Type d'écriture",
    'required' => FALSE,
    'description' => "Système d'écriture de cette variante précise (ex. Hanzi traditionnel), distinct de la langue elle-même.",
    'settings' => ['handler_settings' => ['target_bundles' => ['type_ecriture' => 'type_ecriture']]],
  ])->save();
  echo "Champ rattaché à Attestation.\n";
}

// Vérification finale des deux target_type
echo "\n=== Vérification finale ===\n";
$s1 = FieldStorageConfig::loadByName('node', 'field_att_nom_vernaculaire');
$s2 = FieldStorageConfig::loadByName('node', 'field_att_type_ecriture');
echo "field_att_nom_vernaculaire target_type : " . $s1->getSetting('target_type') . " (attendu : node)\n";
echo "field_att_type_ecriture target_type : " . $s2->getSetting('target_type') . " (attendu : taxonomy_term)\n";

\Drupal::service('cache.discovery')->deleteAll();
echo "\nTerminé.\n";
