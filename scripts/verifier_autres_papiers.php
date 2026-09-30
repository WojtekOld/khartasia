<?php
$ids = \Drupal::entityQuery('node')
  ->condition('type', 'papers')
  ->condition('body', '', '<>')
  ->accessCheck(FALSE)
  ->range(0, 8)
  ->execute();

foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $node) {
  $body_field = $node->get('body');
  $format = $body_field->format ?? '(aucun)';
  $rendu = check_markup($body_field->value ?? '', $format);
  echo "{$node->label()} (nid={$node->id()}, format=$format) :\n";
  echo "  " . (trim(strip_tags((string) $rendu)) ?: '(TOUJOURS VIDE)') . "\n\n";
}
