<?php

namespace Drupal\xntt_views\Plugin\views\relationship;

use Drupal\views\Attribute\ViewsRelationship;
use Drupal\views\Plugin\views\relationship\RelationshipPluginBase;
use Drupal\xntt_views\Plugin\views\query\ExternalEntityViewsQuery;

/**
 * Relationship handler for entity references between external entities.
 *
 * SQL joins cannot resolve references between external entities: the
 * referenced entities live in a remote source, not in a database table.
 * Instead of adding a join, this handler registers the reference with the
 * xntt_query views query plugin, which resolves it after the main query by
 * batch-loading the referenced external entities for the current result
 * page (one storage call per page per relationship) and exposing them on
 * the result rows through the standard Views mechanism
 * ($row->_relationship_entities), so all entity-based handlers work
 * unchanged.
 *
 * Limitations: the relationship resolves entities for display purposes;
 * filtering or sorting the view by fields of the related external entity is
 * not supported (that would require the remote source of the base entity to
 * understand the related source's data).
 *
 * @ingroup views_relationship_handlers
 *
 * @ViewsRelationship("xntt_reference")
 */
#[ViewsRelationship('xntt_reference')]
class ExternalEntityReference extends RelationshipPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (!$this->query instanceof ExternalEntityViewsQuery) {
      return;
    }
    // No SQL join: register the reference mapping with the query plugin. The
    // relationship is keyed by its machine id in the view so that
    // HandlerBase::getEntity() finds the related entity on the result rows.
    $this->alias = $this->options['id'];
    $this->query->addRelationshipMapping(
      $this->options['id'],
      $this->definition['entity field'],
      $this->definition['target xntt type'],
      !empty($this->options['required'])
    );
  }

}
