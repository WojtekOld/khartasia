<?php

namespace Drupal\proc\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleExtensionList;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves the Protected Content Service Worker script.
 *
 * A Service Worker's default scope is the directory of its script URL. To let
 * the worker control pages across the whole site, the script is served from a
 * base-path-root route and returned with a permissive Service-Worker-Allowed
 * header.
 */
class ProcServiceWorkerController extends ControllerBase {

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected ModuleExtensionList $moduleExtensionList;

  /**
   * Constructs the controller.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list.
   */
  public function __construct(ModuleExtensionList $module_extension_list) {
    $this->moduleExtensionList = $module_extension_list;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('extension.list.module')
    );
  }

  /**
   * Returns the Service Worker JavaScript with the appropriate headers.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The Service Worker script response.
   */
  public function serve(): Response {
    $module_path = $this->moduleExtensionList->getPath('proc');
    $script_path = $module_path . '/js/proc-service-worker.js';

    if (!is_file($script_path) || !is_readable($script_path)) {
      throw new NotFoundHttpException('Service Worker script not found.');
    }

    $contents = file_get_contents($script_path);
    if ($contents === FALSE) {
      throw new NotFoundHttpException('Service Worker script could not be read.');
    }

    // Inject the absolute URL to OpenPGP.js so the worker can import it during
    // its initial evaluation. The worker is served from the base-path root and
    // cannot otherwise derive the module directory.
    $openpgp_url = base_path() . $module_path . '/js/third_party/unpkg.com/openpgp.min.js';
    $max_entries = (int) ($this->config('proc.settings')->get('proc-cipher-cache-max-entries') ?? 200);
    $prefix = 'self.PROC_OPENPGP_URL = ' . json_encode($openpgp_url) . ";\n"
      . 'self.PROC_CIPHER_CACHE_MAX = ' . $max_entries . ";\n";
    $contents = $prefix . $contents;

    $response = new Response($contents);
    $response->headers->set('Content-Type', 'text/javascript; charset=utf-8');
    // Allow the worker to control the entire origin, regardless of the
    // base-path-root location it is served from.
    $response->headers->set('Service-Worker-Allowed', '/');
    // The browser performs its own byte-comparison update check for service
    // workers; keep the HTTP cache short so code updates propagate promptly.
    $response->headers->set('Cache-Control', 'no-cache, max-age=0, must-revalidate');
    return $response;
  }

}
