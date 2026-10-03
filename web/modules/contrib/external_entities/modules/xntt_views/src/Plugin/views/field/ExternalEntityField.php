<?php

namespace Drupal\xntt_views\Plugin\views\field;

use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\EntityField;

/**
 * Generic Views field handler for ExternalEntity fields (non-SQL).
 *
 * Provides a simple, robust rendering strategy:
 * - expects the query plugin to populate $row->_entity with a loaded entity,
 * - handles entity translations using the view language when available,
 * - returns a simple string rendering of the field value (first item / 'value'
 *   property if present). Use a custom handler if you need full formatter
 *   output.
 *
 * @ViewsField("external_entity_field")
 */
#[ViewsField('external_entity_field')]
class ExternalEntityField extends EntityField {

  /**
   * No SQL query building for external entities.
   */
  public function query($use_group_by = FALSE) {
    // We don't call parent::query() as there is no SQL query to build.
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable() :bool {
    // If field definition is set, use that.
    if (isset($this->definition['click sortable'])) {
      return (bool) $this->definition['click sortable'];
    }
    // Default to true.
    return TRUE;
  }

  /**
   * Called to determine what to tell the click sorter.
   */
  public function clickSort($order) {
    // Determine requested field name from options/definition.
    $field_name = $this->options['entity_field'] ?? ($this->definition['entity field'] ?? NULL);
    $this->query->addOrderBy(NULL, NULL, $order, $field_name);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() :array {
    $dependencies = parent::calculateDependencies();
    // Add external entity type dependency.
    $entity_config_id =
      'external_entities.external_entity_type.'
      . $this->getEntityType();
    $dependencies['config'][] = $entity_config_id;
    return $dependencies;
  }

}
