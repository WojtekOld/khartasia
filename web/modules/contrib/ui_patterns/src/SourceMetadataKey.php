<?php

declare(strict_types=1);

namespace Drupal\ui_patterns;

/**
 * Keys of the "metadata" bag in derived plugin definitions.
 *
 * Derivers write with `SourceMetadataKey::X->value`, plugins read with
 * SourcePluginBase::getMetadata().
 *
 * @phpstan-type SourceMetadataShape array{field: array{type?: string|null, cardinality: int}, field_name: string, property?: string, provider?: string|null}
 */
enum SourceMetadataKey: string {

  case Field = 'field';
  case FieldName = 'field_name';
  case Property = 'property';
  case Provider = 'provider';
  case Type = 'type';
  case Cardinality = 'cardinality';

}
