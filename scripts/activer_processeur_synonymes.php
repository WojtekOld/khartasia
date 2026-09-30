<?php
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Item\Field;

$index = Index::load('plantes_papiers');
if (!$index) {
  echo "ERREUR : index introuvable.\n";
  exit(1);
}

// === 1. Activer le processeur sur l'index ===
$processeurs = $index->getProcessors();
if (!isset($processeurs['khartasia_synonymes_powo'])) {
  $settings = $index->get('processor_settings') ?? [];
  $settings['khartasia_synonymes_powo'] = [
    'plugin_id' => 'khartasia_synonymes_powo',
    'settings' => [],
  ];
  $index->set('processor_settings', $settings);
  echo "Processeur ajouté à l'index.\n";
} else {
  echo "Processeur déjà présent sur l'index.\n";
}

$index->save();

// === 2. Ajouter le champ indexé, mappé sur la propriété du processeur ===
$champs_existants = $index->getFields();
if (!isset($champs_existants['khartasia_synonymes_powo'])) {
  $field = new Field($index, 'khartasia_synonymes_powo');
  $field->setType('text');
  $field->setPropertyPath('khartasia_synonymes_powo');
  $field->setDatasourceId('entity:node');
  $field->setLabel('Synonymes botaniques (POWO)');
  $index->addField($field);
  $index->save();
  echo "Champ ajouté à l'index et rattaché à la recherche plein texte.\n";
} else {
  echo "Champ déjà présent sur l'index.\n";
}

// === 3. Vérification par relecture ===
$index_verif = Index::load('plantes_papiers');
$champs_verif = $index_verif->getFields();
echo "\n=== Vérification ===\n";
echo isset($champs_verif['khartasia_synonymes_powo']) ? "CONFIRMÉ : champ présent après sauvegarde.\n" : "ÉCHEC.\n";

// === 4. Marquer tout l'index pour réindexation ===
$index->reindex();
echo "\nIndex marqué pour réindexation complète.\n";
echo "Lancer ensuite : ddev drush search-api:index (ou l'équivalent selon le module installé)\n";
