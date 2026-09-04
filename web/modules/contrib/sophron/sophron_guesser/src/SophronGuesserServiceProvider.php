<?php

declare(strict_types=1);

namespace Drupal\sophron_guesser;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\File\MimeType\MimeTypeMapInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Modifies the "Drupal\Core\File\MimeType\MimeTypeMapInterface" service.
 */
class SophronGuesserServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Overrides "Drupal\Core\File\MimeType\MimeTypeMapInterface" to use
    // Sophron.
    $definition = $container->getDefinition(MimeTypeMapInterface::class);
    $definition->setClass(SophronMimeTypeMap::class)
      ->setFactory([
        new Reference(SophronMimeTypeMapFactory::class),
        'create',
      ]);
  }

}
