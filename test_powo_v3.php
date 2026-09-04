<?php
$http = \Drupal::httpClient();

$plantes = [
  'Calamus rotang L.'        => '665387-1',
  'Broussonetia papyrifera'  => '850861-1',
  'Phyllostachys edulis'     => '415998-1',
];

foreach ($plantes as $nom => $ipni_id) {
  // URL sans parametre fields
  $fqid = "urn:lsid:ipni.org:names:$ipni_id";
  $url = "https://powo.science.kew.org/api/2/taxon/" . urlencode($fqid);

  try {
    $r = $http->get($url, [
      'timeout' => 15,
      'headers' => [
        'Accept'     => 'application/json',
        'User-Agent' => 'Khartasia/1.0 (research; contact@khartasia.org)',
      ],
    ]);

    $data = json_decode($r->getBody(), TRUE);
    echo "\n$nom\n";
    echo "  Status HTTP : " . $r->getStatusCode() . "\n";
    echo "  Nom         : " . ($data['name'] ?? 'N/A') . "\n";
    echo "  Famille     : " . ($data['family'] ?? 'N/A') . "\n";
    echo "  Statut taxo : " . ($data['taxonomicStatus'] ?? 'N/A') . "\n";

    // Synonymes
    $synonyms = $data['synonyms'] ?? [];
    echo "  Synonymes   : " . count($synonyms) . "\n";
    foreach (array_slice($synonyms, 0, 5) as $syn) {
      echo "    - " . ($syn['name'] ?? '') . " " . ($syn['author'] ?? '') . "\n";
    }

    // Si pas de synonymes — voir clés disponibles
    if (empty($synonyms)) {
      $keys = array_keys($data);
      echo "  Clés dispo  : " . implode(', ', $keys) . "\n";
    }

  } catch (\GuzzleHttp\Exception\ClientException $e) {
    $code = $e->getResponse()->getStatusCode();
    echo "\n$nom : HTTP $code\n";
    echo "  Body: " . substr($e->getResponse()->getBody(), 0, 200) . "\n";
  } catch (\Exception $e) {
    echo "\n$nom : ERREUR " . $e->getMessage() . "\n";
  }
}
echo "\nDone\n";
