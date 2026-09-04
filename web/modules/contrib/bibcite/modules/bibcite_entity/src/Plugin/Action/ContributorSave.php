<?php

namespace Drupal\bibcite_entity\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Save contributor.
 *
 * @Action(
 *   id = "bibcite_entity_contributor_save",
 *   label = @Translation("Save contributors"),
 *   type = "bibcite_contributor",
 * )
 */
#[Action(
  id: 'bibcite_entity_contributor_save',
  label: new TranslatableMarkup('Save contributors'),
  type: 'bibcite_contributor'
)]
class ContributorSave extends EntitySaveBase {
}
