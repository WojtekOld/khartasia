<?php
$ids = [3585, 3878];
$champs = ['field_plante_ref', 'field_langue_texte', 'field_aire_utilis_ml', 'field_gen_culture_ml',
  'field_graphie_locale_ml', 'field_intr_paper_ml', 'field_mode_prep_ml', 'field_view_pap_plant_ml'];

$data = [];
foreach ($ids as $id) {
  $node = \Drupal\node\Entity\Node::load($id);
  echo "=== nid=$id ===\n";
  echo "Titre : " . $node->label() . "\n";
  echo "Créé le : " . date('Y-m-d H:i:s', $node->getCreatedTime()) . "\n";
  echo "Modifié le : " . date('Y-m-d H:i:s', $node->getChangedTime()) . "\n";
  echo "Statut : " . ($node->isPublished() ? "publié" : "non publié") . "\n";

  foreach ($champs as $champ) {
    if ($node->hasField($champ)) {
      $val = $node->get($champ)->getValue();
      $data[$id][$champ] = $val;
      $summary = empty($val) ? '(vide)' : (isset($val[0]['target_id']) ? 'target_id=' . $val[0]['target_id'] : substr(strip_tags($val[0]['value'] ?? ''), 0, 40) . '...');
      echo "  $champ : $summary\n";
    }
  }
  echo "\n";
}

echo "=== Comparaison champ par champ ===\n";
foreach ($champs as $champ) {
  $v1 = json_encode($data[3585][$champ] ?? null);
  $v2 = json_encode($data[3878][$champ] ?? null);
  echo "$champ : " . ($v1 === $v2 ? "IDENTIQUE" : "DIFFÉRENT") . "\n";
}
