<?php

namespace Drupal\proc\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a ProcReEncRecSet attribute object.
 *
 * Plugin implementations define the wished set of recipients for
 * re-encryption.
 *
 * @see \Drupal\proc\ProcReEncRecSetPluginManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ProcReEncRecSet extends Plugin {

  /**
   * Constructs a ProcReEncRecSet attribute.
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
