<?php
$mots_cles = ['colorant', 'dispersant', 'charge', 'chargé', 'argile', 'gofun', 'coquillage', 'encollage', 'encollé'];

$ids = \Drupal::entityQuery('node')
  ->condition('type', 'papers')
  ->condition('body', '', '<>')
  ->accessCheck(FALSE)->execute();

$candidats = [];
foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
  $texte = strip_tags($n->get('body')->value ?? '');
  if (!$texte) continue;

  $trouves = [];
  foreach ($mots_cles as $m) {
    if (mb_stripos($texte, $m) !== false) $trouves[] = $m;
  }
  if ($trouves) {
    $deja = \Drupal::entityQuery('node')
      ->condition('type', 'attestation')
      ->condition('field_att_papier', $n->id())
      ->accessCheck(FALSE)->count()->execute();
    $candidats[] = ['nid' => $n->id(), 'titre' => $n->label(), 'mots' => $trouves, 'deja_attestations' => $deja];
  }
}

echo "Candidats trouvés : " . count($candidats) . " sur " . count($ids) . " papiers avec texte\n\n";
foreach ($candidats as $c) {
  $marque = $c['deja_attestations'] > 0 ? '[a déjà des attestations]' : '[aucune attestation]';
  echo "$marque {$c['titre']} (nid={$c['nid']}) : " . implode(', ', $c['mots']) . "\n";
}
