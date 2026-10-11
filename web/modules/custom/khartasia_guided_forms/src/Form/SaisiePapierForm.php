<?php

namespace Drupal\khartasia_guided_forms\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Sélecteur de papier de la page « Saisie guidée » : on choisit un papier, puis le formulaire à ouvrir.
 */
class SaisiePapierForm extends FormBase {

  public function getFormId() {
    return 'khartasia_saisie_papier';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['papier'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Papier'),
      '#target_type' => 'node',
      '#selection_settings' => ['target_bundles' => ['papers']],
      '#required' => TRUE,
      '#size' => 40,
      '#description' => $this->t('Tapez les premières lettres du nom du papier.'),
      '#value_callback' => [PapierRecetteForm::class, 'valeurSansEspaces'],
    ];
    $form['actions'] = ['#type' => 'actions'];
    $peut = $this->currentUser()->hasPermission('create attestation content');
    if ($peut) {
      $form['actions']['recette'] = ['#type' => 'submit', '#name' => 'recette', '#value' => $this->t('Saisir la recette (série)'), '#button_type' => 'primary'];
      $form['actions']['attestation'] = ['#type' => 'submit', '#name' => 'attestation', '#value' => $this->t('Nouvelle attestation sur ce papier')];
    }
    $form['actions']['dossier'] = ['#type' => 'submit', '#name' => 'dossier', '#value' => $this->t('Ouvrir le dossier du papier')];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $nid = (int) $form_state->getValue('papier');
    $bouton = $form_state->getTriggeringElement()['#name'] ?? 'dossier';
    $url = match ($bouton) {
      'recette' => Url::fromUserInput("/papier/$nid/recette"),
      'attestation' => Url::fromUserInput('/khartasia/attestation/nouvelle', ['query' => ['papier' => $nid]]),
      default => Url::fromUserInput("/node/$nid/dossier"),
    };
    $form_state->setRedirectUrl($url);
  }

}
