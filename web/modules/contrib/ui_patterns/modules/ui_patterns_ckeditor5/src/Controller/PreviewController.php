<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_ckeditor5\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\filter\FilterFormatInterface;
use Drupal\filter\Plugin\FilterInterface;
use Drupal\ui_patterns_ckeditor5\HostEntity;
use Drupal\ui_patterns_ckeditor5\Plugin\Filter\ComponentEmbed;
use Drupal\ui_patterns_ckeditor5\RenderingFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders the preview shown inside the editor for a component widget.
 */
final class PreviewController extends ControllerBase {

  /**
   * Request header carrying the CSRF token of the preview request.
   */
  public const CSRF_HEADER = 'X-Drupal-ComponentPreview-CSRF-Token';

  public function __construct(
    protected RendererInterface $renderer,
    protected CsrfTokenGenerator $csrfToken,
    protected ComponentPluginManager $componentPluginManager,
    protected HostEntity $hostEntity,
    protected RenderingFilter $renderingFilter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new self(
      $container->get('renderer'),
      $container->get('csrf_token'),
      $container->get('plugin.manager.sdc'),
      $container->get('ui_patterns_ckeditor5.host_entity'),
      $container->get('ui_patterns_ckeditor5.rendering_filter'),
    );
  }

  /**
   * Renders the component described by the JSON request body.
   *
   * The body carries component_id, component_settings (the component
   * configuration, as an array or a JSON string) and optionally entity_type,
   * entity_id and entity_bundle for the entity context. The route checks
   * that the text format uses the component_embed filter and that the user
   * may use it. A forbidden component gets a 403, shown by the editor as an
   * error preview.
   */
  public function preview(FilterFormatInterface $filter_format, Request $request): Response {
    self::checkCsrf($request, $this->currentUser(), $this->csrfToken);
    $content = Json::decode((string) $request->getContent());
    $content = \is_array($content) ? $content : [];
    $component_id = $content['component_id'] ?? '';
    if (!\is_string($component_id) || !$this->componentPluginManager->hasDefinition($component_id)) {
      throw new NotFoundHttpException();
    }
    $filter = $filter_format->filters('component_embed');
    if (!$filter instanceof ComponentEmbed || !$filter->isComponentAllowed($component_id)) {
      throw new AccessDeniedHttpException();
    }
    $definition = $this->componentPluginManager->getDefinition($component_id);
    $settings = $content['component_settings'] ?? [];
    if (\is_string($settings)) {
      $settings = Json::decode($settings);
    }
    $build = [
      '#type' => 'component',
      '#component' => $component_id,
      '#ui_patterns' => \is_array($settings) ? $settings : [],
      '#source_contexts' => $this->hostEntity->getContextsFromValues($content),
    ];
    // The label names the widget when the component renders nothing visible.
    $headers = [
      'Drupal-Component-Label' => (string) ($definition['name'] ?? $component_id),
    ];
    $this->renderingFilter->push($filter);
    try {
      $markup = (string) $this->renderer->renderInIsolation($build);
    }
    finally {
      $this->renderingFilter->pop();
    }
    return new Response($markup, 200, $headers);
  }

  /**
   * Access callback: the text format must use the component_embed filter.
   */
  public static function formatUsesComponentEmbedFilter(FilterFormatInterface $filter_format): AccessResultInterface {
    $filter = $filter_format->filters('component_embed');
    $enabled = $filter instanceof FilterInterface && !empty($filter->getConfiguration()['status']);
    return AccessResult::allowedIf($enabled)->addCacheableDependency($filter_format);
  }

  /**
   * Throws an AccessDeniedHttpException when the CSRF header is invalid.
   *
   * Same protection as core's media preview: anonymous users only need the
   * header, authenticated users need a valid token in it.
   */
  private static function checkCsrf(Request $request, AccountInterface $account, CsrfTokenGenerator $csrf_token): void {
    if (!$request->headers->has(self::CSRF_HEADER)) {
      throw new AccessDeniedHttpException();
    }
    if ($account->isAnonymous()) {
      return;
    }
    if (!$csrf_token->validate((string) $request->headers->get(self::CSRF_HEADER), self::CSRF_HEADER)) {
      throw new AccessDeniedHttpException();
    }
  }

}
