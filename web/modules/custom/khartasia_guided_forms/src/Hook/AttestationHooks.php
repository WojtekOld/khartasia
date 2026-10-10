<?php

namespace Drupal\khartasia_guided_forms\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
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

    // Case « Précision suspecte » (pas un champ : elle pose ou retire un motif de revue).
    $motifs = array_column($node->get('field_att_revue_motifs')->getValue(), 'value');
    $form['precision_suspecte'] = [
      '#type' => 'checkbox',
      '#title' => t('Précision suspecte'),
      '#description' => t("Cochez si l'énoncé paraît plus précis que ce que la source permet (valeur chiffrée, date exacte, proportion sans méthode). L'attestation est alors signalée à la revue."),
      '#default_value' => in_array('precision_suspecte', $motifs, TRUE),
      '#parents' => ['precision_suspecte'],
      '#weight' => 6,
    ];
    $form['#entity_builders'][] = [static::class, 'poserMotifPrecision'];

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


  /**
   * Déclare la case « Précision suspecte » comme champ supplémentaire du formulaire,
   * pour qu'elle puisse être rangée dans un onglet (field_group).
   */
  #[Hook('entity_extra_field_info')]
  public function extraFields(): array {
    return ['node' => ['attestation' => ['form' => ['precision_suspecte' => [
      'label' => t('Précision suspecte'),
      'description' => t('Case qui pose ou retire le motif de revue « précision suspecte ».'),
      'weight' => 6,
      'visible' => TRUE,
    ]]]]];
  }

  /**
   * Avant l'enregistrement : pose ou retire le motif selon la case.
   */
  public static function poserMotifPrecision(string $entity_type, EntityInterface $entity, array &$form, FormStateInterface $form_state): void {
    if ($entity->bundle() !== 'attestation' || !$entity->hasField('field_att_revue_motifs')) {
      return;
    }
    $motifs = array_column($entity->get('field_att_revue_motifs')->getValue(), 'value');
    $motifs = array_values(array_diff($motifs, ['precision_suspecte']));
    if ($form_state->getValue('precision_suspecte')) {
      $motifs[] = 'precision_suspecte';
    }
    $entity->set('field_att_revue_motifs', array_map(fn($m) => ['value' => $m], $motifs));
  }

  /**
   * Pré-remplissage simple d'un nouveau contenu depuis l'adresse (lot 4).
   *
   *  - nom commun : ?plante=NID (article) → plante liée ; ?materiau=TID → matériau désigné ;
   *  - nom de papier : ?papier=NID (papers) → papier concerné.
   * Les identifiants invalides sont ignorés sans erreur. Le paramètre ?edit[...] de Drupal 8+
   * n'existe plus : ce hook en tient lieu.
   */
  #[Hook('entity_prepare_form')]
  public function preRemplir(EntityInterface $entity, string $operation, FormStateInterface $form_state): void {
    if (!$entity instanceof NodeInterface || !$entity->isNew()) {
      return;
    }
    $q = \Drupal::request()->query;
    $noeud = fn($id, $type) => ($id && ctype_digit((string) $id) && ($n = \Drupal::entityTypeManager()->getStorage('node')->load((int) $id)) && $n->bundle() === $type) ? (int) $n->id() : NULL;

    if ($entity->bundle() === 'common_names') {
      if ($entity->get('field_genus_com_entity')->isEmpty() && ($nid = $noeud($q->get('plante'), 'article'))) {
        $entity->set('field_genus_com_entity', [['target_id' => $nid]]);
      }
      $tid = $q->get('materiau');
      if ($entity->hasField('field_materiau_ref') && $entity->get('field_materiau_ref')->isEmpty() && $tid && ctype_digit((string) $tid)) {
        $t = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load((int) $tid);
        if ($t && $t->bundle() === 'materiau') {
          $entity->set('field_materiau_ref', [['target_id' => (int) $t->id()]]);
        }
      }
    }
    elseif ($entity->bundle() === 'nom_papier' && $entity->hasField('field_np_papier')) {
      if ($entity->get('field_np_papier')->isEmpty() && ($nid = $noeud($q->get('papier'), 'papers'))) {
        $entity->set('field_np_papier', [['target_id' => $nid]]);
      }
    }
  }

}
