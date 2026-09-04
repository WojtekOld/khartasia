<?php

declare(strict_types=1);

namespace Drupal\ui_patterns\Resolver;

use Drupal\Core\Entity\EntityInterface;

/**
 * Default implementation of the chain base context entity resolver.
 */
class ChainContextEntityResolver implements ChainContextEntityResolverInterface {

  public function __construct(
    protected array $resolvers = [],
  ) {}

  /**
   * {@inheritdoc}
   */
  public function addResolver(ContextEntityResolverInterface $resolver): void {
    $this->resolvers[] = $resolver;
  }

  /**
   * {@inheritdoc}
   */
  public function getResolvers(): array {
    return $this->resolvers;
  }

  /**
   * {@inheritdoc}
   */
  public function guessEntity(array $contexts = []): ?EntityInterface {
    foreach ($this->resolvers as $resolver) {
      $result = $resolver->guessEntity($contexts);
      if ($result) {
        return $result;
      }
    }
    return NULL;
  }

}
