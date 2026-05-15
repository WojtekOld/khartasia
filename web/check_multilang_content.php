<?php
$nids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->accessCheck(FALSE)->execute();

$stats = ['ch'=>0, 'jap'=>0, 'ko'=>0, 'thai'=>0, 'fr'=>0];
foreach (\Drupal\node\Entity\Node::loadMultiple($nids) as $node) {
  if (!$node->get('field_gen_cult_ch')->isEmpty())  $stats['ch']++;
  if (!$node->get('field_gen_cult_jap')->isEmpty()) $stats['jap']++;
  if (!$node->get('field_gen_cult_ko')->isEmpty())  $stats['ko']++;
  if (!$node->get('field_gen_culture')->isEmpty())  $stats['fr']++;
}
foreach ($stats as $lang => $count) {
  echo "$lang: $count plantes avec contenu\n";
}
