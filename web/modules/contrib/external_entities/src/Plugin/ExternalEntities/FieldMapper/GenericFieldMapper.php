<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\FieldMapper;

use Drupal\external_entities\FieldMapper\FieldMapperBase;
use Drupal\external_entities\Attribute\FieldMapper;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Generic field mapper.
 *
 * This field mapper is suitable for any field type and provides mapping for
 * all the field properties.
 *
 * @FieldMapper(
 *   id = "generic",
 *   label = @Translation("Generic"),
 *   description = @Translation("Provides a generic interface to map any type of field."),
 *   field_types ={
 *     "*"
 *   }
 * )
 *
 * @package Drupal\external_entities\Plugin\ExternalEntities\FieldMapper
 */
#[FieldMapper(
  id: 'generic',
  label: new TranslatableMarkup('Generic'),
  description: new TranslatableMarkup('Provides a generic interface to map any type of field.'),
  field_types: ['*'],
)]
class GenericFieldMapper extends FieldMapperBase {

}
