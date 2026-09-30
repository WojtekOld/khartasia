<?php
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

$note_terme = "Niveau « genre » créé spécifiquement pour porter les matériaux papetiers génériques (chantier materiau_papetier) — ce niveau n'existe pas ailleurs dans classification_botanique, qui va directement de l'espèce à la famille partout ailleurs. Asymétrie justifiée par l'usage, pas un oubli à corriger.";

foreach ([2461, 2462, 2463, 2464] as $tid) {
  $t = Term::load($tid);
  if ($t && $t->hasField('description')) {
    $t->set('description', ['value' => $note_terme, 'format' => 'basic_html']);
    $t->save();
    echo "Description ajoutée sur " . $t->label() . " (tid=$tid)\n";
  }
}

// Note au niveau du vocabulaire lui-même, visible sur la page de gestion des termes
$vocab = Vocabulary::load('classification_botanique');
$description_actuelle = $vocab->getDescription() ?? '';
$ajout = "\n\nNote (ajoutée le " . date('d/m/Y') . ") : quatre termes de genre (Broussonetia, Phyllostachys, Edgeworthia, Wikstroemia) ont été insérés entre espèce et famille, uniquement pour ces quatre genres, pour porter les matériaux papetiers génériques du chantier materiau_papetier. Le reste du vocabulaire reste à plat (espèce directement sous famille) — asymétrie volontaire, pas une incohérence.";

if (!str_contains($description_actuelle, 'materiau_papetier')) {
  $vocab->setDescription($description_actuelle . $ajout);
  $vocab->save();
  echo "\nNote ajoutée à la description du vocabulaire classification_botanique.\n";
} else {
  echo "\nNote déjà présente sur le vocabulaire.\n";
}
