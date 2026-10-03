<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\PropertyMapper;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\external_entities\FieldMapper\FieldMapperInterface;
use Drupal\external_entities\PropertyMapper\PropertyMapperBase;
use JsonPath\InvalidJsonException;
use JsonPath\InvalidJsonPathException;
use JsonPath\JsonObject;
use Drupal\external_entities\Attribute\PropertyMapper;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * A field mapper that uses JSONPath expressions.
 *
 * Multi-valued fields should be mapped using JSONPath expressions that result
 * in an array of values being returned.
 *
 * @PropertyMapper(
 *   id = "jsonpath",
 *   label = @Translation("JSONPath"),
 *   description = @Translation("Maps a property to raw data fields based on JSONPath expressions."),
 *   field_properties = {
 *     "*:*"
 *   }
 * )
 */
#[PropertyMapper(
  id: 'jsonpath',
  label: new TranslatableMarkup('JSONPath'),
  description: new TranslatableMarkup('Maps a property to raw data fields based on JSONPath expressions.'),
  field_properties: ['*:*'],
)]
class JsonPath extends PropertyMapperBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ) {
    $form += parent::buildConfigurationForm($form, $form_state);

    $links = [
      Link::fromTextAndUrl(
        $this->t('JSONPath syntax and limitation'),
        Url::fromUri(
          'https://github.com/Galbar/JsonPath-PHP#jsonpath-language'
        )
      ),

      Link::fromTextAndUrl(
        $this->t('JSONPath examples'),
        Url::fromUri(
          'https://support.smartbear.com/alertsite/docs/monitors/api/endpoint/jsonpath.html'
        )
      ),
    ];

    $form['help']['jsonpath'] = [
      '#theme' => 'item_list',
      '#type' => 'ul',
      '#prefix' => '<p>' . $this->t('See these documentation links:') . '</p>',
      '#items' => array_map(function (Link $link) {
        return $link->toRenderable();
      }, $links),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getMappedSourceFieldName() :?string {
    if ($this->isProcessed()) {
      return NULL;
    }
    $source_field = NULL;
    if (preg_match('/^\$\.(\w+)$/', $this->getConfiguration()['mapping'], $matches)
      || preg_match('/^\$\[[\'"]?(\w+)[\'"]?\]$/', $this->getConfiguration()['mapping'], $matches)
    ) {
      $source_field = $matches[1];
    }
    return $source_field;
  }

  /**
   * {@inheritdoc}
   */
  public function extractPropertyValuesFromRawData(
    array $raw_data,
    array &$context = [],
  ) :array {
    $mapping = $this->getConfiguration()['mapping'] ?? NULL;
    if (empty($mapping)) {
      return [];
    }

    if (empty($context['jsonpath']['object'])) {
      $context['jsonpath']['object'] = new JsonObject($raw_data);
    }
    try {
      $values = $context['jsonpath']['object']->get($mapping) ?: [];
    }
    catch (InvalidJsonException | InvalidJsonPathException $e) {
      $this->logger->warning(
        "Invalid JSONPath: " . $e
      );
      $values = [];
    }
    return $this->processData($values);
  }

  /**
   * {@inheritdoc}
   */
  public function addPropertyValuesToRawData(
    array $property_values,
    array &$raw_data,
    array &$context,
  ) {
    $mapping = $this->getConfiguration()['mapping'] ?? NULL;
    if (empty($mapping)) {
      return;
    }
    // Reverse data processing.
    if (empty($context['jsonpath']['original'])) {
      $context['jsonpath']['original'] = new JsonObject(
        $context[FieldMapperInterface::CONTEXT_SOURCE_KEY] ?? []
      );
    }
    try {
      $original_data =
        $context['jsonpath']['original']->get($mapping)
        ?: [];
    }
    catch (InvalidJsonException | InvalidJsonPathException $e) {
      $this->logger->warning(
        "Invalid JSONPath: " . $e
      );
      $original_data = [];
    }
    $property_values = $this->reverseDataProcessing(
      $property_values,
      $original_data,
    );

    // Prepare JSONPath object on current data.
    if (empty($context['jsonpath']['object'])) {
      $context['jsonpath']['object'] = new JsonObject($raw_data);
    }

    $xntt_type_id = $this->externalEntityType->getDerivedEntityTypeId();
    $field_definition = $this
      ->entityFieldManager
      ->getFieldDefinitions($xntt_type_id, $xntt_type_id)[$this->fieldName]
      ?? NULL;

    if ($field_definition) {
      $field_cardinality = $field_definition
        ->getFieldStorageDefinition()
        ->getCardinality();
    }
    else {
      $field_cardinality = 0;
    }

    // Unwrap the singleton array if the cardinality of the field is 1.
    if ($field_cardinality === 1) {
      $property_values = array_shift($property_values);
    }

    if (isset($property_values)) {
      try {
        $context['jsonpath']['object']->set($mapping, $property_values);
      }
      catch (InvalidJsonPathException $e) {
        $this->logger->warning(
          "Invalid JSONPath: " . $e
        );
      }
    }

    // The JSONPath object holds previous $raw_data with newly added values.
    $raw_data = $context['jsonpath']['object']->getValue();
  }

}
