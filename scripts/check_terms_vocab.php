<?php

/**
 * Vérifie le vocabulaire des termes utilisés par field_origin_taxo.
 * À exécuter via : ddev drush scr scripts/check_terms_vocab.php
 */

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

$tids = [1, 2, 3, 5, 401, 402, 410, 535, 536, 538];

foreach ($tids as $tid) {
  $term = Term::load($tid);
  if (!$term) {
    echo $tid . "\t(terme introuvable)\n";
    continue;
  }

  // Dans Drupal 9+, le vid s'obtient avec bundle().
  $vid = $term->bundle();

  $vocabulary = Vocabulary::load($vid);
  $vocab_label = $vocabulary ? $vocabulary->label() : '(vocabulaire inconnu)';

  echo $tid . "\t" . $term->getName() . "\t" . $vocab_label . " (vid=" . $vid . ")\n";
}