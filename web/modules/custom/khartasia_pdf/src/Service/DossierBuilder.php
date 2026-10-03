<?php

namespace Drupal\khartasia_pdf\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Assemble les données du dossier d'une plante : ses noms vernaculaires, regroupés par langue, et leurs attestations.
 *
 * Ne produit aucun HTML : un tableau, que les gabarits Twig mettent en page. Deux modes :
 *  - public (par défaut) : contenus PUBLIÉS seulement, sans notes de travail ;
 *  - relecture : contenus non publiés inclus (signalés), notes de travail incluses.
 *
 * DÉFINITIONS
 * attestation : une affirmation d'une source sur un nom (énoncé, source, page, statut).
 * note de travail : champ interne de l'attestation (provenance, validation) ; jamais publiée.
 */
class DossierBuilder {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CitationFormatter $citation,
  ) {}

  public function build($plante, bool $relecture, string $lang = 'fr', ?string $langue = NULL): array {
    $L = Labels::get($lang);
    $noeuds = $this->entityTypeManager->getStorage('node');

    $q = $noeuds->getQuery()->accessCheck(FALSE)
      ->condition('type', 'common_names')
      ->condition('field_genus_com_entity', $plante->id());
    if (!$relecture) {
      $q->condition('status', 1);
    }
    $noms = $noeuds->loadMultiple($q->execute());

    if ($langue !== NULL && $langue !== '') {
      // Le filtre accepte le nom du vocabulaire (« tibétain ») comme son équivalent affiché (« Tibetan »).
      $noms = array_filter($noms, function ($n) use ($langue, $L) {
        $brut = $this->libelle($n, 'field_language_taxo_vern');
        $cible = mb_strtolower($langue);
        return mb_strtolower($brut) === $cible || mb_strtolower($L['langues'][$brut] ?? $brut) === $cible;
      });
    }

    // Attestations de tous ces noms, en une seule requête.
    $par_nom = [];
    if ($noms) {
      $qa = $noeuds->getQuery()->accessCheck(FALSE)
        ->condition('type', 'attestation')
        ->condition('field_att_nom_vernaculaire', array_keys($noms), 'IN');
      if (!$relecture) {
        $qa->condition('status', 1);
      }
      foreach ($noeuds->loadMultiple($qa->execute()) as $att) {
        $par_nom[(int) $att->get('field_att_nom_vernaculaire')->target_id][] = $att;
      }
    }

    $refs = [];
    $groupes = [];
    $nb_att = 0;
    foreach ($noms as $nid => $nom) {
      $brut_langue = $this->libelle($nom, 'field_language_taxo_vern');
      $langue_nom = $L['langues'][$brut_langue] ?? $brut_langue;
      $attestations = [];
      foreach ($par_nom[(int) $nid] ?? [] as $att) {
        $attestations[] = $this->attestation($att, $L, $relecture, $refs, $lang);
      }
      usort($attestations, function ($a, $b) {
        return [$a['type_cle'] === 'Désignation' ? 0 : 1, $a['type_cle']] <=> [$b['type_cle'] === 'Désignation' ? 0 : 1, $b['type_cle']];
      });
      $nb_att += count($attestations);

      $plantes = [];
      if ($nom->hasField('field_genus_com_entity')) {
        foreach ($nom->get('field_genus_com_entity') as $item) {
          if ($item->entity) {
            $plantes[trim((string) $item->entity->label())] = TRUE;
          }
        }
      }
      $groupes[$langue_nom][] = [
        'nid' => (int) $nid,
        'titre' => trim((string) $nom->label()),
        'ecriture' => $this->texte($nom, 'field_ver_local_scripture'),
        'translit' => $this->texte($nom, 'field_ver_translitteration'),
        'langue' => $langue_nom,
        'parent' => $this->libelle($nom, 'field_nom_parent'),
        'plantes' => array_keys($plantes),
        'publie' => (bool) $nom->isPublished(),
        'badges' => $this->badges($attestations),
        'attestations' => $attestations,
      ];
    }

    ksort($groupes, SORT_NATURAL | SORT_FLAG_CASE);
    $sortie = [];
    foreach ($groupes as $nom_langue => $liste) {
      // Les noms sans parent d'abord, puis par ordre alphabétique.
      usort($liste, function ($a, $b) {
        return [$a['parent'] !== '' ? 1 : 0, mb_strtolower($a['titre'])] <=> [$b['parent'] !== '' ? 1 : 0, mb_strtolower($b['titre'])];
      });
      $sortie[] = ['langue' => (string) $nom_langue, 'noms' => $liste];
    }

    return [
      'plante' => [
        'nid' => (int) $plante->id(),
        'titre' => trim((string) $plante->label()),
        'famille' => $this->libelle($plante, 'field_tax_family'),
        'ordre' => $this->libelle($plante, 'field_tax_order'),
      ],
      'groupes' => $sortie,
      'nb_noms' => count($noms),
      'nb_att' => $nb_att,
      'refs' => array_values($refs),
      'relecture' => $relecture,
      'filtre_langue' => $langue,
    ];
  }

  /** Pastilles de statut d'un nom : une par statut distinct, dans l'ordre des attestations. */
  protected function badges(array $attestations): array {
    $badges = [];
    foreach ($attestations as $a) {
      $badges[$a['statut']] = ['classe' => $a['statut_classe'], 'label' => $a['statut_label']];
    }
    return array_values($badges);
  }

  protected function attestation($att, array $L, bool $relecture, array &$refs, string $lang): array {
    $type = $this->libelle($att, 'field_att_type');
    $statut = $this->texte($att, 'field_att_statut') ?: 'inconnu';
    $concept = $this->texte($att, 'field_att_concept_taxo');
    $locateur = $this->texte($att, 'field_att_locateur');

    $source = '';
    if ($att->hasField('field_att_source') && !$att->get('field_att_source')->isEmpty()) {
      $ref = $att->get('field_att_source')->entity;
      if ($ref) {
        $source = $this->citation->court($ref);
        $refs[(int) $ref->id()] = ['id' => (int) $ref->id(), 'html' => $this->citation->complete($ref)];
      }
    }
    if ($locateur !== '') {
      $source = $source !== '' ? $source . ', ' . $locateur : $locateur;
    }

    // L'énoncé de référence est le français (« Valeur »). En anglais, on affiche la traduction de l'équipe si elle existe ;
    // sinon le français, et on le signale pour qu'il ne passe pas pour une traduction.
    $valeur = $this->texte($att, 'field_att_valeur');
    $valeur_repli = FALSE;
    if ($lang === 'en') {
      $en = $this->texte($att, 'field_att_valeur_en');
      if ($en !== '') {
        $valeur = $en;
      }
      elseif ($valeur !== '') {
        $valeur_repli = TRUE;
      }
    }

    // Note de travail : réservée au mode relecture. En anglais, la note anglaise si elle existe ; sinon la française, signalée.
    $note = '';
    $note_repli = FALSE;
    if ($relecture) {
      $note = $this->texte($att, 'field_att_note_interne');
      if ($lang === 'en') {
        $note_en = $this->texte($att, 'field_att_note_interne_en');
        if ($note_en !== '') {
          $note = $note_en;
        }
        elseif ($note !== '') {
          $note_repli = TRUE;
        }
      }
    }

    return [
      'nid' => (int) $att->id(),
      'type_cle' => $type,
      'type' => $L['types'][$type] ?? $type,
      'valeur' => $valeur,
      'valeur_repli' => $valeur_repli,
      'statut' => $statut,
      'statut_label' => $L['st'][$statut] ?? $statut,
      'statut_classe' => $statut === 'atteste' ? 'att' : ($statut === 'probable' ? 'prob' : 'oth'),
      'source' => $source,
      'concept' => $concept !== '' ? ($L['concepts'][$concept] ?? $concept) : '',
      'note' => $note,
      'note_repli' => $note_repli,
      'publie' => (bool) $att->isPublished(),
    ];
  }

  protected function texte($e, string $champ): string {
    return $e->hasField($champ) && !$e->get($champ)->isEmpty() ? trim((string) $e->get($champ)->value) : '';
  }

  protected function libelle($e, string $champ): string {
    if (!$e->hasField($champ) || $e->get($champ)->isEmpty()) {
      return '';
    }
    $cible = $e->get($champ)->entity;
    return $cible ? trim((string) $cible->label()) : '';
  }

}
