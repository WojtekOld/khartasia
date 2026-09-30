<?php
$index = \Drupal::entityTypeManager()->getStorage('search_api_index')->load('plantes_papiers');
$resultats = $index->query()->keys('Papyrius papyrifera')->execute();
echo "Nombre de résultats : " . count($resultats->getResultItems()) . "\n";
foreach ($resultats->getResultItems() as $item) {
  echo "  " . $item->getId() . "\n";
}
