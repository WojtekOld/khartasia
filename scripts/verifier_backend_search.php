<?php
$index = \Drupal\search_api\Entity\Index::load('plantes_papiers');
if (!$index) {
  echo "Index introuvable.\n";
  exit;
}

$server = $index->getServerInstance();
echo "=== Serveur ===\n";
echo "ID : " . $server->id() . "\n";
echo "Label : " . $server->label() . "\n";

$backend = $server->getBackend();
echo "Backend (classe) : " . get_class($backend) . "\n";
echo "Backend ID : " . $backend->getPluginId() . "\n";

echo "\n=== Champs actuellement indexés sur cet index ===\n";
foreach ($index->getFields() as $field_id => $field) {
  echo "  - $field_id : " . $field->getLabel() . " [" . $field->getType() . "] (source: " . $field->getDatasourceId() . ")\n";
}

echo "\n=== Datasources de l'index (quels types de contenu sont indexés) ===\n";
foreach ($index->getDatasources() as $ds_id => $ds) {
  echo "  - $ds_id\n";
}
