<?php

namespace Drupal\external_entities\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a property mapper attribute object.
 *
 * Plugin Namespace: Plugin\ExternalEntities\PropertyMapper.
 *
 * @see \Drupal\external_entities\PropertyMapper\PropertyMapperManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class PropertyMapper extends Plugin {

  /**
   * Constructs a PropertyMapper attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The human-friendly name of the plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   A description of the plugin.
   * @param string[] $field_properties
   *   An array of field properties the property mapper supports. The syntax
   *   for a field property is "field_type:property_name". The '*' character
   *   can be used to match any field type and any field property.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly array $field_properties = [],
  ) {}

}
