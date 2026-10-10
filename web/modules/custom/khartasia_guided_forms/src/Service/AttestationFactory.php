<?php

namespace Drupal\khartasia_guided_forms\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeInterface;

/**
 * Fabrique unique d'attestations de saisie.
 *
 * Point d'entrée commun pour créer un BROUILLON d'attestation (nœud non publié,
 * motif de revue « ebauche ») à partir de l'étape d'ancrage. Les futurs
 * formulaires (saisie en série, lot 5) passeront par la même fabrique, afin
 * qu'aucun formulaire ne reconstruise lui-même un nœud à coups de
 * Node::create() sans contrôle.
 *
 * DÉFINITIONS :
 * - « ancrage » : les trois données qui situent une attestation avant qu'on en
 *   rédige l'énoncé — la source, le sujet (de quoi on parle) et l'objet (ce
 *   qu'on affirme à son sujet, ici une plante).
 * - « brouillon » : attestation enregistrée mais non publiée (statut 0) et
 *   portant le motif `ebauche`. Une attestation non publiée SANS ce motif est
 *   une donnée chargée en attente de revue, pas un brouillon de saisie.
 * - « ancrage » attendu sous forme de tableau :
 *     sujet_type (papier|plante|nom), sujet_id,
 *     objet_plante_id (facultatif), source_id (facultatif),
 *     source_a_creer (booléen).
 */
class AttestationFactory {

  const MOTIF_EBAUCHE = 'ebauche';
  const MOTIF_SOURCE_A_CREER = 'source_a_creer';
  const MOTIF_SOURCE_ABSENTE = 'source_absente';

  /** Champ de l'attestation qui porte le sujet, selon son type. */
  const CHAMP_SUJET = [
    'papier' => 'field_att_papier',
    'plante' => 'field_att_plante',
    'nom' => 'field_att_nom_vernaculaire',
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
  ) {}

  /**
   * Motifs de revue requis par ce lot et absents des valeurs permises du champ.
   *
   * @return string[]
   *   Vide si tout est en place.
   */
  public function motifsManquants(): array {
    $storage = FieldStorageConfig::loadByName('node', 'field_att_revue_motifs');
    $permis = $storage ? array_keys($storage->getSetting('allowed_values') ?: []) : [];
    return array_values(array_diff([self::MOTIF_EBAUCHE, self::MOTIF_SOURCE_A_CREER], $permis));
  }

  /**
   * Attestations déjà enregistrées pour cet ancrage (10 au plus).
   *
   * Sans objet ni source, rien n'est cherché : un papier peut porter des
   * dizaines d'attestations, la liste n'aurait aucun intérêt.
   *
   * @return array[]
   *   Chaque entrée : nid, titre, publie (bool), url.
   */
  public function trouverExistantes(array $ancrage): array {
    $champ = self::CHAMP_SUJET[$ancrage['sujet_type']] ?? NULL;
    $objet = $ancrage['objet_plante_id'] ?? NULL;
    $source = $ancrage['source_id'] ?? NULL;
    if (!$champ || empty($ancrage['sujet_id']) || (!$objet && !$source)) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'attestation')
      ->condition($champ, $ancrage['sujet_id']);
    if ($objet && $ancrage['sujet_type'] !== 'plante') {
      $query->condition('field_att_plante', $objet);
    }
    if ($source) {
      $query->condition('field_att_source', $source);
    }
    $ids = $query->sort('nid', 'DESC')->range(0, 10)->execute();

    $resultat = [];
    foreach ($storage->loadMultiple($ids) as $node) {
      $resultat[] = [
        'nid' => (int) $node->id(),
        'titre' => $node->label(),
        'publie' => $node->isPublished(),
        'url' => $node->toUrl()->toString(),
      ];
    }
    return $resultat;
  }

  /**
   * Doublon « fort » : mêmes sujet, objet ET source qu'une attestation existante.
   */
  public function doublonFort(array $ancrage, array $existantes): bool {
    return !empty($existantes)
      && !empty($ancrage['source_id'])
      && !empty($ancrage['objet_plante_id'])
      && $ancrage['sujet_type'] !== 'plante';
  }

  /**
   * Brouillons de saisie de l'utilisateur courant (les plus récents d'abord).
   */
  public function brouillonsDe(int $uid, int $limite = 8): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'attestation')
      ->condition('uid', $uid)
      ->condition('status', 0)
      ->condition('field_att_revue_motifs', self::MOTIF_EBAUCHE)
      ->sort('changed', 'DESC')
      ->range(0, $limite)
      ->execute();
    $liste = [];
    foreach ($storage->loadMultiple($ids) as $node) {
      $liste[] = ['titre' => $node->label(), 'url' => $node->toUrl('edit-form')->toString()];
    }
    return $liste;
  }

  /**
   * Crée le brouillon. AUCUNE validation de publication n'est faite ici :
   * c'est voulu (un brouillon peut être incomplet). Les règles de publication
   * s'appliquent au passage à « publié » (lot 3).
   */
  public function creerBrouillon(array $ancrage): NodeInterface {
    $champ = self::CHAMP_SUJET[$ancrage['sujet_type']];
    $storage = $this->entityTypeManager->getStorage('node');

    $sujet = $storage->load($ancrage['sujet_id']);
    $titre = 'Brouillon — ' . ($sujet ? $sujet->label() : $ancrage['sujet_id']);
    $objet = NULL;
    if (!empty($ancrage['objet_plante_id']) && $ancrage['sujet_type'] !== 'plante') {
      $objet = $storage->load($ancrage['objet_plante_id']);
      if ($objet) {
        $titre .= ' / ' . $objet->label();
      }
    }

    $motifs = [self::MOTIF_EBAUCHE];
    if (empty($ancrage['source_id'])) {
      $motifs[] = !empty($ancrage['source_a_creer']) ? self::MOTIF_SOURCE_A_CREER : self::MOTIF_SOURCE_ABSENTE;
    }

    $valeurs = [
      'type' => 'attestation',
      'title' => mb_substr($titre, 0, 255),
      'status' => 0,
      'uid' => $this->currentUser->id(),
      $champ => ['target_id' => $ancrage['sujet_id']],
      'field_att_statut' => 'inconnu',
      'field_att_revue_motifs' => array_map(fn($m) => ['value' => $m], $motifs),
    ];
    if ($objet) {
      $valeurs['field_att_plante'] = ['target_id' => $objet->id()];
    }
    if (!empty($ancrage['source_id'])) {
      $valeurs['field_att_source'] = ['target_id' => $ancrage['source_id']];
    }

    $node = $storage->create($valeurs);
    $node->save();
    return $node;
  }

}
