<?php
// Audit complet papers + common_names pour analyse qualitative

echo "=== AUDIT PAPERS (238 noeuds) ===\n\n";

$papers = \Drupal::entityQuery('node')
  ->condition('type', 'papers')
  ->accessCheck(FALSE)->execute();

$stats = [
  'total' => count($papers),
  'with_taxo' => 0,
  'with_local_scripture' => 0,
  'with_synonyme' => 0,
  'with_origin' => 0,
  'multi_espece' => 0,
  'no_espece' => 0,
  'especes_count' => [],
  'origins' => [],
  'noms' => [],
];

foreach (\Drupal\node\Entity\Node::loadMultiple($papers) as $node) {
  $title = $node->label();
  $stats['noms'][] = $title;

  // Especes liees
  $taxo_count = $node->get('field_genus_taxo_pap')->count();
  if ($taxo_count == 0) $stats['no_espece']++;
  if ($taxo_count > 1) $stats['multi_espece']++;
  if ($taxo_count > 0) {
    $stats['with_taxo']++;
    if (!isset($stats['especes_count'][$taxo_count]))
      $stats['especes_count'][$taxo_count] = 0;
    $stats['especes_count'][$taxo_count]++;
  }

  // Ecriture locale
  if (!$node->get('field_pap_local_scripture')->isEmpty())
    $stats['with_local_scripture']++;

  // Synonymes
  if (!$node->get('field_pap_synonyme')->isEmpty())
    $stats['with_synonyme']++;

  // Origine
  if (!$node->get('field_origin_taxo')->isEmpty()) {
    $stats['with_origin']++;
    $origin = $node->get('field_origin_taxo')->entity;
    if ($origin) {
      $label = $origin->label();
      if (!isset($stats['origins'][$label]))
        $stats['origins'][$label] = 0;
      $stats['origins'][$label]++;
    }
  }
}

echo "Total papers : {$stats['total']}\n";
echo "Avec espece(s) liee(s) : {$stats['with_taxo']}\n";
echo "Sans espece : {$stats['no_espece']}\n";
echo "Multi-especes : {$stats['multi_espece']}\n";
echo "Avec ecriture locale : {$stats['with_local_scripture']}\n";
echo "Avec synonyme : {$stats['with_synonyme']}\n";
echo "Avec origine geo : {$stats['with_origin']}\n";

echo "\n--- Repartition nb especes par papier ---\n";
ksort($stats['especes_count']);
foreach ($stats['especes_count'] as $n => $count)
  echo "  $n espece(s) : $count papiers\n";

echo "\n--- Origines geographiques ---\n";
arsort($stats['origins']);
foreach ($stats['origins'] as $label => $count)
  echo "  $label : $count papiers\n";

// Doublons de noms
$noms_lower = array_map('strtolower', array_map('trim', $stats['noms']));
$doublons = array_filter(array_count_values($noms_lower), fn($v) => $v > 1);
if ($doublons) {
  echo "\n--- DOUBLONS de noms papers ---\n";
  arsort($doublons);
  foreach ($doublons as $nom => $count)
    echo "  '$nom' : $count fois\n";
} else {
  echo "\n--- Pas de doublons exacts dans papers ---\n";
}

echo "\n\n=== AUDIT COMMON_NAMES (765 noeuds) ===\n\n";

$cn = \Drupal::entityQuery('node')
  ->condition('type', 'common_names')
  ->accessCheck(FALSE)->execute();

$cn_stats = [
  'total' => count($cn),
  'par_langue' => [],
  'with_scripture' => 0,
  'with_synonyme' => 0,
  'with_taxo' => 0,
  'no_taxo' => 0,
  'multi_taxo' => 0,
  'noms' => [],
  'doublons_cross_lang' => [],
];

foreach (\Drupal\node\Entity\Node::loadMultiple($cn) as $node) {
  $title = trim($node->label());
  $cn_stats['noms'][$node->id()] = [
    'titre' => $title,
    'langue' => null,
    'taxo' => [],
  ];

  // Langue
  $lang = $node->get('field_language_taxo_vern')->entity;
  if ($lang) {
    $l = $lang->label();
    $cn_stats['noms'][$node->id()]['langue'] = $l;
    if (!isset($cn_stats['par_langue'][$l]))
      $cn_stats['par_langue'][$l] = 0;
    $cn_stats['par_langue'][$l]++;
  }

  // Ecriture locale
  if (!$node->get('field_ver_local_scripture')->isEmpty())
    $cn_stats['with_scripture']++;

  // Synonymes
  if (!$node->get('field_vern_syn')->isEmpty())
    $cn_stats['with_synonyme']++;

  // Taxo liee
  $taxo_count = $node->get('field_genus_taxo')->count();
  if ($taxo_count == 0) $cn_stats['no_taxo']++;
  elseif ($taxo_count > 1) $cn_stats['multi_taxo']++;
  if ($taxo_count > 0) {
    $cn_stats['with_taxo']++;
    foreach ($node->get('field_genus_taxo') as $ref) {
      if ($ref->entity)
        $cn_stats['noms'][$node->id()]['taxo'][] = $ref->entity->label();
    }
  }
}

echo "Total common_names : {$cn_stats['total']}\n";
echo "Avec taxo liee : {$cn_stats['with_taxo']}\n";
echo "Sans taxo : {$cn_stats['no_taxo']}\n";
echo "Multi-taxo : {$cn_stats['multi_taxo']}\n";
echo "Avec ecriture locale : {$cn_stats['with_scripture']}\n";
echo "Avec synonyme : {$cn_stats['with_synonyme']}\n";

echo "\n--- Repartition par langue ---\n";
arsort($cn_stats['par_langue']);
foreach ($cn_stats['par_langue'] as $lang => $count)
  echo "  $lang : $count noms\n";

// Meme nom dans plusieurs langues
echo "\n--- Noms identiques cross-langues (top 20) ---\n";
$by_titre = [];
foreach ($cn_stats['noms'] as $nid => $data) {
  $t = strtolower(trim($data['titre']));
  if (!isset($by_titre[$t])) $by_titre[$t] = [];
  $by_titre[$t][] = $data['langue'];
}
$cross = array_filter($by_titre, fn($v) => count($v) > 1);
arsort($cross);
$top = array_slice($cross, 0, 20, true);
foreach ($top as $nom => $langs)
  echo "  '$nom' : " . implode(', ', $langs) . "\n";

// Noms sans taxo — echantillon
echo "\n--- 20 common_names SANS taxo liee ---\n";
$no_taxo_sample = 0;
foreach ($cn_stats['noms'] as $nid => $data) {
  if (empty($data['taxo']) && $no_taxo_sample < 20) {
    echo "  NID $nid [{$data['langue']}]: {$data['titre']}\n";
    $no_taxo_sample++;
  }
}

// Taxo les plus representees
echo "\n--- Especes les plus citees dans common_names ---\n";
$taxo_freq = [];
foreach ($cn_stats['noms'] as $data) {
  foreach ($data['taxo'] as $t) {
    if (!isset($taxo_freq[$t])) $taxo_freq[$t] = 0;
    $taxo_freq[$t]++;
  }
}
arsort($taxo_freq);
$top_taxo = array_slice($taxo_freq, 0, 15, true);
foreach ($top_taxo as $espece => $count)
  echo "  $espece : $count noms communs\n";

echo "\nDone\n";
