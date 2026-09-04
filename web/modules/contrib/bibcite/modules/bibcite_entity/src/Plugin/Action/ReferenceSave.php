<?php

namespace Drupal\bibcite_entity\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Save reference.
 *
 * @Action(
 *   id = "bibcite_entity_reference_save",
 *   label = @Translation("Save references"),
 *   type = "bibcite_reference",
 * )
 */
#[Action(
  id: 'bibcite_entity_reference_save',
  label: new TranslatableMarkup('Save references'),
  type: 'bibcite_reference'
)]
class ReferenceSave extends EntitySaveBase {
}
