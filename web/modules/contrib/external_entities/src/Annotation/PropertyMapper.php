<?php

namespace Drupal\external_entities\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a property mapper annotation object.
 *
 * @see \Drupal\external_entities\PropertyMapper\PropertyMapperManager
 * @see plugin_api
 *
 * @Annotation
 */
class PropertyMapper extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-friendly name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * An array of field properties the property mapper supports.
   *
   * The syntax for a field property is "field_type:property_name". The '*'
   * character can be used to match any field type and any field property, ie.
   * '*:*' supports any property while '*:value' supports the 'value' property
   * of any field type.
   *
   * @var array
   */
  public $field_properties = [];

}
