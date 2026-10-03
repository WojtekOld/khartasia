<?php

namespace Drupal\xntt_file_field\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\Plugin\EntityReferenceSelection\FileSelection;

/**
 * Provides specific access control for the file entity type.
 *
 * @EntityReferenceSelection(
 *   id = "xntt_file_field:xnttfile",
 *   label = @Translation("External file selection"),
 *   entity_types = {"file"},
 *   group = "xntt_file_field",
 *   weight = 1
 * )
 */
#[EntityReferenceSelection(
  id: "xntt_file_field:xnttfile",
  label: new TranslatableMarkup("External file selection"),
  entity_types: ["file"],
  group: "xntt_file_field",
  weight: 1
)]
class ExternalFileSelection extends FileSelection {

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableEntities(array $ids) {
    // Skip FileSelection::validateReferenceableEntities() override.
    return DefaultSelection::validateReferenceableEntities($ids);
  }

}
