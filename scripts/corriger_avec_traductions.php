<?php
use Drupal\node\Entity\Node;

$pdo = new PDO('mysql:host=db2;port=3306;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');
$stmt = $pdo->query("SELECT nid, tnid FROM node WHERE type = 'article'");
$mapping = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  if ($row['nid'] != $row['tnid']) {
    $mapping[$row['nid']] = $row['tnid'];
  }
}
echo "Correspondance : " . count($mapping) . " entrées\n";

function corriger_champ_avec_traductions($bundle, $field_name, $mapping, $ecrire) {
  $ids = \Drupal::entityQuery('node')->condition('type', $bundle)->accessCheck(FALSE)->execute();
  $corriges = 0;

  foreach (Node::loadMultiple($ids) as $node) {
    $langues = array_keys($node->getTranslationLanguages());
    foreach ($langues as $langue) {
      $trad = $node->getTranslation($langue);
      if (!$trad->hasField($field_name) || $trad->get($field_name)->isEmpty()) continue;

      $valeurs = $trad->get($field_name)->getValue();
      $modifie = false;
      $nouvelles_valeurs = [];
      $vus = [];

      foreach ($valeurs as $v) {
        $tid = $v['target_id'];
        $tid_final = $mapping[$tid] ?? $tid;
        if ($tid_final != $tid) $modifie = true;
        if (!in_array($tid_final, $vus)) {
          $nouvelles_valeurs[] = ['target_id' => $tid_final];
          $vus[] = $tid_final;
        }
      }

      if ($modifie) {
        $corriges++;
        if ($ecrire) {
          $trad->set($field_name, $nouvelles_valeurs);
          $trad->save();
        }
      }
    }
  }
  return $corriges;
}

echo "\n=== Aperçu (toutes langues/traductions incluses) ===\n";
$n1 = corriger_champ_avec_traductions('papers', 'field_genus_pap_entity', $mapping, false);
echo "Papers : $n1 traductions à corriger\n";
$n2 = corriger_champ_avec_traductions('common_names', 'field_genus_com_entity', $mapping, false);
echo "common_names : $n2 traductions à corriger\n";

echo "\nTaper CONTINUER pour corriger réellement : ";
if (trim(fgets(STDIN)) !== 'CONTINUER') {
  echo "Annulé.\n";
  exit(0);
}

$n1 = corriger_champ_avec_traductions('papers', 'field_genus_pap_entity', $mapping, true);
echo "Papers corrigées : $n1\n";
$n2 = corriger_champ_avec_traductions('common_names', 'field_genus_com_entity', $mapping, true);
echo "common_names corrigées : $n2\n";

echo "\n=== Vérification finale ===\n";
$db = \Drupal::database();
$reste_p = $db->query("SELECT COUNT(DISTINCT p.field_genus_pap_entity_target_id) FROM node__field_genus_pap_entity p LEFT JOIN node n ON n.nid = p.field_genus_pap_entity_target_id WHERE n.nid IS NULL")->fetchField();
$reste_c = $db->query("SELECT COUNT(DISTINCT c.field_genus_com_entity_target_id) FROM node__field_genus_com_entity c LEFT JOIN node n ON n.nid = c.field_genus_com_entity_target_id WHERE n.nid IS NULL")->fetchField();
echo "Restant cassé — Papers : $reste_p, common_names : $reste_c\n";
