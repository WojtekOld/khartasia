<?php

namespace Drupal\khartasia_guided_forms\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\khartasia_guided_forms\Policy\AttestationPolicy as P;

/**
 * Hooks des lots 3b et 3c (déclarés par attribut, sans toucher au fichier .module).
 *
 * DÉFINITIONS (lot 3c)
 * - Brouillon : attestation non publiée. Le bouton « Enregistrer le brouillon » ne publie jamais.
 * - Publier : bouton réservé à qui peut modifier n'importe quelle attestation. Il fait passer le
 *   statut à « publié » AVANT la validation, ce qui déclenche les règles de publication
 *   (source, rôle, locateur…) ; si l'une manque, rien n'est enregistré et le message
 *   s'affiche sur le champ concerné.
 */
class AttestationHooks {

  /**
   * Déclare les deux contraintes sur le type d'entité « node » (chacune filtre son bundle).
   */
  #[Hook('entity_type_alter')]
  public function typeAlter(array &$entity_types): void {
    if (isset($entity_types['node'])) {
      $entity_types['node']->addConstraint('KhartasiaAttestationIntegrite');
      $entity_types['node']->addConstraint('KhartasiaAttestationPublication');
    }
  }

  /**
   * Déduit l'axe de la fonction d'intrant (une seule saisie : la fonction).
   *
   * Si la fonction est connue de la table FONCTION_VERS_AXE et que l'axe correspondant
   * existe, il remplace l'axe saisi ; sinon l'axe n'est pas touché.
   */
  #[Hook('node_presave')]
  public function presave(EntityInterface $node): void {
    if ($node->bundle() !== 'attestation' || !$node->hasField('field_att_fonction') || !$node->hasField('field_att_axe')) {
      return;
    }
    $f = P::cibles($node, 'field_att_fonction');
    if (!$f || !P::aUnIntrant($node)) {
      return;
    }
    $axe_nom = P::axeDepuisFonction(P::nomTerme($f[0]));
    if (!$axe_nom) {
      return;
    }
    $ids = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->getQuery()->accessCheck(FALSE)
      ->condition('vid', 'attestation_axe')->condition('name', $axe_nom)->range(0, 1)->execute();
    if ($ids) {
      $node->set('field_att_axe', ['target_id' => (int) reset($ids)]);
    }
  }

  /**
   * Formulaire d'attestation : boutons Brouillon / Publier pour un contenu non publié.
   *
   * Pour un contenu déjà publié, le formulaire reste celui de Drupal (case « Publié »).
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (!in_array($form_id, ['node_attestation_form', 'node_attestation_edit_form'], TRUE)) {
      return;
    }
    $node = $form_state->getFormObject()->getEntity();
    if ($node->isPublished() || !isset($form['actions']['submit'])) {
      return;
    }
    // Le statut ne se choisit plus par une case : il découle du bouton cliqué.
    $form['status']['#access'] = FALSE;
    $form['actions']['submit']['#value'] = t('Enregistrer le brouillon');

    $peut_publier = \Drupal::currentUser()->hasPermission('edit any attestation content')
      || \Drupal::currentUser()->hasPermission('administer nodes');
    if ($peut_publier) {
      $form['actions']['publier'] = [
        '#type' => 'submit',
        '#name' => 'khartasia_publier',
        '#value' => t('Publier'),
        '#submit' => $form['actions']['submit']['#submit'],
        '#button_type' => 'primary',
        '#weight' => ($form['actions']['submit']['#weight'] ?? 0) + 1,
      ];
      $form['actions']['submit']['#button_type'] = '';
      // Exécuté AVANT la validation du formulaire : le statut vaut déjà « publié » quand les
      // contraintes sont vérifiées.
      array_unshift($form['#validate'], [static::class, 'demandePublication']);
    }
  }

  /**
   * Si le bouton « Publier » est cliqué, fixe le statut à publié avant la validation.
   */
  public static function demandePublication(array &$form, FormStateInterface $form_state): void {
    $declencheur = $form_state->getTriggeringElement();
    if (($declencheur['#name'] ?? '') === 'khartasia_publier') {
      $form_state->setValue(['status', 'value'], 1);
    }
  }

}
