<?php
$total_cn = \Drupal::entityQuery('node')->condition('type', 'common_names')->accessCheck(FALSE)->count()->execute();
echo "Total common_names (attendu proche de 2040) : $total_cn\n";

$sans_langue = \Drupal::entityQuery('node')->condition('type', 'common_names')->condition('field_language_taxo_vern', NULL, 'IS NULL')->accessCheck(FALSE)->count()->execute();
echo "Sans langue : $sans_langue\n";

echo "\n=== Répartition par langue ===\n";
$db = \Drupal::database();
$repartition = $db->query("
  SELECT t.name, COUNT(*) as total
  FROM node__field_language_taxo_vern f
  INNER JOIN taxonomy_term_field_data t ON t.tid = f.field_language_taxo_vern_target_id
  GROUP BY t.name ORDER BY total DESC
")->fetchAllKeyed();
foreach ($repartition as $langue => $n) echo "  $langue : $n\n";

echo "\n=== Bilan complet des trois imports de la nuit ===\n";
echo "article : " . \Drupal::entityQuery('node')->condition('type', 'article')->accessCheck(FALSE)->count()->execute() . " (attendu 242)\n";
echo "papers : " . \Drupal::entityQuery('node')->condition('type', 'papers')->accessCheck(FALSE)->count()->execute() . " (attendu 692)\n";
echo "common_names : " . \Drupal::entityQuery('node')->condition('type', 'common_names')->accessCheck(FALSE)->count()->execute() . " (attendu ~2040)\n";
