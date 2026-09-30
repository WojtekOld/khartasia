<?php
use Drupal\views\Entity\View;

$view = View::load('papers_noms_communs');
if ($view) {
  $view->delete();
  echo "papers_noms_communs supprimée.\n";
} else {
  echo "Déjà absente.\n";
}

$verif = View::load('papers_noms_communs');
echo "Vérification : " . ($verif ? "ENCORE PRÉSENTE — problème" : "confirmée absente") . "\n";

\Drupal::service('cache.discovery')->deleteAll();
echo "Terminé.\n";
