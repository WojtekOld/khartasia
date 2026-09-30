<?php

namespace Drupal\khartasia_core\Service;

use Drupal\Core\Entity\EntityInterface;

/**
 * Calcule les motifs de revue et le booléen dérivé sur `attestation`.
 * Rembourse la dette F13. Point G tranché : source_sans_type est
 * désormais persisté (pas seulement scanné en direct par le hub) —
 * ParcoursPapier::etat() interroge ce champ directement.
 */
class EntityReviewCalculator {

  private function valeursChamp(EntityInterface $entity, string $field): array {
    if (!$entity->hasField($field) || $entity->get($field)->isEmpty()) {
      return [];
    }
    return array_column($entity->get($field)->getValue(), 'value');
  }

  /**
   * Calcule et met à jour les motifs de revue et le booléen dérivé.
   * Recalcule TOUJOURS depuis l'état actuel de l'entité — jamais une
   * recopie aveugle de ce qui existait avant (source du bug D initial).
   */
  public function updateReviewStatus(EntityInterface $entity): void {
    if ($entity->getEntityTypeId() !== 'node' || $entity->bundle() !== 'attestation') {
      return;
    }

    $motifs_precedents = $this->valeursChamp($entity, 'field_att_revue_motifs');
    $motifs = [];

    // --- source_sans_type : recalculé à chaque sauvegarde, pas recopié ---
    $source_sans_type = $entity->hasField('field_att_source') && $entity->hasField('field_att_type_source')
      && !$entity->get('field_att_source')->isEmpty()
      && $entity->get('field_att_type_source')->isEmpty();
    if ($source_sans_type) {
      $motifs[] = 'source_sans_type';
    }

    // --- source_absente : attestation d'un papier sans aucune source ---
    // (hors perimetre : les attestations sans papier, ex. caracterisations de plantes)
    $source_absente = $entity->hasField('field_att_papier') && $entity->hasField('field_att_source')
      && !$entity->get('field_att_papier')->isEmpty()
      && $entity->get('field_att_source')->isEmpty();
    if ($source_absente) {
      $motifs[] = 'source_absente';
    }

    // --- dispute_signalee : préservé tant que non résolu ---
    $dispute_resolue = $entity->hasField('field_att_concurrente')
      && !$entity->get('field_att_concurrente')->isEmpty();
    if (in_array('dispute_signalee', $motifs_precedents, TRUE) && !$dispute_resolue) {
      $motifs[] = 'dispute_signalee';
    }

    if ($entity->hasField('field_att_revue_motifs')) {
      $entity->set('field_att_revue_motifs', array_values(array_unique($motifs)));
    }
    if ($entity->hasField('field_att_a_revoir')) {
      $entity->set('field_att_a_revoir', !empty($motifs));
    }
  }

}
