<?php
use Drupal\node\Entity\Node;

// Grammage Jiajiang — retirer le chiffre non étayé
$ids = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('title', 'Composition (Grammage) — Papier de Jiajiang')->accessCheck(FALSE)->execute();
foreach (Node::loadMultiple($ids) as $att) {
  $att->set('field_att_valeur', "Aucune valeur normative publiée trouvée pour Jiajiang spécifiquement. La norme nationale GB/T 22828-2008 (書畫紙) s'applique à la catégorie, mais son tableau d'indicateurs (quantité, blancheur, longueur de rupture, encollage) n'a pas été extrait à ce stade. Les chiffres \"31-36 g/m²\" parfois avancés ne sont pas étayés par une source vérifiée — retirés de cette fiche.");
  $att->set('field_att_statut', 'inconnu');
  $att->save();
  echo "Grammage Jiajiang corrigé (chiffre non étayé retiré).\n";
}

// Blancheur Jiajiang — même correction
$ids2 = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('title', 'Composition (Blancheur) — Papier de Jiajiang')->accessCheck(FALSE)->execute();
foreach (Node::loadMultiple($ids2) as $att) {
  $att->set('field_att_valeur', "Aucune valeur publiée vérifiée. Le chiffre \"85-90%\" parfois avancé n'est pas étayé par une source fiable identifiée à ce stade — retiré de cette fiche plutôt que reconduit sans vérification.");
  $att->set('field_att_statut', 'inconnu');
  $att->save();
  echo "Blancheur Jiajiang corrigée (chiffre non étayé retiré).\n";
}
