<?php
// TIDs a garder => TID canonique de remplacement
// Tous les noeuds referençant un TID doublon seront mis a jour
$merge_map = [
  // Anglais : garder 394, fusionner 391 et 533
  391 => 394,
  533 => 394,
  // Français : garder 395, fusionner 390 et 534
  390 => 395,
  534 => 395,
  // Chinois : garder 393, fusionner 387 et 539
  387 => 393,
  539 => 393,
  // Japonais : garder 396, fusionner 388 et 544
  388 => 396,
  544 => 396,
  // Coréen : garder 397, fusionner 389 et 540
  389 => 397,
  540 => 397,
  // Thai : garder 403, fusionner 404, 405, 406, 407
  404 => 403,
  405 => 403,
  406 => 403,
  407 => 403,
];

$db = \Drupal::database();
$total = 0;

foreach ($merge_map as $old_tid => $new_tid) {
  // Mettre a jour field_language_taxo_vern sur les noeuds
  $count = $db->update('node__field_language_taxo_vern')
    ->fields(['field_language_taxo_vern_target_id' => $new_tid])
    ->condition('field_language_taxo_vern_target_id', $old_tid)
    ->execute();
  $total += $count;
  echo "TID $old_tid => $new_tid : $count noeuds mis a jour\n";

  // Supprimer le terme doublon
  $term = \Drupal\taxonomy\Entity\Term::load($old_tid);
  if ($term) {
    $label = $term->label();
    $term->delete();
    echo "  Supprime: TID $old_tid ($label)\n";
  }
}

echo "\nTotal: $total references mises a jour\n";
echo "Done\n";
