<?php
$db = \Drupal::database();

echo "=== Termes 'classification_botanique' contenant 'Broussonetia' ===\n";
$termes = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
  ->loadByProperties(['vid' => 'classification_botanique']);
foreach ($termes as $t) {
  if (str_contains($t->label(), 'Broussonetia')) {
    $parent_ids = array_column($t->get('parent')->getValue(), 'target_id');
    echo "  - " . $t->label() . " (tid=" . $t->id() . ", parent=" . implode(',', $parent_ids) . ")\n";
  }
}

echo "\n=== Un terme de genre seul existe-t-il quelque part (nom sans espace) ? ===\n";
$sans_espace = 0;
foreach ($termes as $t) {
  if (!str_contains(trim($t->label()), ' ')) {
    $sans_espace++;
    if ($sans_espace <= 5) {
      echo "  - " . $t->label() . "\n";
    }
  }
}
echo "Total de termes à un seul mot (potentiellement genre seul) : $sans_espace\n";
