<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\PropertyMapper;

use Drupal\Core\Form\FormStateInterface;
use Drupal\external_entities\PropertyMapper\PropertyMapperBase;
use Drupal\external_entities\Attribute\PropertyMapper;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Constant value property mapper.
 *
 * This property mapper allows to map a constant value to any field property.
 *
 * @PropertyMapper(
 *   id = "constant",
 *   label = @Translation("Constant"),
 *   description = @Translation("Maps a property to constant value."),
 *   field_properties = {
 *     "*:*"
 *   }
 * )
 *
 * @package Drupal\external_entities\Plugin\ExternalEntities\PropertyMapper
 */
#[PropertyMapper(
  id: 'constant',
  label: new TranslatableMarkup('Constant'),
  description: new TranslatableMarkup('Maps a property to constant value.'),
  field_properties: ['*:*'],
)]
class ConstantPropertyMapper extends PropertyMapperBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() :array {
    return [
      'mapping' => '',
      'required_field' => FALSE,
      'main_property' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ) {
    $form += parent::buildConfigurationForm($form, $form_state);
    $form['mapping']['#title'] = $this->t('Constant value:');
    unset($form['data_processors']);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getMappedSourceFieldName() :?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function extractPropertyValuesFromRawData(
    array $raw_data,
    array &$context = [],
  ) :array {
    // @todo Handle multiple constant values.
    return [$this->getConfiguration()['mapping'] ?? ''];
  }

  /**
   * {@inheritdoc}
   */
  public function addPropertyValuesToRawData(
    array $property_values,
    array &$raw_data,
    array &$context,
  ) {
    // Nothing to do...
    // @todo ...Unless we add a parameter to force-save constant values.
  }

}
