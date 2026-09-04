<?php
// Vérifier les plante_texte liés au nœud 446 (Phyllostachys edulis)
$nids = \Drupal::entityQuery('node')
  ->condition('type', 'plante_texte')
  ->condition('field_plante_ref', 446)
  ->accessCheck(FALSE)->execute();

echo count($nids) . " textes trouvés pour NID 446\n\n";

foreach (\Drupal\node\Entity\Node::loadMultiple($nids) as $node) {
  $lang = $node->get('field_langue_texte')->entity;
  echo "[" . ($lang ? $lang->label() : '?') . "] NID=" . $node->id() . "\n";
  echo "  gen_culture: " . (
    $node->get('field_gen_culture_ml')->isEmpty() ? '(vide)' :
    substr($node->get('field_gen_culture_ml')->value, 0, 60)
  ) . "\n";
  echo "  intr_paper: " . (
    $node->get('field_intr_paper_ml')->isEmpty() ? '(vide)' :
    substr($node->get('field_intr_paper_ml')->value, 0, 60)
  ) . "\n";
}
