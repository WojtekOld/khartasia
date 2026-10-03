<?php

namespace Drupal\xntt_file_field\Entity\Query;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\xntt_file_field\ExternalFileStorageInterface;

/**
 * The external entities storage entity query class.
 */
class Query extends QueryBase implements QueryInterface {

  /**
   * The parameters to send to the external entity storage client.
   *
   * @var array
   */
  protected $parameters = [];

  /**
   * Stores the entity type manager used by the query.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a query object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param string $conjunction
   *   - AND: all of the conditions on the query need to match.
   * @param array $namespaces
   *   List of potential namespaces of the classes belonging to this query.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeInterface $entity_type, $conjunction, array $namespaces, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_type, $conjunction, $namespaces);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Implements \Drupal\Core\Entity\Query\QueryInterface::execute().
   */
  public function execute() {
    return $this
      ->compile()
      ->finish()
      ->result();
  }

  /**
   * Compiles the conditions.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   Returns the called object.
   */
  protected function compile() {
    $this->condition->compile($this);
    return $this;
  }

  /**
   * Finish the query by adding fields, GROUP BY and range.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   Returns the called object.
   */
  protected function finish() {
    $this->initializePager();
    return $this;
  }

  /**
   * Executes the query and returns the result.
   *
   * @return int|array
   *   Returns the query result as entity IDs.
   */
  protected function result() {
    $storage = $this
      ->entityTypeManager
      ->getStorage($this->getEntityTypeId());
    if (!is_a($storage, ExternalFileStorageInterface::class)) {
      throw new \LogicException(
        'The storage must implement ExternalFileStorageInterface. The current storage is: '
         . get_class($storage)
      );
    }
    // We do not support query on external files as we do not know where they
    // are stored, so we cannot count or list/filter them.
    if ($this->count) {
      return 0;
    }
    $result = [];
    return $result;
  }

  /**
   * Set a parameters.
   *
   * @param array $parameters
   *   An array of parameters, each value is an array of one of the two
   *   following structure:
   *   - type condition:
   *     - field: the Drupal field machine name the parameter applies to
   *     - value: the value of the parameter or NULL
   *     - operator: the Drupal operator of how the parameter should be applied.
   *       Should be one of '=', '<>', '>', '>=', '<', '<=', 'STARTS_WITH',
   *       'CONTAINS', 'ENDS_WITH', 'IN', 'NOT IN', 'IS NULL', 'IS NOT NULL',
   *       'BETWEEN' and 'NOT BETWEEN', but may also be a custom operator.
   *   - type sub-condition:
   *     - conjunction: either 'or' or 'and'
   *     - conditions: an array of array of type condition described above or
   *       type sub-condition.
   */
  public function setParameters(array $parameters) {
    $this->parameters = $parameters;
  }

  /**
   * Gets the external entity type.
   *
   * @return \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   *   The external entity type.
   */
  public function getExternalEntityType() {
    return $this
      ->entityTypeManager
      ->getStorage('external_entity_type')
      ->load($this->getEntityTypeId());
  }

}
