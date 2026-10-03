<?php

namespace Drupal\external_entities\Event;

use Drupal\external_entities\Entity\ExternalEntityTypeInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Defines an external entity base definition event.
 */
class ExternalEntityBaseDefinitionEvent extends Event {

  /**
   * The base definition.
   *
   * @var array
   */
  protected array $baseDefinition;

  /**
   * The external entity type.
   *
   * @var \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   */
  protected ExternalEntityTypeInterface $externalEntityType;

  /**
   * Constructs a base definition event object.
   *
   * @param array $base_definition
   *   The base definition.
   * @param \Drupal\external_entities\Entity\ExternalEntityTypeInterface $external_entity_type
   *   The external entity type.
   */
  public function __construct(
    array &$base_definition,
    ExternalEntityTypeInterface $external_entity_type,
  ) {
    $this->baseDefinition = &$base_definition;
    $this->externalEntityType = $external_entity_type;
  }

  /**
   * Gets the base definition.
   *
   * @return array
   *   The base definition.
   */
  public function getBaseDefinition(): array {
    return $this->baseDefinition;
  }

  /**
   * Sets the base definition.
   *
   * @param array $base_definition
   *   The base definition.
   */
  public function setBaseDefinition(array $base_definition): void {
    $this->baseDefinition = $base_definition;
  }

  /**
   * Gets the external entity type.
   *
   * @return \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   *   The external entity type.
   */
  public function getExternalEntityType(): ExternalEntityTypeInterface {
    return $this->externalEntityType;
  }

}
