<?php
$http = \Drupal::httpClient();

$plantes = [
  'Calamus rotang',
  'Broussonetia papyrifera',
  'Edgeworthia chrysantha',
  'Pteroceltis tatarinowii',
  'Phyllostachys edulis',
];

foreach ($plantes as $nom) {
  $q = urlencode($nom);
  $url = "https://powo.science.kew.org/api/2/search?q=$q&f=accepted_names";

  try {
    $r = $http->get($url, ['timeout' => 15,
      'headers' => ['Accept' => 'application/json']]);
    $data = json_decode($r->getBody(), TRUE);
    $results = $data['results'] ?? [];

    echo "\n$nom :\n";
    foreach (array_slice($results, 0, 3) as $res) {
      echo "  " . ($res['name'] ?? '') . " " . ($res['author'] ?? '') . "\n";
      echo "  fqId: " . ($res['fqId'] ?? '') . "\n";
      echo "  Status: " . ($res['taxonomicStatus'] ?? '') . "\n";
    }
    if (empty($results)) echo "  Aucun résultat\n";

  } catch (\Exception $e) {
    echo "\n$nom : ERREUR " . $e->getMessage() . "\n";
  }
}
echo "\nDone\n";
