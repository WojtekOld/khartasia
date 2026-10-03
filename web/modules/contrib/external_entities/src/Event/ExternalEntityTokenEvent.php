<?php

namespace Drupal\external_entities\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\external_entities\Entity\ExternalEntityTypeInterface;
use Drupal\external_entities\StorageClient\StorageClientInterface;

/**
 * Defines an external entity token event.
 */
class ExternalEntityTokenEvent extends Event {

  /**
   * Tokens.
   *
   * @var array
   */
  protected $tokens = [];

  /**
   * The external entity type this storage client is configured for.
   *
   * @var \Drupal\external_entities\Entity\ExternalEntityTypeInterface|null
   */
  protected $externalEntityType;

  /**
   * The storage client used.
   *
   * @var \Drupal\external_entities\StorageClient\StorageClientInterface|null
   */
  protected $storageClient;

  /**
   * Constructs a token event object.
   *
   * @param array $tokens
   *   An associative array of tokens. Only arrays and sub-arrays are supported,
   *   do not provide objects.
   * @param \Drupal\external_entities\Entity\ExternalEntityTypeInterface|null $external_entity_type
   *   External entity type or NULL if not available.
   * @param \Drupal\external_entities\StorageClient\StorageClientInterface|null $storage_client
   *   Storage client or NULL if not available.
   */
  public function __construct(
    array $tokens,
    ?ExternalEntityTypeInterface $external_entity_type = NULL,
    ?StorageClientInterface $storage_client = NULL,
  ) {
    $this->tokens = $tokens;
    $this->externalEntityType = $external_entity_type;
    $this->storageClient = $storage_client;
  }

  /**
   * Gets the external entity type.
   *
   * @return \Drupal\external_entities\Entity\ExternalEntityTypeInterface|null
   *   The external entity type or NULL if not available.
   */
  public function getExternalEntityType() :?ExternalEntityTypeInterface {
    return $this->externalEntityType;
  }

  /**
   * Gets the storage client.
   *
   * @return \Drupal\external_entities\StorageClient\StorageClientInterface|null
   *   The storage client used or NULL if not available.
   */
  public function getStorageClient() :?StorageClientInterface {
    return $this->storageClient;
  }

  /**
   * Gets the tokens.
   *
   * @return array
   *   The tokens.
   */
  public function getTokens() {
    return $this->tokens;
  }

  /**
   * Sets the tokens.
   *
   * @param array $tokens
   *   The tokens.
   */
  public function setTokens(array $tokens) {
    $this->tokens = $tokens;
  }

}
