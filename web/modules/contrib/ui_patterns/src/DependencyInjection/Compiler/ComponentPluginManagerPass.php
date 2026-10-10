<?php

declare(strict_types=1);

namespace Drupal\ui_patterns\DependencyInjection\Compiler;

use Drupal\Core\Theme\ComponentPluginManager as SdcPluginManager;
use Drupal\ui_patterns\ComponentPluginManager;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Stops decorating plugin.manager.sdc when a module replaced its class.
 *
 * That module types against its own class.
 */
final class ComponentPluginManagerPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    if (!$container->hasDefinition(ComponentPluginManager::class) || !$container->hasDefinition('plugin.manager.sdc')) {
      return;
    }
    $sdc_class = $container->getParameterBag()->resolveValue($container->getDefinition('plugin.manager.sdc')->getClass());
    if ($sdc_class === SdcPluginManager::class) {
      return;
    }
    $container->getDefinition(ComponentPluginManager::class)
      ->setDecoratedService(NULL)
      ->setArgument('$decorated', new Reference('plugin.manager.sdc'));
  }

}
