<?php
// Vérifier ce que POWO retourne comme données bibliographiques
$http = \Drupal::httpClient();

// Test sur Broussonetia papyrifera
$fqid = 'urn:lsid:ipni.org:names:850861-1';
$url = "https://powo.science.kew.org/api/2/taxon/" . urlencode($fqid);

$r = $http->get($url, [
  'timeout' => 15,
  'headers' => ['Accept' => 'application/json', 'User-Agent' => 'Khartasia/1.0'],
]);
$data = json_decode($r->getBody(), TRUE);

echo "=== Données disponibles pour Broussonetia papyrifera ===\n\n";
echo "Clés racine: " . implode(', ', array_keys($data)) . "\n\n";

// Références bibliographiques
echo "bibliographicCitation: " . ($data['bibliographicCitation'] ?? 'N/A') . "\n";
echo "reference: " . ($data['reference'] ?? 'N/A') . "\n";
echo "namePublishedInYear: " . ($data['namePublishedInYear'] ?? 'N/A') . "\n";
echo "source: " . ($data['source'] ?? 'N/A') . "\n\n";

// Regarder les synonymes avec leurs refs
$synonyms = $data['synonyms'] ?? [];
echo "=== Détail 3 premiers synonymes ===\n";
foreach (array_slice($synonyms, 0, 3) as $syn) {
  echo "\nSynonyme: " . ($syn['name'] ?? '') . " " . ($syn['author'] ?? '') . "\n";
  echo "  Clés: " . implode(', ', array_keys($syn)) . "\n";
  foreach ($syn as $k => $v) {
    if (is_string($v)) echo "  $k: $v\n";
  }
}
echo "\nDone\n";
