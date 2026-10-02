<?php
$node = \Drupal\node\Entity\Node::load(4591);
$build = \Drupal::entityTypeManager()->getViewBuilder('node')->view($node, 'full');
$html = (string) \Drupal::service('renderer')->renderRoot($build);
echo "Longueur : " . strlen($html) . "\n";
echo "ATTESTATIONS : " . substr_count($html, 'ATTESTATIONS') . "\n";
echo "kh-dispute : " . substr_count($html, 'kh-dispute') . "\n";
file_put_contents('/tmp/rendu_direct_hetzner.html', $html);
echo "Sauvegardé dans /tmp/rendu_direct_hetzner.html\n";
