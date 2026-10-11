<?php

namespace Drupal\khartasia_guided_forms\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Page « Saisie guidée » : point d'entrée unique vers les formulaires de saisie.
 *
 * DÉFINITIONS
 * - Raccourci : lien vers un formulaire ou un écran de saisie. Il est RÉSOLU à partir d'un chemin
 *   d'adresse (« /node/add/papers »…) : s'il n'existe pas ou si l'utilisateur n'y a pas accès, le
 *   raccourci s'affiche grisé (non cliquable) au lieu de mener à une erreur.
 * - Vue « diagramme » : schéma cliquable de l'architecture (univers papier, charnière = attestation,
 *   univers plante, matériaux, référentiels). Vue « liste » : les mêmes raccourcis en liste groupée.
 *   Sur écran étroit, la liste remplace automatiquement le diagramme (texte illisible sinon).
 */
final class SaisieGuideeController extends ControllerBase {

  /** Droits dont l'un suffit pour voir la page. */
  private const DROITS = [
    'create attestation content', 'create papers content', 'create common_names content',
    'create article content', 'administer nodes',
  ];

  public static function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermissions($account, self::DROITS, 'OR');
  }

  public function page(Request $request): array {
    $vue = $request->query->get('vue') === 'liste' ? 'liste' : 'diagramme';
    $liens = $this->liens();

    return [
      '#theme' => 'khartasia_saisie_guidee',
      '#vue' => $vue,
      '#liens' => $liens,
      '#groupes' => $this->groupes(),
      '#selecteur' => \Drupal::formBuilder()->getForm('Drupal\khartasia_guided_forms\Form\SaisiePapierForm'),
      '#url_diagramme' => Url::fromRoute('<current>', [], ['query' => ['vue' => 'diagramme']])->toString(),
      '#url_liste' => Url::fromRoute('<current>', [], ['query' => ['vue' => 'liste']])->toString(),
      '#regles' => self::regles(),
      '#attached' => ['library' => ['khartasia_guided_forms/saisie_guidee']],
      '#cache' => ['contexts' => ['user.permissions', 'url.query_args:vue']],
    ];
  }

  /**
   * Définition des raccourcis : clé => titre, aide, chemin. L'URL est ajoutée si le chemin est valide
   * et accessible à l'utilisateur courant (sinon NULL : raccourci grisé).
   */
  public function liens(): array {
    $defs = [
      'papier_nouveau' => ['Nouveau papier', "Saisie guidée d'une fiche papier : identité, formats, techniques.", '/papier/nouveau-guide'],
      'papier_fiche' => ['Fiche papier (formulaire complet)', 'Le formulaire standard de Drupal, tous les champs.', '/node/add/papers'],
      'nom_papier' => ['Nom de papier', 'Marque, terroir, exonyme… : un nom rattaché à un papier.', '/node/add/nom_papier'],
      'zone' => ['Zone historique de production', 'Ajouter une zone au vocabulaire.', '/admin/structure/taxonomy/manage/zone_historique_production/add'],
      'attestation' => ['Nouvelle attestation', 'Ancrage guidé : source, sujet, objet ; crée un brouillon.', '/khartasia/attestation/nouvelle'],
      'fonction' => ["Fonctions de l'intrant", 'Vocabulaire : fibre principale, colorant, dispersant, adhésif, charge…', '/admin/structure/taxonomy/manage/fonction_intrant/overview'],
      'plante' => ['Fiche plante', 'Classification botanique (pivot latin).', '/node/add/article'],
      'nom_commun' => ['Nom commun', 'Variante, faux nom, polysémie : un nom vernaculaire lié à des plantes ou à un matériau.', '/node/add/common_names'],
      'plante_geo' => ['Plante × lieu × période', 'Présence attestée d\'une plante.', '/node/add/plante_geo'],
      'materiau_nouveau' => ['Nouveau matériau', 'Intrant non végétal ou préparation (colle, argile, cendre…).', '/admin/structure/taxonomy/manage/materiau/add'],
      'materiau_liste' => ['Liste des matériaux', 'Consulter et modifier les matériaux existants.', '/admin/structure/taxonomy/manage/materiau/overview'],
      'source' => ['Nouvelle source (BibCit)', 'Référence bibliographique.', '/bibcite/reference/add'],
      'ecritures' => ['Écritures', "Types d'écriture des noms.", '/admin/structure/taxonomy/manage/type_ecriture/overview'],
      'aires' => ['Aires culturelles', 'Aires et sous-aires culturelles.', '/admin/structure/taxonomy/manage/aire_culturelle/overview'],
    ];
    $liens = [];
    foreach ($defs as $cle => [$titre, $aide, $chemin]) {
      $url = \Drupal::pathValidator()->getUrlIfValid($chemin);
      $liens[$cle] = ['titre' => $titre, 'aide' => $aide, 'chemin' => $chemin, 'url' => $url ? $url->toString() : NULL];
    }
    // La saisie en série part d'un papier à choisir : on renvoie vers le sélecteur de la page.
    $liens['recette'] = [
      'titre' => 'Recette du papier (saisie en série)',
      'aide' => 'Une source, plusieurs intrants : un brouillon par ligne. Choisissez d\'abord le papier.',
      'chemin' => '/papier/{nid}/recette',
      'url' => $liens['attestation']['url'] ? '#choisir-papier' : NULL,
    ];
    return $liens;
  }

  /** Regroupement pour la vue « liste ». */
  public function groupes(): array {
    return [
      ['titre' => 'Univers papier', 'intro' => 'Le papier est le pivot métier : ce que parcourt le public.', 'cles' => ['papier_nouveau', 'papier_fiche', 'nom_papier', 'zone']],
      ['titre' => 'Attestations — la charnière', 'intro' => '« Ce papier contient cet intrant, dans cette fonction » est une affirmation sourcée, jamais un champ.', 'cles' => ['attestation', 'recette', 'fonction']],
      ['titre' => 'Univers plante', 'intro' => 'La plante est le pivot nomenclatural.', 'cles' => ['plante', 'nom_commun', 'plante_geo']],
      ['titre' => 'Matériaux', 'intro' => 'Intrants non végétaux et préparations, de toute origine.', 'cles' => ['materiau_nouveau', 'materiau_liste']],
      ['titre' => 'Référentiels partagés', 'intro' => 'Communs aux trois univers.', 'cles' => ['source', 'ecritures', 'aires']],
    ];
  }

  /** Règles de gouvernance reprises des schémas d'architecture (6 à 13). */
  public static function regles(): array {
    return [
      "Un nom appartient à un univers : les typologies « noms de papiers » et « noms de plantes » restent séparées (même méta-modèle, deux vocabulaires).",
      "Le lien papier → plante ne s'écrit jamais dans un champ de la fiche papier : c'est une attestation d'intrant de fabrication, sourcée, datée, qualifiée.",
      "La classification botanique est de l'infrastructure référentielle (comme BibCit) : pivot de la configuration, non du site.",
      "La fonction appartient à la recette : un même matériau peut être charge ici, adhésif là. Elle se saisit sur l'attestation, pas sur le matériau.",
      "Un intrant végétal brut reste une plante ; une préparation, quelle que soit son origine, est un matériau (pas de doublon).",
      "Matériau = nature × état, en deux champs orthogonaux ; l'origine végétale est une provenance sourcée, jamais la classification botanique.",
      "Un nœud préparation ne se crée que si une source le nomme distinctement (neri ≠ tororo-aoi) ou si la transformation est un enjeu d'identification.",
      "Proportion citée sans méthode = précision fabriquée : la recette est un faisceau d'attestations, pas un tableau de pourcentages.",
    ];
  }

}
