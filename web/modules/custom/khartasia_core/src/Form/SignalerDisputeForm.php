<?php

namespace Drupal\khartasia_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;

/**
 * Formulaire dédié — "Signaler une dispute" sur une attestation existante.
 * Un seul paramètre (l'attestation elle-même, résolue par la route).
 * Rôle contributeur : signale seulement. Rôle éditeur : voit et peut
 * trancher directement (lien vers le bloc complet, pas dupliqué ici).
 */
class SignalerDisputeForm extends FormBase {

  public function getFormId() {
    return 'khartasia_core_signaler_dispute';
  }

  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $attestation = NULL) {
    if (!$attestation || $attestation->bundle() !== 'attestation') {
      $form['erreur'] = ['#markup' => '<p>' . $this->t('Attestation introuvable.') . '</p>'];
      return $form;
    }
    $form['attestation_nid'] = ['#type' => 'value', '#value' => $attestation->id()];

    $valeur = $attestation->hasField('field_att_valeur') && !$attestation->get('field_att_valeur')->isEmpty()
      ? $attestation->get('field_att_valeur')->value : $attestation->getTitle();

    $form['contexte'] = [
      '#markup' => '<p>' . $this->t('Attestation concernée : <em>@valeur</em>', ['@valeur' => mb_substr($valeur, 0, 150)]) . '</p>',
    ];

    $hasDisputeAdmin = \Drupal::currentUser()->hasPermission('administer attestation disputes');

    if (!$hasDisputeAdmin) {
      $form['detail'] = [
        '#type' => 'textarea',
        '#title' => $this->t('En quoi cette attestation entre-t-elle en contradiction avec une autre ?'),
        '#required' => TRUE,
        '#description' => $this->t('Texte libre — un éditeur tranchera ensuite la relation exacte et la source concurrente.'),
      ];
    }
    else {
      $form['field_att_concurrente'] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'node',
        '#selection_settings' => ['target_bundles' => ['attestation']],
        '#title' => $this->t('Attestation concurrente'),
        '#required' => TRUE,
      ];
      $ecart_options = ['_none' => $this->t('- Sélectionner -')];
      $storage = \Drupal\field\Entity\FieldStorageConfig::load('node.field_att_relation_ecart');
      if ($storage) {
        $ecart_options += $storage->getSetting('allowed_values');
      }
      $form['field_att_relation_ecart'] = [
        '#type' => 'select',
        '#title' => $this->t('Relation d\'écart'),
        '#options' => $ecart_options,
        '#required' => TRUE,
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $hasDisputeAdmin ? $this->t('Enregistrer la relation') : $this->t('Signaler'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $attestation = \Drupal\node\Entity\Node::load($form_state->getValue('attestation_nid'));
    if (!$attestation) {
      return;
    }
    $hasDisputeAdmin = \Drupal::currentUser()->hasPermission('administer attestation disputes');

    if (!$hasDisputeAdmin) {
      $motifs = $attestation->hasField('field_att_revue_motifs') && !$attestation->get('field_att_revue_motifs')->isEmpty()
        ? array_column($attestation->get('field_att_revue_motifs')->getValue(), 'value') : [];
      if (!in_array('dispute_signalee', $motifs, TRUE)) {
        $motifs[] = 'dispute_signalee';
      }
      $attestation->set('field_att_revue_motifs', $motifs);
      $attestation->set('field_att_note_interne', $form_state->getValue('detail'));
      \Drupal::messenger()->addStatus($this->t('Signalement enregistré — un éditeur tranchera.'));
    }
    else {
      $attestation->set('field_att_concurrente', [['target_id' => $form_state->getValue('field_att_concurrente')]]);
      $attestation->set('field_att_relation_ecart', $form_state->getValue('field_att_relation_ecart'));
      \Drupal::messenger()->addStatus($this->t('Relation enregistrée.'));
      // field_att_revue_motifs se corrige automatiquement au save() via
      // hook_node_presave() (khartasia_core) — le retrait de
      // dispute_signalee est arbitré là, pas ici.
    }

    // hook_node_presave() recalcule field_att_revue_motifs/field_att_a_revoir
    // à ce save() — aucun appel manuel au service ici.
    $attestation->save();

    $papier_nid = $attestation->hasField('field_att_papier') && !$attestation->get('field_att_papier')->isEmpty()
      ? $attestation->get('field_att_papier')->target_id : NULL;
    if ($papier_nid) {
      $form_state->setRedirect('entity.node.canonical', ['node' => $papier_nid]);
    }
  }

}
