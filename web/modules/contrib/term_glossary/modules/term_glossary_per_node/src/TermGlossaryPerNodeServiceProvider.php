<?php

declare(strict_types=1);

namespace Drupal\term_glossary_per_node;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\term_glossary_per_node\Service\TermGlossaryPerNodeManager;

/**
 * ServiceProvider to swap TermGlossaryManager with TermGlossaryPerNodeManager.
 */
final class TermGlossaryPerNodeServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if ($container->hasDefinition('term_glossary.manager')) {
      $definition = $container->getDefinition('term_glossary.manager');
      $definition->setClass(TermGlossaryPerNodeManager::class);
    }
  }

}
