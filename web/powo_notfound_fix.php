<?php
$http = \Drupal::httpClient();
$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

// Noms alternatifs a tester pour les Not Found
$alternatives = [
  'Cinnamomum camphora (L.) J. Presl'        => 'Camphora officinarum',
  'Cinnamomum Loureirii Nees'                => 'Cinnamomum loureiroi',
  'Dendrocalamus affinis Rendle'             => 'Bambusa affinis',
  'Hibiscus cannabinus L.'                   => 'Hibiscus cannabinus',
  'Hibiscus manihot L. var. manihot'         => 'Abelmoschus manihot',
  'Polygonum tinctorium Aiton'               => 'Persicaria tinctoria',
  'Wikstroemia sikokiania Franc. & Sav.'     => 'Wikstroemia sikokiana',
  'Euonymus sieboldianus Blume'              => 'Euonymus hamiltonianus',
  'Opuntia dillenii (Ker Gawl.) Haw.'        => 'Opuntia stricta',
  'Morus australis Poir.'                    => 'Morus australis',
  'Caesalpinia sappan L.'                    => 'Biancaea sappan',
];

$total = 0;

foreach ($alternatives as $notre_nom => $powo_nom) {
  // Trouver le TID de notre nom
  $tids = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', 'classification_botanique')
    ->condition('name', $notre_nom)
    ->accessCheck(FALSE)->execute();
  if (!$tids) { echo "ABSENT en base: $notre_nom\n"; continue; }
  $tid = reset($tids);

  sleep(2);

  $parts = explode(' ', $powo_nom);
  $q = urlencode($parts[0] . ' ' . ($parts[1] ?? ''));
  $url = "https://powo.science.kew.org/api/2/search?q=$q&f=accepted_names";

  try {
    $r = $http->get($url, [
      'timeout' => 15,
      'headers' => ['Accept' => 'application/json', 'User-Agent' => 'Khartasia/1.0'],
    ]);
    $results = json_decode($r->getBody(), TRUE)['results'] ?? [];

    $fqid = null;
    foreach ($results as $res) {
      $res_name = strtolower($res['name'] ?? '');
      if (str_contains($res_name, strtolower($parts[0]))) {
        $fqid = $res['fqId'] ?? null;
        break;
      }
    }

    if (!$fqid) { echo "TOUJOURS NOT FOUND: $powo_nom\n"; continue; }

    sleep(2);
    $tr = $http->get("https://powo.science.kew.org/api/2/taxon/" . urlencode($fqid), [
      'timeout' => 15,
      'headers' => ['Accept' => 'application/json', 'User-Agent' => 'Khartasia/1.0'],
    ]);
    $synonyms = json_decode($tr->getBody(), TRUE)['synonyms'] ?? [];

    echo "$notre_nom (via $powo_nom) → " . count($synonyms) . " synonymes\n";

    foreach ($synonyms as $syn) {
      $syn_name = trim(($syn['name'] ?? '') . ' ' . ($syn['author'] ?? ''));
      if (!$syn_name) continue;
      $existing = \Drupal::entityQuery('taxonomy_term')
        ->condition('vid', 'synonymes_botaniques')
        ->condition('name', $syn_name)
        ->accessCheck(FALSE)->execute();
      if ($existing) continue;

      $type = str_contains(strtolower($syn['taxonomicStatus'] ?? ''), 'homotypic')
        ? 'homotopique' : 'heterotypique';
      $syn_fqid = $syn['fqId'] ?? '';
      $syn_ipni = str_replace('urn:lsid:ipni.org:names:', '', $syn_fqid);

      \Drupal\taxonomy\Entity\Term::create([
        'vid'                   => 'synonymes_botaniques',
        'name'                  => $syn_name,
        'field_syn_nom_accepte' => [['target_id' => $tid]],
        'field_syn_type'        => [['value' => $type]],
        'field_syn_source'      => [['value' => 'powo']],
        'field_syn_source_id'   => [['value' => $syn_ipni]],
        'field_syn_source_url'  => [['uri' => "https://powo.science.kew.org/taxon/" . urlencode($syn_fqid)]],
      ])->save();
      $total++;
    }

  } catch (\Exception $e) {
    echo "ERREUR $powo_nom: " . $e->getMessage() . "\n";
    sleep(5);
  }
}

$grand_total = \Drupal::entityQuery('taxonomy_term')
  ->condition('vid', 'synonymes_botaniques')
  ->accessCheck(FALSE)->count()->execute();

echo "\nNouveaux synonymes: $total\n";
echo "Total general: $grand_total\n";
echo "Done\n";
