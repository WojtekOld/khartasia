<?php
echo "Module paragraphs activé : " . (\Drupal::moduleHandler()->moduleExists('paragraphs') ? "OUI" : "NON") . "\n";
echo "Module entity_reference_revisions activé : " . (\Drupal::moduleHandler()->moduleExists('entity_reference_revisions') ? "OUI" : "NON") . "\n";

if (\Drupal::entityTypeManager()->hasDefinition('paragraph')) {
  echo "Entité 'paragraph' disponible : OUI\n";
  $bundles = \Drupal::entityTypeManager()->getStorage('paragraphs_type')->loadMultiple();
  echo "Types de paragraphes existants : " . count($bundles) . "\n";
  foreach ($bundles as $b) {
    echo "  - " . $b->id() . "\n";
  }
} else {
  echo "Entité 'paragraph' disponible : NON\n";
}
