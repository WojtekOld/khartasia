<?php
$http = \Drupal::httpClient();

$plantes = [
  'Calamus rotang L.'                              => '665387-1',
  'Broussonetia papyrifera (L.) L\'Her. ex Vent.' => '850861-1',
  'Edgeworthia chrysantha Lindl.'                  => '831625-1',
  'Pteroceltis tatarinowii Maxim.'                 => '856413-1',
  'Phyllostachys edulis (Carriere) J.Houz.'        => '415998-1',
];

foreach ($plantes as $nom => $ipni_id) {
  $url = "https://powo.science.kew.org/api/2/taxon/urn:lsid:ipni.org:names:$ipni_id?fields=synonyms";

  try {
    $r = $http->get($url, [
      'timeout' => 15,
      'headers' => ['Accept' => 'application/json'],
    ]);
    $data = json_decode($r->getBody(), TRUE);

    $synonyms = $data['synonyms'] ?? [];
    echo "\n$nom (" . count($synonyms) . " synonymes) :\n";
    foreach ($synonyms as $syn) {
      $syn_name = ($syn['name'] ?? '') . ' ' . ($syn['author'] ?? '');
      $type = $syn['taxonomicStatus'] ?? 'N/A';
      $fqid = $syn['fqId'] ?? '';
      echo "  - $syn_name\n";
      echo "    Type: $type | fqId: $fqid\n";
    }
    if (empty($synonyms)) echo "  Aucun synonyme retourné\n";

  } catch (\Exception $e) {
    echo "\n$nom : ERREUR " . $e->getMessage() . "\n";
  }
}
echo "\nDone\n";
