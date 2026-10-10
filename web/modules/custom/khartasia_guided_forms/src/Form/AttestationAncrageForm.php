<?php

namespace Drupal\khartasia_guided_forms\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\khartasia_guided_forms\Service\AttestationFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Étape d'ancrage d'une attestation : source, sujet, objet.
 *
 * Elle précède la rédaction de l'énoncé. Quand les trois repères sont posés,
 * elle crée un BROUILLON (nœud non publié, motif « ebauche ») et envoie
 * l'utilisateur sur le formulaire d'édition standard de ce nœud. Le brouillon
 * existe donc réellement : rien ne se perd si l'on s'interrompt.
 *
 * Paramètres d'adresse acceptés (pré-remplissage, remplacent l'ancien
 * ?edit[...] de Drupal 7, ignoré par Drupal 11) :
 *   ?papier=NID  ?plante=NID  ?nom=NID  ?objet=NID  ?source=ID
 *
 * Règle de sujet : un seul sujet par attestation (papier OU plante OU nom).
 * Objet : une plante (le matériau viendra avec le schéma du lot 2).
 */
class AttestationAncrageForm extends FormBase {

  public function __construct(
    protected AttestationFactory $factory,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('khartasia_guided_forms.attestation_factory'),
      $container->get('entity_type.manager'),
    );
  }

  public function getFormId() {
    return 'khartasia_attestation_ancrage_form';
  }

  /**
   * Charge un nœud d'un des bundles attendus, sinon NULL (paramètre d'adresse
   * inconnu ou du mauvais type : ignoré sans bruit).
   */
  protected function chargerNoeud($id, array $bundles) {
    if (!$id || !ctype_digit((string) $id)) {
      return NULL;
    }
    $node = $this->entityTypeManager->getStorage('node')->load($id);
    return ($node && in_array($node->bundle(), $bundles, TRUE)) ? $node : NULL;
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'khartasia_guided_forms/guided-form';

    // Prérequis de configuration : motifs de revue ajoutés par le script du lot.
    $manquants = $this->factory->motifsManquants();
    if ($manquants) {
      $form['erreur'] = ['#markup' => '<div class="messages messages--error">'
        . $this->t('Configuration incomplète : le champ des motifs de revue n\'accepte pas encore « @m ». Exécutez le script lot1_config_locale.php puis importez la configuration.', ['@m' => implode(', ', $manquants)])
        . '</div>'];
      return $form;
    }

    $q = $this->getRequest()->query;
    $papier = $this->chargerNoeud($q->get('papier'), ['papers']);
    $plante = $this->chargerNoeud($q->get('plante'), ['article']);
    $nom = $this->chargerNoeud($q->get('nom'), ['common_names', 'nom_papier']);
    $objet = $this->chargerNoeud($q->get('objet'), ['article']);
    $source = NULL;
    if ($q->get('source') && ctype_digit((string) $q->get('source'))) {
      $source = $this->entityTypeManager->getStorage('bibcite_reference')->load($q->get('source'));
    }
    $type_defaut = $papier ? 'papier' : ($plante ? 'plante' : ($nom ? 'nom' : 'papier'));

    $form['intro'] = ['#markup' => '<div class="kh-guide__intro">
      <span class="kh-guide__eyebrow">Saisie d\'une attestation · Ancrage</span>
      <h1 class="kh-guide__title">Nouvelle attestation</h1>
      <p class="kh-guide__lead">Trois repères avant l\'énoncé : la source que vous avez lue, de quoi l\'on parle (le sujet), et ce que l\'on affirme à son sujet (l\'objet). Un brouillon est créé ; vous rédigerez l\'énoncé à l\'étape suivante.</p>
    </div>'];

    // Brouillons de l'utilisateur : reprise d'un travail interrompu.
    $brouillons = $this->factory->brouillonsDe((int) $this->currentUser()->id());
    if ($brouillons) {
      $items = [];
      foreach ($brouillons as $b) {
        $items[] = ['#markup' => '<a href="' . $b['url'] . '">' . $b['titre'] . '</a>'];
      }
      $form['brouillons'] = [
        '#type' => 'details',
        '#title' => $this->t('Reprendre un de mes brouillons (@n)', ['@n' => count($brouillons)]),
        'liste' => ['#theme' => 'item_list', '#items' => $items],
      ];
    }

    $form['container'] = ['#type' => 'container', '#attributes' => ['class' => ['kh-guide__form']]];
    $c = &$form['container'];

    // --- Source -------------------------------------------------------------
    $c['source'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'bibcite_reference',
      '#selection_handler' => 'default:khartasia_source',
      '#title' => $this->t('Source'),
      '#default_value' => $source,
      '#description' => $this->t('Tapez un nom d\'auteur, une année ou un mot du titre. La source primaire, pas le texte qui la cite ; si vous n\'avez lu qu\'un relais, citez le relais.'),
    ];
    if ($this->currentUser()->hasPermission('create bibcite_reference')) {
      $url = Url::fromUserInput('/bibcite/reference/add', ['query' => array_filter([
        'retour' => 'ancrage',
        'papier' => $q->get('papier'),
        'plante' => $q->get('plante'),
        'nom' => $q->get('nom'),
        'objet' => $q->get('objet'),
      ])]);
      $c['source_creer'] = ['#markup' => '<p>' . Link::fromTextAndUrl($this->t('Ma source n\'existe pas : la créer'), $url)->toString() . '</p>'];
    }
    else {
      $c['source_a_creer'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Ma source n\'est pas dans la liste'),
        '#description' => $this->t('Seuls les éditeurs créent des sources. Cochez cette case : l\'attestation sera signalée à la revue pour que la source soit créée.'),
      ];
    }

    // --- Sujet ---------------------------------------------------------------
    $c['sujet_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('De quoi parle l\'attestation ? (un seul sujet)'),
      '#options' => [
        'papier' => $this->t('Un papier'),
        'plante' => $this->t('Une plante'),
        'nom' => $this->t('Un nom (vernaculaire ou de papier)'),
      ],
      '#default_value' => $type_defaut,
      '#required' => TRUE,
    ];
    $c['sujet_papier'] = [
      '#type' => 'entity_autocomplete', '#target_type' => 'node',
      '#selection_settings' => ['target_bundles' => ['papers']],
      '#title' => $this->t('Papier'), '#default_value' => $papier,
      '#states' => ['visible' => [':input[name="sujet_type"]' => ['value' => 'papier']]],
    ];
    $c['sujet_plante'] = [
      '#type' => 'entity_autocomplete', '#target_type' => 'node',
      '#selection_settings' => ['target_bundles' => ['article']],
      '#title' => $this->t('Plante'), '#default_value' => $plante,
      '#states' => ['visible' => [':input[name="sujet_type"]' => ['value' => 'plante']]],
    ];
    $c['sujet_nom'] = [
      '#type' => 'entity_autocomplete', '#target_type' => 'node',
      '#selection_settings' => ['target_bundles' => ['common_names', 'nom_papier']],
      '#title' => $this->t('Nom'), '#default_value' => $nom,
      '#states' => ['visible' => [':input[name="sujet_type"]' => ['value' => 'nom']]],
    ];

    // --- Objet ----------------------------------------------------------------
    $c['objet_plante'] = [
      '#type' => 'entity_autocomplete', '#target_type' => 'node',
      '#selection_settings' => ['target_bundles' => ['article']],
      '#title' => $this->t('Plante concernée (objet)'),
      '#default_value' => $objet,
      '#description' => $this->t('Facultatif à ce stade. Ex. : le papier contient cette plante ; ce nom désigne cette plante. Les matériaux (colle, suie, kaolin…) seront proposés après la mise à jour du modèle.'),
      '#states' => ['invisible' => [':input[name="sujet_type"]' => ['value' => 'plante']]],
    ];

    // --- Doublons (affichés au retour d'une première soumission) ---------------
    $doublons = $form_state->get('doublons');
    if ($doublons) {
      $items = [];
      foreach ($doublons as $d) {
        $items[] = ['#markup' => '<a href="' . $d['url'] . '">' . $d['titre'] . '</a> — '
          . ($d['publie'] ? $this->t('publiée') : $this->t('non publiée'))];
      }
      $c['doublons'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--warning']],
        'titre' => ['#markup' => '<p><strong>' . $this->t('Des attestations existent déjà pour ce sujet, cet objet et cette source :') . '</strong></p>'],
        'liste' => ['#theme' => 'item_list', '#items' => $items],
      ];
      if ($form_state->get('doublon_fort')) {
        $c['confirmer_doublon'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('J\'ai vérifié la liste ci-dessus : créer quand même un nouveau brouillon.'),
        ];
      }
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Créer le brouillon et rédiger l\'énoncé →'),
      '#attributes' => ['class' => ['kh-guide__submit']],
    ];
    return $form;
  }

  /**
   * Un seul sujet, et celui qui correspond au type choisi doit être renseigné.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $type = $form_state->getValue('sujet_type');
    $champ = ['papier' => 'sujet_papier', 'plante' => 'sujet_plante', 'nom' => 'sujet_nom'][$type] ?? NULL;
    if (!$champ || !$form_state->getValue($champ)) {
      $form_state->setErrorByName($champ ?: 'sujet_type', $this->t('Indiquez le sujet de l\'attestation.'));
    }
  }

  /**
   * Relève l'ancrage saisi.
   */
  protected function lireAncrage(FormStateInterface $form_state): array {
    $type = $form_state->getValue('sujet_type');
    $champ = ['papier' => 'sujet_papier', 'plante' => 'sujet_plante', 'nom' => 'sujet_nom'][$type];
    return [
      'sujet_type' => $type,
      'sujet_id' => $form_state->getValue($champ),
      'objet_plante_id' => $type === 'plante' ? NULL : ($form_state->getValue('objet_plante') ?: NULL),
      'source_id' => $form_state->getValue('source') ?: NULL,
      'source_a_creer' => (bool) $form_state->getValue('source_a_creer'),
    ];
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $ancrage = $this->lireAncrage($form_state);
    $existantes = $this->factory->trouverExistantes($ancrage);
    $fort = $this->factory->doublonFort($ancrage, $existantes);

    // Première soumission avec attestations déjà présentes : on montre la liste.
    // Doublon fort : confirmation explicite requise. Doublon simple : on
    // informe, sans bloquer (ce peut être le même papier, une autre affirmation).
    if ($existantes && !$form_state->get('doublons_vus')) {
      $form_state->set('doublons', $existantes);
      $form_state->set('doublon_fort', $fort);
      $form_state->set('doublons_vus', TRUE);
      $form_state->setRebuild(TRUE);
      $this->messenger()->addWarning($this->t('Vérifiez les attestations existantes ci-dessous, puis validez de nouveau.'));
      return;
    }
    if ($fort && !$form_state->getValue('confirmer_doublon')) {
      $form_state->setRebuild(TRUE);
      $this->messenger()->addWarning($this->t('Cochez la case de confirmation pour créer un nouveau brouillon malgré les attestations existantes.'));
      return;
    }

    $node = $this->factory->creerBrouillon($ancrage);
    $this->messenger()->addStatus($this->t('Brouillon créé. Rédigez l\'énoncé et complétez les informations ; il ne sera pas publié tant que vous ne l\'aurez pas décidé.'));
    $form_state->setRedirectUrl($node->toUrl('edit-form'));
  }

}
