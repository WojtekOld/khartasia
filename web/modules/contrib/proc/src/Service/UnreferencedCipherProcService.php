<?php

namespace Drupal\proc\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service for finding cipher proc entities not referenced by proc fields.
 */
final class UnreferencedCipherProcService implements UnreferencedCipherProcServiceInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Constructs the service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, Connection $database, LoggerChannelFactoryInterface $loggerFactory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->database = $database;
    $this->loggerFactory = $loggerFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function getUnreferencedCipherProcIds(bool $count_only = FALSE, ?int $orphan_minimal_age = NULL, ?int $orphan_maximal_age = NULL): array|int {
    $proc_storage = $this->entityTypeManager->getStorage('proc');
    if (!$proc_storage) {
      return $count_only ? 0 : [];
    }

    $query = $proc_storage->getQuery();
    $query->accessCheck(TRUE);
    $query->condition('type', 'cipher');

    if ($orphan_minimal_age !== NULL) {
      $query->condition('created', \time() - $orphan_minimal_age, '<');
    }

    if ($orphan_maximal_age !== NULL && $orphan_maximal_age > 0) {
      $query->condition('created', \time() - $orphan_maximal_age, '>');
    }

    $cipher_proc_ids = array_values($query->execute());
    if (empty($cipher_proc_ids)) {
      return $count_only ? 0 : [];
    }

    $field_storage_storage = $this->entityTypeManager->getStorage('field_storage_config');
    if (!$field_storage_storage) {
      return $count_only ? 0 : [];
    }

    $field_storages = $field_storage_storage->loadByProperties([
      'type' => 'proc_entity_reference_field',
    ]);

    $referenced_proc_ids = [];
    if (!empty($field_storages)) {
      $schema = $this->database->schema();

      foreach ($field_storages as $field_storage) {
        $field_name = $field_storage->getName();
        $table_name = $field_storage->getTargetEntityTypeId() . '__' . $field_name;

        if (!$schema->tableExists($table_name)) {
          continue;
        }

        $column_name = NULL;
        foreach (["{$field_name}_target_id", 'target_id'] as $candidate) {
          if ($schema->fieldExists($table_name, $candidate)) {
            $column_name = $candidate;
            break;
          }
        }

        if ($column_name === NULL) {
          continue;
        }

        $ref_query = $this->database->select($table_name, 'peref');
        $ref_query->fields('peref', [$column_name]);
        $ref_query->distinct();
        $ref_query->condition("peref.{$column_name}", NULL, 'IS NOT NULL');
        $ids = $ref_query->execute()->fetchCol();

        if (!empty($ids)) {
          $referenced_proc_ids = array_merge($referenced_proc_ids, $ids);
        }
      }
    }

    $referenced_proc_ids = array_values(array_unique(array_map('strval', $referenced_proc_ids)));
    $unreferenced_proc_ids = array_values(array_diff(array_map('strval', $cipher_proc_ids), $referenced_proc_ids));

    if (!empty($unreferenced_proc_ids)) {
      $this->loggerFactory->get('proc')->info(
        'Found @count unreferenced cipher proc item(s).',
        ['@count' => count($unreferenced_proc_ids)]
      );
    }

    sort($unreferenced_proc_ids, SORT_NUMERIC);

    return $count_only ? count($unreferenced_proc_ids) : $unreferenced_proc_ids;
  }

}
