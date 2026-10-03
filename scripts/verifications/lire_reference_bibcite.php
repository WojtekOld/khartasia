<?php
/**
 * lire_reference_bibcite.php - LECTURE SEULE : champs d'une notice bibcite et services bibcite disponibles.
 * Usage (depuis la racine du projet) :
 *   ddev exec "REF=58 vendor/bin/drush php-script scripts/verifications/lire_reference_bibcite.php"
 * Ne modifie rien. Sert a savoir comment construire une citation (auteurs, annee, titre, pages) pour l'export PDF.
 *
 * DEFINITIONS
 * notice bibcite : une reference bibliographique (entite bibcite_reference) : titre, auteurs, annee, etc.
 * service : objet fourni par un module, appelable par son identifiant (ici ceux dont le nom contient "bibcite").
 */
$id = (int) (getenv('REF') ?: 58);
$ref = \Drupal::entityTypeManager()->getStorage('bibcite_reference')->load($id);
if (!$ref) { echo "notice $id introuvable\n"; return; }
echo "notice $id : " . $ref->label() . " (type " . $ref->bundle() . ")\n\nchamps non vides :\n";
foreach ($ref->getFields() as $nom => $champ) {
  if ($champ->isEmpty()) { continue; }
  $type = $champ->getFieldDefinition()->getType();
  $vals = [];
  foreach ($champ as $item) {
    $v = $item->getValue();
    $txt = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) $v;
    if ($type === 'entity_reference' && $item->entity) { $txt .= ' => ' . $item->entity->label(); }
    $vals[] = $txt;
  }
  echo sprintf("  %-26s %-18s %s\n", $nom, $type, mb_substr(implode(' | ', $vals), 0, 170));
}
echo "\nservices dont le nom contient bibcite :\n";
foreach (\Drupal::getContainer()->getServiceIds() as $sid) { if (stripos($sid, 'bibcite') !== FALSE) { echo "  $sid\n"; } }
