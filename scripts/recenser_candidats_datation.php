<?php
$db = \Drupal::database();

// Motifs de dynasties déjà connues dans le vocabulaire periode_dynastique,
// plus quelques motifs génériques de date (années entre parenthèses, siècle)
$dynasties_connues = ['Goryeo', 'Joseon', 'Tang', 'Song', 'Yuan', 'Ming', 'Qing'];

$ids = \Drupal::entityQuery('node')
  ->condition('type', 'papers')
  ->condition('body', '', '<>')
  ->accessCheck(FALSE)
  ->execute();

$candidats = [];
foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
  $texte = strip_tags($n->get('body')->value ?? '');
  if (!$texte) continue;

  $trouve_dynastie = [];
  foreach ($dynasties_connues as $d) {
    if (stripos($texte, $d) !== false) {
      $trouve_dynastie[] = $d;
    }
  }
  $trouve_annees = preg_match('/\d{3,4}\s*-\s*\d{3,4}/', $texte, $m);
  $deja_attestation = $db->query("
    SELECT COUNT(*) FROM node__field_att_papier
    WHERE field_att_papier_target_id = :nid
  ", [':nid' => $n->id()])->fetchField();

  if ($trouve_dynastie || $trouve_annees) {
    $candidats[] = [
      'nid' => $n->id(),
      'titre' => $n->label(),
      'dynasties' => $trouve_dynastie,
      'annees' => $trouve_annees ? $m[0] : NULL,
      'deja_attestation' => $deja_attestation > 0,
    ];
  }
}

echo "=== Candidats trouvés : " . count($candidats) . " (sur " . count($ids) . " papiers avec du texte) ===\n\n";
foreach ($candidats as $c) {
  $statut = $c['deja_attestation'] ? '[DÉJÀ TRAITÉ]' : '[à traiter]';
  echo "$statut {$c['titre']} (nid={$c['nid']})\n";
  if ($c['dynasties']) echo "    Dynasties mentionnées : " . implode(', ', $c['dynasties']) . "\n";
  if ($c['annees']) echo "    Dates détectées : {$c['annees']}\n";
}
