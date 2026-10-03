<?php

namespace Drupal\external_entities\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a storage client attribute object.
 *
 * Plugin Namespace: Plugin\ExternalEntities\StorageClient.
 *
 * @see \Drupal\external_entities\StorageClient\StorageClientManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class StorageClient extends Plugin {

  /**
   * Constructs a StorageClient attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The human-friendly name of the plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   A description of the plugin.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
  ) {}

}
