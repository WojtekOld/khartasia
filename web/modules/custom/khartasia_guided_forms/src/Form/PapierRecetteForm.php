<?php

namespace Drupal\khartasia_guided_forms\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeInterface;

/**
 * Saisie en série d'une RECETTE : un papier, une source, N intrants.
 *
 * Remplace, pour la composition, la section « Composition » de PapierAttestationsForm
 * (qui enregistrait sans valider). Chaque ligne passe par la fabrique unique, qui appelle
 * validate() ; les attestations créées sont des BROUILLONS (non publiés, motif « ebauche »),
 * à compléter et à publier depuis leur formulaire d'édition.
 *
 * DÉFINITIONS
 * - Recette : l'ensemble des intrants d'un papier selon une seule source.
 * - Intrant : une plante (référence vers une fiche plante) OU un matériau (terme du vocabulaire
 *   « materiau »), avec sa fonction (fibre principale, colorant, adhésif…).
 * - Référentiel taxonomique : « sensu » suivi pour nommer les plantes (POWO, GRIN…), commun à
 *   toute la recette ; exigé dès qu'une ligne porte une plante.
 * - Règle 13 : aucun champ de proportion (« 70 % / 30 % ») ; la source n'en donne pas la méthode.
 */
class PapierRecetteForm extends FormBase {

  const LIGNES_PAR_DEFAUT = 4;

  public function getFormId() {
    return 'khartasia_papier_recette_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $papier_nid = NULL) {
    $papier = $papier_nid ? \Drupal::entityTypeManager()->getStorage('node')->load($papier_nid) : NULL;
    if (!$papier instanceof NodeInterface || $papier->bundle() !== 'papers') {
      $form['erreur'] = ['#markup' => '<p>' . $this->t('Papier introuvable.') . '</p>'];
      return $form;
    }
    $form_state->set('papier_nid', (int) $papier->id());
    $nb = $form_state->get('nb') ?? self::LIGNES_PAR_DEFAUT;
    $form_state->set('nb', $nb);

    $form['intro'] = ['#markup' => '<p>' . $this->t('Papier : <strong>@t</strong>. Une source, plusieurs intrants. Chaque ligne crée un brouillon, à compléter et à publier ensuite.', ['@t' => $papier->label()]) . '</p>'];

    $form['source'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Source de cette recette'),
      '#target_type' => 'bibcite_reference',
      '#selection_handler' => 'default:khartasia_source',
      '#value_callback' => [static::class, 'valeurSansEspaces'],
      '#description' => $this->t('Facultative pour un brouillon, obligatoire pour publier. Tous les intrants ci-dessous s\'y rattachent.'),
    ];

    $concepts = [];
    if ($fs = FieldStorageConfig::loadByName('node', 'field_att_concept_taxo')) {
      $concepts = $fs->getSetting('allowed_values') ?: [];
    }
    $form['concept'] = [
      '#type' => 'select',
      '#title' => $this->t('Référentiel taxonomique suivi'),
      '#options' => $concepts,
      '#empty_option' => $this->t('— choisir —'),
      '#description' => $this->t('Exigé si une ligne porte une plante.'),
    ];

    $fonctions = [];
    $defs = [];
    foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('fonction_intrant', 0, NULL, TRUE) as $t) {
      $fonctions[$t->id()] = $t->label();
      $defs[] = $t;
    }

    $form['aide_colonnes'] = [
      '#type' => 'details', '#title' => $this->t('Comment remplir les colonnes'), '#open' => TRUE,
      'liste' => ['#markup' => '<dl>' . implode('', array_map(
        fn($c) => '<dt><strong>' . $c[0] . '</strong></dt><dd>' . $c[1] . '</dd>', $this->aideColonnes()
      )) . '</dl>'],
    ];
    $form['lignes'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => $this->entetes(),
      '#prefix' => '<div id="khartasia-lignes">',
      '#suffix' => '</div>',
    ];
    // Plantes déjà liées à ce papier (field_genus_pap_entity) : choix par défaut.
    $liees = [];
    if ($papier->hasField('field_genus_pap_entity')) {
      foreach ($papier->get('field_genus_pap_entity')->referencedEntities() as $pl) {
        $liees[$pl->id()] = $pl->label();
      }
    }
    asort($liees);
    $form['info_plantes'] = ['#markup' => $liees
      ? '<p>' . $this->t('Colonne « Plante » : plantes déjà associées à ce papier. Pour une plante non associée, utilisez « Autre plante ».') . '</p>'
      : '<p><em>' . $this->t('Aucune plante n\'est associée à ce papier : utilisez « Autre plante », ou associez d\'abord les plantes à la fiche du papier.') . '</em></p>',
      '#weight' => -1,
    ];
    $statuts = [];
    if ($fs = FieldStorageConfig::loadByName('node', 'field_att_statut')) {
      $statuts = $fs->getSetting('allowed_values') ?: [];
    }
    for ($i = 0; $i < $nb; $i++) {
      $form['lignes'][$i]['plante'] = [
        '#type' => 'select', '#options' => $liees, '#empty_option' => $this->t('—'),
      ];
      $form['lignes'][$i]['plante_autre'] = [
        '#type' => 'entity_autocomplete', '#target_type' => 'node',
        '#selection_settings' => ['target_bundles' => ['article']], '#size' => 18,
        '#value_callback' => [static::class, 'valeurSansEspaces'],
      ];
      $form['lignes'][$i]['materiau'] = [
        '#type' => 'entity_autocomplete', '#target_type' => 'taxonomy_term',
        '#selection_settings' => ['target_bundles' => ['materiau']], '#size' => 18,
        '#value_callback' => [static::class, 'valeurSansEspaces'],
      ];
      $form['lignes'][$i]['fonction'] = ['#type' => 'select', '#options' => $fonctions, '#empty_option' => $this->t('—')];
      $form['lignes'][$i]['statut'] = ['#type' => 'select', '#options' => $statuts, '#default_value' => 'inconnu'];
      $form['lignes'][$i]['locateur'] = ['#type' => 'textfield', '#size' => 14];
      $form['lignes'][$i]['valeur'] = ['#type' => 'textfield', '#size' => 28];
    }
    $form['ajouter'] = [
      '#type' => 'submit', '#value' => $this->t('Ajouter une ligne'), '#submit' => ['::ajouterLigne'],
      '#limit_validation_errors' => [],
      '#ajax' => ['callback' => '::rafraichir', 'wrapper' => 'khartasia-lignes'],
    ];

    $form['aide'] = ['#type' => 'details', '#title' => $this->t('Définitions des fonctions'), '#open' => FALSE];
    foreach ($defs as $t) {
      $desc = $t->getDescription();
      $form['aide'][$t->id()] = [
        '#type' => 'container',
        'titre' => ['#markup' => '<h4>' . $t->label() . '</h4>'],
        'texte' => ['#type' => 'processed_text', '#text' => $desc, '#format' => $t->getFormat() ?: 'plain_text'],
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Créer les brouillons'), '#button_type' => 'primary'];
    return $form;
  }

  /**
   * Libellé + explication de chaque colonne (affichée sous forme d'aide et d'infobulle).
   */
  protected function aideColonnes(): array {
    return [
      [(string) $this->t('Plante du papier'), (string) $this->t('Plante déjà associée à ce papier (fiche du papier). Choix par défaut pour un intrant végétal : on évite ainsi de citer une plante étrangère à ce papier.')],
      [(string) $this->t('Autre plante'), (string) $this->t('Plante absente de la liste précédente : tapez son nom pour la chercher dans toute la base. À utiliser seulement si la source la cite pour ce papier ; pensez ensuite à l\'associer au papier.')],
      [(string) $this->t('ou Matériau'), (string) $this->t('Intrant non végétal ou dont la plante n\'est pas identifiée (ex. : argile, cendre, « plante indéterminée (genre) »). Remplir soit une plante, soit un matériau, jamais les deux.')],
      [(string) $this->t('Fonction'), (string) $this->t('Rôle de l\'intrant dans la fabrication (fibre principale, colorant, dispersant…). Voir « Définitions des fonctions » plus bas.')],
      [(string) $this->t('Statut'), (string) $this->t('Degré de certitude de la source : attesté, probable, inconnu…. Par défaut « inconnu » tant que la source n\'a pas été relue.')],
      [(string) $this->t('Locateur (page, folio…)'), (string) $this->t('Endroit précis de la source où figure l\'information (page, folio, note). Exigé pour publier une attestation « attesté ».')],
      [(string) $this->t('Énoncé (facultatif)'), (string) $this->t('Formulation de la source, citée ou résumée. Si vide, « Fonction : intrant » est utilisé.')],
    ];
  }

  protected function entetes(): array {
    $h = [];
    foreach ($this->aideColonnes() as $c) {
      $h[] = ['data' => ['#markup' => '<span title="' . htmlspecialchars($c[1], ENT_QUOTES) . '">' . $c[0] . ' <sup>?</sup></span>']];
    }
    return $h;
  }

  /**
   * Une saisie réduite à des espaces est traitée comme un champ vide (sinon Drupal cherche
   * un contenu nommé « » et refuse la ligne).
   */
  public static function valeurSansEspaces(&$element, $input, FormStateInterface $form_state) {
    if (is_string($input)) {
      $input = trim($input);
    }
    return EntityAutocomplete::valueCallback($element, $input, $form_state);
  }

  public function ajouterLigne(array &$form, FormStateInterface $form_state) {
    $form_state->set('nb', ($form_state->get('nb') ?? self::LIGNES_PAR_DEFAUT) + 1);
    $form_state->setRebuild(TRUE);
  }

  public function rafraichir(array &$form, FormStateInterface $form_state) {
    return $form['lignes'];
  }

  /**
   * Lignes réellement remplies (une ligne entièrement vide est ignorée).
   */
  protected function lignesRemplies(FormStateInterface $form_state): array {
    $lignes = [];
    foreach ($form_state->getValue('lignes') ?? [] as $i => $l) {
      $l['plante_autre'] = $l['plante_autre'] ?? '';
      if (!empty($l['plante']) && !empty($l['plante_autre'])) {
        $l['conflit_plante'] = TRUE;
      }
      elseif (empty($l['plante'])) {
        $l['plante'] = $l['plante_autre'];
      }
      $vide = empty($l['plante']) && empty($l['materiau']) && empty($l['fonction']) && trim((string) ($l['locateur'] ?? '')) === '' && trim((string) ($l['valeur'] ?? '')) === '';
      if (!$vide) {
        $lignes[$i] = $l;
      }
    }
    return $lignes;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $lignes = $this->lignesRemplies($form_state);
    if (!$lignes) {
      $form_state->setErrorByName('lignes', $this->t('Renseignez au moins un intrant.'));
      return;
    }
    $a_plante = FALSE;
    foreach ($lignes as $i => $l) {
      $n = $i + 1;
      if (!empty($l['conflit_plante'])) {
        $form_state->setErrorByName("lignes][$i][plante_autre", $this->t('Ligne @n : une seule plante (liste du papier OU autre plante).', ['@n' => $n]));
        continue;
      }
      if (!empty($l['plante']) && !empty($l['materiau'])) {
        $form_state->setErrorByName("lignes][$i][materiau", $this->t('Ligne @n : une plante ou un matériau, pas les deux.', ['@n' => $n]));
      }
      elseif (empty($l['plante']) && empty($l['materiau'])) {
        $form_state->setErrorByName("lignes][$i][plante", $this->t('Ligne @n : choisissez une plante ou un matériau.', ['@n' => $n]));
      }
      if (empty($l['fonction'])) {
        $form_state->setErrorByName("lignes][$i][fonction", $this->t('Ligne @n : la fonction est obligatoire.', ['@n' => $n]));
      }
      if (!empty($l['plante'])) {
        $a_plante = TRUE;
      }
    }
    if ($a_plante && $form_state->getValue('concept') === '') {
      $form_state->setErrorByName('concept', $this->t('Choisissez le référentiel taxonomique suivi : une ligne porte une plante.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $papier_nid = (int) $form_state->get('papier_nid');
    $source = $form_state->getValue('source');
    $lignes = [];
    foreach ($this->lignesRemplies($form_state) as $l) {
      $lignes[] = [
        'plante' => !empty($l['plante']) ? (int) $l['plante'] : NULL,
        'materiau' => !empty($l['materiau']) ? (int) $l['materiau'] : NULL,
        'fonction' => (int) $l['fonction'],
        'statut' => $l['statut'] ?: 'inconnu',
        'locateur' => trim((string) $l['locateur']),
        'valeur' => trim((string) $l['valeur']),
      ];
    }
    $res = \Drupal::service('khartasia_guided_forms.attestation_factory')
      ->creerEnSerie($papier_nid, $source ? (int) $source : NULL, (string) $form_state->getValue('concept'), $lignes);

    $m = \Drupal::messenger();
    if ($res['crees']) {
      $m->addStatus($this->formatPlural(count($res['crees']), '1 brouillon créé.', '@count brouillons créés.')
        . ' ' . $this->t('Complétez-les et publiez-les depuis <a href=":u">vos brouillons</a>.', [':u' => \Drupal\Core\Url::fromRoute('khartasia_guided_forms.attestation_ancrage')->toString()]));
    }
    foreach ($res['erreurs'] as $e) {
      $m->addWarning($e);
    }
    if ($res['erreurs'] && !$res['crees']) {
      $form_state->setRebuild(TRUE);
      return;
    }
    $form_state->setRedirect('entity.node.canonical', ['node' => $papier_nid]);
  }

}
