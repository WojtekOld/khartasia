<?php
use Drupal\node\Entity\Node;

$db = \Drupal::database();

echo "=== Références cassées vers papers ===\n";
$cassees_att = $db->query("
  SELECT COUNT(*) FROM node__field_att_papier a
  LEFT JOIN node n ON n.nid = a.field_att_papier_target_id
  WHERE n.nid IS NULL
")->fetchField();
echo "field_att_papier (Attestation) : $cassees_att\n";

// Y a-t-il d'autres champs qui référencent papers ?
$champs_ref = \Drupal::entityTypeManager()->getStorage('field_config')->loadByProperties(['entity_type' => 'node']);
foreach ($champs_ref as $fc) {
  $settings = $fc->getSetting('handler_settings') ?? [];
  if (isset($settings['target_bundles']['papers'])) {
    echo "Autre champ référençant papers : " . $fc->getName() . " sur " . $fc->getTargetBundle() . "\n";
  }
}

if ($cassees_att > 0) {
  echo "\n=== Correction avec la table de correspondance tnid ===\n";
  $pdo = new PDO('mysql:host=db2;port=3306;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');
  $stmt = $pdo->query("SELECT nid, tnid FROM node WHERE type = 'papers'");
  $mapping = [];
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($row['nid'] != $row['tnid'] && $row['tnid'] != 0) {
      $mapping[$row['nid']] = $row['tnid'];
    }
  }

  $ids = \Drupal::entityQuery('node')->condition('type', 'attestation')->accessCheck(FALSE)->execute();
  $corriges = 0;
  foreach (Node::loadMultiple($ids) as $node) {
    foreach (array_keys($node->getTranslationLanguages()) as $langue) {
      $trad = $node->getTranslation($langue);
      if (!$trad->hasField('field_att_papier') || $trad->get('field_att_papier')->isEmpty()) continue;
      $tid = $trad->get('field_att_papier')->target_id;
      if (isset($mapping[$tid])) {
        $trad->set('field_att_papier', ['target_id' => $mapping[$tid]]);
        $trad->save();
        $corriges++;
      }
    }
  }
  echo "Corrigées : $corriges\n";
}

echo "\n=== Vérification finale ===\n";
$reste = $db->query("
  SELECT COUNT(*) FROM node__field_att_papier a
  LEFT JOIN node n ON n.nid = a.field_att_papier_target_id
  WHERE n.nid IS NULL
")->fetchField();
echo "Restant cassé : $reste\n";
