<?php
$ids = \Drupal::entityQuery('node')
  ->condition('type', 'plante_fibre')
  ->accessCheck(FALSE)
  ->execute();

$avec_graff = 0;
$sans_graff = 0;
$formats = [];

foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $node) {
  if (!$node->hasField('field_pf_notes') || $node->get('field_pf_notes')->isEmpty()) {
    continue;
  }
  $texte = strip_tags($node->get('field_pf_notes')->value);

  if (stripos($texte, 'graff') !== false) {
    $avec_graff++;
    echo "--- nid=" . $node->id() . " (" . $node->label() . ") ---\n";
    echo $texte . "\n\n";
  } else {
    $sans_graff++;
  }
}

echo "=== Résumé ===\n";
echo "Fiches avec 'Graff' mentionné dans field_pf_notes : $avec_graff\n";
echo "Fiches sans mention de Graff dans field_pf_notes : $sans_graff\n";
