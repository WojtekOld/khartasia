<?php

namespace Drupal\xntt_views\Plugin\views\field;

use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\EntityLinkEdit;

/**
 * Field handler to present a link to edit an external entity.
 */
#[ViewsField("external_entity_link_edit")]
class ExternalEntityLinkEdit extends EntityLinkEdit {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // We purposefully do not call parent::query() to avoid SQL query behavior.
  }

}
