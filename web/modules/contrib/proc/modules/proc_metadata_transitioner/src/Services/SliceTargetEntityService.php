<?php

namespace Drupal\proc_metadata_transitioner\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service to slice target entities.
 */
class SliceTargetEntityService implements SliceTargetEntityServiceInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $logger;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * Create a new instance of this class.
   */
  public static function create($container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
    );
  }

  /**
   * Get sliced entity IDs.
   *
   * @param int $max_operations
   *   The batch size.
   * @param int|null $limit
   *   The maximum limit.
   * @param string|null $entity_machine_name
   *   The entity machine name.
   * @param array $conditions
   *   The conditions to filter the entities.
   *
   * @return array
   *   An array containing:
   *   - 'target_ids': The total number of target IDs.
   *   - 'target_intervals': An array of start and end ID pairs.
   *   - 'step': The step size.
   *   - 'max_number_operations': The maximum number of operations.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   *
   * @SuppressWarnings(PHPMD.NPathComplexity)
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function getSlicedEntityIds(
    int $max_operations,
    ?int $limit = NULL,
    ?string $entity_machine_name = NULL,
    array $conditions = [],
  ): array {

    if (!$entity_machine_name) {
      throw new \Exception('The entity machine name is required.');
    }

    // Check if the machine name is valid.
    $entity_types = $this->entityTypeManager->getDefinitions();

    if (!isset($entity_types[$entity_machine_name])) {
      throw new \Exception('The entity type ' . $entity_machine_name . ' does not exist.');
    }

    $query = $this->entityTypeManager->getStorage($entity_machine_name)
      ->getQuery();

    // Add conditions if any.
    if (!empty($conditions) && is_array($conditions)) {
      foreach ($conditions as $field => $value) {
        $query->condition($field, $value);
      }
    }

    $query->sort('id');
    if ($limit) {
      $query->range(0, $limit);
    }
    $ids = $query->accessCheck(FALSE)
      ->execute();

    $ids = array_values($ids);

    $count = (int) count($ids);

    if ($count === 0) {
      return [
        'target_ids' => 0,
        'target_intervals' => [],
        'step' => 0,
        'max_number_operations' => 0,
      ];
    }

    if ($count < $max_operations) {
      $max_operations = $count;
    }

    $ratio = $count / $max_operations;

    $last_chunk_items = 0;
    if (!is_int($ratio)) {
      $ratio = ceil($ratio);
      $full_chunks = $max_operations - 1;
      $last_chunk_items = $count - ($ratio * $full_chunks);
      // Check if the sum of items is correct:
      if (($ratio * $full_chunks + $last_chunk_items) != $count) {
        throw new \Exception('The sum of items is incorrect.');
      }
    }

    $pairs = [];
    $chunks_index = 0;

    while ($chunks_index < $max_operations && (($chunks_index * $ratio) < $count)) {
      $endIndex = (($chunks_index + 1) * $ratio) - 1;
      if (isset($ids[$endIndex])) {
        $start = $ids[$chunks_index * $ratio];
        $end = $ids[$endIndex];
        $pairs[] = ['start' => $start, 'end' => $end];
      }
      $chunks_index++;
    }

    if ($last_chunk_items) {
      $start_index = ($count - 1) - $last_chunk_items + 1;
      if (isset($ids[$start_index])) {
        // Add the last pair.
        $start = $ids[$start_index];
        $end = $ids[$count - 1];
        $pairs[] = ['start' => $start, 'end' => $end];
      }
    }

    return [
      'target_ids' => $count,
      'target_intervals' => $pairs,
      'step' => (int) $ratio,
      'max_number_operations' => $max_operations,
    ];
  }

}
