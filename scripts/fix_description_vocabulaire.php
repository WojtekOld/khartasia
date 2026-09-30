<?php
use Drupal\taxonomy\Entity\Vocabulary;

$vocab = Vocabulary::load('classification_botanique');
$description_actuelle = $vocab->get('description') ?? '';

$ajout = "\n\nNote (ajoutée le " . date('d/m/Y') . ") : quatre termes de genre (Broussonetia, Phyllostachys, Edgeworthia, Wikstroemia) ont été insérés entre espèce et famille, uniquement pour ces quatre genres, pour porter les matériaux papetiers génériques du chantier materiau_papetier. Le reste du vocabulaire reste à plat (espèce directement sous famille) — asymétrie volontaire, pas une incohérence.";

if (!str_contains($description_actuelle, 'materiau_papetier')) {
  $vocab->set('description', $description_actuelle . $ajout);
  $vocab->save();
  echo "Note ajoutée à la description du vocabulaire classification_botanique.\n";
} else {
  echo "Note déjà présente.\n";
}
