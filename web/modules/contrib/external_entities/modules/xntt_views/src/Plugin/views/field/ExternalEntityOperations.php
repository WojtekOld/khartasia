<?php

namespace Drupal\xntt_views\Plugin\views\field;

use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\EntityOperations;

/**
 * Renders all operations links for an external entity.
 */
#[ViewsField("external_entity_operations")]
class ExternalEntityOperations extends EntityOperations {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // We purposefully do not call parent::query() to avoid SQL query behavior.
  }

}
