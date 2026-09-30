<?php
foreach (\Drupal\taxonomy\Entity\Vocabulary::loadMultiple() as $v) {
  $total = \Drupal::entityQuery('taxonomy_term')->condition('vid', $v->id())->accessCheck(FALSE)->count()->execute();
  echo $v->id() . " : " . $v->label() . " ($total termes)\n";
}
