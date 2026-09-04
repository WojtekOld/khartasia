<?php
/**
 * Import synonymes POWO pour toutes les plantes de classification_botanique
 * Etape 1 : recherche POWO ID pour chaque plante
 * Etape 2 : import synonymes dans synonymes_botaniques
 */

$http = \Drupal::httpClient();
$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

// Charger toutes les especes (niveau feuille = pas de enfants)
$all_tids = \Drupal::entityQuery('taxonomy_term')
  ->condition('vid', 'classification_botanique')
  ->accessCheck(FALSE)->execute();

$especes = [];
foreach ($storage->loadMultiple($all_tids) as $term) {
  $children = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', 'classification_botanique')
    ->condition('parent', $term->id())
    ->accessCheck(FALSE)->execute();
  // Garder seulement les feuilles (especes, pas ordres/familles)
  if (empty($children)) {
    $especes[$term->id()] = $term->label();
  }
}

echo "Especes a traiter: " . count($especes) . "\n\n";

$total_created = 0;
$total_errors = 0;
$not_found = [];

foreach ($especes as $tid => $nom) {
  // Recherche POWO
  $q = urlencode(explode(' ', $nom)[0] . ' ' . (explode(' ', $nom)[1] ?? ''));
  $search_url = "https://powo.science.kew.org/api/2/search?q=$q&f=accepted_names";

  try {
    $r = $http->get($search_url, [
      'timeout' => 10,
      'headers' => [
        'Accept'     => 'application/json',
        'User-Agent' => 'Khartasia/1.0',
      ],
    ]);
    $search_data = json_decode($r->getBody(), TRUE);
    $results = $search_data['results'] ?? [];

    // Trouver le meilleur match
    $fqid = null;
    foreach ($results as $res) {
      $res_name = strtolower(trim($res['name'] ?? ''));
      $our_name = strtolower(trim(explode(' (', $nom)[0]));
      $our_name = strtolower(trim(explode(',', $our_name)[0]));
      // Match sur genre + epitete
      $parts = explode(' ', $our_name);
      $genus = $parts[0] ?? '';
      $epitete = $parts[1] ?? '';
      if ($genus && $epitete &&
          str_contains($res_name, $genus) &&
          str_contains($res_name, $epitete)) {
        $fqid = $res['fqId'] ?? null;
        break;
      }
    }

    if (!$fqid) {
      $not_found[] = $nom;
      echo "NOT FOUND: $nom\n";
      continue;
    }

    // Recuperer les synonymes
    $taxon_url = "https://powo.science.kew.org/api/2/taxon/" . urlencode($fqid);
    $tr = $http->get($taxon_url, [
      'timeout' => 10,
      'headers' => ['Accept' => 'application/json', 'User-Agent' => 'Khartasia/1.0'],
    ]);
    $taxon_data = json_decode($tr->getBody(), TRUE);
    $synonyms = $taxon_data['synonyms'] ?? [];

    if (empty($synonyms)) {
      echo "NO SYN: $nom\n";
      continue;
    }

    echo "$nom → " . count($synonyms) . " synonymes\n";

    foreach ($synonyms as $syn) {
      $syn_name = trim(($syn['name'] ?? '') . ' ' . ($syn['author'] ?? ''));
      $syn_name = trim($syn_name);
      if (!$syn_name) continue;

      // Verifier si deja present
      $existing = \Drupal::entityQuery('taxonomy_term')
        ->condition('vid', 'synonymes_botaniques')
        ->condition('name', $syn_name)
        ->accessCheck(FALSE)->execute();
      if ($existing) continue;

      // Determiner type
      $status = $syn['taxonomicStatus'] ?? '';
      $type = 'heterotypique';
      if (str_contains(strtolower($status), 'homotypic')) $type = 'homotopique';
      if (str_contains(strtolower($status), 'pro_parte')) $type = 'pro_parte';

      // Extraire IPNI ID depuis fqId
      $syn_fqid = $syn['fqId'] ?? '';
      $syn_ipni = str_replace('urn:lsid:ipni.org:names:', '', $syn_fqid);

      // Creer le terme synonyme
      $term_data = [
        'vid'                  => 'synonymes_botaniques',
        'name'                 => $syn_name,
        'field_syn_nom_accepte'=> [['target_id' => $tid]],
        'field_syn_type'       => [['value' => $type]],
        'field_syn_source'     => [['value' => 'powo']],
      ];
      if ($syn_ipni) {
        $term_data['field_syn_source_id']  = [['value' => $syn_ipni]];
        $term_data['field_syn_source_url'] = [['uri' => "https://powo.science.kew.org/taxon/" . urlencode($syn_fqid)]];
      }

      $new_term = \Drupal\taxonomy\Entity\Term::create($term_data);
      $new_term->save();
      $total_created++;
    }

    // Pause pour ne pas surcharger l API
    usleep(200000); // 200ms

  } catch (\Exception $e) {
    echo "ERREUR $nom: " . $e->getMessage() . "\n";
    $total_errors++;
  }
}

echo "\n=== BILAN ===\n";
echo "Synonymes crees : $total_created\n";
echo "Erreurs         : $total_errors\n";
echo "Non trouves     : " . count($not_found) . "\n";
if ($not_found) {
  echo "\nNon trouvés:\n";
  foreach ($not_found as $n) echo "  - $n\n";
}
echo "\nDone\n";
