<?php

namespace Drupal\external_entities\Entity\Query\External;

use Drupal\Core\Entity\Query\ConditionBase;
use Drupal\Core\Entity\Query\ConditionInterface;

/**
 * Implements entity query conditions for SQL databases.
 */
class Condition extends ConditionBase {

  /**
   * The entity query object this condition belongs to.
   *
   * @var \Drupal\external_entities\Entity\Query\External\Query
   */
  protected $query;

  /**
   * {@inheritdoc}
   */
  public function compile($query) {
    /** @var \Drupal\external_entities\Entity\Query\External\Query $query */
    if (!$query instanceof Query) {
      return;
    }

    // @todo Conjunctions should be simplified when an 'and' is embeded into
    //   another 'and', or an 'or' is embeded into another 'or': the
    //   corresponding sub-conditions should be flattened into the level above.
    //   It should also be applied to self::subcompile().
    $parameters = [];
    foreach ($this->conditions as $condition) {
      if ($condition['field'] instanceof ConditionInterface) {
        $parameters[] = [
          'conjunction' => $condition['field']->getConjunction(),
          'conditions' => $this->subcompile($condition['field']),
        ];
      }
      else {
        $parameters[] = [
          'field' => $condition['field'],
          'value' => $condition['value'],
          'operator' => $condition['operator'],
        ];
      }
    }
    $query->setParameters($parameters);
  }

  /**
   * Compile sub-conditions recursively.
   *
   * @param \Drupal\Core\Entity\Query\ConditionInterface $condition
   *   Condition.
   *
   * @return array
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
  protected function subcompile(ConditionInterface $condition) :array {
    $subparameters = [];

    foreach ($condition->conditions as $subcondition) {
      if ($subcondition['field'] instanceof ConditionInterface) {
        $subparameters[] = [
          'conjunction' => $subcondition['field']->getConjunction(),
          'conditions' => $this->subcompile($subcondition['field']),
        ];
      }
      else {
        $subparameters[] = [
          'field' => $subcondition['field'],
          'value' => $subcondition['value'],
          'operator' => $subcondition['operator'],
        ];
      }
    }

    return $subparameters;
  }

  /**
   * {@inheritdoc}
   */
  public function exists($field, $langcode = NULL) :static {
    return $this->condition($field, NULL, 'IS NOT NULL', $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function notExists($field, $langcode = NULL) :static {
    return $this->condition($field, NULL, 'IS NULL', $langcode);
  }

}
