<?php
/**
 * Test API POWO — Calamus rotang L.
 * IPNI ID : 672892-1
 */

$http = \Drupal::httpClient();
$ipni_id = '672892-1';
$url = "https://powo.science.kew.org/api/2/taxon/urn:lsid:ipni.org:names:$ipni_id";

echo "Requête: $url\n\n";

try {
  $response = $http->get($url, [
    'timeout' => 15,
    'headers' => ['Accept' => 'application/json'],
  ]);

  $data = json_decode($response->getBody(), TRUE);

  echo "Nom accepté : " . ($data['name'] ?? 'N/A') . "\n";
  echo "Auteur      : " . ($data['author'] ?? 'N/A') . "\n";
  echo "Famille     : " . ($data['family'] ?? 'N/A') . "\n";
  echo "Statut      : " . ($data['taxonomicStatus'] ?? 'N/A') . "\n\n";

  $synonyms = $data['synonyms'] ?? [];
  echo "Synonymes (" . count($synonyms) . ") :\n";
  foreach ($synonyms as $syn) {
    echo "  - " . ($syn['name'] ?? '') . " " . ($syn['author'] ?? '') . "\n";
    echo "    Type: " . ($syn['taxonomicStatus'] ?? 'N/A') . "\n";
  }

  // Afficher la réponse brute si pas de synonymes
  if (empty($synonyms)) {
    echo "\nRéponse brute (clés disponibles):\n";
    foreach (array_keys($data) as $key) {
      echo "  $key\n";
    }
  }

} catch (\Exception $e) {
  echo "ERREUR: " . $e->getMessage() . "\n";

  // Tester URL alternative
  echo "\nTest URL alternative POWO search...\n";
  try {
    $search_url = "https://powo.science.kew.org/api/2/search?q=Calamus+rotang&f=accepted_names";
    $r2 = $http->get($search_url, ['timeout' => 15]);
    $d2 = json_decode($r2->getBody(), TRUE);
    echo "Résultats search: " . count($d2['results'] ?? []) . "\n";
    foreach (($d2['results'] ?? []) as $r) {
      echo "  " . ($r['name'] ?? '') . " — " . ($r['fqId'] ?? '') . "\n";
    }
  } catch (\Exception $e2) {
    echo "ERREUR search: " . $e2->getMessage() . "\n";
  }
}

echo "\nDone\n";
