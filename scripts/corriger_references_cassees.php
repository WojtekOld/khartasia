<?php
use Drupal\node\Entity\Node;

// Reconstruire la correspondance nid isolé -> nid canonique (même table que le script de suppression)
$pdo = new PDO('mysql:host=db2;port=3306;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');
$stmt = $pdo->query("SELECT nid, tnid FROM node WHERE type = 'article'");
$mapping = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  if ($row['nid'] != $row['tnid']) {
    $mapping[$row['nid']] = $row['tnid'];
  }
}
echo "Correspondance nid isolé -> canonique : " . count($mapping) . " entrées\n";

function corriger_champ($bundle, $field_name, $mapping, $ecrire) {
  $ids = \Drupal::entityQuery('node')->condition('type', $bundle)->accessCheck(FALSE)->execute();
  $corriges = 0;
  $deja_bon = 0;

  foreach (Node::loadMultiple($ids) as $node) {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) continue;

    $valeurs = $node->get($field_name)->getValue();
    $modifie = false;
    $nouvelles_valeurs = [];
    $vus = [];

    foreach ($valeurs as $v) {
      $tid = $v['target_id'];
      if (isset($mapping[$tid])) {
        $tid_corrige = $mapping[$tid];
        if (!in_array($tid_corrige, $vus)) {
          $nouvelles_valeurs[] = ['target_id' => $tid_corrige];
          $vus[] = $tid_corrige;
        }
        $modifie = true;
      } else {
        if (!in_array($tid, $vus)) {
          $nouvelles_valeurs[] = ['target_id' => $tid];
          $vus[] = $tid;
        }
      }
    }

    if ($modifie) {
      $corriges++;
      if ($ecrire) {
        $node->set($field_name, $nouvelles_valeurs);
        $node->save();
      }
    } else {
      $deja_bon++;
    }
  }
  return ['corriges' => $corriges, 'deja_bon' => $deja_bon];
}

echo "\n=== Aperçu (rien n'est écrit) ===\n";
$r1 = corriger_champ('papers', 'field_genus_pap_entity', $mapping, false);
echo "Papers.field_genus_pap_entity : {$r1['corriges']} fiches à corriger\n";
$r2 = corriger_champ('common_names', 'field_genus_com_entity', $mapping, false);
echo "common_names.field_genus_com_entity : {$r2['corriges']} fiches à corriger\n";

echo "\nTaper CONTINUER pour corriger réellement, autre chose pour annuler : ";
if (trim(fgets(STDIN)) !== 'CONTINUER') {
  echo "Annulé — rien modifié.\n";
  exit(0);
}

echo "\n=== Correction réelle ===\n";
$r1 = corriger_champ('papers', 'field_genus_pap_entity', $mapping, true);
echo "Papers : {$r1['corriges']} corrigées\n";
$r2 = corriger_champ('common_names', 'field_genus_com_entity', $mapping, true);
echo "common_names : {$r2['corriges']} corrigées\n";

echo "\n=== Vérification finale ===\n";
$db = \Drupal::database();
$reste_papers = $db->query("SELECT COUNT(DISTINCT p.field_genus_pap_entity_target_id) FROM node__field_genus_pap_entity p LEFT JOIN node n ON n.nid = p.field_genus_pap_entity_target_id WHERE n.nid IS NULL")->fetchField();
$reste_cn = $db->query("SELECT COUNT(DISTINCT c.field_genus_com_entity_target_id) FROM node__field_genus_com_entity c LEFT JOIN node n ON n.nid = c.field_genus_com_entity_target_id WHERE n.nid IS NULL")->fetchField();
echo "Références encore cassées — Papers : $reste_papers, common_names : $reste_cn\n";
