<?php

namespace Drupal\external_entities\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\external_entities\Entity\ExternalEntityTypeInterface;
use Drupal\external_entities\StorageClient\StorageClientInterface;

/**
 * Defines an external entity transliterate sorts event.
 *
 * @see \Drupal\external_entities\StorageClient\StorageClientBase::transliterateDrupalSortsAlter()
 */
class ExternalEntityTransliterateDrupalSortsEvent extends Event {

  /**
   * The transliterated sorts array.
   *
   * @var array
   *
   * @see \Drupal\external_entities\StorageClient\StorageClientInterface::transliterateDrupalSorts()
   */
  protected $transliteratedSorts;

  /**
   * The original sorts array.
   *
   * @var array
   */
  protected $originalSorts;

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
   * Constructs a transliterate sorts event object.
   *
   * @param array $trans_sorts
   *   The transliterated sorts.
   * @param array $original_sorts
   *   The original sorts.
   * @param \Drupal\external_entities\Entity\ExternalEntityTypeInterface|null $external_entity_type
   *   External entity type or NULL if not available.
   * @param \Drupal\external_entities\StorageClient\StorageClientInterface|null $storage_client
   *   Storage client or NULL if not available.
   * @param array $context
   *   The context.
   */
  public function __construct(
    array $trans_sorts,
    array $original_sorts,
    ?ExternalEntityTypeInterface $external_entity_type = NULL,
    ?StorageClientInterface $storage_client = NULL,
    array $context = [],
  ) {
    $this->transliteratedSorts = $trans_sorts;
    $this->originalSorts = $original_sorts;
    $this->externalEntityType = $external_entity_type;
    $this->storageClient = $storage_client;
    $this->context = $context;
  }

  /**
   * Gets the original sorts.
   *
   * @return array
   *   The original sorts.
   */
  public function getOriginalSorts() :array {
    return $this->originalSorts;
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
   * Returns the transliterated sorts.
   *
   * @return array
   *   The transliterated sorts array.
   *
   * @see \Drupal\external_entities\StorageClient\StorageClientInterface::transliterateDrupalSorts()
   */
  public function getTransliteratedDrupalSorts() :array {
    return $this->transliteratedSorts;
  }

  /**
   * Sets the new transliterated sorts.
   *
   * @param array $trans_sorts
   *   The new transliterated sorts array.
   *
   * @see \Drupal\external_entities\StorageClient\StorageClientInterface::transliterateDrupalSorts()
   */
  public function setTransliteratedDrupalSorts(array $trans_sorts) {
    $this->transliteratedSorts = $trans_sorts;
  }

}
