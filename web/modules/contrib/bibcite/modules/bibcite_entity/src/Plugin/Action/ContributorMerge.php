<?php

namespace Drupal\bibcite_entity\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Merge contributor action.
 *
 * @Action(
 *   id = "bibcite_entity_contributor_merge",
 *   label = @Translation("Merge contributor"),
 *   type = "bibcite_contributor",
 *   confirm_form_route_name = "entity.bibcite_contributor.bibcite_merge_multiple_form",
 * )
 */
#[Action(
  id: 'bibcite_entity_contributor_merge',
  label: new TranslatableMarkup('Merge contributor'),
  type: 'bibcite_contributor',
  confirm_form_route_name: 'entity.bibcite_contributor.bibcite_merge_multiple_form'
)]
class ContributorMerge extends EntityMergeBase {
}
