<?php
// verifier_url_dispute.php — via `drush php-script`, aucun argument.
// Rend la vraie page telle que Drupal la génère (view builder + renderer),
// plutôt que de rejouer le preprocess à la main — exerce le vrai chemin
// (thème actif, hooks, ordre de chargement), sans reconstruction manuelle.

$papier = \Drupal\node\Entity\Node::load(4591);
if (!$papier) {
  echo "papers/4591 introuvable.\n";
  return;
}

$build = \Drupal::entityTypeManager()->getViewBuilder('node')->view($papier, 'full');
$html = (string) \Drupal::service('renderer')->renderRoot($build);

echo "Longueur HTML générée : " . strlen($html) . " caractères\n\n";

if (strpos($html, 'url_dispute') !== false) {
  echo "[ATTENTION] 'url_dispute' apparaît littéralement dans le HTML — signe que le template affiche la clé au lieu de la valeur (erreur de syntaxe Twig).\n";
}
if (strpos($html, 'Signaler une dispute') !== false) {
  echo "[OK] Le lien « Signaler une dispute » est bien présent dans le rendu réel.\n";
  // Extrait autour de la première occurrence pour contexte.
  $pos = strpos($html, 'Signaler une dispute');
  echo "Extrait : ..." . substr($html, max(0, $pos - 100), 250) . "...\n";
}
else {
  echo "[ABSENT] Le lien n'apparaît nulle part dans le rendu réel.\n";
}

if (strpos($html, 'kh-dispute-lien') !== false || strpos($html, 'kh-dispute-badge') !== false) {
  echo "[OK] Les classes CSS du bloc dispute sont présentes quelque part dans le HTML (lien ou badge).\n";
}
else {
  echo "[ABSENT] Aucune trace des classes kh-dispute-lien / kh-dispute-badge dans le HTML — le bloc entier n'est jamais atteint.\n";
}
