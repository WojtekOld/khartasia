<?php
// Relancer uniquement les especes avec erreurs 429
// Pause de 2 secondes entre requetes
$http = \Drupal::httpClient();
$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

// Especes deja traitees (ont des synonymes dans la base)
$already_done = \Drupal::entityQuery('taxonomy_term')
  ->condition('vid', 'synonymes_botaniques')
  ->accessCheck(FALSE)->execute();

$done_species = [];
foreach ($storage->loadMultiple($already_done) as $syn) {
  $ref = $syn->get('field_syn_nom_accepte')->target_id;
  if ($ref) $done_species[$ref] = TRUE;
}

echo "Especes deja traitees: " . count($done_species) . "\n\n";

// Charger les especes restantes
$all_tids = \Drupal::entityQuery('taxonomy_term')
  ->condition('vid', 'classification_botanique')
  ->accessCheck(FALSE)->execute();

$especes_restantes = [];
foreach ($storage->loadMultiple($all_tids) as $term) {
  if (isset($done_species[$term->id()])) continue;
  $children = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', 'classification_botanique')
    ->condition('parent', $term->id())
    ->accessCheck(FALSE)->execute();
  if (empty($children))
    $especes_restantes[$term->id()] = $term->label();
}

echo "Especes restantes: " . count($especes_restantes) . "\n\n";

$not_found_powo = [
  'Caesalpinia sappan L.',
  'Cannabis chinensis Delile',
  'Cannabis indica Lam.',
  'Cinnamomum camphora (L.) J. Presl',
  'Cinnamomum Loureirii Nees',
  'Deinanthe Bifida Maxim.',
  'Dendrocalamus affinis Rendle',
  'Euonymus sieboldianus Blume',
  'Hibiscus cannabinus L.',
  'Hibiscus manihot L. var. manihot',
  'Morus australis Poir.',
  'Opuntia dillenii (Ker Gawl.) Haw.',
  'Polygonum tinctorium Aiton',
  'Smithiodendron artocarpioideum Hu',
  'Sterculiaceae',
  'Wikstroemia sikokiania Franc. & Sav.',
];

$total_created = 0;
$total_errors = 0;

foreach ($especes_restantes as $tid => $nom) {
  if (in_array($nom, $not_found_powo)) {
    echo "SKIP (not found): $nom\n";
    continue;
  }

  // Pause 2 secondes
  sleep(2);

  $parts = explode(' ', $nom);
  $genus = $parts[0] ?? '';
  $epitete = $parts[1] ?? '';
  if (!$genus || !$epitete) continue;

  $q = urlencode("$genus $epitete");
  $search_url = "https://powo.science.kew.org/api/2/search?q=$q&f=accepted_names";

  try {
    $r = $http->get($search_url, [
      'timeout' => 15,
      'headers' => [
        'Accept'     => 'application/json',
        'User-Agent' => 'Khartasia/1.0',
      ],
    ]);
    $results = json_decode($r->getBody(), TRUE)['results'] ?? [];

    $fqid = null;
    foreach ($results as $res) {
      $res_name = strtolower($res['name'] ?? '');
      if (str_contains($res_name, strtolower($genus)) &&
          str_contains($res_name, strtolower($epitete))) {
        $fqid = $res['fqId'] ?? null;
        break;
      }
    }

    if (!$fqid) { echo "NOT FOUND: $nom\n"; continue; }

    sleep(2);

    $taxon_url = "https://powo.science.kew.org/api/2/taxon/" . urlencode($fqid);
    $tr = $http->get($taxon_url, [
      'timeout' => 15,
      'headers' => ['Accept' => 'application/json', 'User-Agent' => 'Khartasia/1.0'],
    ]);
    $synonyms = json_decode($tr->getBody(), TRUE)['synonyms'] ?? [];

    if (empty($synonyms)) { echo "NO SYN: $nom\n"; continue; }

    echo "$nom → " . count($synonyms) . " synonymes\n";

    foreach ($synonyms as $syn) {
      $syn_name = trim(($syn['name'] ?? '') . ' ' . ($syn['author'] ?? ''));
      if (!$syn_name) continue;

      $existing = \Drupal::entityQuery('taxonomy_term')
        ->condition('vid', 'synonymes_botaniques')
        ->condition('name', $syn_name)
        ->accessCheck(FALSE)->execute();
      if ($existing) continue;

      $status = strtolower($syn['taxonomicStatus'] ?? '');
      $type = str_contains($status, 'homotypic') ? 'homotopique' : 'heterotypique';
      $syn_fqid = $syn['fqId'] ?? '';
      $syn_ipni = str_replace('urn:lsid:ipni.org:names:', '', $syn_fqid);

      $term_data = [
        'vid'                   => 'synonymes_botaniques',
        'name'                  => $syn_name,
        'field_syn_nom_accepte' => [['target_id' => $tid]],
        'field_syn_type'        => [['value' => $type]],
        'field_syn_source'      => [['value' => 'powo']],
      ];
      if ($syn_ipni) {
        $term_data['field_syn_source_id']  = [['value' => $syn_ipni]];
        $term_data['field_syn_source_url'] = [['uri' => "https://powo.science.kew.org/taxon/" . urlencode($syn_fqid)]];
      }

      \Drupal\taxonomy\Entity\Term::create($term_data)->save();
      $total_created++;
    }

  } catch (\Exception $e) {
    echo "ERREUR $nom: " . $e->getMessage() . "\n";
    $total_errors++;
    sleep(5); // Pause plus longue apres erreur
  }
}

echo "\n=== BILAN RETRY ===\n";
echo "Synonymes crees : $total_created\n";
echo "Erreurs         : $total_errors\n";

// Total general
$total = \Drupal::entityQuery('taxonomy_term')
  ->condition('vid', 'synonymes_botaniques')
  ->accessCheck(FALSE)->count()->execute();
echo "Total synonymes en base: $total\n";
echo "\nDone\n";
