<?php
// Remapper field_tax_family et field_tax_order
// depuis vocab family/order vers classification_botanique
$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$nids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->accessCheck(FALSE)->execute();

$ok = 0; $err = 0;

foreach (\Drupal\node\Entity\Node::loadMultiple($nids) as $node) {
  $changed = FALSE;

  // Remapper field_tax_family
  if (!$node->get('field_tax_family')->isEmpty()) {
    $old_tid = $node->get('field_tax_family')->target_id;
    $old_term = $storage->load($old_tid);
    if ($old_term && $old_term->bundle() !== 'classification_botanique') {
      // Chercher le meme nom dans classification_botanique
      $new_ids = \Drupal::entityQuery('taxonomy_term')
        ->condition('vid', 'classification_botanique')
        ->condition('name', $old_term->label())
        ->accessCheck(FALSE)->execute();
      if ($new_ids) {
        $node->set('field_tax_family', [['target_id' => reset($new_ids)]]);
        $changed = TRUE;
        echo "family: " . $old_term->label() . " TID $old_tid => " . reset($new_ids) . "\n";
      } else {
        echo "MANQUANT family: " . $old_term->label() . "\n"; $err++;
      }
    }
  }

  // Remapper field_tax_order
  if (!$node->get('field_tax_order')->isEmpty()) {
    $old_tid = $node->get('field_tax_order')->target_id;
    $old_term = $storage->load($old_tid);
    if ($old_term && $old_term->bundle() !== 'classification_botanique') {
      $new_ids = \Drupal::entityQuery('taxonomy_term')
        ->condition('vid', 'classification_botanique')
        ->condition('name', $old_term->label())
        ->accessCheck(FALSE)->execute();
      if ($new_ids) {
        $node->set('field_tax_order', [['target_id' => reset($new_ids)]]);
        $changed = TRUE;
      } else {
        echo "MANQUANT order: " . $old_term->label() . "\n"; $err++;
      }
    }
  }

  if ($changed) { $node->save(); $ok++; }
}

echo "\n--- $ok noeuds mis a jour, $err erreurs ---\n";
