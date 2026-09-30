<?php
$db = \Drupal::database();

// Charger tous les termes zone_historique_production de niveau préfecture/province
// et région, pour détecter leur mention dans le texte
$termes_lieu = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
  ->loadByProperties(['vid' => 'zone_historique_production']);
$noms_lieu = [];
foreach ($termes_lieu as $t) {
  $noms_lieu[$t->label()] = $t->id();
}
uksort($noms_lieu, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

$dynasties_connues = ['Goryeo', 'Joseon', 'Tang', 'Song', 'Yuan', 'Ming', 'Qing', 'Nara', 'Heian', 'Muromachi', 'Edo', 'Meiji'];

$ids = \Drupal::entityQuery('node')
  ->condition('type', 'papers')
  ->condition('body', '', '<>')
  ->accessCheck(FALSE)
  ->execute();

$resultats = [];
foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
  $texte = strip_tags($n->get('body')->value ?? '');
  if (!$texte) continue;

  $dynasties_trouvees = array_values(array_filter($dynasties_connues, fn($d) => stripos($texte, $d) !== false));
  $lieux_trouves = [];
  foreach ($noms_lieu as $nom => $tid) {
    if (mb_stripos($texte . ' ' . $n->label(), $nom) !== false) {
      $lieux_trouves[] = $nom;
    }
  }

  if ($dynasties_trouvees || $lieux_trouves) {
    $deja = $db->query("SELECT COUNT(*) FROM node__field_att_papier WHERE field_att_papier_target_id = :nid", [':nid' => $n->id()])->fetchField();
    $resultats[] = [
      'nid' => $n->id(), 'titre' => $n->label(),
      'dynasties' => $dynasties_trouvees, 'lieux' => $lieux_trouves,
      'deja' => $deja > 0,
    ];
  }
}

echo "=== " . count($resultats) . " candidats, avec correspondance de lieu quand trouvée ===\n\n";
$avec_lieu = 0;
foreach ($resultats as $r) {
  $statut = $r['deja'] ? '[DÉJÀ TRAITÉ]' : '[à traiter]';
  echo "$statut {$r['titre']} (nid={$r['nid']})\n";
  if ($r['dynasties']) echo "    Période(s) : " . implode(', ', $r['dynasties']) . "\n";
  if ($r['lieux']) { echo "    Lieu(x) reconnu(s) : " . implode(', ', $r['lieux']) . "\n"; $avec_lieu++; }
  echo "\n";
}
echo "=== Bilan : $avec_lieu candidats sur " . count($resultats) . " ont AUSSI un lieu reconnu (pas seulement une période) ===\n";
