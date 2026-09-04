<?php

declare(strict_types=1);

namespace Drupal\sophron\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\sophron\MimeMapManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Routing callbacks for JSON routes of the Sophron module.
 */
class JsonController extends ControllerBase {

  // phpcs:disable
  /**
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   */
  // phpcs:enable
  public function __construct(
    protected readonly MimeMapManagerInterface $mimeMapManager,
    protected $configFactory,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(MimeMapManagerInterface::class),
      $container->get(ConfigFactoryInterface::class),
    );
  }

  /**
   * Build a JSON response of mime types and their extensions.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   A JSON response containing the JSON data to render.
   */
  public function mimeTypes(): CacheableJsonResponse {
    $config = $this->configFactory->get('sophron.settings');

    $response = new CacheableJsonResponse();
    $response->setContent($this->mimeMapManager->getMimeTypesJson());
    $response->addCacheableDependency((new CacheableMetadata())
      ->setCacheTags($config->getCacheTags())
      ->setCacheMaxAge(60 * 60)
    );
    return $response;
  }

}
