<?php

namespace Drupal\khartasia_guided_forms\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\khartasia_guided_forms\Policy\AttestationPolicy as P;

/**
 * Hooks du lot 6 : le « bloc dispute » du formulaire d'attestation, par rôle.
 *
 * DÉFINITIONS
 * - Bloc dispute : attestation concurrente, nature de l'écart, note de comparaison, et le statut
 *   « réfuté ».
 * - Signaler une dispute (tout éditeur) : case « Dispute signalée » ; elle pose le motif de revue
 *   « dispute_signalee », qui tombe tout seul quand une attestation concurrente est choisie.
 * - Trancher une dispute (permission « administer attestation disputes ») : choisir la
 *   concurrente, qualifier l'écart, réfuter. Les autres voient ces champs en lecture seule
 *   (ou ne les voient pas s'ils sont vides). La règle est aussi appliquée côté serveur
 *   (AttestationDisputeConstraint).
 */
class AttestationDisputeHooks {

  #[Hook('entity_type_alter')]
  public function typeAlter(array &$entity_types): void {
    if (isset($entity_types['node'])) {
      $entity_types['node']->addConstraint('KhartasiaAttestationDispute');
    }
  }

  #[Hook('entity_extra_field_info')]
  public function extraFields(): array {
    return ['node' => ['attestation' => ['form' => ['dispute_signalee' => [
      'label' => t('Dispute signalée'),
      'description' => t('Case qui pose ou retire le motif de revue « dispute signalée ».'),
      'weight' => 7,
      'visible' => TRUE,
    ]]]]];
  }

  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (!in_array($form_id, ['node_attestation_form', 'node_attestation_edit_form'], TRUE)) {
      return;
    }
    $node = $form_state->getFormObject()->getEntity();
    $peut_trancher = \Drupal::currentUser()->hasPermission('administer attestation disputes');

    // Case de signalement, ouverte à tous les éditeurs.
    $motifs = array_column($node->get('field_att_revue_motifs')->getValue(), 'value');
    $form['dispute_signalee'] = [
      '#type' => 'checkbox',
      '#title' => t('Dispute signalée'),
      '#description' => t("Cochez si cette attestation vous paraît contredite ou remplacée par une autre. Un responsable des disputes désignera l'attestation concurrente."),
      '#default_value' => in_array('dispute_signalee', $motifs, TRUE),
      '#parents' => ['dispute_signalee'],
      '#weight' => 7,
    ];
    $form['#entity_builders'][] = [static::class, 'poserMotifDispute'];

    if ($peut_trancher) {
      $this->limiterConcurrentes($form, $node);
      return;
    }

    // Sans la permission : lecture seule, ou masqué si vide.
    foreach (['field_att_concurrente', 'field_att_relation_ecart', 'field_att_comparaison_note'] as $f) {
      if (!isset($form[$f])) {
        continue;
      }
      if ($node->hasField($f) && $node->get($f)->isEmpty()) {
        $form[$f]['#access'] = FALSE;
      }
      else {
        $form[$f]['#disabled'] = TRUE;
      }
    }
    // Le statut « réfuté » n'est proposé qu'à qui peut trancher (ou s'il est déjà posé).
    if (isset($form['field_att_statut']['widget']['#options']) && P::valeur($node, 'field_att_statut') !== 'refute') {
      unset($form['field_att_statut']['widget']['#options']['refute']);
    }
  }

  /**
   * Restreint l'autocomplétion de l'attestation concurrente au même papier.
   */
  protected function limiterConcurrentes(array &$form, EntityInterface $node): void {
    if (!isset($form['field_att_concurrente']['widget'])) {
      return;
    }
    $papier = P::cibles($node, 'field_att_papier');
    $reglages = [
      'target_bundles' => ['attestation'],
      'papier_nid' => $papier ? (int) $papier[0] : 0,
      'exclure_nid' => $node->id() ? (int) $node->id() : 0,
    ];
    foreach ($form['field_att_concurrente']['widget'] as $delta => &$element) {
      if (!is_numeric($delta) || !isset($element['target_id'])) {
        continue;
      }
      $element['target_id']['#selection_handler'] = 'khartasia_concurrente';
      $element['target_id']['#selection_settings'] = $reglages;
      $element['target_id']['#description'] = $papier
        ? t('Attestations du même papier seulement.')
        : t("Cette attestation n'a pas de papier : toutes les attestations sont proposées.");
    }
  }

  /**
   * Avant l'enregistrement : pose ou retire le motif « dispute_signalee » selon la case.
   */
  public static function poserMotifDispute(string $entity_type, EntityInterface $entity, array &$form, FormStateInterface $form_state): void {
    if ($entity->bundle() !== 'attestation' || !$entity->hasField('field_att_revue_motifs')) {
      return;
    }
    $motifs = array_column($entity->get('field_att_revue_motifs')->getValue(), 'value');
    $motifs = array_values(array_diff($motifs, ['dispute_signalee']));
    if ($form_state->getValue('dispute_signalee')) {
      $motifs[] = 'dispute_signalee';
    }
    $entity->set('field_att_revue_motifs', array_map(fn($m) => ['value' => $m], $motifs));
  }

}
