<?php

namespace Drupal\khartasia_noms\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Wizard multistep pour la saisie de nom_papier.
 *
 * Six étapes (0-5). Verrous C1-C4 appliqués côté serveur, jamais seulement
 * en #states. Corrections L1-L5 / F12 / F13 et escalade vers Attestation
 * documentées dans Formulaire_saisie_nom_papier_v2.md — cette classe en est
 * l'implémentation, à recouper avec votre schéma réel avant mise en prod
 * (noms de vocabulaires, présence de field_np_revue_motifs/field_np_notes).
 */
class NomPapierWizardForm extends FormBase {

  const ETAPES = [
    'Contexte', 'Valeur et couche', 'Détail', 'Identité', 'Statut', 'Source et récapitulatif',
  ];

  public function getFormId() {
    return 'khartasia_noms_wizard';
  }

  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    if (!$node || $node->bundle() !== 'papers') {
      $form['erreur'] = ['#markup' => '<p>' . $this->t('Fiche papier introuvable ou incorrecte.') . '</p>'];
      return $form;
    }
    $form_state->set('papers_nid', $node->id());
    $form_state->set('papers_titre', $node->getTitle());

    $step = $form_state->get('step');
    if ($step === NULL) {
      $step = 0;
      $form_state->set('step', 0);
    }

    $form['progress'] = [
      '#theme' => 'khartasia_noms_progress',
      '#etapes' => self::ETAPES,
      '#etape_actuelle' => $step,
      '#etapes_urls' => array_fill(0, count(self::ETAPES), NULL),
    ];

    switch ($step) {
      case 0:
        $this->buildStep0Contexte($form, $form_state, $node);
        break;

      case 1:
        $this->buildStep1ValeurCouche($form, $form_state);
        break;

      case 2:
        $this->buildStep2Detail($form, $form_state);
        break;

      case 3:
        $this->buildStep3Identite($form, $form_state);
        break;

      case 4:
        $this->buildStep4Statut($form, $form_state);
        break;

      case 5:
        $this->buildStep5SourceRecap($form, $form_state);
        break;
    }

    $form['actions'] = ['#type' => 'actions'];
    if ($step > 0) {
      $form['actions']['retour'] = [
        '#type' => 'submit',
        '#value' => $this->t('Retour'),
        '#submit' => ['::retour'],
        '#limit_validation_errors' => [],
      ];
    }
    $form['actions']['continuer'] = [
      '#type' => 'submit',
      '#value' => $step === 5 ? $this->t('Enregistrer') : ($step === 0 ? $this->t('Commencer') : $this->t('Continuer')),
    ];

    return $form;
  }

  // --- Étapes -----------------------------------------------------------

  protected function buildStep0Contexte(array &$form, FormStateInterface $form_state, NodeInterface $node) {
    $form['contexte'] = [
      '#theme' => 'khartasia_noms_existing',
      '#papier_titre' => $node->getTitle(),
      '#papier_url' => $node->toUrl()->toString(),
      '#noms' => $this->chargerNomsExistants($node->id()),
    ];
  }

  protected function buildStep1ValeurCouche(array &$form, FormStateInterface $form_state) {
    $form['valeur'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Forme du nom'),
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('valeur'),
      '#attributes' => ['data-drupal-selector' => 'edit-valeur'],
      '#ajax' => ['callback' => '::checkDoublon', 'event' => 'change', 'wrapper' => 'khartasia-doublon'],
    ];
    $form['doublon'] = ['#type' => 'container', '#attributes' => ['id' => 'khartasia-doublon']];
    $form['couche'] = [
      '#type' => 'radios',
      '#title' => $this->t('Couche'),
      '#required' => TRUE,
      '#options' => [
        'graphie' => $this->t('Graphie (sinogrammes)'),
        'romanisation' => $this->t('Romanisation'),
        'traduction' => $this->t('Traduction'),
      ],
      '#default_value' => $form_state->getValue('couche'),
      '#description' => $this->t('Détection automatique au blur — corrigez si besoin.'),
    ];
    $form['couche_suggeree'] = [
      '#type' => 'hidden',
      '#default_value' => $form_state->getValue('couche_suggeree'),
    ];
    $form['#attached']['library'][] = 'khartasia_noms/couche-detect';
  }

  protected function buildStep2Detail(array &$form, FormStateInterface $form_state) {
    $couche = $form_state->getValue('couche');
    $map = [
      'graphie' => ['type_ecriture', $this->t('Écriture')],
      'romanisation' => ['type_romanisation', $this->t('Système de romanisation')],
      'traduction' => ['languages', $this->t('Langue')],
    ];
    if (!isset($map[$couche])) {
      $form['erreur_couche'] = ['#markup' => '<p>' . $this->t('Couche non renseignée — utilisez « Retour ».') . '</p>'];
      return;
    }
    [$vocab, $label] = $map[$couche];
    $form['detail'] = [
      '#type' => 'select',
      '#title' => $label,
      '#empty_option' => $this->t('— non précisé —'),
      '#options' => $this->termesDuVocabulaire($vocab),
      '#default_value' => $form_state->getValue('detail'),
      '#description' => $this->t('Non obligatoire — le vide laisse une trace en revue, mieux vide que faux.'),
    ];
  }

  protected function buildStep3Identite(array &$form, FormStateInterface $form_state) {
    $form['canonique'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Nom canonique de ce papier'),
      '#default_value' => $form_state->getValue('canonique') ?? 0,
      '#ajax' => ['callback' => '::checkCanoniqueExistant', 'event' => 'change', 'wrapper' => 'khartasia-canonique-warning'],
    ];
    $form['canonique_warning'] = ['#type' => 'container', '#attributes' => ['id' => 'khartasia-canonique-warning']];

    $form['relation'] = [
      '#type' => 'select',
      '#title' => $this->t('Relation au canonique'),
      '#options' => $this->termesDuVocabulaire('type_de_nom_papier'),
      '#default_value' => $form_state->getValue('relation'),
      '#states' => ['invisible' => [':input[name="canonique"]' => ['checked' => TRUE]]],
    ];

    $marque_tid = $this->tidDe('marque_commerciale');
    if ($marque_tid) {
      $form['marque_warning'] = [
        '#markup' => '<div class="khartasia-warning" data-marque-warning="1"><p>'
          . $this->t('⚠ Classification orpheline : le référentiel producteur (paper_mill) est gelé — la maison émettrice ne pourra pas être liée.')
          . '</p></div>',
        '#states' => ['visible' => [':input[name="relation"]' => ['value' => $marque_tid]]],
      ];
    }
  }

  protected function buildStep4Statut(array &$form, FormStateInterface $form_state) {
    // L4 : "rejeté" réservé aux éditeurs — un contributeur ne peut que le proposer.
    $is_editeur = \Drupal::currentUser()->hasPermission('administer nom_papier statuts');
    $options = [
      'atteste' => $this->t('attesté'),
      'courant' => $this->t('courant'),
      'historique' => $this->t('historique'),
    ];
    if ($is_editeur) {
      $options['rejete'] = $this->t('rejeté (non documenté)');
    }
    // Si le delta 'hypothese' a été ajouté au schéma réel, le proposer aussi.
    $storage = FieldStorageConfig::load('node.field_np_statut');
    $allowed = $storage ? ($storage->getSetting('allowed_values') ?: []) : [];
    if (isset($allowed['hypothese'])) {
      $options['hypothese'] = $this->t('hypothèse (non étayée)');
    }

    $form['statut'] = [
      '#type' => 'select',
      '#title' => $this->t('Statut épistémique'),
      '#options' => $options,
      '#default_value' => $form_state->getValue('statut'),
      '#description' => $is_editeur ? '' : $this->t('Un statut « rejeté » nécessite une validation éditoriale — cochez la case ci-dessous si vous pensez ce nom non documenté.'),
    ];
    if (!$is_editeur) {
      $form['proposer_rejet'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Proposer ce nom comme rejeté (non documenté)'),
        '#default_value' => $form_state->getValue('proposer_rejet') ?? 0,
        '#description' => $this->t('Le nom sera enregistré avec le statut choisi ci-dessus et signalé à la file de revue éditoriale pour arbitrage.'),
      ];
    }
    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notes'),
      '#default_value' => $form_state->getValue('notes'),
      '#description' => $this->t('Justification, divergence de sources, question de terrain ouverte.'),
    ];
  }

  protected function buildStep5SourceRecap(array &$form, FormStateInterface $form_state) {
    if (\Drupal::entityTypeManager()->getDefinition('bibcite_reference', FALSE)) {
      $form['source'] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'bibcite_reference',
        '#title' => $this->t('Source'),
        '#description' => $this->t('Optionnelle — mais absente avec statut « attesté » est signalé à la revue.'),
      ];
    }
    else {
      $form['source_indisponible'] = [
        '#markup' => '<p><em>' . $this->t('Entité bibcite_reference indisponible sur ce site.') . '</em></p>',
      ];
    }

    // Escalade vers Attestation (§7 de la v2) pour les relations disputées.
    $relation_tid = $form_state->getValue('relation');
    $relations_disputees = array_filter([
      $this->tidDe('nom_concurrent_revendique'),
      $this->tidDe('appellation_commerciale'),
      $this->tidDe('corruption_phonetique'),
    ]);
    if ($relation_tid && in_array($relation_tid, $relations_disputees, TRUE)) {
      $papers_nid = $form_state->get('papers_nid');
      $url = Url::fromRoute('node.add', ['node_type' => 'attestation'], [
        'query' => ['edit[field_att_papier][0][target_id]' => $papers_nid],
      ])->toString();
      $form['escalade_attestation'] = [
        '#markup' => '<div class="khartasia-escalade"><p>' . $this->t(
          'Cette relation implique souvent plusieurs sources concurrentes. Le champ ci-dessus n\'en porte qu\'une. <a href=":url" target="_blank">Créer une Attestation liée (axe Nomenclature)</a> pour documenter la dispute avec toutes ses sources et son statut propre.',
          [':url' => $url]
        ) . '</p></div>',
      ];
    }

    $rows = [
      [$this->t('Valeur'), $form_state->getValue('valeur')],
      [$this->t('Couche'), $form_state->getValue('couche')],
      [$this->t('Détail'), $form_state->getValue('detail') ?: $this->t('— non précisé —')],
      [$this->t('Canonique'), $form_state->getValue('canonique') ? $this->t('Oui') : $this->t('Non')],
      [
        $this->t('Relation'),
        $form_state->getValue('canonique') ? $this->t('(vide — canonique, cf. C2)') : ($this->libelleTerme($relation_tid) ?: $this->t('— non précisée —')),
      ],
      [$this->t('Statut'), $form_state->getValue('statut')],
    ];
    $form['recapitulatif'] = [
      '#type' => 'table',
      '#header' => [$this->t('Champ'), $this->t('Valeur qui sera enregistrée')],
      '#rows' => $rows,
    ];
  }

  // --- Callbacks AJAX -----------------------------------------------------

  public function checkDoublon(array &$form, FormStateInterface $form_state) {
    $valeur = $form_state->getValue('valeur');
    $couche = $form_state->getValue('couche') ?: 'graphie';
    $papers_nid = $form_state->get('papers_nid');

    $existing = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'nom_papier')
      ->condition('field_np_papier', $papers_nid)
      ->condition('field_np_valeur', $valeur)
      ->condition('field_np_couche', $couche)
      ->execute();

    if ($existing) {
      $nid = reset($existing);
      $url = Node::load($nid)->toUrl()->toString();
      $form['doublon']['#markup'] = '<p class="khartasia-warning">' . $this->t('Ce nom existe déjà pour ce papier sur cette couche. <a href=":url">Voir la fiche existante</a>.', [':url' => $url]) . '</p>';
    }
    else {
      $form['doublon']['#markup'] = '';
    }
    return $form['doublon'];
  }

  public function checkCanoniqueExistant(array &$form, FormStateInterface $form_state) {
    $papers_nid = $form_state->get('papers_nid');
    if (!$form_state->getValue('canonique')) {
      $form['canonique_warning']['#markup'] = '';
      return $form['canonique_warning'];
    }
    $existing = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'nom_papier')
      ->condition('field_np_papier', $papers_nid)
      ->condition('field_np_canonique', 1)
      ->execute();

    if ($existing) {
      $titre = $form_state->get('papers_titre');
      $form['canonique_warning']['#markup'] = '<p class="khartasia-warning">' . $this->t('« @titre » est déjà marqué canonique. Deux canoniques déclencheront la vue de contrôle.', ['@titre' => $titre]) . '</p>';
    }
    else {
      $form['canonique_warning']['#markup'] = '';
    }
    return $form['canonique_warning'];
  }

  // --- Navigation -----------------------------------------------------------

  public function retour(array &$form, FormStateInterface $form_state) {
    $form_state->set('step', max(0, $form_state->get('step') - 1));
    $form_state->setRebuild(TRUE);
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $step = $form_state->get('step');

    if ($step === 1 && !$form_state->getValue('valeur')) {
      $form_state->setErrorByName('valeur', $this->t('La forme du nom est obligatoire.'));
    }

    if ($step === 3) {
      // C2 côté serveur — le #states n'est qu'un confort visuel.
      if ($form_state->getValue('canonique')) {
        $form_state->setValue('relation', NULL);
      }
      elseif (!$form_state->getValue('relation')) {
        $form_state->setErrorByName('relation', $this->t('Un nom non canonique doit porter une relation (au besoin « relation non tranchée »).'));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $step = $form_state->get('step');

    if ($step < 5) {
      $form_state->set('step', $step + 1);
      $form_state->setRebuild(TRUE);
      return;
    }

    $papers_nid = $form_state->get('papers_nid');
    $couche = $form_state->getValue('couche');
    $canonique = (bool) $form_state->getValue('canonique');
    $relation_tid = $canonique ? NULL : $form_state->getValue('relation');

    // L4 : un contributeur non-éditeur ne peut jamais poser 'rejete' lui-même,
    // quelle que soit la valeur transmise (garde-fou serveur, pas seulement UI).
    $is_editeur = \Drupal::currentUser()->hasPermission('administer nom_papier statuts');
    $statut = $form_state->getValue('statut') ?: 'courant';
    if (!$is_editeur && $statut === 'rejete') {
      $statut = 'courant';
    }

    // F12 : motifs de revue structurés, calculés, jamais demandés à l'utilisateur.
    $motifs = [];
    $detail = $form_state->getValue('detail');
    if (!$detail) {
      $motifs[] = 'detail_manquant';
    }
    if (!$canonique && $this->libelleTerme($relation_tid) === 'relation_non_tranchee') {
      $motifs[] = 'relation_non_tranchee';
    }
    if ($statut === 'atteste' && !$form_state->getValue('source')) {
      $motifs[] = 'source_manquante';
    }
    if (!$is_editeur && $form_state->getValue('proposer_rejet')) {
      $motifs[] = 'rejet_propose';
    }
    $suggeree = $form_state->getValue('couche_suggeree');
    if ($suggeree && $suggeree !== $couche) {
      $motifs[] = 'couche_contradiction';
    }

    $values = [
      'type' => 'nom_papier',
      'title' => mb_substr($form_state->getValue('valeur'), 0, 255),
      'field_np_papier' => $papers_nid,
      'field_np_valeur' => $form_state->getValue('valeur'),
      'field_np_couche' => $couche,
      'field_np_canonique' => (int) $canonique,
      'field_np_statut' => $statut,
    ];
    if ($relation_tid) {
      $values['field_np_relation'] = $relation_tid;
    }
    if ($form_state->getValue('source')) {
      $values['field_np_source'] = $form_state->getValue('source');
    }

    $node = Node::create($values);

    if ($node->hasField('field_np_ecriture') && $couche === 'graphie' && $detail) {
      $node->set('field_np_ecriture', $detail);
    }
    if ($node->hasField('field_np_romanisation') && $couche === 'romanisation' && $detail) {
      $node->set('field_np_romanisation', $detail);
    }
    if ($node->hasField('field_np_langue') && $couche === 'traduction' && $detail) {
      $node->set('field_np_langue', $detail);
    }
    if ($node->hasField('field_np_revue_motifs') && $motifs) {
      $node->set('field_np_revue_motifs', $motifs);
    }
    if ($node->hasField('field_np_a_revoir')) {
      $node->set('field_np_a_revoir', (bool) $motifs);
    }
    if ($node->hasField('field_np_notes') && $form_state->getValue('notes')) {
      $node->set('field_np_notes', $form_state->getValue('notes'));
    }

    $node->save();

    // L3 : journalisation de la fiabilité de l'heuristique (patron Wallpaper Canton).
    if ($suggeree) {
      \Drupal::logger('khartasia_noms')->info('couche suggérée=@s retenue=@r valeur=@v', [
        '@s' => $suggeree,
        '@r' => $couche,
        '@v' => $form_state->getValue('valeur'),
      ]);
    }

    // L5 : passage de relais — retour sur la fiche, accordéon déplié une fois.
    $form_state->setRedirect('entity.node.canonical', ['node' => $papers_nid], [
      'fragment' => 'noms-de-papier',
      'query' => ['nom_ajoute' => $node->id()],
    ]);
    \Drupal::messenger()->addStatus($this->t('Nom ajouté. <a href=":url">Ajouter un autre nom</a> à ce même papier ?', [
      ':url' => Url::fromRoute('khartasia_noms.add_wizard', ['node' => $papers_nid])->toString(),
    ]));
  }

  // --- Aides ------------------------------------------------------------

  protected function chargerNomsExistants($papers_nid) {
    $ids = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'nom_papier')
      ->condition('field_np_papier', $papers_nid)
      ->execute();

    $noms = [];
    foreach (Node::loadMultiple($ids) as $n) {
      $noms[] = [
        'valeur' => $n->hasField('field_np_valeur') ? $n->get('field_np_valeur')->value : $n->getTitle(),
        'couche' => $n->hasField('field_np_couche') ? $n->get('field_np_couche')->value : '',
        'relation' => ($n->hasField('field_np_relation') && !$n->get('field_np_relation')->isEmpty())
          ? $n->get('field_np_relation')->entity?->label() : NULL,
        'statut' => $n->hasField('field_np_statut') ? $n->get('field_np_statut')->value : '',
        'canonique' => $n->hasField('field_np_canonique') ? (bool) $n->get('field_np_canonique')->value : FALSE,
        'a_revoir' => $n->hasField('field_np_a_revoir') ? (bool) $n->get('field_np_a_revoir')->value : FALSE,
      ];
    }
    return $noms;
  }

  protected function termesDuVocabulaire($vid) {
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vid);
    $options = [];
    foreach ($terms as $t) {
      $options[$t->tid] = $t->name;
    }
    return $options;
  }

  protected function tidDe($nom) {
    $found = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'type_de_nom_papier', 'name' => $nom]);
    return $found ? (int) reset($found)->id() : NULL;
  }

  protected function libelleTerme($tid) {
    if (!$tid) {
      return NULL;
    }
    $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid);
    return $term ? $term->label() : NULL;
  }

}
