<?php

namespace Drupal\bibcite_entity\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Save keyword.
 *
 * @Action(
 *   id = "bibcite_entity_keyword_save",
 *   label = @Translation("Save keywords"),
 *   type = "bibcite_keyword",
 * )
 */
#[Action(
  id: 'bibcite_entity_keyword_save',
  label: new TranslatableMarkup('Save keywords'),
  type: 'bibcite_keyword'
)]
class KeywordSave extends EntitySaveBase {
}
