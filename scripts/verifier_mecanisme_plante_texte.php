<?php

try {
  $pdo = new PDO('mysql:host=db2;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
  echo "ERREUR : " . $e->getMessage() . "\n";
  exit(1);
}

echo "=== Valeurs D7 pour nid=22 (Broussonetia papyrifera) ===\n";
foreach (['field_aire_utilis_ch', 'field_gen_cult_ch', 'field_intr_paper_ch', 'field_mode_prep_ch'] as $champ) {
  $table = "field_data_$champ";
  $val = $pdo->query("SELECT {$champ}_value FROM `$table` WHERE entity_id = 22")->fetchColumn();
  echo "$champ :\n" . mb_substr(strip_tags($val ?: ''), 0, 200) . "\n\n";
}

echo "\n=== Champs du type plante_texte en D11 ===\n";
$champs_pt = \Drupal::entityTypeManager()->getStorage('field_config')->loadByProperties(['entity_type' => 'node', 'bundle' => 'plante_texte']);
foreach ($champs_pt as $fc) {
  echo "  " . $fc->getName() . " : " . $fc->getLabel() . "\n";
}

echo "\n=== Nœuds plante_texte liés à Broussonetia papyrifera (nid=22) en D11 ===\n";
$db = \Drupal::database();
// Chercher un champ de référence vers la plante
$ids = $db->query("
  SELECT entity_id FROM node__field_plante_ref
  WHERE field_plante_ref_target_id = 22
")->fetchCol();

if (!$ids) {
  echo "Aucun trouvé via field_plante_ref — recherche du nom de champ exact.\n";
} else {
  foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
    echo "nid={$n->id()} : {$n->label()} (langue: " . ($n->hasField('field_langue_texte') && !$n->get('field_langue_texte')->isEmpty() ? $n->get('field_langue_texte')->entity->label() : '?') . ")\n";
    foreach ($n->getFields() as $fname => $field) {
      if (str_starts_with($fname, 'field_') && !$field->isEmpty() && $fname != 'field_plante_ref' && $fname != 'field_langue_texte') {
        $v = $field->value ?? ($field->entity ? $field->entity->label() : '?');
        echo "  $fname : " . mb_substr(strip_tags((string) $v), 0, 150) . "\n";
      }
    }
  }
}
