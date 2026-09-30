<?php
$n1 = \Drupal\node\Entity\Node::load(3586);
$n2 = \Drupal\node\Entity\Node::load(3879);

foreach (['field_gen_culture_ml', 'field_intr_paper_ml', 'field_mode_prep_ml'] as $champ) {
  echo "=== $champ ===\n\n";
  echo "--- nid=3586 (créé mai) ---\n";
  echo $n1->hasField($champ) && !$n1->get($champ)->isEmpty() ? strip_tags($n1->get($champ)->value) . "\n" : "(vide)\n";
  echo "\n--- nid=3879 (créé juin) ---\n";
  echo $n2->hasField($champ) && !$n2->get($champ)->isEmpty() ? strip_tags($n2->get($champ)->value) . "\n" : "(vide)\n";
  echo "\n\n";
}
