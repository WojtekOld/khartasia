<?php

namespace Drupal\external_entities\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a field mapper annotation object.
 *
 * @see \Drupal\external_entities\FieldMapper\FieldMapperManager
 * @see plugin_api
 *
 * @Annotation
 */
class FieldMapper extends Plugin {

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
   * An array of field types the field mapper supports.
   *
   * The '*' character can be used to support any field type.
   *
   * @var array
   */
  public $field_types = [];

}
