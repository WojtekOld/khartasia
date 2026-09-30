<?php
$node = \Drupal\node\Entity\Node::load(3030);
$corps = $node->get('body')->value;

$ancien = '<a href="/methode/fabrication" class="kh-block">';
$nouveau = '<a href="/methode/fabrication" class="kh-block kh-block--accent">';

if (str_contains($corps, $ancien)) {
  $corps = str_replace($ancien, $nouveau, $corps);
  $node->set('body', ['value' => $corps, 'format' => $node->get('body')->format]);
  $node->save();
  echo "Classe ajoutée avec succès.\n";
} else {
  echo "Motif exact introuvable — le HTML a peut-être changé depuis.\n";
  // Afficher un extrait autour de "Techniques" pour diagnostiquer
  $pos = strpos($corps, 'Techniques');
  if ($pos !== FALSE) {
    echo "Contexte trouvé autour de 'Techniques' :\n";
    echo substr($corps, max(0, $pos - 200), 300) . "\n";
  }
}
