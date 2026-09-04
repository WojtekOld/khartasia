<?php

namespace Drupal\bibcite_entity\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Delete reference action.
 *
 * @Action(
 *   id = "bibcite_entity_reference_delete",
 *   label = @Translation("Delete references"),
 *   type = "bibcite_reference",
 *   confirm_form_route_name = "entity.bibcite_reference.delete_multiple_form",
 * )
 */
#[Action(
  id: 'bibcite_entity_reference_delete',
  label: new TranslatableMarkup('Delete references'),
  type: 'bibcite_reference',
  confirm_form_route_name: 'entity.bibcite_reference.delete_multiple_form'
)]
class ReferenceDelete extends EntityDeleteBase {
}
