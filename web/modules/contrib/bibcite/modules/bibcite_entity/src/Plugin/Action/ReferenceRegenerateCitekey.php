<?php

namespace Drupal\bibcite_entity\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Regenerate citekey for reference.
 *
 * @Action(
 *   id = "bibcite_entity_reference_regenerate_citekey",
 *   label = @Translation("Regenerate citation key"),
 *   type = "bibcite_reference",
 * )
 */
#[Action(
  id: 'bibcite_entity_reference_regenerate_citekey',
  label: new TranslatableMarkup('Regenerate citation key'),
  type: 'bibcite_reference'
)]
class ReferenceRegenerateCitekey extends EntitySaveBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $entity->bibcite_citekey = $entity->generateCitekey();
    parent::execute($entity);
  }

}
