<?php

namespace Drupal\khartasia_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\khartasia_core\Service\ParcoursPapier;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Contrôleur du hub — /node/{node}/dossier.
 *
 * Lot 6a : la carte « attestations » affiche les brouillons et les précisions
 * suspectes (définitions : brouillon = attestation non publiée, motif « ebauche » ;
 * précision suspecte = motif posé à la main « precision_suspecte ») et propose les
 * actions de saisie (recette en série, attestation unitaire) aux utilisateurs autorisés.
 */
final class PapierDossierHubController extends ControllerBase {

  public function __construct(
    private readonly ParcoursPapier $parcours,
  ) {}

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('khartasia_core.parcours_papier')
    );
  }

  public function view(NodeInterface $node): array {
    if ($node->bundle() !== 'papers') {
      throw new NotFoundHttpException();
    }

    $etat = $this->parcours->etat($node);
    $build = [];

    foreach (ParcoursPapier::SECTIONS as $key => $section) {
      $build['cards'][$key] = [
        '#theme' => 'khartasia_dossier_card',
        '#title' => $section['label'],
        '#url' => Url::fromRoute($section['route'], [$section['param'] => $node->id()]),
      ];
    }
    $build['cards']['noms']['#summary'] = $this->t('@total noms (@a_revoir à revoir)', [
      '@total' => $etat['noms']['total'],
      '@a_revoir' => $etat['noms']['a_revoir'],
    ]);
    $build['cards']['attestations']['#summary'] = $this->t('@total attestations', [
      '@total' => $etat['attestations']['total'],
    ]);

    $details = [];
    if (($n = (int) ($etat['attestations']['brouillons'] ?? 0)) > 0) {
      $details[] = $this->formatPlural($n, '1 brouillon à compléter', '@count brouillons à compléter');
    }
    if (($n = (int) ($etat['attestations']['precisions_suspectes'] ?? 0)) > 0) {
      $details[] = $this->formatPlural($n, '1 précision suspecte', '@count précisions suspectes');
    }
    if ($details) {
      $build['cards']['attestations']['#details'] = $details;
    }

    $actions = [];
    $liens = [
      ['khartasia_guided_forms.papier_recette', ['papier_nid' => $node->id()], [], $this->t('Saisir la recette (série)')],
      ['khartasia_guided_forms.attestation_ancrage', [], ['query' => ['papier' => $node->id()]], $this->t('Nouvelle attestation')],
    ];
    foreach ($liens as [$route, $params, $options, $titre]) {
      $url = Url::fromRoute($route, $params, $options);
      if ($url->access()) {
        $actions[] = ['title' => $titre, 'url' => $url->toString()];
      }
    }
    if ($actions) {
      $build['cards']['attestations']['#actions'] = $actions;
    }

    $backlog_items = [];
    if ($etat['attestations']['sources_sans_type_source'] > 0) {
      $backlog_items[] = $this->t('@count attestations sourcées sans type de source', ['@count' => $etat['attestations']['sources_sans_type_source']]);
    }
    if ($etat['attestations']['sources_absentes'] > 0) {
      $backlog_items[] = $this->formatPlural(
        $etat['attestations']['sources_absentes'],
        '1 attestation sans source',
        '@count attestations sans source'
      );
    }
    if ($etat['attestations']['contradictions_non_resolues'] > 0) {
      $backlog_items[] = $this->t('@count contradictions non tranchées', ['@count' => $etat['attestations']['contradictions_non_resolues']]);
    }

    if (!empty($backlog_items)) {
      $build['backlog'] = [
        '#theme' => 'item_list',
        '#title' => $this->t('File de revue'),
        '#items' => $backlog_items,
        '#attributes' => ['class' => ['dossier-backlog']],
      ];
    }

    // Les boutons d'action dépendent des permissions de l'utilisateur.
    $build['#cache']['contexts'] = ['user.permissions'];
    $build['#cache']['tags'] = Cache::mergeTags($node->getCacheTags(), ['node_list']);

    return $build;
  }

}
