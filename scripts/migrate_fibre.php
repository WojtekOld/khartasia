<?php
/**
 * migrate_fibre.php
 * Parse le HTML de field_ident_fibre et crée des nodes plante_fibre
 */

$storage = \Drupal::entityTypeManager()->getStorage('node');
$nids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->accessCheck(FALSE)
  ->execute();
$nodes = $storage->loadMultiple($nids);

$created = 0;
$skipped = 0;

// Map labels HTML → champs Drupal
$field_map = [
  'type de fibre'                    => 'field_pf_type',
  'fibre type'                       => 'field_pf_type',
  'extremites des fibres'            => 'field_pf_extremites',
  'fibre ends'                       => 'field_pf_extremites',
  'striations'                       => 'field_pf_striations',
  'cellules associees'               => 'field_pf_cellules',
  'associated cells'                 => 'field_pf_cellules',
  'caracteristiques particulieres'   => 'field_pf_particularites',
  'special features'                 => 'field_pf_particularites',
  'coloration herzberg'              => 'field_pf_herzberg_raw',
  'herzberg staining'                => 'field_pf_herzberg_raw',
  'coloration graff'                 => 'field_pf_graff_raw',
  'graff'                            => 'field_pf_graff_raw',
  'notes'                            => 'field_pf_notes',
];

// Normaliser une string pour comparaison
function normalize($s) {
  $s = strtolower($s);
  $s = preg_replace('/[àáâã]/u', 'a', $s);
  $s = preg_replace('/[éèêë]/u', 'e', $s);
  $s = preg_replace('/[îï]/u', 'i', $s);
  $s = preg_replace('/[ôõ]/u', 'o', $s);
  $s = preg_replace('/[ùûü]/u', 'u', $s);
  $s = preg_replace('/[ç]/u', 'c', $s);
  $s = preg_replace('/[^a-z0-9\s]/u', '', $s);
  $s = trim(preg_replace('/\s+/', ' ', $s));
  return $s;
}

// Parser le HTML tableau
function parse_fibre_html($html, $field_map) {
  $data = [];
  $dom = new DOMDocument();
  @$dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
  $rows = $dom->getElementsByTagName('tr');
  foreach ($rows as $row) {
    $cells = $row->getElementsByTagName('td');
    if ($cells->length < 2) continue;
    $label = normalize(strip_tags($cells->item(0)->textContent));
    $value = trim(strip_tags($cells->item(1)->textContent));
    if (empty($label) || empty($value)) continue;
    // Chercher dans le map
    foreach ($field_map as $pattern => $field) {
      if (strpos($label, $pattern) !== false) {
        $data[$field] = $value;
        break;
      }
    }
  }
  return $data;
}

// Map couleurs Herzberg
function map_herzberg($val) {
  $v = strtolower($val);
  if (strpos($v, 'jaune') !== false) return 'jaune';
  if (strpos($v, 'vert')  !== false) return 'vert';
  if (strpos($v, 'bleu')  !== false) return 'bleu';
  if (strpos($v, 'rouge') !== false) return 'rouge';
  if (strpos($v, 'viol')  !== false) return 'violet';
  if (strpos($v, 'orang') !== false) return 'orange';
  return null;
}

// Map Graff C
function map_graff($val) {
  $v = strtolower($val);
  if (strpos($v, 'positif') !== false || strpos($v, 'positive') !== false) return 'positif';
  if (strpos($v, 'negatif') !== false || strpos($v, 'negative') !== false || strpos($v, 'négatif') !== false) return 'negatif';
  return 'variable';
}

// Parser longueur/largeur
function parse_measure($val, &$min, &$max) {
  // Ex: "10 mm" ou "28µm (25-35µm)" ou "1.5-3 mm"
  if (preg_match('/(\d+[\.,]?\d*)\s*[-–]\s*(\d+[\.,]?\d*)/', $val, $m)) {
    $min = (float) str_replace(',', '.', $m[1]);
    $max = (float) str_replace(',', '.', $m[2]);
  } elseif (preg_match('/(\d+[\.,]?\d*)/', $val, $m)) {
    $min = $max = (float) str_replace(',', '.', $m[1]);
  }
}

foreach ($nodes as $plante) {
  if (!$plante->hasField('field_ident_fibre')) { $skipped++; continue; }
  if ($plante->get('field_ident_fibre')->isEmpty()) { $skipped++; continue; }

  $html = $plante->get('field_ident_fibre')->value;
  $data = parse_fibre_html($html, $field_map);

  if (empty($data)) { $skipped++; continue; }

  $fibre = $storage->create([
    'type'   => 'plante_fibre',
    'title'  => $plante->label() . ' — Fibres',
    'status' => 1,
    'uid'    => 1,
  ]);

  foreach ($data as $field => $val) {
    if ($field === 'field_pf_herzberg_raw') {
      $mapped = map_herzberg($val);
      if ($mapped) $fibre->set('field_pf_herzberg', $mapped);
      $fibre->set('field_pf_notes', ($fibre->get('field_pf_notes')->value ?? '') . ' Herzberg: ' . $val);
    } elseif ($field === 'field_pf_graff_raw') {
      $mapped = map_graff($val);
      $fibre->set('field_pf_graff_c', $mapped);
      $fibre->set('field_pf_notes', ($fibre->get('field_pf_notes')->value ?? '') . ' Graff C: ' . $val);
    } elseif (in_array($field, ['field_pf_type','field_pf_extremites','field_pf_striations',
                                 'field_pf_cellules','field_pf_particularites','field_pf_notes'])) {
      $fibre->set($field, $val);
    }
  }

  // Longueur
  if (isset($data['field_pf_long_raw'])) {
    $min = $max = null;
    parse_measure($data['field_pf_long_raw'], $min, $max);
    if ($min) $fibre->set('field_pf_long_min', $min);
    if ($max) $fibre->set('field_pf_long_max', $max);
  }
  // Largeur
  if (isset($data['field_pf_larg_raw'])) {
    $min = $max = null;
    parse_measure($data['field_pf_larg_raw'], $min, $max);
    if ($min) $fibre->set('field_pf_larg_min', $min);
    if ($max) $fibre->set('field_pf_larg_max', $max);
  }

  $fibre->save();
  $plante->set('field_fibres', ['target_id' => $fibre->id()]);
  $plante->save();
  $created++;
  echo "✓ " . $plante->label() . " (nid:" . $fibre->id() . ")\n";
}

echo "\n=== Migration fibres terminée ===\n";
echo "Créés : $created\n";
echo "Ignorés : $skipped\n";