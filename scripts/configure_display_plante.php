<?php
use Drupal\Core\Entity\Entity\EntityViewDisplay;

$display = EntityViewDisplay::load('node.article.default');
if (!$display) {
  echo "Création du display\n";
  $display = EntityViewDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => 'article',
    'mode' => 'default',
    'status' => TRUE,
  ]);
}

// Masquer les champs non pertinents
$hide = [
  'field_aire_utilis_ch','field_gen_cult_ch','field_intr_paper_ch','field_mode_prep_ch',
  'field_aire_utilis_ko','field_gen_cult_ko','field_intr_paper_ko','field_mode_prep_ko',
  'field_aire_utilis_ja','field_gen_cult_jap','field_intr_paper_jap','field_mode_prep_ja',
  'field_aire_utilis_thai','field_gen_cult_thai','field_intr_paper_thai','field_mode_prep_thai',
  'field_ident_fibre','links','comment',
];
foreach($hide as $f) $display->removeComponent($f);

// Configurer les champs visibles avec leur ordre et widget
$weight = 0;

// Bloc 1 — En-tête
$display->setComponent('field_image', [
  'type' => 'image', 'weight' => $weight++,
  'settings' => ['image_style' => 'large', 'image_link' => ''],
  'label' => 'hidden',
]);
$display->setComponent('field_class_botan_texte', [
  'type' => 'text_default', 'weight' => $weight++,
  'label' => 'above',
]);
$display->setComponent('field_tax_order', [
  'type' => 'entity_reference_label', 'weight' => $weight++,
  'label' => 'inline', 'settings' => ['link' => FALSE],
]);
$display->setComponent('field_tax_family', [
  'type' => 'entity_reference_label', 'weight' => $weight++,
  'label' => 'inline', 'settings' => ['link' => FALSE],
]);
$display->setComponent('field_tax_genus', [
  'type' => 'entity_reference_label', 'weight' => $weight++,
  'label' => 'inline', 'settings' => ['link' => FALSE],
]);
$display->setComponent('field_synonymes_bot', [
  'type' => 'text_default', 'weight' => $weight++,
  'label' => 'above',
]);

// Bloc 2 — Corps
$display->setComponent('body', [
  'type' => 'text_default', 'weight' => $weight++,
  'label' => 'hidden',
]);
$display->setComponent('field_gen_culture', [
  'type' => 'text_default', 'weight' => $weight++,
  'label' => 'above',
]);
$display->setComponent('field_aire_croissance', [
  'type' => 'text_default', 'weight' => $weight++,
  'label' => 'above',
]);
$display->setComponent('field_aire_utilis', [
  'type' => 'text_default', 'weight' => $weight++,
  'label' => 'above',
]);
$display->setComponent('field_used_part_plant', [
  'type' => 'entity_reference_label', 'weight' => $weight++,
  'label' => 'inline', 'settings' => ['link' => FALSE],
]);
$display->setComponent('field_use_paper_making', [
  'type' => 'entity_reference_label', 'weight' => $weight++,
  'label' => 'inline', 'settings' => ['link' => FALSE],
]);

// Bloc 3 — Zones géo
$display->setComponent('field_zones_geo', [
  'type' => 'entity_reference_entity_view', 'weight' => $weight++,
  'label' => 'above',
  'settings' => ['view_mode' => 'default', 'link' => FALSE],
]);

// Bloc 4 — Fibres
$display->setComponent('field_fibres', [
  'type' => 'entity_reference_entity_view', 'weight' => $weight++,
  'label' => 'above',
  'settings' => ['view_mode' => 'default', 'link' => FALSE],
]);

// Bloc 5 — Galeries
$display->setComponent('field_image_gallery_1', [
  'type' => 'image', 'weight' => $weight++,
  'label' => 'above',
  'settings' => ['image_style' => 'medium', 'image_link' => ''],
]);
$display->setComponent('field_vern_name', [
  'type' => 'basic_string', 'weight' => $weight++,
  'label' => 'above',
]);
$display->setComponent('field_paper_name', [
  'type' => 'text_default', 'weight' => $weight++,
  'label' => 'above',
]);
$display->setComponent('field_tags', [
  'type' => 'entity_reference_label', 'weight' => $weight++,
  'label' => 'above', 'settings' => ['link' => TRUE],
]);

$display->save();
echo "Display configuré — " . $weight . " champs\n";