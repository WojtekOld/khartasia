<?php

namespace Drupal\khartasia_pdf\Service;

/**
 * Libellés de l'export PDF, en français et en anglais.
 *
 * DÉFINITIONS
 * libellé : un texte fixe de l'interface (titre de section, nom de champ), par opposition au contenu des fiches.
 * Les contenus (énoncés, notes) ne sont jamais traduits : ils s'affichent tels qu'enregistrés.
 */
class Labels {

  /** Mention de copyright imprimée dans le pied de page de chaque page. */
  const COPYRIGHT = '© Khartasia — C. Laroque';

  public static function get(string $lang): array {
    $fr = [
      'colon' => ' :', 'doc_public' => 'Fiche', 'doc_relecture' => 'Dossier de relecture', 'sub' => 'Noms vernaculaires et attestations', 'fam' => 'Famille',
      'read_t' => 'Lire ce document',
      'read_p' => "Chaque nom est enregistré avec ce que dit une source, à quelle page, et jusqu'où nous l'avons vérifié. Le statut mesure notre <b>vérification</b>, pas la vraisemblance botanique. Ce document affiche tous les statuts, y compris « attesté » ; le site public ne signale que les exceptions.",
      'lang' => 'Langue', 'designates' => 'Désigne', 'variant_of' => 'Variante de', 'transl' => 'translittération',
      'stmt' => 'Énoncé enregistré', 'source' => 'Source', 'concept' => 'Nomenclature suivie',
      'note' => 'Note de travail (non publiée).', 'unpub' => 'Non publié', 'no_att' => 'Aucune attestation enregistrée.',
      'sec_refs' => 'Références', 'none' => 'Aucun nom vernaculaire enregistré pour cette plante.', 'lang_unknown' => 'Langue non précisée',
      'footer_l' => self::COPYRIGHT, 'page' => 'Page', 'gen' => 'Généré le', 'draft' => 'Pour relecture', 'wm' => 'EN RELECTURE', 'repli' => '',
      'st' => ['atteste' => 'Attesté', 'probable' => 'Probable', 'hypothetique' => 'Hypothétique', 'refute' => 'Réfuté', 'inconnu' => 'À vérifier'],
      'concepts' => ['powo' => 'POWO', 'grin' => 'GRIN', 'flora_china' => 'Flora of China', 'autre' => 'Autre'],
      'types' => [],
      'langues' => [],
    ];
    $en = [
      'colon' => ':', 'doc_public' => 'Record', 'doc_relecture' => 'Review dossier', 'sub' => 'Vernacular names and attestations', 'fam' => 'Family',
      'read_t' => 'How to read this document',
      'read_p' => 'Each name is recorded with what a source says, at which page, and how far we have verified it. The status measures our <b>verification</b>, not botanical likelihood. This document shows every status, including “attested”; the public site only flags the exceptions.',
      'lang' => 'Language', 'designates' => 'Designates', 'variant_of' => 'Variant of', 'transl' => 'transliteration',
      'stmt' => 'Recorded statement', 'source' => 'Source', 'concept' => 'Nomenclature followed',
      'note' => 'Working note (unpublished).', 'unpub' => 'Unpublished', 'no_att' => 'No attestation recorded.',
      'sec_refs' => 'References', 'none' => 'No vernacular name recorded for this plant.', 'lang_unknown' => 'Language not specified',
      'footer_l' => self::COPYRIGHT, 'page' => 'Page', 'gen' => 'Generated on', 'draft' => 'For review', 'wm' => 'UNDER REVIEW', 'repli' => '(French text: no English version recorded)',
      'st' => ['atteste' => 'Attested', 'probable' => 'Probable', 'hypothetique' => 'Hypothetical', 'refute' => 'Refuted', 'inconnu' => 'To check'],
      'concepts' => ['powo' => 'POWO', 'grin' => 'GRIN', 'flora_china' => 'Flora of China', 'autre' => 'Other'],
      'langues' => [
        'Anglais' => 'English', 'Chinois' => 'Chinese', 'Coréen' => 'Korean', 'Français' => 'French', 'Japonais' => 'Japanese',
        'Tibétain' => 'Tibetan', 'Thai' => 'Thai', 'Népalais' => 'Nepali', 'Dzongkha' => 'Dzongkha',
      ],
      'types' => [
        'Désignation' => 'Designation', 'Usage' => 'Use', 'Géographie' => 'Geography', 'Composition' => 'Composition',
        'Circuit commercial' => 'Trade circuit', 'Dégradation matérielle' => 'Material degradation',
        'Critère morphologique' => 'Morphological criterion', 'Écriture vernaculaire' => 'Vernacular writing',
        'Datation' => 'Dating', 'Synonyme' => 'Synonym',
      ],
    ];
    return $lang === 'en' ? $en : $fr;
  }

}
