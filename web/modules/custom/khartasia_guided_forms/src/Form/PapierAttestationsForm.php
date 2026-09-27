<?php

namespace Drupal\khartasia_guided_forms\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

/**
 * Étape 2 du parcours guidé : Datation, Composition, Usage — sous forme
 * d'Attestations liées au Papier créé à l'étape 1. Chaque section est
 * facultative ; seules celles remplies produisent une Attestation.
 *
 * v2 — consolidée (points 1-4) :
 *  1. Champ Source partagé (bibcite_reference), appliqué à toutes les
 *     attestations créées dans cette soumission — sans lui, un formulaire
 *     qui produit en série des attestations "probable" sans source
 *     contredit la discipline même que le modèle Attestation impose.
 *  2. try/catch centralisé (creerAttestation()) autour de chaque création
 *     — un échec sur l'une n'empêche plus les suivantes, et l'utilisateur
 *     est informé de ce qui a réellement été enregistré.
 *  3. Redirection finale vers le wizard de noms (khartasia_noms), pas
 *     seulement la fiche — une fiche neuve sans aucun nom canonique ne
 *     doit pas sortir de ce parcours sans qu'on le propose explicitement.
 *  4. usage_papier : jamais audité comme utilisation_dans_la_fabricatio —
 *     filtrage défensif par écriture (regex) en attendant un vrai audit
 *     (script auditer_usage_papier.php fourni séparément) et, le cas
 *     échéant, une vraie liste blanche comme pour l'autre vocabulaire.
 */
class PapierAttestationsForm extends FormBase {

  public function getFormId() {
    return 'khartasia_papier_attestations_form';
  }

  /**
   * Filtre une liste de termes pour ne garder que les libellés français.
   * Liste blanche construite sur le contenu réel du vocabulaire
   * utilisation_dans_la_fabricatio (mélange FR/EN/ZH/JA/KO sous un même
   * vocabulaire, hérité de D7) — plus fiable qu'une détection par
   * écriture, qui laisse passer les doublons anglais en alphabet latin
   * ("Dye", "Mordant", "Filler"...).
   */
  private function optionsFrancaisUniquement($vid, $exclure = []) {
    $liste_blanche_fr = [
      'Agent de blanchiment', 'Agent de cuisson', 'Agent de surfaçage',
      'Agent de teinture', 'Agent dispersant', 'Agent d\'encollage',
      'Agent d\'imprégnation', 'Charge', 'Colorant', 'Fibre',
      'Mordant', 'Insecticide', 'Éléments de décor',
    ];
    $options = [];
    $vus = [];
    foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid]) as $t) {
      $label = $t->label();
      if (in_array($label, $exclure)) continue;
      if (!in_array($label, $liste_blanche_fr)) continue;
      if (in_array($label, $vus)) continue;
      $vus[] = $label;
      $options[$t->id()] = $label;
    }
    asort($options);
    return $options;
  }

  /**
   * v2 point 4 : usage_papier n'a JAMAIS été audité (contrairement à
   * utilisation_dans_la_fabricatio, qui a sa vraie liste blanche
   * ci-dessus). AUDIT FAIT (26/09) : 13 termes, tous français, propre —
   * cette méthode n'est plus utilisée (voir buildForm), gardée en
   * référence si un futur vocabulaire nécessite le même filet de sécurité
   * en attendant son propre audit.
   */
  private function optionsSansEcritureNonLatine($vid) {
    $options = [];
    foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid]) as $t) {
      $label = $t->label();
      if (preg_match('/\p{Han}|\p{Hiragana}|\p{Katakana}|\p{Hangul}/u', $label)) continue;
      $options[$t->id()] = $label;
    }
    asort($options);
    return $options;
  }

  /**
   * {@inheritdoc}
   * $papier_nid est fourni par la route (paramètre d'URL).
   */
  public function buildForm(array $form, FormStateInterface $form_state, $papier_nid = NULL) {
    $papier = $papier_nid ? Node::load($papier_nid) : NULL;
    if (!$papier || $papier->bundle() !== 'papers') {
      $form['erreur'] = [
        '#type' => 'markup',
        '#markup' => '<div class="kh-guide__intro"><p>Papier introuvable. Reprenez le parcours depuis l\'étape 1.</p></div>',
      ];
      return $form;
    }

    $form['#attached']['library'][] = 'khartasia_guided_forms/guided-form';
    $form['papier_nid'] = ['#type' => 'value', '#value' => $papier_nid];

    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => '<div class="kh-guide__intro">
        <span class="kh-guide__eyebrow">Saisie guidée · Étape 2</span>
        <h1 class="kh-guide__title">Attestations — ' . $papier->label() . '</h1>
        <p class="kh-guide__lead">Chaque section ci-dessous est facultative. Seules celles que vous remplissez produiront une Attestation — datée, localisée, sourcée.</p>
      </div>',
    ];

    $form['container'] = ['#type' => 'container', '#attributes' => ['class' => ['kh-guide__form']]];

    // === Source partagée (point 1) ===
    if (\Drupal::entityTypeManager()->getDefinition('bibcite_reference', FALSE)) {
      $form['container']['source_partagee'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('Source de cette saisie'),
        '#target_type' => 'bibcite_reference',
        '#description' => $this->t('Optionnelle mais fortement recommandée — s\'applique à toutes les attestations créées ci-dessous. Une saisie sans source reste possible (statut "probable" par défaut), mais s\'accumule mal : à sourcer dès que possible.'),
        '#weight' => -10,
      ];
    }

    // === Datation ===
    $form['container']['datation'] = [
      '#type' => 'details',
      '#title' => $this->t('Datation'),
      '#open' => TRUE,
    ];
    $periode_options = [];
    foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'periode_dynastique']) as $t) {
      if (!$t->get('parent')->isEmpty() && $t->get('parent')->target_id != 0) {
        $periode_options[$t->id()] = $t->label();
      }
    }
    asort($periode_options);
    $form['container']['datation']['periodes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Période(s)'),
      '#options' => $periode_options,
      '#default_value' => [],
    ];

    $lieu_options = ['_none' => $this->t('— Non renseigné —')];
    foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'zone_historique_production']) as $t) {
      $lieu_options[$t->id()] = $t->label();
    }
    asort($lieu_options);
    $form['container']['datation']['lieu'] = [
      '#type' => 'select',
      '#title' => $this->t('Lieu'),
      '#options' => $lieu_options,
    ];
    $form['container']['datation']['valeur'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Détail (facultatif)'),
      '#rows' => 2,
    ];

    // === Composition ===
    $form['container']['composition'] = [
      '#type' => 'details',
      '#title' => $this->t('Composition'),
      '#open' => TRUE,
    ];

    $fibre_options = ['_none' => $this->t('— Non renseigné —')];
    if ($papier->hasField('field_genus_pap_entity')) {
      foreach ($papier->get('field_genus_pap_entity') as $item) {
        if ($item->entity) {
          $fibre_options[$item->entity->id()] = $item->entity->label();
        }
      }
    }
    $form['container']['composition']['fibre'] = [
      '#type' => 'select',
      '#title' => $this->t('Fibre principale'),
      '#description' => $this->t('Parmi les plantes liées à ce Papier à l\'étape 1. Si la liste est vide ou incomplète, revenez à l\'étape 1 pour l\'enrichir.'),
      '#options' => $fibre_options,
    ];
    $form['container']['composition']['fibre_non_identifiee'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Fibre principale — si non identifiée précisément'),
      '#description' => $this->t('Deux niveaux de flou sont possibles, tous deux acceptés : (1) le genre est connu mais l\'espèce incertaine — dans ce cas, préférez relier une entrée générique "sp. (indéterminé)" du bon genre à l\'étape 1 plutôt que d\'utiliser ce champ ; (2) rien n\'est identifiable avec certitude (observation macroscopique seule, sans analyse) — décrivez ici en texte libre ("fibre végétale non identifiée, écorce probable"), sans sélectionner de plante ci-dessus.'),
      '#rows' => 2,
    ];

    $roles_options = $this->optionsFrancaisUniquement('utilisation_dans_la_fabricatio', ['Fibre']);
    $roles_options = ['_none' => $this->t('— Non renseigné —')] + $roles_options;

    $form['container']['composition']['colorant_plantes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Colorant — plante(s) source'),
      '#description' => $this->t('Une attestation sera créée par plante cochée, partageant le même rôle et détail ci-dessous.'),
      '#options' => $fibre_options === ['_none' => $this->t('— Non renseigné —')] ? [] : array_diff_key($fibre_options, ['_none' => '']),
      '#default_value' => [],
    ];
    $form['container']['composition']['colorant_role'] = [
      '#type' => 'select',
      '#title' => $this->t('Colorant — rôle'),
      '#options' => $roles_options,
    ];
    $form['container']['composition']['colorant_detail'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Colorant — détail'),
      '#rows' => 2,
    ];

    $form['container']['composition']['agent_plantes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Agent de traitement — plante(s) source'),
      '#description' => $this->t('Facultatif — un agent de traitement n\'est pas toujours une plante (argile, gélatine...).'),
      '#options' => $fibre_options === ['_none' => $this->t('— Non renseigné —')] ? [] : array_diff_key($fibre_options, ['_none' => '']),
      '#default_value' => [],
    ];
    $form['container']['composition']['agent_role'] = [
      '#type' => 'select',
      '#title' => $this->t('Agent de traitement — rôle'),
      '#options' => $roles_options,
    ];
    $form['container']['composition']['agent_detail'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Agent de traitement — détail'),
      '#rows' => 2,
    ];

    $form['container']['composition']['methode'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Méthode de fabrication'),
      '#rows' => 2,
    ];

    // === Usage ===
    $form['container']['usage'] = [
      '#type' => 'details',
      '#title' => $this->t('Usage'),
      '#open' => TRUE,
    ];
    $usage_options = [];
    // usage_papier audité (26/09) : 13 termes, tous en français, aucun
    // mélange d'écriture — contrairement à utilisation_dans_la_fabricatio.
    // Aucun filtrage nécessaire, chargement direct.
    foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'usage_papier']) as $t) {
      $usage_options[$t->id()] = $t->label();
    }
    asort($usage_options);
    $form['container']['usage']['termes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Usages attestés'),
      '#options' => $usage_options,
      '#default_value' => [],
    ];
    $form['container']['usage']['observation'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Observation (facultatif)'),
      '#description' => $this->t('Précision, nuance ou contexte au-delà des usages cochés ci-dessus.'),
      '#rows' => 2,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Enregistrer les attestations →'),
      '#attributes' => ['class' => ['kh-guide__submit']],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {}

  private function trouverTerme($vid, $nom) {
    $r = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $nom]);
    return $r ? reset($r) : NULL;
  }

  /**
   * Point 2 : centralise la création + try/catch. Retourne TRUE si créée.
   */
  private function creerAttestation(array $values, ?int $source_id, &$compteur, array &$erreurs): bool {
    if ($source_id) {
      $values['field_att_source'] = ['target_id' => $source_id];
    }
    try {
      Node::create($values)->save();
      $compteur++;
      return TRUE;
    }
    catch (\Exception $e) {
      $erreurs[] = ($values['title'] ?? 'attestation') . ' : ' . $e->getMessage();
      return FALSE;
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $papier_nid = $form_state->getValue('papier_nid');
    $papier = Node::load($papier_nid);
    $compteur = 0;
    $erreurs = [];

    // Point 1 : source partagée, résolue une fois, appliquée à tout.
    $source_brute = $form_state->getValue('source_partagee');
    $source_id = !empty($source_brute) ? (int) $source_brute : NULL;

    // === Datation ===
    $periodes_brutes = $form_state->getValue(['datation', 'periodes']) ?? [];
    $periodes_choisies = array_filter($periodes_brutes);
    $lieu_brut = $form_state->getValue(['datation', 'lieu']);
    $lieu_id = (!empty($lieu_brut) && $lieu_brut !== '_none') ? $lieu_brut : NULL;
    $datation_valeur = $form_state->getValue(['datation', 'valeur']);

    if ($periodes_choisies || $lieu_id || !empty($datation_valeur)) {
      $type_dat = $this->trouverTerme('attestation_type', 'Datation');
      if ($type_dat) {
        $values = [
          'type' => 'attestation',
          'title' => 'Datation — ' . $papier->label(),
          'field_att_type' => ['target_id' => $type_dat->id()],
          'field_att_papier' => ['target_id' => $papier_nid],
          'field_att_statut' => 'probable',
        ];
        if ($periodes_choisies) {
          $values['field_att_periode'] = array_map(fn($tid) => ['target_id' => $tid], array_keys($periodes_choisies));
        }
        if ($lieu_id) { $values['field_att_lieu'] = ['target_id' => $lieu_id]; }
        if (!empty($datation_valeur)) { $values['field_att_valeur'] = $datation_valeur; }
        $this->creerAttestation($values, $source_id, $compteur, $erreurs);
      }
      else {
        $erreurs[] = 'Datation : terme attestation_type "Datation" introuvable — rien créé.';
      }
    }

    // === Composition — Fibre principale ===
    $fibre_id = $form_state->getValue(['composition', 'fibre']);
    $fibre_texte_libre = $form_state->getValue(['composition', 'fibre_non_identifiee']);

    if ((!empty($fibre_id) && $fibre_id !== '_none') || !empty($fibre_texte_libre)) {
      $type_comp = $this->trouverTerme('attestation_type', 'Composition');
      $axe_fibre = $this->trouverTerme('attestation_axe', 'Fibre principale');
      if ($type_comp && $axe_fibre) {
        $values = [
          'type' => 'attestation',
          'title' => 'Composition (Fibre principale) — ' . $papier->label(),
          'field_att_type' => ['target_id' => $type_comp->id()],
          'field_att_axe' => ['target_id' => $axe_fibre->id()],
          'field_att_papier' => ['target_id' => $papier_nid],
          'field_att_statut' => 'probable',
        ];
        if (!empty($fibre_id) && $fibre_id !== '_none') {
          $plante = Node::load($fibre_id);
          $values['field_att_plante'] = ['target_id' => $fibre_id];
          $values['field_att_valeur'] = 'Fibre structurelle : ' . ($plante ? $plante->label() : '') . ($fibre_texte_libre ? ' — ' . $fibre_texte_libre : '');
        }
        else {
          $values['field_att_valeur'] = $fibre_texte_libre;
        }
        $this->creerAttestation($values, $source_id, $compteur, $erreurs);
      }
      else {
        $erreurs[] = 'Fibre principale : terme(s) attestation_type/attestation_axe introuvable(s) — rien créé.';
      }
    }

    // === Composition — Colorant ===
    $colorant_plantes_brutes = $form_state->getValue(['composition', 'colorant_plantes']) ?? [];
    $colorant_plantes_choisies = array_filter($colorant_plantes_brutes);
    $colorant_role_id = $form_state->getValue(['composition', 'colorant_role']);
    $colorant_detail = $form_state->getValue(['composition', 'colorant_detail']);
    $colorant_role_label = (!empty($colorant_role_id) && $colorant_role_id !== '_none')
      ? \Drupal\taxonomy\Entity\Term::load($colorant_role_id)?->label() : NULL;
    $colorant_valeur = trim(($colorant_role_label ? "$colorant_role_label — " : '') . $colorant_detail);

    if ($colorant_valeur || $colorant_plantes_choisies) {
      $type_comp = $this->trouverTerme('attestation_type', 'Composition');
      $axe_colorant = $this->trouverTerme('attestation_axe', 'Colorant');
      if ($type_comp && $axe_colorant) {
        if ($colorant_plantes_choisies) {
          foreach (array_keys($colorant_plantes_choisies) as $plante_id) {
            $plante = Node::load($plante_id);
            $values = [
              'type' => 'attestation',
              'title' => 'Composition (Colorant, ' . ($plante ? $plante->label() : $plante_id) . ') — ' . $papier->label(),
              'field_att_type' => ['target_id' => $type_comp->id()],
              'field_att_axe' => ['target_id' => $axe_colorant->id()],
              'field_att_papier' => ['target_id' => $papier_nid],
              'field_att_plante' => ['target_id' => $plante_id],
              'field_att_valeur' => $colorant_valeur ?: ('Colorant : ' . ($plante ? $plante->label() : '')),
              'field_att_statut' => 'probable',
            ];
            $this->creerAttestation($values, $source_id, $compteur, $erreurs);
          }
        }
        else {
          $values = [
            'type' => 'attestation',
            'title' => 'Composition (Colorant) — ' . $papier->label(),
            'field_att_type' => ['target_id' => $type_comp->id()],
            'field_att_axe' => ['target_id' => $axe_colorant->id()],
            'field_att_papier' => ['target_id' => $papier_nid],
            'field_att_valeur' => $colorant_valeur,
            'field_att_statut' => 'probable',
          ];
          $this->creerAttestation($values, $source_id, $compteur, $erreurs);
        }
      }
      else {
        $erreurs[] = 'Colorant : terme(s) attestation_type/attestation_axe introuvable(s) — rien créé.';
      }
    }

    // === Composition — Agent de traitement ===
    $agent_plantes_brutes = $form_state->getValue(['composition', 'agent_plantes']) ?? [];
    $agent_plantes_choisies = array_filter($agent_plantes_brutes);
    $agent_role_id = $form_state->getValue(['composition', 'agent_role']);
    $agent_detail = $form_state->getValue(['composition', 'agent_detail']);
    $agent_role_label = (!empty($agent_role_id) && $agent_role_id !== '_none')
      ? \Drupal\taxonomy\Entity\Term::load($agent_role_id)?->label() : NULL;
    $agent_valeur = trim(($agent_role_label ? "$agent_role_label — " : '') . $agent_detail);

    if ($agent_valeur || $agent_plantes_choisies) {
      $type_comp = $this->trouverTerme('attestation_type', 'Composition');
      $axe_agent = $this->trouverTerme('attestation_axe', 'Agent de traitement');
      if ($type_comp && $axe_agent) {
        if ($agent_plantes_choisies) {
          foreach (array_keys($agent_plantes_choisies) as $plante_id) {
            $plante = Node::load($plante_id);
            $values = [
              'type' => 'attestation',
              'title' => 'Composition (Agent de traitement, ' . ($plante ? $plante->label() : $plante_id) . ') — ' . $papier->label(),
              'field_att_type' => ['target_id' => $type_comp->id()],
              'field_att_axe' => ['target_id' => $axe_agent->id()],
              'field_att_papier' => ['target_id' => $papier_nid],
              'field_att_plante' => ['target_id' => $plante_id],
              'field_att_valeur' => $agent_valeur ?: ('Agent de traitement : ' . ($plante ? $plante->label() : '')),
              'field_att_statut' => 'probable',
            ];
            $this->creerAttestation($values, $source_id, $compteur, $erreurs);
          }
        }
        else {
          $values = [
            'type' => 'attestation',
            'title' => 'Composition (Agent de traitement) — ' . $papier->label(),
            'field_att_type' => ['target_id' => $type_comp->id()],
            'field_att_axe' => ['target_id' => $axe_agent->id()],
            'field_att_papier' => ['target_id' => $papier_nid],
            'field_att_valeur' => $agent_valeur,
            'field_att_statut' => 'probable',
          ];
          $this->creerAttestation($values, $source_id, $compteur, $erreurs);
        }
      }
      else {
        $erreurs[] = 'Agent de traitement : terme(s) attestation_type/attestation_axe introuvable(s) — rien créé.';
      }
    }

    // === Composition — Méthode de fabrication ===
    $methode_valeur = $form_state->getValue(['composition', 'methode']);
    if (!empty($methode_valeur)) {
      $type_comp = $this->trouverTerme('attestation_type', 'Composition');
      $axe_methode = $this->trouverTerme('attestation_axe', 'Méthode de fabrication');
      if ($type_comp && $axe_methode) {
        $values = [
          'type' => 'attestation',
          'title' => 'Composition (Méthode de fabrication) — ' . $papier->label(),
          'field_att_type' => ['target_id' => $type_comp->id()],
          'field_att_axe' => ['target_id' => $axe_methode->id()],
          'field_att_papier' => ['target_id' => $papier_nid],
          'field_att_valeur' => $methode_valeur,
          'field_att_statut' => 'probable',
        ];
        $this->creerAttestation($values, $source_id, $compteur, $erreurs);
      }
      else {
        $erreurs[] = 'Méthode de fabrication : terme(s) introuvable(s) — rien créé.';
      }
    }

    // === Usage ===
    $usages_brutes = $form_state->getValue(['usage', 'termes']) ?? [];
    $usages_choisis = array_filter($usages_brutes);
    $observation = $form_state->getValue(['usage', 'observation']);
    if ($usages_choisis || !empty($observation)) {
      $type_usage = $this->trouverTerme('attestation_type', 'Usage');
      if ($type_usage) {
        $labels = [];
        foreach (array_keys($usages_choisis) as $tid) {
          $t = \Drupal\taxonomy\Entity\Term::load($tid);
          if ($t) $labels[] = $t->label();
        }
        $valeur = implode(', ', $labels);
        if (!empty($observation)) {
          $valeur .= ($valeur ? ' — ' : '') . $observation;
        }
        $values = [
          'type' => 'attestation',
          'title' => 'Usage — ' . $papier->label(),
          'field_att_type' => ['target_id' => $type_usage->id()],
          'field_att_papier' => ['target_id' => $papier_nid],
          'field_att_valeur' => $valeur,
          'field_att_statut' => 'probable',
        ];
        $this->creerAttestation($values, $source_id, $compteur, $erreurs);
      }
      else {
        $erreurs[] = 'Usage : terme attestation_type "Usage" introuvable — rien créé.';
      }
    }

    if ($erreurs) {
      \Drupal::messenger()->addWarning($this->t('@n attestation(s) créée(s), mais des problèmes sont survenus : @detail', [
        '@n' => $compteur, '@detail' => implode(' ; ', $erreurs),
      ]));
    }
    else {
      \Drupal::messenger()->addStatus($this->t('@n attestation(s) créée(s).', ['@n' => $compteur]));
    }

    // Point 3 : chaîner vers le wizard de noms plutôt que la simple fiche —
    // une fiche neuve sort d'ici sans aucun nom canonique.
    if (\Drupal::entityTypeManager()->getStorage('node_type')->load('nom_papier')) {
      \Drupal::messenger()->addStatus($this->t('Dernière étape : donnez au moins un nom à ce papier.'));
      $form_state->setRedirect('khartasia_noms.add_wizard', ['node' => $papier_nid]);
    }
    else {
      $form_state->setRedirect('entity.node.canonical', ['node' => $papier_nid]);
    }
  }

}
