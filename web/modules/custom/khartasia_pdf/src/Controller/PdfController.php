<?php

namespace Drupal\khartasia_pdf\Controller;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\khartasia_pdf\Service\DossierBuilder;
use Drupal\khartasia_pdf\Service\Labels;
use Drupal\khartasia_pdf\Service\PdfRenderer;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Route /node/{nid}/dossier-pdf : le dossier PDF d'une plante.
 *
 * DÉFINITIONS
 * mode public : contenus publiés seulement ; mode relecture (?mode=relecture) : contenus non publiés et notes de travail,
 *   réservé à la permission « view khartasia review pdf ».
 */
class PdfController implements ContainerInjectionInterface {

  const PERMISSION = 'view khartasia review pdf';

  public function __construct(
    protected DossierBuilder $builder,
    protected PdfRenderer $renderer,
    protected TransliterationInterface $transliteration,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('khartasia_pdf.dossier_builder'),
      $container->get('khartasia_pdf.renderer'),
      $container->get('transliteration'),
    );
  }

  /**
   * Règle d'accès, isolée pour être vérifiée seule.
   *  - relecture : la permission dédiée suffit (le contenu peut être non publié) ;
   *  - public : la plante doit être publiée ET visible par l'utilisateur.
   */
  public static function decide(bool $relecture, bool $publie, bool $peut_voir, bool $a_permission): bool {
    return $relecture ? $a_permission : ($publie && $peut_voir);
  }

  public function access(NodeInterface $node, AccountInterface $account, Request $request): AccessResultInterface {
    $relecture = $request->query->get('mode') === 'relecture';
    $ok = self::decide($relecture, (bool) $node->isPublished(), (bool) $node->access('view', $account), (bool) $account->hasPermission(self::PERMISSION));
    $resultat = $ok ? AccessResult::allowed() : AccessResult::forbidden();
    return $resultat->addCacheableDependency($node)->cachePerPermissions()->addCacheContexts(['url.query_args:mode']);
  }

  public function dossier(NodeInterface $node, Request $request): Response {
    if ($node->bundle() !== 'article') {
      throw new NotFoundHttpException();
    }
    $relecture = $request->query->get('mode') === 'relecture';
    $lang = $request->query->get('lang') === 'en' ? 'en' : 'fr';
    $langue = trim((string) $request->query->get('langue', '')) ?: NULL;

    $d = $this->builder->build($node, $relecture, $lang, $langue);
    $url = $node->toUrl('canonical', ['absolute' => TRUE])->toString();
    $pdf = $this->renderer->pdf($d, Labels::get($lang), $relecture, $url, date('Y-m-d'));

    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($this->transliteration->transliterate($d['plante']['titre'], 'en'))), '-');
    $nom = 'khartasia-' . ($slug !== '' ? $slug : 'fiche') . '-dossier' . ($relecture ? '-relecture' : '') . '.pdf';
    $disposition = $request->query->get('telecharger') ? 'attachment' : 'inline';

    return new Response($pdf, 200, [
      'Content-Type' => 'application/pdf',
      'Content-Disposition' => $disposition . '; filename="' . $nom . '"',
      'Cache-Control' => 'private, no-store',
    ]);
  }

}
