<?php
use Drupal\taxonomy\Entity\Term;

$descriptions = [
  'Jeonju' => "Centre papetier le plus renommé de Corée, dans le Jeolla du Nord. Réputé pour la souplesse de sa texture. Abrite le musée du hanji de Jeonju et accueille le plus grand festival papetier du pays chaque printemps (Jeonju Hanji Culture Festival). Production toujours active, intégrée au village traditionnel hanok de Jeonju.",
  'Andong' => "Centre du Gyeongsang du Nord, considéré comme la référence de qualité pour le hanji, en partie attribué à l'eau de source pure qui traverse le village de Hahoe. Le musée du Louvre achète directement auprès d'ateliers d'Andong.",
  'Wonju' => "Centre papetier du Gangwon, reconnu pour sa qualité aux côtés d'Andong. Abrite un parc thématique dédié au hanji (Wonju Hanji Theme Park).",
  'Goesan' => "Centre papetier du Chungcheong du Nord, prisé par des musées européens. Le Louvre achète directement auprès d'ateliers de Goesan, aux côtés d'Andong et de Mungyeong.",
];

foreach ($descriptions as $nom => $texte) {
  $terme = reset(\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'zone_historique_production', 'name' => $nom]));
  if (!$terme) {
    echo "$nom : terme introuvable — ignoré.\n";
    continue;
  }
  $terme->set('description', ['value' => $texte . "\n\nSources (recherche web, à vérifier davantage avant citation savante) : Daebak Magazine, twobrownfeet.com, Jeonju Hanji Museum.", 'format' => 'basic_html']);
  $terme->save();
  echo "$nom : description ajoutée.\n";
}

echo "\nTerminé.\n";
