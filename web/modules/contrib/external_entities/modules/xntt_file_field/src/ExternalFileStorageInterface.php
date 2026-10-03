<?php

namespace Drupal\xntt_file_field;

use Drupal\xntt_file_field\Entity\ExternalFile;
use Drupal\file\FileStorageInterface;
use Drupal\external_entities\ExternalEntityStorageInterface;

/**
 * An external file "storage" implementation.
 *
 * This class does not really store external files but rather provide a mean to
 * manage them virtually.
 */
interface ExternalFileStorageInterface extends FileStorageInterface {

  /**
   * Regular expression to match and capture external file identifier data.
   */
  const XNTT_FILE_ID_REGEX = '/^xntt-([a-z_]\w*)-(.+)-([a-z_]\w*)(?:#(\d*))?$/';

  /**
   * Get file real URI.
   *
   * @param string $xntt_type
   *   The machine name of the external entity type.
   * @param string $xntt_id
   *   The identifier of the external entity instance.
   * @param string $file_field_name
   *   The machine name of the file field.
   * @param int $file_delta
   *   The file delta in the field (0 for the first file).
   * @param \Drupal\external_entities\Entity\ExternalEntityStorageInterface|null $storage
   *   The external entity storage service.
   * @param array|null $fm_config
   *   The field mapper configuration for the file field.
   *
   * @return string|null
   *   The real URI of the file, or NULL if not found or not valid.
   */
  public function getRealUri(
    string $xntt_type,
    string $xntt_id,
    string $file_field_name,
    int $file_delta = 0,
    ?ExternalEntityStorageInterface &$storage = NULL,
    ?array &$fm_config = NULL,
  );

  /**
   * Instanciate an external file entity.
   *
   * @param string $id
   *   External file special identifier containg file URI. It must match the
   *   pattern:
   *   "xntt-<content type machine name>-<uri drupal field machine name>-
   *   <content identifier>#<file number>" in order to use those external
   *   entities as Drupal managed files.
   *   The matching external entities must return at least a non-empty 'uri'
   *   field in order to be taken into account.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   If the external file identifier is invalid.
   */
  public function loadExternalFile(string $id) :ExternalFile;

}
