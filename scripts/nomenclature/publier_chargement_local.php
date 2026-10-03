<?php
/**
 * publier_chargement_local.php - publie, EN LOCAL SEULEMENT, les noms et attestations qui viennent d'etre charges.
 *
 * Usage (depuis la racine du projet) :
 *   ddev exec "vendor/bin/drush php-script scripts/nomenclature/publier_chargement_local.php"
 *
 * Pourquoi : voir a quoi ressemblent les fiches une fois publiees (captures pour la relectrice), sans toucher a la production.
 *
 * GARDE-FOUS
 *  1. Refuse de tourner hors de DDEV (variable IS_DDEV_PROJECT) : impossible de l'executer par erreur sur le serveur.
 *  2. Ne publie que des noeuds de type common_names ou attestation, NON publies, crees depuis moins de 2 heures, et seulement
 *     si leur nombre est exactement 12 (6 noms + 6 attestations du jeu pilote). Sinon : abandon, rien n'est modifie.
 *
 * DEFINITIONS
 * publier : passer le statut d'un contenu a "publie" ; il devient visible des visiteurs.
 * garde-fou : controle qui arrete le script plutot que de le laisser agir sur un etat inattendu.
 */

if (getenv('IS_DDEV_PROJECT') !== 'true') {
  echo "ERREUR : ce script ne tourne qu'en local (DDEV). Abandon, rien n'a ete modifie.\n";
  return;
}
$attendu = 12;
$fenetre = 7200;
$ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', ['common_names', 'attestation'], 'IN')
  ->condition('status', 0)
  ->condition('created', time() - $fenetre, '>')
  ->execute();
echo "noeuds non publies (noms et attestations) crees depuis moins de 2 heures : " . count($ids) . "\n";
if (count($ids) !== $attendu) {
  echo "ERREUR : $attendu attendus (6 noms + 6 attestations). Abandon, rien n'a ete modifie.\n";
  return;
}
foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
  $n->setPublished();
  $n->save();
  echo "  publie : " . $n->id() . " (" . $n->bundle() . ") " . $n->label() . "\n";
}
echo "\nFait : $attendu noeuds publies, en local.\n";
