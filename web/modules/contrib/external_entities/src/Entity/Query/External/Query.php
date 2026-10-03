<?php

namespace Drupal\external_entities\Entity\Query\External;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Pager\PagerParametersInterface;
use Drupal\external_entities\ExternalEntityStorageInterface;

/**
 * The external entities storage entity query class.
 */
class Query extends QueryBase implements ExternalQueryInterface, \Stringable {

  /**
   * The parameters to send to the external entity storage client(s).
   *
   * @var array
   */
  protected $parameters = [];

  /**
   * The filters that could not be processed by the storage client(s).
   *
   * @var array
   *
   * @see\Drupal\external_entities\Entity\Query\External\ExternalQueryInterface::getUnhandledFilters()
   */
  protected $unhandledFilters = [];

  /**
   * Stores the entity type manager used by the query.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The pager parameters.
   *
   * @var \Drupal\Core\Pager\PagerParametersInterface
   */
  protected $pagerParameters;

  /**
   * The pager manager.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * Storage client instance.
   *
   * @var \Drupal\external_entities\StorageClient\StorageClientInterface
   */
  protected $storageClient;

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
   * @param \Drupal\Core\Pager\PagerParametersInterface $pager_parameters
   *   The pager parameters.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
   *   The pager manager.
   */
  public function __construct(EntityTypeInterface $entity_type, $conjunction, array $namespaces, EntityTypeManagerInterface $entity_type_manager, PagerParametersInterface $pager_parameters, PagerManagerInterface $pager_manager) {
    parent::__construct($entity_type, $conjunction, $namespaces);
    $this->entityTypeManager = $entity_type_manager;
    $this->pagerParameters = $pager_parameters;
    $this->pagerManager = $pager_manager;
  }

  /**
   * Implements \Drupal\Core\Entity\Query\QueryInterface::execute().
   */
  public function execute() :int|array {
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
    if (!is_a($storage, ExternalEntityStorageInterface::class)) {
      throw new \LogicException('The storage must implement ExternalEntityStorageInterface. The current storage is: ' . get_class($storage));
    }
    if ($this->count) {
      $count = $storage->countExternalEntities(
        $this->parameters,
        $this->unhandledFilters
      );
      return $count;
    }

    $start = $this->range['start'] ?? NULL;
    $length = $this->range['length'] ?? $this->pager['limit'] ?? NULL;
    $result = $storage->queryRawDataFromExternalStorage(
      $this->parameters,
      $this->sort,
      $start,
      $length,
      TRUE,
      $this->unhandledFilters
    );
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
   * {@inheritdoc}
   */
  protected function initializePager() {
    if ($this->pager && !empty($this->pager['limit']) && !$this->count) {
      $page = $this->pagerParameters->findPage($this->pager['element']);
      $count_query = clone $this;
      $this->pager['total'] = $count_query->count()
        ->execute();
      if (0 > $this->pager['total']) {
        // If not countable, use max.
        $this->pager['total'] = PHP_INT_MAX;
      }
      $this->pager['start'] = $page * $this->pager['limit'];
      $this->pagerManager->createPager($this->pager['total'], $this->pager['limit'], $this->pager['element']);
      $this->range($this->pager['start'], $this->pager['limit']);
    }
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

  /**
   * {@inheritdoc}
   */
  public function getUnhandledFilters() :array {
    return $this->unhandledFilters;
  }

  /**
   * Convert the query parameters to a string.
   *
   * @param array $parameters
   *   The parameters to convert.
   * @param string $parent_conjunction
   *   The parent conjunction (AND/OR).
   *
   * @return string
   *   The string representation of the parameters.
   */
  public function parametersToString(
    array $parameters,
    string $parent_conjunction = 'AND',
  ) :string {
    $parts = [];
    foreach ($parameters as $cond) {
      if (isset($cond['conjunction'])) {
        // Sub-condition.
        $sub_string = $this->parametersToString(
          $cond['conditions'],
          $cond['conjunction']
        );
        if (!empty($sub_string)) {
          $parts[] = "($sub_string)";
        }
      }
      else {
        // Simple condition.
        $field = $cond['field'];
        $operator = strtoupper($cond['operator'] ?? '=');
        $value = $cond['value'] ?? NULL;

        // Handle operators.
        $formatted_value = match ($operator) {
          'IS NULL', 'IS NOT NULL' => '',
          'IN', 'NOT IN' => '('
            . implode(
              ', ',
              array_map(fn($v) => is_string($v) ? "'$v'" : $v, $value)
            )
            . ')',
          'BETWEEN', 'NOT BETWEEN' => ($value[0] ?? 'NULL')
            . ' AND '
            . ($value[1] ?? 'NULL'),
          default => is_string($value) ? "'$value'" : ($value ?? 'NULL'),
        };

        $parts[] = "($field $operator $formatted_value)";
      }
    }

    return implode(" $parent_conjunction ", $parts);
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    $parameters_string = $this->parametersToString($this->parameters);
    if (!empty($this->unhandledFilters)) {
      $parameters_string .= "\nUnhandled filters: " . print_r($this->unhandledFilters, TRUE);
    }
    // Add pager info.
    $parameters_string .= "Pager: start=" . $this->range['start'] . ", length=" . $this->range['length'];
    return $parameters_string;
  }

}
