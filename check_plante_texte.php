<?php
// Stats par langue
$lang_tids = [395 => 'Français', 393 => 'Chinois', 396 => 'Japonais', 397 => 'Coréen'];
foreach ($lang_tids as $tid => $label) {
  $count = \Drupal::entityQuery('node')
    ->condition('type', 'plante_texte')
    ->condition('field_langue_texte', $tid)
    ->accessCheck(FALSE)->count()->execute();
  echo "$label: $count noeuds\n";
}

echo "\n-- Exemple Phyllostachys edulis --\n";
$nids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->condition('title', 'Phyllostachys edulis%', 'LIKE')
  ->accessCheck(FALSE)->execute();

if ($nids) {
  $plante_nid = reset($nids);
  $textes = \Drupal::entityQuery('node')
    ->condition('type', 'plante_texte')
    ->condition('field_plante_ref', $plante_nid)
    ->accessCheck(FALSE)->execute();
  foreach (\Drupal\node\Entity\Node::loadMultiple($textes) as $t) {
    $lang = $t->get('field_langue_texte')->entity;
    echo "\n[" . ($lang ? $lang->label() : '?') . "] " . $t->label() . "\n";
    if (!$t->get('field_gen_culture_ml')->isEmpty())
      echo "  gen_culture: " . substr($t->get('field_gen_culture_ml')->value, 0, 80) . "...\n";
    if (!$t->get('field_mode_prep_ml')->isEmpty())
      echo "  mode_prep: " . substr($t->get('field_mode_prep_ml')->value, 0, 80) . "...\n";
  }
}
