<?php

namespace Drupal\external_entities\DataProcessor;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\external_entities\Plugin\PluginDebugInterface;

/**
 * Interface for data processor plugins.
 *
 * Data processors control how raw data is mapped into and out of entity
 * objects.
 * The original idea comes from
 * [External Entities Field Processors](https://www.drupal.org/project/external_entities_field_processors).
 */
interface DataProcessorInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface, PluginDebugInterface {

  /**
   * Returns the administrative label for this data processor plugin.
   *
   * @return string
   *   The data processor administrative label.
   */
  public function getLabel() :string;

  /**
   * Returns the administrative description for this data processor plugin.
   *
   * @return string
   *   The data processor administrative description.
   */
  public function getDescription() :string;

  /**
   * Returns the processed data.
   *
   * @param array $raw_data
   *   The raw data in an array to support multiple values.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Definition of the target field.
   * @param string $property_name
   *   Name of the target property.
   *
   * @return array
   *   The processed data.
   */
  public function processData(
    array $raw_data,
    FieldDefinitionInterface $field_definition,
    string $property_name,
  ) :array;

  /**
   * Reverse data processing.
   *
   * @param array $data
   *   The processed data in an array to support multiple values.
   * @param array $original_data
   *   The corresponding original data provided by the source storage before it
   *   has been processed and possibly altered.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Definition of the corresponding field.
   * @param string $property_name
   *   Name of the corresponding property.
   *
   * @return array|null
   *   The raw data like it should have been before being processed or NULL if
   *   the process can not be reversed.
   */
  public function reverseDataProcessing(
    array $data,
    array $original_data,
    FieldDefinitionInterface $field_definition,
    string $property_name,
  ) :array|null;

  /**
   * Tells if the processor could reverse processed data into its original form.
   *
   * This method should be used to know if the data processor can be used for
   * writting back data to its source. It should mainly be used on UI as
   * self::reverseDataProcessing() returns NULL to tell when a processed data
   * cannot be reversed into its original form.
   * Note: the data processor may return TRUE if it is *theorically* possible to
   * reverse the process, but this may not be possible in practice. For
   * instance, a string data processor that capitalizes all the letters of a
   * string cannot reverse the process if it does not know where letter should
   * be lowercased, so calling this method should return FALSE. However, if that
   * data processor has a setting to tell how to lowercase letters of a string,
   * then this method should return TRUE. But if that setting works for a single
   * word but not on multiple words, that data processor would only be able to
   * reverse single words and not multiple words. Therefore, this method will
   * return TRUE while finally, in some cases (multiple words), the data
   * processor will fail to reverse the data (ie. self::reverseDataProcessing()
   * will return NULL).
   *
   * @return bool
   *   TRUE if the data processing can be reversed, FALSE otherwise.
   */
  public function couldReverseDataProcessing() :bool;

  /**
   * Tells if the processor may alter the returned data.
   *
   * This method should be used to know if the data processor may alter the
   * data it processes and may return a value that would be evaluated as
   * different from the original value (using '==' and not '==='). For instance,
   * a processor that changes lower case letters into capital letters would
   * return TRUE for this method, even if it would return the same value for a
   * string that only contains capital letters, because any string that would
   * contain a lower case letter would be altered. However, a processor that
   * just does a typecast would return FALSE for this method. Also, removing a
   * value from an array of values of the source data can not be considered as
   * a data alteration.
   * The idea behind this method is to be able to know if a processed value can
   * be directly used to match the same value on the source side.
   *
   * @return bool
   *   TRUE if there is a possibility that the data can be altered, FALSE if the
   *   data won't be altered. A type cast or a data filtering can be considered
   *   as leaving the data unchanged (ie. not altered).
   */
  public function mayAlterData() :bool;

}
