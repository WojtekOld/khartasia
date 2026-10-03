<?php

namespace Drupal\proc\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a RecipientStorage annotation object.
 *
 * Provided for backwards compatibility with the annotation-based plugin
 * discovery. New plugins should use the
 * \Drupal\proc\Attribute\RecipientStorage attribute instead.
 *
 * @see \Drupal\proc\RecipientStoragePluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class RecipientStorage extends Plugin {

  /**
   * The human-readable description of the recipient storage plugin.
   *
   * @ingroup plugin_translatable
   */
  public Translation $description;

}
