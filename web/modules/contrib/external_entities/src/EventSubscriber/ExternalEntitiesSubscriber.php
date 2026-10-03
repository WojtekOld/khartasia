<?php

namespace Drupal\external_entities\EventSubscriber;

use Drupal\external_entities\Event\ExternalEntitiesEvents;
use Drupal\external_entities\Event\ExternalEntityTestDrupalFilterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Manages External Entities events.
 */
class ExternalEntitiesSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ExternalEntitiesEvents::TEST_DRUPAL_FILTER => ['onTestDrupalFilter', 0],
    ];
  }

  /**
   * Test Drupal filter event handler.
   *
   * @param \Drupal\external_entities\Event\ExternalEntityTestDrupalFilterEvent $event
   *   The event.
   */
  public function onTestDrupalFilter(
    ExternalEntityTestDrupalFilterEvent $event,
  ): void {
    // Check if the filter has already been processed.
    if ($event->isPassingStatusSet()) {
      return;
    }

    $field_values = $event->getFieldValues();
    $filter_value = $event->getFilterValue();
    $operator = $event->getOperator();
    $matching_values = [];

    $operator = strtoupper($operator);
    $passing = FALSE;
    $not = FALSE;
    if (str_starts_with($operator, 'NOT ')) {
      $not = TRUE;
      $operator = substr($operator, 4);
    }
    foreach ($field_values as $index => $field_value) {
      switch ($operator) {
        case '=':
          $passing = ($field_value == $filter_value);
          break;

        case '<>':
        case '!=':
          $passing = ($field_value != $filter_value);
          break;

        case '>':
          $passing = ($field_value > $filter_value);
          break;

        case '>=':
          $passing = ($field_value >= $filter_value);
          break;

        case '<':
          $passing = ($field_value < $filter_value);
          break;

        case '<=':
          $passing = ($field_value <= $filter_value);
          break;

        case 'STARTS':
        case 'STARTS_WITH':
          if (is_string($field_value)) {
            $passing =
              (substr($field_value, 0, strlen($filter_value)) == $filter_value);
          }
          break;

        case 'CONTAINS':
          if (is_string($field_value)) {
            $passing = (FALSE !== strpos($field_value, $filter_value));
          }
          break;

        case 'ENDS':
        case 'ENDS_WITH':
          if (is_string($field_value)) {
            $passing =
              (substr($field_value, -1 * strlen($filter_value)) == $filter_value);
          }
          break;

        case 'IN':
          if (is_array($filter_value)) {
            $passing = in_array($field_value, $filter_value);
          }
          break;

        case 'IS NULL':
          $passing = !isset($field_value);
          break;

        case 'EXISTS':
        case 'IS NOT NULL':
          $passing = isset($field_value);
          break;

        case 'BETWEEN':
          if (is_numeric($field_value)
              && is_array($filter_value)
              && (2 == count($filter_value))
              && is_numeric($filter_value[0])
              && is_numeric($filter_value[1])
          ) {
            $passing = ($filter_value[0] <= $field_value)
              && ($field_value <= $filter_value[1]
            );
          }
          break;

        default:
          // Unsupported operator, stop here.
          return;
      }
      if ($not) {
        $passing = !$passing;
      }
      // Add matching value.
      if ($passing) {
        $matching_values[$index] = $field_value;
      }
    }

    // Special case of NOT EXISTS/IS NULL: we don't get into the for loop above.
    if (empty($field_values)
      && (($not && ($operator === 'EXISTS'))
          || ($operator === 'IS NULL'))
    ) {
      $passing = TRUE;
    }
    else {
      // Otherwise, we pass the global test if we got elements.
      $passing = !empty($matching_values);
    }

    $event->setMatchingValues($matching_values);
    $event->setPassingStatus($passing, 'external_entities');
  }

}
