<?php
// Table de correspondance Famille => Ordre
$family_to_order = [
  'Acanthaceae'      => 'Lamiales',
  'Actinidiaceae'    => 'Ericales',
  'Amaryllidaceae'   => 'Asparagales',
  'Araceae'          => 'Alismatales',
  'Arecaceae'        => 'Arecales',
  'Asteraceae'       => 'Asterales',
  'Berberidaceae'    => 'Ranunculales',
  'Boraginaceae'     => 'Boraginales',
  'Brassicaceae'     => 'Brassicales',
  'cactaceae'        => 'Caryophyllales',
  'Caesalpiniaceae'  => 'Fabales',
  'Cannabaceae'      => 'Rosales',
  'Celastraceae'     => 'Celastrales',
  'Cyperaceae'       => 'Poales',
  'Euphorbiaceae'    => 'Malpighiales',
  'Fabaceae'         => 'Fabales',
  'Fagaceae'         => 'Fagales',
  'Hydrangeaceae'    => 'Cornales',
  'Juncaceae'        => 'Poales',
  'Lauraceae'        => 'Laurales',
  'Linaceae'         => 'Malpighiales',
  'Malvaceae'        => 'Malvales',
  'Moraceae'         => 'Rosales',
  'Musaceae'         => 'Zingiberales',
  'Myricaceae'       => 'Fagales',
  'Pinaceae'         => 'Pinales',
  'Poaceae'          => 'Poales',
  'Polygonaceae'     => 'Caryophyllales',
  'Rosaceae'         => 'Rosales',
  'Rubiaceae'        => 'Gentianales',
  'Rutaceae'         => 'Sapindales',
  'Salicaceae'       => 'Malpighiales',
  'Schisandraceae'   => 'Austrobaileyales',
  'Sphagnaceae'      => 'Sphagnales',
  'Sterculiaceae'    => 'Malvales',
  'Symplocaceae'     => 'Ericales',
  'Thymelaeaceae'    => 'Malvales',
  'Tiliaceae'        => 'Malvales',
  'Typhaceae'        => 'Poales',
  'Ulmaceae'         => 'Rosales',
  'Urticaceae'       => 'Rosales',
  'Zingiberaceae'    => 'Zingiberales',
  'Zygnemataceae'    => 'Zygnematales',
];

$vid = 'classification_botanique';
$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$ok = 0; $err = 0;

foreach ($family_to_order as $family_name => $order_name) {
  // Trouver le TID de l'ordre
  $order_ids = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', $vid)
    ->condition('name', $order_name)
    ->accessCheck(FALSE)->execute();

  // Trouver le TID de la famille
  $family_ids = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', $vid)
    ->condition('name', $family_name)
    ->accessCheck(FALSE)->execute();

  if (!$order_ids) {
    echo "ORDRE MANQUANT: $order_name\n"; $err++; continue;
  }
  if (!$family_ids) {
    echo "FAMILLE MANQUANTE: $family_name\n"; $err++; continue;
  }

  $order_tid = reset($order_ids);
  $family_tid = reset($family_ids);

  $family_term = $storage->load($family_tid);
  $family_term->set('parent', [$order_tid]);
  $family_term->save();
  echo "OK: $family_name => $order_name\n";
  $ok++;
}

echo "\n--- $ok liens crees, $err erreurs ---\n";
