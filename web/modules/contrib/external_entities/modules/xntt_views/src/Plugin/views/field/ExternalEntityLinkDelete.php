<?php

namespace Drupal\xntt_views\Plugin\views\field;

use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\EntityLinkDelete;

/**
 * Field handler to present a link to delete an external entity.
 */
#[ViewsField("external_entity_link_delete")]
class ExternalEntityLinkDelete extends EntityLinkDelete {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // We purposefully do not call parent::query() to avoid SQL query behavior.
  }

}
