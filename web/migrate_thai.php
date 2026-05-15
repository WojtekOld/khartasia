<?php
$lang_tid = 403; // Thai

$field_mapping = [
  'field_gen_culture_ml'   => 'field_fiel_gen_cult_thai',
  'field_intr_paper_ml'    => 'field_intr_paper_thai',
  'field_mode_prep_ml'     => 'field_mode_prep_thai',
  'field_aire_utilis_ml'   => 'field_aire_utilis_thai',
];

$nids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->accessCheck(FALSE)->execute();

$created = 0;
foreach (\Drupal\node\Entity\Node::loadMultiple($nids) as $plante) {
  $has_content = FALSE;
  foreach ($field_mapping as $target => $source) {
    if ($plante->hasField($source) && !$plante->get($source)->isEmpty()) {
      $has_content = TRUE; break;
    }
  }
  if (!$has_content) continue;

  $new_node = \Drupal\node\Entity\Node::create([
    'type'               => 'plante_texte',
    'title'              => $plante->label() . ' [Thai]',
    'status'             => 1,
    'field_plante_ref'   => [['target_id' => $plante->id()]],
    'field_langue_texte' => [['target_id' => $lang_tid]],
  ]);

  foreach ($field_mapping as $target => $source) {
    if ($plante->hasField($source) && !$plante->get($source)->isEmpty())
      $new_node->set($target, $plante->get($source)->getValue());
  }
  $new_node->save();
  echo "OK: " . $plante->label() . " [Thai] NID=" . $new_node->id() . "\n";
  $created++;
}
echo "\n--- $created noeuds Thai crees ---\n";
