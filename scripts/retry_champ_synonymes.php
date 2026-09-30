<?php
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Item\Field;

// Vider le cache de découverte AVANT toute chose, pour que le processeur
// tout juste créé soit reconnu
\Drupal::service('cache.discovery')->deleteAll();
\Drupal::service('plugin.manager.search_api.processor')->clearCachedDefinitions();
echo "Cache de découverte des plugins vidé.\n";

$index = Index::load('plantes_papiers');

echo "\n=== Processeurs réellement actifs sur l'index ===\n";
foreach ($index->getProcessors() as $id => $p) {
  echo "  - $id\n";
}

echo "\n=== Champs réellement présents sur l'index ===\n";
foreach ($index->getFields() as $id => $f) {
  echo "  - $id\n";
}

$champs = $index->getFields();
if (!isset($champs['khartasia_synonymes_powo'])) {
  echo "\nLe champ manque toujours — nouvelle tentative d'ajout.\n";
  $field = new Field($index, 'khartasia_synonymes_powo');
  $field->setType('text');
  $field->setPropertyPath('khartasia_synonymes_powo');
  $field->setDatasourceId('entity:node');
  $field->setLabel('Synonymes botaniques (POWO)');
  $index->addField($field);
  $index->save();

  $index_verif = Index::load('plantes_papiers');
  $ok = isset($index_verif->getFields()['khartasia_synonymes_powo']);
  echo $ok ? "CONFIRMÉ cette fois.\n" : "ÉCHEC PERSISTANT — problème plus profond.\n";
} else {
  echo "\nLe champ est déjà là, contrairement à ce que la relecture précédente indiquait.\n";
}
