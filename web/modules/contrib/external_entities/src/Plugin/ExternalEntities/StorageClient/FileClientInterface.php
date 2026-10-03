<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\StorageClient;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\external_entities\StorageClient\StorageClientInterface;

/**
 * Abstract class for external entities storage client using files.
 */
interface FileClientInterface extends StorageClientInterface, PluginFormInterface {

  /**
   * String used to trim path.
   */
  const PATH_TRIM = " \n\r\t\v\0\\/";

  /**
   * Regex to match substring parameters.
   */
  const SUBSTR_REGEXP = '(?:(-?\d+)(?:,(-?\d+))?:)?';

  /**
   * Regex to match field regex.
   */
  const FIELDRE_REGEXP = '(?:#([^#]*)#)?';

  /**
   * Default source identifier field name.
   */
  const DEFAULT_ID_FIELD = '_id';

  /**
   * Field name used to store file original path.
   */
  const ORIGINAL_PATH_FIELD = '_xntt_path';

  /**
   * One record by file.
   */
  const RECORD_TYPE_SINGLE = 0;

  /**
   * Multiple records by file.
   */
  const RECORD_TYPE_MULTI = 1;

  /**
   * File statistics as record.
   */
  const RECORD_TYPE_FILE = 2;

  /**
   * Submission handler for update index file button.
   */
  public function submitUpdateIndexFileForm(
    array &$form,
    FormStateInterface $form_state,
  );

  /**
   * Launch batch jobs to regenerate/update index file.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $mode
   *   The update mode. One of 'regenerate' or 'update'.
   * @param string $title
   *   Batch title.
   */
  public function launchIndexFileRegenerationBatch(
    FormStateInterface $form_state,
    string $mode,
    string $title,
  ) :void;

  /**
   * Turns a file path into a valid entity identifier compatible with URLs.
   *
   * @param string $file_path
   *   The file path.
   *
   * @return string
   *   The identifier.
   */
  public function filePathToId(string $file_path) :string;

  /**
   * Turns a "file path" id into a regular file path.
   *
   * @param string $id
   *   The entity identifier that uses a file path as base.
   *
   * @return string
   *   The corresponding file path.
   */
  public function idToFilePath(string $id) :string;

  /**
   * Generates a regular expression from a file name pattern.
   *
   * @param string $structure
   *   A file name pattern as defined in this module documentation.
   *   If omitted, it will use the pattern provided by the configuration.
   *
   * @return string
   *   A regular expression that can match valid external entity files (
   *   excluding the data main directory part).
   */
  public function generateFileFilterRegex(?string $structure = NULL) :string;

  /**
   * Extract values from file path using file name pattern.
   *
   * @param string $file_path
   *   The file path from wich field values could be extracted.
   *
   * @return array
   *   Array of field values keyed by field names.
   */
  public function getValuesFromPath(string $file_path) :array;

  /**
   * Loads entity file index from index file if available.
   *
   * @param bool $reload
   *   If TRUE, clear cache and reload data from index file.
   *
   * @return array
   *   An array which keys are entity identifiers and values are corresponding
   *   data file path relative the the data directory set in config.
   */
  public function getEntityFileIndex(bool $reload = FALSE) :array;

  /**
   * Set the entity file index to use.
   *
   * @param array $entity_file_index
   *   An array of entity file path relative to the data directory set in
   *   config, keyed by corresponding entity identifiers.
   * @param bool $save
   *   If TRUE, saves the update into the index file otherwise, the file is left
   *   unchanged and just current class instance will use the changes.
   */
  public function setEntityFileIndex(array $entity_file_index, $save = FALSE);

  /**
   * Tells if an entity is indexed.
   *
   * @param string $entity_id
   *   The identifier of the entity to check.
   *
   * @return bool
   *   TRUE if the entity is indexed, FALSE otherwise.
   */
  public function isEntityIndexed(string $entity_id) :bool;

  /**
   * Returns all available external entity files matching this entity type.
   *
   * @param bool $only_from_index
   *   If TRUE, no directory listing will be done and only the provided file
   *   index will be used. Default: FALSE.
   * @param array $parameters
   *   An array of filtering parameters such as one provided to ::querySource().
   *
   * @return array
   *   An array of file path (including file schemes).
   */
  public function getAllEntitiesFilePath(
    bool $only_from_index = FALSE,
    array $parameters = [],
  ) :array;

  /**
   * Returns the file path of the file containing to the given entity.
   *
   * @param array|object $entity
   *   The entity of wich the path is requested.
   *
   * @return string
   *   Returns the path to the file file corresponding to the given entity
   *   including file scheme or an empty string if not available.
   */
  public function getEntityFilePath($entity) :string;

  /**
   * Loads the given file or use cached data and return loaded entities.
   *
   * @param string $file_path
   *   Full path to an existing file.
   * @param bool $with_column_names
   *   If TRUE, columns names will be included in the returned array using the
   *   key '' (empty string).
   * @param bool $reload
   *   If TRUE, reloads the cache if it exists.
   *
   * @return array
   *   Returns the loaded array of entities keyed by identifiers.
   */
  public function loadFile(
    string $file_path,
    bool $with_column_names = FALSE,
    bool $reload = FALSE,
  ) :array;

  /**
   * Saves the given file or return cached data if available.
   *
   * Client implementations may override this in order to remove empty files.
   *
   * @param string $file_path
   *   Full path to an existing file.
   * @param array $entities_data
   *   Array of entities keyed by identifiers plus the column (field) names
   *   keyed using the empty string key ''.
   *
   * @throw \Drupal\external_entities\Exception\FilesExternalEntityException
   */
  public function saveFile(string $file_path, array $entities_data);

}
