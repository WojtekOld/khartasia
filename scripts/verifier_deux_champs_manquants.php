<?php

echo "=== Ces deux champs sont-ils JAMAIS remplis sur plante_texte en D11 ? ===\n";
foreach (['field_graphie_locale_ml', 'field_view_pap_plant_ml'] as $champ) {
  $ids = \Drupal::entityQuery('node')
    ->condition('type', 'plante_texte')
    ->condition($champ, '', '<>')
    ->accessCheck(FALSE)->execute();
  echo "$champ : " . count($ids) . " nœuds non vides sur le total plante_texte.\n";
  if ($ids) {
    $exemple = \Drupal\node\Entity\Node::load(reset($ids));
    echo "  Exemple (nid=" . $exemple->id() . ") : " . mb_substr(strip_tags((string) $exemple->get($champ)->value), 0, 150) . "\n";
  }
}

echo "\n=== Contenu D7 de field_class_botan_texte pour nid=22 (candidat pour 'graphie locale' ?) ===\n";
try {
  $pdo = new PDO('mysql:host=db2;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $val = $pdo->query("SELECT field_class_botan_texte_value FROM field_data_field_class_botan_texte WHERE entity_id = 22")->fetchColumn();
  echo strip_tags($val ?: '(vide)') . "\n";
} catch (Exception $e) {
  echo "Erreur : " . $e->getMessage() . "\n";
}

echo "\n=== Total plante_texte en D11, pour contexte ===\n";
$total_pt = \Drupal::entityQuery('node')->condition('type', 'plante_texte')->accessCheck(FALSE)->count()->execute();
echo "Total : $total_pt\n";
