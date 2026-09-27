<?php

namespace Drupal\khartasia_guided_forms\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

/**
 * Étape 1 du parcours guidé : la fiche Papier de base.
 *
 * v2 — consolidée :
 *  - Synonymes / Écriture locale ne s'écrivent plus dans field_pap_synonyme
 *    / field_pap_local_scripture (champs texte libre que toute
 *    l'architecture nom_papier a été construite pour remplacer — les y
 *    écrire à chaque création annule le travail de migration en cours).
 *    Chaque ligne devient une entité nom_papier : couche détectée par
 *    présence de sinogrammes (même heuristique que detect_couche() du
 *    scaffold), relation = relation_non_tranchee (provisoire, à retyper),
 *    field_np_a_revoir = 1 — cohérent avec le traitement de la file de
 *    revue existante, pas un nouveau mécanisme.
 *  - try/catch autour de chaque Node::create()->save().
 */
class PapierBaseForm extends FormBase {

  public function getFormId() {
    return 'khartasia_papier_base_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'khartasia_guided_forms/guided-form';

    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => '<div class="kh-guide__intro">
        <span class="kh-guide__eyebrow">Saisie guidée · Étape 1</span>
        <h1 class="kh-guide__title">Nouveau Papier</h1>
        <p class="kh-guide__lead">Commençons par l\'identité de base de ce Papier. Les attestations détaillées (datation, composition, usage) viendront ensuite, une fois cette fiche créée.</p>
      </div>',
    ];

    $form['container'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['kh-guide__form']],
    ];

    $form['container']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nom du Papier'),
      '#description' => $this->t('Le nom sous lequel ce papier est le mieux identifié — romanisation ou nom usuel. Deviendra le nom canonique.'),
      '#required' => TRUE,
      '#attributes' => ['class' => ['kh-guide__input']],
    ];

    $form['container']['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Présentation générale — matière, fabrication, contexte historique. Les faits précis et sourcés seront ajoutés plus tard sous forme d\'attestations.'),
      '#rows' => 6,
      '#attributes' => ['class' => ['kh-guide__input']],
    ];

    $form['container']['composition'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Composition (plante source)'),
      '#description' => $this->t('La ou les plantes dont ce papier est fabriqué. Pour en ajouter plusieurs : validez la première suggestion, puis tapez une virgule et continuez à saisir le nom suivant dans le même champ. À l\'étape suivante, vous désignerez laquelle sert de fibre principale et lesquelles servent de colorant ou d\'agent de traitement.'),
      '#target_type' => 'node',
      '#selection_settings' => ['target_bundles' => ['article']],
      '#tags' => TRUE,
      '#maxlength' => 2048,
      '#attributes' => ['class' => ['kh-guide__input']],
    ];

    $pays_options = ['_none' => $this->t('— Non renseigné —')];
    $termes_pays = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'pays']);
    foreach ($termes_pays as $t) {
      $label = $t->label();
      if (preg_match('/\p{Han}|\p{Hiragana}|\p{Katakana}|\p{Hangul}/u', $label)) continue;
      if (in_array($label, ['China', 'Japan', 'Korea', 'Thailand'])) continue;
      $pays_options[$t->id()] = $label;
    }
    asort($pays_options);

    $form['container']['origine'] = [
      '#type' => 'select',
      '#title' => $this->t('Origine'),
      '#options' => $pays_options,
      '#attributes' => ['class' => ['kh-guide__input']],
    ];

    $form['container']['scripture'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Écriture locale'),
      '#description' => $this->t('Une variante d\'écriture par ligne (ex. caractères originaux, une entrée par ligne). Chaque ligne devient un nom de papier (couche graphie), à retyper précisément plus tard.'),
      '#rows' => 3,
      '#attributes' => ['class' => ['kh-guide__input']],
    ];

    $form['container']['synonymes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Synonymes'),
      '#description' => $this->t('Un synonyme par ligne (romanisation, autre nom historique...). Chaque ligne devient un nom de papier, à retyper précisément plus tard.'),
      '#rows' => 3,
      '#attributes' => ['class' => ['kh-guide__input']],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Créer et continuer →'),
      '#attributes' => ['class' => ['kh-guide__submit']],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validation minimale au-delà du #required natif ; les règles plus
    // fines (cohérence origine/composition, etc.) pourront être ajoutées
    // une fois les premiers cas réels testés.
  }

  /**
   * Détecte la couche (graphie/romanisation) par présence de sinogrammes —
   * même heuristique que detect_couche() du scaffold. Reste une heuristique,
   * pas une certitude : field_np_a_revoir=1 posé systématiquement.
   */
  private function detecterCouche(string $valeur): string {
    return preg_match('/\p{Han}/u', $valeur) ? 'graphie' : 'romanisation';
  }

  /**
   * Crée une entité nom_papier à partir d'une ligne de texte libre —
   * relation provisoire, signalée pour revue, jamais canonique.
   */
  private function creerNomPapierProvisoire(string $valeur, int $papier_nid, ?string $couche_forcee, array &$erreurs): void {
    if (!\Drupal::entityTypeManager()->getStorage('node_type')->load('nom_papier')) {
      return; // module khartasia_noms pas encore installé — silencieux, pas bloquant pour ce formulaire
    }
    $couche = $couche_forcee ?? $this->detecterCouche($valeur);
    $rel = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'type_de_nom_papier', 'name' => 'relation_non_tranchee']);

    $values = [
      'type' => 'nom_papier',
      'title' => mb_substr($valeur, 0, 255),
      'field_np_valeur' => $valeur,
      'field_np_couche' => $couche,
      'field_np_canonique' => 0,
      'field_np_statut' => 'courant',
      'field_np_papier' => $papier_nid,
      'status' => 1,
    ];
    if ($rel) { $values['field_np_relation'] = ['target_id' => reset($rel)->id()]; }

    try {
      $n = Node::create($values);
      if ($n->hasField('field_np_a_revoir')) { $n->set('field_np_a_revoir', TRUE); }
      $n->save();
    }
    catch (\Exception $e) {
      $erreurs[] = "nom_papier \"$valeur\" : " . $e->getMessage();
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $erreurs = [];

    $node = Node::create([
      'type' => 'papers',
      'title' => $values['title'],
      'langcode' => 'fr',
      'status' => 0, // brouillon par défaut — publication explicite après relecture
    ]);

    if (!empty($values['body'])) {
      $node->set('body', ['value' => $values['body'], 'format' => 'full_html']);
    }

    if (!empty($values['composition'])) {
      $cibles = [];
      foreach ($values['composition'] as $item) {
        if (!empty($item['target_id'])) {
          $cibles[] = ['target_id' => $item['target_id']];
        }
      }
      if ($cibles) {
        $node->set('field_genus_pap_entity', $cibles);
      }
    }

    if (!empty($values['origine']) && $values['origine'] !== '_none') {
      $node->set('field_origin_taxo', ['target_id' => $values['origine']]);
    }

    try {
      $node->save();
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError($this->t('Échec de la création de la fiche : @msg', ['@msg' => $e->getMessage()]));
      return;
    }

    // Écriture locale -> nom_papier, couche graphie forcée (champ dédié à ça).
    if (!empty($values['scripture'])) {
      foreach (array_filter(array_map('trim', explode("\n", $values['scripture']))) as $ligne) {
        $this->creerNomPapierProvisoire($ligne, (int) $node->id(), 'graphie', $erreurs);
      }
    }

    // Synonymes -> nom_papier, couche détectée par ligne (mélange possible
    // romanisation/graphie selon ce que l'utilisateur tape).
    if (!empty($values['synonymes'])) {
      foreach (array_filter(array_map('trim', explode("\n", $values['synonymes']))) as $ligne) {
        $this->creerNomPapierProvisoire($ligne, (int) $node->id(), NULL, $erreurs);
      }
    }

    if ($erreurs) {
      \Drupal::messenger()->addWarning($this->t('Fiche créée, mais @n nom(s) n\'ont pas pu être enregistrés : @detail', [
        '@n' => count($erreurs), '@detail' => implode(' ; ', $erreurs),
      ]));
    }
    \Drupal::messenger()->addStatus($this->t('Fiche « @titre » créée (brouillon). Passons aux attestations.', ['@titre' => $node->label()]));

    $form_state->setRedirect('khartasia_guided_forms.papier_attestations', ['papier_nid' => $node->id()]);
  }

}
