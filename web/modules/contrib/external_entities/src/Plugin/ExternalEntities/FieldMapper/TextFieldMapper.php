<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\FieldMapper;

use Drupal\Core\Form\FormStateInterface;
use Drupal\external_entities\FieldMapper\FieldMapperBase;
use Drupal\external_entities\Attribute\FieldMapper;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\filter\FilterFormatRepositoryInterface;

/**
 * Formatted text field mapper.
 *
 * Thie field mapper provides an interface to handle text format according to
 * local site available text format, and avoids custom constant mapping.
 *
 * @FieldMapper(
 *   id = "text",
 *   label = @Translation("Text field mapper"),
 *   description = @Translation("Provides an interface to map formatted text fields."),
 *   field_types = {
 *     "text",
 *     "text_long",
 *     "text_with_summary"
 *   }
 * )
 *
 * @package Drupal\external_entities\Plugin\ExternalEntities\FieldMapper
 */
#[FieldMapper(
  id: 'text',
  label: new TranslatableMarkup('Text field mapper'),
  description: new TranslatableMarkup('Provides an interface to map formatted text fields.'),
  field_types: ['text', 'text_long', 'text_with_summary'],
)]
class TextFieldMapper extends FieldMapperBase {

  /**
   * The filter format repository.
   *
   * @var \Drupal\filter\FilterFormatRepositoryInterface
   */
  protected $filterFormatRepository;

  /**
   * Constructs a TextFieldMapper object.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    TranslationInterface $string_translation,
    LoggerInterface $logger,
    EntityFieldManagerInterface $entity_field_manager,
    PluginManagerInterface $property_mapper_manager,
    ?FilterFormatRepositoryInterface $filter_format_repository = NULL,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $string_translation, $logger, $entity_field_manager, $property_mapper_manager);
    $this->filterFormatRepository = $filter_format_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) :static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('string_translation'),
      $container->get('logger.factory')->get('xntt_field_mapper_' . $plugin_id),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.external_entities.property_mapper'),
      $container->has('filter.format.repository') ? $container->get('filter.format.repository') : NULL
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function initProperties() {
    parent::initProperties();
    $this->properties[static::SPECIFIC_PROPERTIES]['value'] =
      $this->properties[static::GENERAL_PROPERTIES]['value'];
    unset($this->properties[static::GENERAL_PROPERTIES]['value']);
    $this->properties[static::SPECIFIC_PROPERTIES]['format'] =
      $this->properties[static::GENERAL_PROPERTIES]['format'];
    unset($this->properties[static::GENERAL_PROPERTIES]['format']);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() :array {
    return ['format' => 'plain']
      + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ) {

    $specific_properties = $this->getProperties(static::SPECIFIC_PROPERTIES);
    $this->buildPropertyMapperSelectForm($form, $form_state, 'value', $specific_properties['value']);
    $this->buildPropertyMapperConfigForm($form, $form_state, 'value', $specific_properties['value']);
    $this->buildTextFormatSelectionForm($form, $form_state);
    $form = parent::buildConfigurationForm($form, $form_state);
    return $form;
  }

  /**
   * Build a form element for configuring a field property mapping.
   *
   * @todo API: Return $form array instead of using reference &$form.
   *
   * @param array &$form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function buildTextFormatSelectionForm(
    array &$form,
    FormStateInterface $form_state,
  ) {
    $configuration = $this->getConfiguration();
    $format_options = [];
    $formats = $this->filterFormatRepository ? $this->filterFormatRepository->getAllFormats() : [];

    foreach ($formats as $format_id => $format) {
      $format_options[$format_id] = $format->label();
    }

    $allowed_formats = array_flip(
      $this->getFieldDefinition()->getSettings()["allowed_formats"]
      ?? []
    );
    if (!empty($allowed_formats)) {
      // Restrict to allowed filter formats.
      $format_options = array_filter(
        $format_options,
        function ($format) use ($allowed_formats) {
          return array_key_exists($format, $allowed_formats);
        },
        ARRAY_FILTER_USE_KEY
      );
    }

    $form['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Text format'),
      '#options' => $format_options,
      '#default_value' => $configuration['format'] ?? NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    // Validate value.
    $specific_properties = $this->getProperties(static::SPECIFIC_PROPERTIES);
    $this->validatePropertyForms(
      $form,
      $form_state,
      ['value' => $specific_properties['value']]
    );
    // Validate format.
    // @todo Check if format is one of the available ones for this field.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Submit and save text and format property mapper configs.
    $format = $form_state->getValue('format');
    $form_state->setValue('format', $format);
    $specific_properties = $this->getProperties(static::SPECIFIC_PROPERTIES);
    $this->submitPropertyForms(
      $form,
      $form_state,
      ['value' => $specific_properties['value']]
    );
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function extractFieldValuesFromRawData(
    array $raw_data,
    array &$context = [],
  ) :?array {
    $format = $this->getConfiguration()['format'] ?? '';
    $field_array = parent::extractFieldValuesFromRawData($raw_data, $context);
    foreach ($field_array as $delta => $field_value) {
      $field_array[$delta]['format'] = $format;
    }
    return $field_array;
  }

}
