<?php
foreach (['article', 'papers', 'common_names'] as $bundle) {
  $ids = \Drupal::entityQuery('node')->condition('type', $bundle)->accessCheck(FALSE)->execute();
  $total = count($ids);

  $repartition = [];
  foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
    $langues = array_keys($n->getTranslationLanguages());
    sort($langues);
    $cle = implode('+', $langues);
    $repartition[$cle] = ($repartition[$cle] ?? 0) + 1;
  }
  arsort($repartition);

  echo "=== $bundle (total: $total) ===\n";
  foreach ($repartition as $combo => $n) {
    echo "  $combo : $n fiches\n";
  }
  echo "\n";
}
