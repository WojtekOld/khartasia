<?php
/**
 * inventaire_champs_article.php
 * Liste tous les champs du bundle 'article' avec leur type et cardinalité.
 * Usage : ddev drush php:script scripts/inventaire_champs_article.php
 */

$defs = \Drupal::service('entity_field.manager')
  ->getFieldDefinitions('node', 'article');

$skip = ['nid','vid','uuid','langcode','revision_uid','revision_timestamp',
  'revision_log','revision_default','revision_translation_affected',
  'default_langcode','promote','sticky','status','created','changed',
  'uid','type','path','menu_link','comment','metatag'];

echo "\nChamps du bundle 'article' :\n";
echo str_repeat('─', 72) . "\n";
printf("%-40s %-20s %s\n", 'Champ', 'Type', 'Cardinalité');
echo str_repeat('─', 72) . "\n";

foreach ($defs as $fn => $def) {
  if (in_array($fn, $skip)) continue;
  $card = $def->getFieldStorageDefinition()->getCardinality();
  $card_label = $card === -1 ? '∞' : (string)$card;
  printf("%-40s %-20s %s\n", $fn, $def->getType(), $card_label);
}
echo str_repeat('─', 72) . "\n";
