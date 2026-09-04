<?php

declare(strict_types=1);

namespace Drupal\ui_patterns\SchemaManager;

use JsonSchema\Constraints\BaseConstraint;
use JsonSchema\Exception\RuntimeException;
use JsonSchema\SchemaStorage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * JSON Schema References resolver.
 *
 * Because SchemaStorage::resolveRefSchema() is not recursively resolving the
 * referenced schemas.
 * See: https://github.com/justinrainbow/json-schema/issues/427
 */
class ReferencesResolver {

  public const int MAXIMUM_RECURSIVITY_LEVEL = 10;

  public function __construct(
    #[Autowire(service: 'logger.channel.ui_patterns')]
    protected LoggerInterface $logger,
  ) {}

  /**
   * Resolve schema references recursively.
   */
  public function resolve(array $schema, int $depth = 0): array {
    if ($depth > self::MAXIMUM_RECURSIVITY_LEVEL) {
      return $schema;
    }

    ++$depth;

    // The resolver converts arrays to objects and adds an "id" property.
    if (isset($schema['$ref'])) {
      $storage = new SchemaStorage();

      try {
        $schemaObject = BaseConstraint::arrayToObjectRecursive($schema);
        $refSchema = (array) $storage->resolveRefSchema($schemaObject);
        $schema = (array) $schemaObject;

        unset($schema['$ref']);

        // Merge referenced schema into the current schema.
        $schema += $refSchema;

        // The "id" added by the resolver is a string. A schema declaring a
        // property named "id" holds a schema definition instead.
        if (isset($schema['id']) && \is_string($schema['id'])) {
          // Prop types like enum_list has an underscore.
          // This leads to an "Invalid URL format" exception.
          $schema['id'] = \str_replace('_', '-', $schema['id']);
        }
      }
      catch (RuntimeException $e) {
        // $schema is untouched: only resolveRefSchema() throws.
        $this->logger->error("Could not resolve schema referenced by \$ref property '@ref': @error", [
          '@ref' => $schema['$ref'],
          '@error' => $e->getMessage(),
        ]);
      }
    }

    // Recursively resolve nested schemas.
    foreach ($schema as $key => $value) {
      if (\is_object($value)) {
        $schema[$key] = $this->resolve((array) $value, $depth);
      }
      elseif (\is_array($value)) {
        $schema[$key] = $this->resolve($value, $depth);
      }
    }

    return $schema;
  }

}
