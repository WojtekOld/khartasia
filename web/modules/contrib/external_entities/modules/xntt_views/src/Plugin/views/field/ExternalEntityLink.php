<?php

namespace Drupal\xntt_views\Plugin\views\field;

use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\EntityLink;

/**
 * Field handler to present a link to an external entity.
 */
#[ViewsField("external_entity_link")]
class ExternalEntityLink extends EntityLink {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // We purposefully do not call parent::query() to avoid SQL query behavior.
  }

}
