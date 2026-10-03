<?php

namespace Drupal\xntt_file_field\Plugin\ExternalEntities\FieldMapper;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\external_entities\Attribute\FieldMapper;
use Drupal\external_entities\FieldMapper\FieldMapperBase;

/**
 * Field field property mapper.
 *
 * This property mapper handles file fields (files, images,...). It provides
 * support for external files (remote or local but not managed by Drupal) to be
 * treated just like Drupal managed files. It uses a custom stream wrapper that
 * provides support for custom protocol "xntt://".
 *
 * @FieldMapper(
 *   id = "file",
 *   label = @Translation("File field mapper"),
 *   description = @Translation("Provides an interface to map file and image fields."),
 *   field_types = {
 *     "file",
 *     "image"
 *   }
 * )
 *
 * @package Drupal\external_entities\Plugin\ExternalEntities\FieldMapper
 */
#[FieldMapper(
  id: 'file',
  label: new TranslatableMarkup('File field mapper'),
  description: new TranslatableMarkup('Provides an interface to map file and image fields.'),
  field_types: [
    'file',
    'image',
  ],
)]
class FileFieldMapper extends FieldMapperBase {

  /**
   * {@inheritdoc}
   */
  protected function initProperties() {
    parent::initProperties();
    // Hide mapping for the 'target_id' (file ID) property.
    $this->properties[static::SPECIFIC_PROPERTIES]['target_id'] =
      $this->properties[static::GENERAL_PROPERTIES]['target_id'];
    unset($this->properties[static::GENERAL_PROPERTIES]['target_id']);
    // Prepend a virtual property to hold the file URI.
    $this->properties[static::GENERAL_PROPERTIES] =
      [
        'real_uri' =>
        DataDefinition::create('string')
          ->setLabel('External file URI')
          ->setRequired(TRUE)
          ->setDescription(new TranslatableMarkup('The URI of the external file.'))
          ->setSetting('max_length', 4096),
      ]
      + $this->properties[static::GENERAL_PROPERTIES];
    $this->properties[static::MAPPABLE_PROPERTIES] += [
      'real_uri' => $this->properties[static::GENERAL_PROPERTIES]['real_uri'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getMainPropertyName() :string|null {
    return 'real_uri';
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() :array {
    return [
      'property_mappings' => [
        'real_uri' => [],
      ],
      'filter' => '',
      'extension' => '',
    ]
    + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ) :array {
    $fm_config = $this->getConfiguration();
    $form['filter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Regular expression to filter allowed URI (optional)'),
      '#required' => FALSE,
      '#default_value' => $fm_config['filter']
      ?? '(?:https?|ftp)://(?!.*/\.)[^\s]+',
      '#description' => $this->t(
        'Leave empty to disable filtering. Default regexp restricts to http, https and ftp protocols and forbid access to special directories starting with dot. Do not include regex delimiters (the given regexp will be processed between "#^" and "$#"). For security reasons, it is recommended to limit allowed protocols as PHP supports more than HTTP and FTP protocols.'
      ),
      '#field_prefix' => '#^',
      '#field_suffix' => '$#',
      '#attributes' => [
        'placeholder' => $this->t('eg.: (https?|ftp)://shared/drupal/(?!.*/\.).*\.(?i:png|gif|jpg|jpeg|webp)'),
      ],
    ];
    $form['extension'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Force file extension (optional)'),
      '#required' => FALSE,
      '#default_value' => $fm_config['extension']
      ?? NULL,
      '#description' => $this->t(
        'Do not include dot prefix. Specifying an extension might be required to guess file type if the given URI does not provide a full file name.'
      ),
      '#field_prefix' => '<i>filename.</i>',
    ];
    // @todo Add possibility to use another entity source to fill file fields.
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['property_mappings']['real_uri']['#required'] = TRUE;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // @todo Validate uri, filter and extension values.
    $filter = $form_state->getValue('filter');
    if (!empty($filter)) {
      if (FALSE === preg_match('#^' . $filter . '$#', 'file.ext')) {
        // Regex failed.
        $form_state->setErrorByName(
          'filter',
          $this->t(
            'The regular expression to filter allowed URI failed to compile with the error: @error',
            ['@error' => preg_last_error_msg()]
          )
        );
      }
    }
    $extension = $form_state->getValue('extension');
    if (!empty($extension)) {
      $extension = preg_replace('/^[\s\.]*/', '', $extension);
      if (!preg_match('#^\w+$#', $extension)) {
        // Invalid extension.
        $form_state->setErrorByName(
          'extension',
          $this->t(
            'The optional extension field contains invalid characters (ie. non-word characters).'
          )
        );
      }
      $form_state->setValue('extension', $extension);
    }
    parent::validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValuesToRawData(
    array $field_values,
    array &$raw_data,
    array &$context,
  ) :void {
    // Get field mappable properties.
    $properties = $this->getProperties();
    foreach ($properties as $property_name => $property) {
      $mapper = $this->getPropertyMapper($property_name);
      if (!empty($mapper)) {
        // Convert [delta][property] structure to [property][delta] structure,
        // so that each property can be set in the raw data all at once in one
        // setter operation.
        $propery_values = [];
        foreach ($field_values as $field_value) {
          if (array_key_exists($property_name, $field_value)) {
            $propery_values[] = $field_value[$property_name];
          }
        }
        $mapper->addPropertyValuesToRawData(
          $propery_values,
          $raw_data,
          $context
        );
      }
    }
  }

}
