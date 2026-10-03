<?php

namespace Drupal\xntt_views\EventSubscriber;

use Drupal\Core\Database\Database;
use Drupal\external_entities\Event\ExternalEntitiesEvents;
use Drupal\external_entities\Event\ExternalEntityBaseDefinitionEvent;
use Drupal\external_entities\Event\ExternalEntityTestDrupalFilterEvent;
use Drupal\xntt_views\Plugin\views\data\ExternalEntityViewsData;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Add views handlers to base definitions.
 */
class ExternalEntitiesSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ExternalEntitiesEvents::EXTERNAL_ENTITY_BASE_DEFINITION => 'onBaseDefinitionAlter',
      // We run the handler before the one in external_entities module to
      // translitterate the operators to have default filtering working.
      ExternalEntitiesEvents::TEST_DRUPAL_FILTER => ['onTestDrupalFilter', 10],
    ];
  }

  /**
   * Alters the base definition to add views handlers.
   *
   * @param \Drupal\external_entities\Event\ExternalEntityBaseDefinitionEvent $event
   *   The event.
   */
  public function onBaseDefinitionAlter(
    ExternalEntityBaseDefinitionEvent $event,
  ): void {
    $base_definition = $event->getBaseDefinition();
    $base_definition['handlers']['views_data'] = ExternalEntityViewsData::class;
    $event->setBaseDefinition($base_definition);
  }

  /**
   * Test Drupal filter event handler.
   *
   * Handles some SQL operators for views filters: NOT *, LIKE, ILIKE, REGEXP,
   * and "~*".
   *
   * @param \Drupal\external_entities\Event\ExternalEntityTestDrupalFilterEvent $event
   *   The event.
   *
   * @see https://api.drupal.org/api/drupal/core%21modules%21views%21src%21Plugin%21views%21filter%21FilterPluginBase.php/group/views_filter_handlers/11.x
   */
  public function onTestDrupalFilter(
    ExternalEntityTestDrupalFilterEvent $event,
  ): void {
    $field_values = $event->getFieldValues();
    $filter_value = $event->getFilterValue();
    $operator = $event->getOperator();

    // Check if PostgreSQL is used which would mean some operators where
    // remapped.
    $is_pgsql = ('pgsql' === Database::getConnection()->getConnectionOptions()['driver']);
    $operator = strtoupper($operator);
    // Check for negated condition.
    $not = '';
    if (str_starts_with($operator, 'NOT ')) {
      $not = 'NOT ';
      $operator = substr($operator, 4);
    }
    elseif ($operator === '!~*') {
      $not = '!';
      $operator = substr($operator, 1);
    }

    // Handle operators.
    if (($operator === 'LIKE BINARY')
        || (($operator === 'LIKE') && $is_pgsql)
    ) {
      $update = FALSE;
      // We need to test if it was "STARTS_WITH" or "ENDS_WITH" or "CONTAINS".
      if (preg_match('/^%(?!%)(?:[^%_\\\]+|\\\\.|%%|__)+(?<!%)%$/', $filter_value)) {
        // CONTAINS.
        $operator = 'CONTAINS';
        $filter_value = substr($filter_value, 1, -1);
        $update = TRUE;
      }
      elseif (preg_match('/^(?:[^%_\\\]+|\\\\.|%%|__)+(?<!%)%$/', $filter_value)) {
        // STARTS_WITH.
        $operator = 'STARTS_WITH';
        $filter_value = substr($filter_value, 0, -1);
        $update = TRUE;
      }
      elseif (preg_match('/^%(?!%)(?:[^%_\\\]+|\\\\.|%%|__)+$/', $filter_value)) {
        // ENDS_WITH.
        $operator = 'ENDS_WITH';
        $filter_value = substr($filter_value, 1);
        $update = TRUE;
      }
      else {
        // Switch to a regexp.
        $operator = 'REGEXP';
        $filter_value = preg_quote($filter_value, '/');
        // Not the best way to do it but it should be enough for most of the
        // cases.
        $filter_value = str_replace(
          ['\%', '%%', '\_', '__', '%', '_'],
          ['%', '%', '_', '_', '.*', '.'],
          $filter_value
        );
      }

      // Update operator and filter value.
      if ($update) {
        if ($not) {
          $operator = 'NOT ' . $operator;
        }
        $event->setOperator($operator);
        $event->setFilterValue($filter_value);
      }
    }
    // Check if we need to process tests here.
    if (($operator === 'ILIKE')
        || (($operator === 'LIKE') && !$is_pgsql)
    ) {
      $filter_value = preg_quote($filter_value, '/');
      // Not the best way to do it but it should be enough for most of the
      // cases.
      $filter_value = str_replace(
        ['\%', '%%', '\_', '__', '%', '_'],
        ['%', '%', '_', '_', '.*', '.'],
        $filter_value
      );
      $matching_values = [];
      foreach ($field_values as $field_value) {
        $passing = (bool) preg_match("/$filter_value/i", $field_value ?? '');
        if ($not) {
          $passing = !$passing;
        }
        if ($passing) {
          $matching_values[] = $field_value;
        }
      }
      $event->setPassingStatus(!empty($matching_values), 'xntt_views');
    }
    elseif (($operator === 'REGEXP') || ($operator === '~*')) {
      $matching_values = [];
      foreach ($field_values as $field_value) {
        $passing = (bool) preg_match('/' . $filter_value . '/', $field_value);
        if ($not) {
          $passing = !$passing;
        }
        if ($passing) {
          $matching_values[] = $field_value;
        }
      }
      $event->setPassingStatus(!empty($matching_values), 'xntt_views');
    }

  }

}
