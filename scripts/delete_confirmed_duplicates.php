<?php
// Doublons confirmés identiques — on garde la version de mai (originale),
// on supprime la version de juin (doublon de re-migration).
$a_supprimer = [
  3878 => 'Broussonetia papyrifera',
  3880 => 'Musa textilis',
  3881 => 'Spirogyra turfosa',
  3882 => 'Areca catechu',
];

echo "=== SUPPRESSION DES DOUBLONS CONFIRMÉS ===\n\n";
echo "Nœuds à supprimer :\n";
foreach ($a_supprimer as $nid => $label) {
  $node = \Drupal\node\Entity\Node::load($nid);
  echo "  - nid=$nid ($label) : " . ($node ? "trouvé, prêt à supprimer" : "INTROUVABLE, ignoré") . "\n";
}

echo "\nTaper SUPPRIMER en majuscules pour confirmer, autre chose pour annuler : ";
$confirmation = trim(fgets(STDIN));

if ($confirmation !== 'SUPPRIMER') {
  echo "Annulé — aucune suppression effectuée.\n";
  exit(0);
}

echo "\nSuppression en cours...\n";
foreach ($a_supprimer as $nid => $label) {
  $node = \Drupal\node\Entity\Node::load($nid);
  if ($node) {
    $node->delete();
    echo "  Supprimé : nid=$nid ($label)\n";
  }
}

echo "\n=== TERMINÉ ===\n";
