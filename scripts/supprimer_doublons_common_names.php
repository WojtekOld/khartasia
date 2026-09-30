<?php
use Drupal\node\Entity\Node;

$a_supprimer = json_decode(file_get_contents('/tmp/cn_a_supprimer.json'), TRUE);
echo "Nœuds à supprimer : " . count($a_supprimer) . "\n";
echo "Échantillon (5) :\n";
foreach (array_slice($a_supprimer, 0, 5) as $s) {
  echo "  nid={$s['nid']} [{$s['langue']}] {$s['titre']} — doublon du canonique {$s['canonique']}\n";
}

echo "\nTaper CONTINUER pour supprimer réellement : ";
if (trim(fgets(STDIN)) !== 'CONTINUER') {
  echo "Annulé.\n";
  exit(0);
}

$compteur = 0;
foreach ($a_supprimer as $s) {
  $n = Node::load($s['nid']);
  if ($n) { $n->delete(); $compteur++; }
}
echo "\n=== Terminé : $compteur nœuds supprimés ===\n";

$total_final = \Drupal::entityQuery('node')->condition('type', 'common_names')->accessCheck(FALSE)->count()->execute();
echo "Total common_names restant : $total_final (attendu 765)\n";
