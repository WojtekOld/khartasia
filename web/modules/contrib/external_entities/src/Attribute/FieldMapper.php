<?php

namespace Drupal\external_entities\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a field mapper attribute object.
 *
 * Plugin Namespace: Plugin\ExternalEntities\FieldMapper.
 *
 * @see \Drupal\external_entities\FieldMapper\FieldMapperManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class FieldMapper extends Plugin {

  /**
   * Constructs a FieldMapper attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The human-friendly name of the plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   A description of the plugin.
   * @param string[] $field_types
   *   An array of field types the field mapper supports. The '*' character can
   *   be used to support any field type.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly array $field_types = [],
  ) {}

}
