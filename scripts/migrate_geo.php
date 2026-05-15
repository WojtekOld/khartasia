<?php
/**
 * migrate_geo.php
 * Migre les champs géo par pays vers des nodes plante_geo
 * liés via field_zones_geo sur chaque plante
 */

$pays_map = [
  'ch' => [
    'tid_name'  => 'Chine',
    'fields'    => [
      'utilisation' => 'field_aire_utilis_ch',
      'culture'     => 'field_gen_cult_ch',
      'intro'       => 'field_intr_paper_ch',
      'preparation' => 'field_mode_prep_ch',
    ],
  ],
  'ko' => [
    'tid_name'  => 'Corée',
    'fields'    => [
      'utilisation' => 'field_aire_utilis_ko',
      'culture'     => 'field_gen_cult_ko',
      'intro'       => 'field_intr_paper_ko',
      'preparation' => 'field_mode_prep_ko',
    ],
  ],
  'ja' => [
    'tid_name'  => 'Japon',
    'fields'    => [
      'utilisation' => 'field_aire_utilis_ja',
      'culture'     => 'field_gen_cult_jap',
      'intro'       => 'field_intr_paper_jap',
      'preparation' => 'field_mode_prep_ja',
    ],
  ],
  'thai' => [
    'tid_name'  => 'Thaïlande',
    'fields'    => [
      'utilisation' => 'field_aire_utilis_thai',
      'culture'     => 'field_gen_cult_thai',
      'intro'       => 'field_intr_paper_thai',
      'preparation' => 'field_mode_prep_thai',
    ],
  ],
];

// Charger les TIDs du vocabulaire pays
$pays_tids = [];
foreach ($pays_map as $code => $def) {
  $terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadByProperties(['name' => $def['tid_name'], 'vid' => 'pays']);
  if ($terms) {
    $pays_tids[$code] = reset($terms)->id();
    echo "Pays " . $def['tid_name'] . " → tid:" . $pays_tids[$code] . PHP_EOL;
  } else {
    // Créer le terme s'il n'existe pas
    $term = \Drupal\taxonomy\Entity\Term::create([
      'vid'  => 'pays',
      'name' => $def['tid_name'],
    ]);
    $term->save();
    $pays_tids[$code] = $term->id();
    echo "Pays " . $def['tid_name'] . " créé → tid:" . $pays_tids[$code] . PHP_EOL;
  }
}

$storage = \Drupal::entityTypeManager()->getStorage('node');
$nids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->accessCheck(FALSE)
  ->execute();
$nodes = $storage->loadMultiple($nids);

$created = 0;
$skipped = 0;

foreach ($nodes as $plante) {
  $geo_refs = [];

  foreach ($pays_map as $code => $def) {
    $has_data = false;
    $data = [];

    foreach ($def['fields'] as $type => $field_name) {
      if (!$plante->hasField($field_name)) continue;
      if (!$plante->get($field_name)->isEmpty()) {
        $has_data = true;
        $val = $plante->get($field_name)->value;
        $fmt = $plante->get($field_name)->format ?? 'basic_html';
        $data[$type] = ['value' => $val, 'format' => $fmt];
      }
    }

    if (!$has_data) { $skipped++; continue; }

    // Créer le node plante_geo
    $geo = $storage->create([
      'type'   => 'plante_geo',
      'title'  => $plante->label() . ' — ' . $def['tid_name'],
      'status' => 1,
      'uid'    => 1,
    ]);

    if (isset($pays_tids[$code])) {
      $geo->set('field_pg_pays', ['target_id' => $pays_tids[$code]]);
    }
    if (isset($data['utilisation'])) {
      $geo->set('field_pg_utilisation', $data['utilisation']);
    }
    if (isset($data['culture'])) {
      $geo->set('field_pg_culture', $data['culture']);
    }
    if (isset($data['intro'])) {
      $geo->set('field_pg_intro', $data['intro']);
    }
    if (isset($data['preparation'])) {
      $geo->set('field_pg_preparation', $data['preparation']);
    }

    $geo->save();
    $geo_refs[] = ['target_id' => $geo->id()];
    $created++;
    echo "  → " . $plante->label() . " / " . $def['tid_name'] . " (nid:" . $geo->id() . ")\n";
  }

  // Lier les zones géo à la plante
  if (!empty($geo_refs)) {
    $plante->set('field_zones_geo', $geo_refs);
    $plante->save();
  }
}

echo "\n=== Migration terminée ===\n";
echo "Zones créées : $created\n";
echo "Ignorées (vides) : $skipped\n";