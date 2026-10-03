<?php

namespace Drupal\proc\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a RecipientStorage attribute object.
 *
 * This plugin implementations defines how the set of recipients of a cipher
 * proc entity is stored and retrieved. This allows the implementing
 * application to delegate recipient storage to an external, rule-based source
 * instead of persisting one database row per recipient.
 *
 * @see \Drupal\proc\RecipientStoragePluginManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class RecipientStorage extends Plugin {

  /**
   * Constructs a RecipientStorage attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   The human-readable description of the plugin, shown in the widget
   *   settings selector.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $description,
  ) {}

}
