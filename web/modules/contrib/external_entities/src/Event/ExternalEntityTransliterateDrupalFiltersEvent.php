<?php

namespace Drupal\external_entities\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\external_entities\Entity\ExternalEntityTypeInterface;
use Drupal\external_entities\StorageClient\StorageClientInterface;

/**
 * Defines an external entity transliterate filters event.
 *
 * @see \Drupal\external_entities\StorageClient\StorageClientBase::transliterateDrupalFiltersAlter()
 */
class ExternalEntityTransliterateDrupalFiltersEvent extends Event {

  /**
   * The transliterated filter array.
   *
   * @var array
   *
   * @see \Drupal\external_entities\StorageClient\StorageClientInterface::transliterateDrupalFilters()
   */
  protected $transliteratedFilters;

  /**
   * The original filtering parameter array provided.
   *
   * @var array
   */
  protected $originalParameters;

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
   * The context.
   *
   * @var array
   */
  protected $context;

  /**
   * Constructs a transform raw data event object.
   *
   * @param array $trans_filters
   *   The transliterated filters.
   * @param array $original_parameters
   *   The original filter parameters.
   * @param \Drupal\external_entities\Entity\ExternalEntityTypeInterface|null $external_entity_type
   *   External entity type or NULL if not available.
   * @param \Drupal\external_entities\StorageClient\StorageClientInterface|null $storage_client
   *   Storage client or NULL if not available.
   * @param array $context
   *   The context.
   */
  public function __construct(
    array $trans_filters,
    array $original_parameters,
    ?ExternalEntityTypeInterface $external_entity_type = NULL,
    ?StorageClientInterface $storage_client = NULL,
    array $context = [],
  ) {
    $this->transliteratedFilters = $trans_filters;
    $this->originalParameters = $original_parameters;
    $this->externalEntityType = $external_entity_type;
    $this->storageClient = $storage_client;
    $this->context = $context;
  }

  /**
   * Gets the original filter parameters.
   *
   * @return array
   *   The original filter parameters.
   */
  public function getOriginalParameters() :array {
    return $this->originalParameters;
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
   * Gets the context.
   *
   * @return array
   *   The context.
   */
  public function getContext() :array {
    return $this->context;
  }

  /**
   * Changes the context.
   *
   * @param array $context
   *   The new context.
   */
  public function setContext(array $context) {
    $this->context = $context;
  }

  /**
   * Returns the transliterated filters.
   *
   * @return array
   *   The transliterated filter array.
   *
   * @see \Drupal\external_entities\StorageClient\StorageClientInterface::transliterateDrupalFilters()
   */
  public function getTransliteratedDrupalFilters() :array {
    return $this->transliteratedFilters;
  }

  /**
   * Sets the new transliterated filters.
   *
   * @param array $trans_filters
   *   The new transliterated filter array.
   *
   * @see \Drupal\external_entities\StorageClient\StorageClientInterface::transliterateDrupalFilters()
   */
  public function setTransliteratedDrupalFilters(array $trans_filters) {
    $this->transliteratedFilters = $trans_filters;
  }

}
