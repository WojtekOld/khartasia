<?php

namespace Drupal\bibcite_entity\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Delete keyword action.
 *
 * @Action(
 *   id = "bibcite_entity_keyword_delete",
 *   label = @Translation("Delete keywords"),
 *   type = "bibcite_reference",
 *   confirm_form_route_name = "entity.bibcite_keyword.delete_multiple_form",
 * )
 */
#[Action(
  id: 'bibcite_entity_keyword_delete',
  label: new TranslatableMarkup('Delete keywords'),
  type: 'bibcite_reference',
  confirm_form_route_name: 'entity.bibcite_keyword.delete_multiple_form'
)]
class KeywordDelete extends EntityDeleteBase {
}
