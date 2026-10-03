<?php

namespace Drupal\external_entities\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\external_entities\Entity\ExternalEntityTypeInterface;
use Drupal\external_entities\StorageClient\StorageClientInterface;

/**
 * Defines an external entity filter test passing event.
 *
 * @see \Drupal\external_entities\StorageClient\StorageClientBase::testDrupalFilter()
 */
class ExternalEntityTestDrupalFilterEvent extends Event {

  /**
   * The field values to test.
   *
   * @var array
   */
  protected array $fieldValues = [];

  /**
   * The value or values to filter on.
   *
   * @var mixed
   */
  protected $filterValue;

  /**
   * The filtering operator.
   *
   * @var string
   */
  protected string $operator = '=';

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
   * The test passing status.
   *
   * @var bool
   */
  protected bool $passing = FALSE;

  /**
   * Module name that set the passing status.
   *
   * @var string
   */
  protected string $passingSet = '';

  /**
   * Matching values.
   *
   * @var array
   */
  protected array $matchingValues = [];

  /**
   * Constructs a Drupal filter test event object.
   *
   * @param array $field_values
   *   An array of field property values to test (ie. all the ordered values of
   *   a given field property).
   * @param mixed $filter_value
   *   The optional filter value which may be a numeric value, a string, NULL
   *   for "IS (NOT) NULL/(NOT) EXISTS" operators, or an array of 2 numeric
   *   value for "(NOT) BETWEEN" operator, or an array of values for "(NOT) IN"
   *   operator.
   * @param string $operator
   *   The operator to use. For example: '=', '!=', '<>', '>', '>=',
   *   '<', '<=', 'STARTS_WITH', 'CONTAINS', 'ENDS_WITH', 'IN', 'NOT IN',
   *   'IS NULL', 'IS NOT NULL', 'EXISTS', 'NOT EXISTS', 'BETWEEN',
   *   'NOT BETWEEN', ...
   * @param \Drupal\external_entities\Entity\ExternalEntityTypeInterface|null $external_entity_type
   *   External entity type or NULL if not available.
   * @param \Drupal\external_entities\StorageClient\StorageClientInterface|null $storage_client
   *   Storage client or NULL if not available.
   */
  public function __construct(
    array $field_values,
    $filter_value,
    string $operator,
    ?ExternalEntityTypeInterface $external_entity_type = NULL,
    ?StorageClientInterface $storage_client = NULL,
  ) {
    $this->fieldValues = $field_values;
    $this->filterValue = $filter_value;
    $this->operator = $operator;
    $this->externalEntityType = $external_entity_type;
    $this->storageClient = $storage_client;
  }

  /**
   * Gets the field values.
   *
   * @return array
   *   The field values.
   */
  public function getFieldValues() :array {
    if ($this->passingSet) {
      return $this->matchingValues;
    }
    return $this->fieldValues;
  }

  /**
   * Gets the operator.
   *
   * @return string
   *   The operator.
   */
  public function getOperator() :string {
    return $this->operator;
  }

  /**
   * Sets the operator.
   *
   * @param string $new_operator
   *   The new operator.
   *
   * @return \Drupal\external_entities\Event\ExternalEntityTestDrupalFilterEvent
   *   The current event object.
   */
  public function setOperator(string $new_operator) :self {
    $this->operator = $new_operator;
    return $this;
  }

  /**
   * Gets the filter value or values.
   *
   * @return mixed
   *   The filter value(s).
   */
  public function getFilterValue() {
    return $this->filterValue;
  }

  /**
   * Changes the filter value or values.
   *
   * @param array $filter_value
   *   The new filter value(s).
   *
   * @return \Drupal\external_entities\Event\ExternalEntityTestDrupalFilterEvent
   *   The current event object.
   */
  public function setFilterValue(array $filter_value) :self {
    $this->filterValue = $filter_value;
    return $this;
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
   * Returns the test passing status.
   *
   * @return bool
   *   The test passing status.
   */
  public function getPassingStatus() :bool {
    return $this->passing;
  }

  /**
   * Sets the new test passing status.
   *
   * @param bool $passing_status
   *   The new test passing status.
   * @param string $module_name
   *   The machine name of the module that is setting the passing status.
   *
   * @return \Drupal\external_entities\Event\ExternalEntityTestDrupalFilterEvent
   *   The current event object.
   */
  public function setPassingStatus(
    bool $passing_status,
    string $module_name,
  ) :self {
    $this->passing = $passing_status;
    $this->passingSet = $module_name;
    if ($passing_status) {
      // When passing, set matching values to all field values if empty (not set
      // yet by self::setMatchingValues()).
      $this->matchingValues = $this->matchingValues ?: $this->fieldValues;
    }
    else {
      // Not passing, clear matching values.
      $this->matchingValues = [];
    }
    return $this;
  }

  /**
   * Returns the module name that set the status or an empty string.
   *
   * @return string
   *   A machine name of the module that processed the values and set the
   *   passing status (ecaluated as TRUE). If the test passing status has not
   *   been set, an empty string (evaluated as FALSE).
   */
  public function isPassingStatusSet() :string {
    return $this->passingSet;
  }

  /**
   * Unsets the test passing status.
   *
   * @return \Drupal\external_entities\Event\ExternalEntityTestDrupalFilterEvent
   *   The current event object.
   */
  public function unsetPassingStatus() :self {
    $this->passing = FALSE;
    $this->passingSet = '';
    return $this;
  }

  /**
   * Gets matching values.
   *
   * @return array
   *   The matching values.
   */
  public function getMatchingValues() :array {
    return $this->matchingValues;
  }

  /**
   * Set matching values.
   *
   * @param array $matching_values
   *   The new matching values.
   *
   * @return \Drupal\external_entities\Event\ExternalEntityTestDrupalFilterEvent
   *   The current event object.
   */
  public function setMatchingValues(array $matching_values) :self {
    $this->matchingValues = $matching_values;
    return $this;
  }

}
