<?php

namespace Drupal\proc\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a ProcRelabelling attribute object.
 *
 * Plugin implementations allow the relabeling of a file cipher text just
 * created.
 *
 * @see \Drupal\proc\ProcRelabellingPluginManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ProcRelabelling extends Plugin {

  /**
   * Constructs a ProcRelabelling attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   The description of the plugin.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $description,
  ) {}

}
