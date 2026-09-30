<?php

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;

// Correspondance type de champ -> widget/formatteur, cohérente avec ce
// qui a déjà fonctionné ce soir sur plante_texte.
$widgets = [
  'list_string' => ['type' => 'options_select', 'formatter' => 'list_default'],
  'string_long' => ['type' => 'string_textarea', 'formatter' => 'basic_string'],
  'integer' => ['type' => 'number', 'formatter' => 'number_integer'],
  'boolean' => ['type' => 'boolean_checkbox', 'formatter' => 'boolean'],
  'entity_reference' => ['type' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label'],
];

$champs_ordre = [
  'field_att_type', 'field_att_axe', 'field_att_papier', 'field_att_plante',
  'field_att_valeur', 'field_att_source', 'field_att_type_source', 'field_att_statut',
  'field_att_periode_debut', 'field_att_periode_fin', 'field_att_approx', 'field_att_lieu',
  'field_att_concurrente', 'field_att_relation_ecart', 'field_att_commentaire_ecart',
  'field_att_comparaison_ref', 'field_att_comparaison_note',
];

echo "=== Affichage du formulaire ===\n";
$form_display = EntityFormDisplay::load('node.attestation.default');
if (!$form_display) {
  $form_display = EntityFormDisplay::create([
    'targetEntityType' => 'node', 'bundle' => 'attestation', 'mode' => 'default', 'status' => TRUE,
  ]);
}
$weight = 0;
foreach ($champs_ordre as $name) {
  $field_config = \Drupal\field\Entity\FieldConfig::loadByName('node', 'attestation', $name);
  if (!$field_config) {
    echo "  Champ $name introuvable, ignoré\n";
    continue;
  }
  $type = $field_config->getType();
  $w = $widgets[$type] ?? ['type' => 'string_textfield'];
  $settings = [];
  if ($type === 'entity_reference') {
    $settings = ['match_operator' => 'CONTAINS', 'match_limit' => 10, 'size' => 60, 'placeholder' => ''];
  }
  $form_display->setComponent($name, ['type' => $w['type'], 'weight' => $weight++, 'region' => 'content', 'settings' => $settings]);
}
$form_display->save();
echo "  Enregistré, " . count($champs_ordre) . " champs positionnés.\n\n";

echo "=== Affichage de vue ===\n";
$view_display = EntityViewDisplay::load('node.attestation.default');
if (!$view_display) {
  $view_display = EntityViewDisplay::create([
    'targetEntityType' => 'node', 'bundle' => 'attestation', 'mode' => 'default', 'status' => TRUE,
  ]);
}
$weight = 0;
foreach ($champs_ordre as $name) {
  $field_config = \Drupal\field\Entity\FieldConfig::loadByName('node', 'attestation', $name);
  if (!$field_config) {
    continue;
  }
  $type = $field_config->getType();
  $f = $widgets[$type] ?? ['formatter' => 'string'];
  $settings = [];
  if ($type === 'entity_reference') {
    $settings = ['link' => TRUE];
  }
  $view_display->setComponent($name, ['type' => $f['formatter'], 'label' => 'above', 'weight' => $weight++, 'region' => 'content', 'settings' => $settings]);
}
$view_display->save();
echo "  Enregistré, " . count($champs_ordre) . " champs positionnés.\n\n";

\Drupal::service('cache.discovery')->deleteAll();
echo "=== TERMINÉ ===\n";
echo "Teste maintenant : /node/add/attestation en local.\n";
