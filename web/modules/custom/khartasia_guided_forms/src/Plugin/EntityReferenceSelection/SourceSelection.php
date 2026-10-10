<?php

namespace Drupal\khartasia_guided_forms\Plugin\EntityReferenceSelection;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Sélecteur de sources bibliographiques : auteur, année ou titre.
 *
 * Remplace la recherche « sur le titre seul » du sélecteur standard (cause des
 * doublons de notices). Dans une liste de suggestions, chaque source s'affiche
 * « Auteur(s) (année) — Titre », ce qui permet de distinguer deux notices au
 * titre voisin.
 *
 * Utilisation dans un élément de formulaire :
 *   '#type' => 'entity_autocomplete',
 *   '#target_type' => 'bibcite_reference',
 *   '#selection_handler' => 'default:khartasia_source',
 *
 * DÉFINITIONS :
 * - « sélecteur » (selection handler) : le composant qui décide quelles entités
 *   proposer pour une saisie donnée dans un champ de référence.
 * - L'identifiant doit commencer par « default: » (règle de Drupal : il doit
 *   être égal au groupe ou préfixé par lui).
 */
#[EntityReferenceSelection(
  id: 'default:khartasia_source',
  label: new TranslatableMarkup('Sources Khartasia (auteur, année, titre)'),
  group: 'default',
  weight: 10,
  entity_types: ['bibcite_reference'],
)]
class SourceSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   *
   * Ajoute la recherche par nom d'auteur et par année à celle par titre.
   * Chaque champ n'est interrogé que s'il existe réellement (une faute de nom
   * de champ ferait échouer toute la requête).
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    // Requête de base (accès, bundles, tri) sans condition de recherche.
    $query = parent::buildEntityQuery(NULL, $match_operator);

    if ($match !== NULL && trim((string) $match) !== '') {
      $efm = \Drupal::service('entity_field.manager');
      $champs_ref = $efm->getBaseFieldDefinitions('bibcite_reference');
      $champs_ctr = $efm->getBaseFieldDefinitions('bibcite_contributor');

      $or = $query->orConditionGroup();
      $or->condition('title', $match, $match_operator);
      if (isset($champs_ref['author']) && isset($champs_ctr['last_name'])) {
        $or->condition('author.entity.last_name', $match, $match_operator);
      }
      if (isset($champs_ref['bibcite_year'])) {
        $or->condition('bibcite_year', $match, $match_operator);
      }
      $query->condition($or);
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   *
   * Même contrat que le parent, avec une étiquette « Auteur (année) — Titre ».
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $query = $this->buildEntityQuery($match, $match_operator);
    if ($limit > 0) {
      $query->range(0, $limit);
    }
    $ids = $query->execute();
    if (!$ids) {
      return [];
    }

    $options = [];
    $entities = $this->entityTypeManager->getStorage('bibcite_reference')->loadMultiple($ids);
    foreach ($entities as $id => $entity) {
      $options[$entity->bundle()][$id] = Html::escape($this->etiquette($entity));
    }
    return $options;
  }

  /**
   * Construit « Auteur1; Auteur2 (année) — Titre ».
   */
  protected function etiquette($entity): string {
    $auteurs = [];
    if ($entity->hasField('author')) {
      foreach ($entity->get('author')->referencedEntities() as $contributeur) {
        $auteurs[] = $contributeur->label();
      }
    }
    $annee = ($entity->hasField('bibcite_year') && !$entity->get('bibcite_year')->isEmpty())
      ? $entity->get('bibcite_year')->value : '';

    $morceaux = [];
    if ($auteurs) {
      $morceaux[] = implode('; ', array_slice($auteurs, 0, 3)) . (count($auteurs) > 3 ? ' et al.' : '');
    }
    if ($annee !== '') {
      $morceaux[] = '(' . $annee . ')';
    }
    $debut = implode(' ', $morceaux);
    $titre = Unicode::truncate((string) $entity->label(), 90, TRUE, TRUE);
    return $debut !== '' ? $debut . ' — ' . $titre : $titre;
  }

}
