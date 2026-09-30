<?php

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;

// === Sécurité : vérifier qu'aucune donnée réelle ne sera perdue ===
$ids = \Drupal::entityQuery('node')->condition('type', 'attestation')->accessCheck(FALSE)->execute();
if ($ids) {
  echo "ATTENTION : " . count($ids) . " nœud(s) Attestation existe(nt) déjà.\n";
  foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
    $type = $n->hasField('field_att_type') && !$n->get('field_att_type')->isEmpty() ? $n->get('field_att_type')->value : '(vide)';
    $src = $n->hasField('field_att_type_source') && !$n->get('field_att_type_source')->isEmpty() ? $n->get('field_att_type_source')->value : '(vide)';
    echo "  nid=" . $n->id() . " type=$type source=$src\n";
  }
  echo "\nCes valeurs seront perdues si tu continues (elles ne sont pas migrées automatiquement).\n";
  echo "Taper CONTINUER pour poursuivre quand même, autre chose pour annuler : ";
  if (trim(fgets(STDIN)) !== 'CONTINUER') {
    echo "Annulé.\n";
    exit(0);
  }
} else {
  echo "Aucun nœud Attestation existant — conversion sans risque de perte.\n\n";
}

// === Étape 1 : créer les deux vocabulaires ===
$vocabs = [
  'attestation_type' => [
    'name' => 'Type d\'attestation',
    'terms' => ['Usage', 'Géographie', 'Composition', 'Circuit commercial', 'Dégradation matérielle', 'Critère morphologique'],
  ],
  'attestation_type_source' => [
    'name' => 'Type de source',
    'terms' => ['Analyse (laboratoire, microscopie...)', 'Texte historique', 'Témoignage d\'artisan', 'Enquête de terrain', 'Expertise professionnelle'],
  ],
];

foreach ($vocabs as $vid => $def) {
  if (!Vocabulary::load($vid)) {
    Vocabulary::create(['vid' => $vid, 'name' => $def['name']])->save();
    echo "Vocabulaire créé : $vid\n";
  }
  foreach ($def['terms'] as $t) {
    $existing = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $t]);
    if (!$existing) {
      Term::create(['vid' => $vid, 'name' => $t])->save();
    }
  }
  echo "  " . count($def['terms']) . " terme(s) amorcé(s)\n";
}

// === Étape 2 : supprimer les deux anciens champs liste ===
foreach (['field_att_type', 'field_att_type_source'] as $name) {
  $config = FieldConfig::loadByName('node', 'attestation', $name);
  if ($config) {
    $config->delete();
  }
  $storage = FieldStorageConfig::loadByName('node', $name);
  if ($storage) {
    $storage->delete();
  }
}
// Purge immédiate pour libérer le nom du champ tout de suite
field_purge_batch(50);
echo "\nAnciens champs liste supprimés et purgés.\n";

// === Étape 3 : recréer les deux champs en entity_reference ===
$nouveaux_champs = [
  'field_att_type' => ['vid' => 'attestation_type', 'label' => 'Type d\'attestation',
    'desc' => 'Le grand axe concerné : usage, géographie, composition, circuit commercial, dégradation matérielle, ou critère morphologique. Vocabulaire extensible depuis l\'interface.'],
  'field_att_type_source' => ['vid' => 'attestation_type_source', 'label' => 'Type de source',
    'desc' => 'Nature de la source de cette affirmation. Vocabulaire extensible depuis l\'interface.'],
];

foreach ($nouveaux_champs as $name => $def) {
  FieldStorageConfig::create([
    'field_name' => $name, 'entity_type' => 'node', 'type' => 'entity_reference',
    'settings' => ['target_type' => 'taxonomy_term'], 'cardinality' => 1,
  ])->save();

  FieldConfig::create([
    'field_name' => $name, 'entity_type' => 'node', 'bundle' => 'attestation',
    'label' => $def['label'], 'description' => $def['desc'], 'required' => FALSE,
    'settings' => ['handler_settings' => ['target_bundles' => [$def['vid'] => $def['vid']]]],
  ])->save();
  echo "Champ recréé : $name (référence vers $def[vid])\n";
}

// === Étape 4 : remettre les deux champs dans les affichages ===
$form_display = EntityFormDisplay::load('node.attestation.default');
$view_display = EntityViewDisplay::load('node.attestation.default');

foreach (array_keys($nouveaux_champs) as $i => $name) {
  $form_display->setComponent($name, [
    'type' => 'entity_reference_autocomplete', 'weight' => $i, 'region' => 'content',
    'settings' => ['match_operator' => 'CONTAINS', 'match_limit' => 10, 'size' => 60, 'placeholder' => ''],
  ]);
  $view_display->setComponent($name, [
    'type' => 'entity_reference_label', 'label' => 'above', 'weight' => $i, 'region' => 'content',
    'settings' => ['link' => TRUE],
  ]);
}
$form_display->save();
$view_display->save();

\Drupal::service('cache.discovery')->deleteAll();
echo "\n=== TERMINÉ ===\n";
echo "Recharge /node/add/attestation pour vérifier.\n";
