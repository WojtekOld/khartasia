<?php

declare(strict_types=1);

namespace Drupal\external_entities_storage_test\Plugin\ExternalEntities\StorageClient;

use Drupal\external_entities\Attribute\StorageClient;
use Drupal\external_entities\Entity\ExternalEntityInterface;
use Drupal\external_entities\StorageClient\StorageClientBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * In-memory storage client used by storage handler tests.
 *
 * The raw data and a per-id call counter are kept in static properties so the
 * test can seed data, assert how many times the client was hit (to verify
 * persistent caching), and observe writes/deletes.
 *
 * @StorageClient(
 *   id = "memory_test",
 *   label = @Translation("In-memory test client"),
 *   description = @Translation("Holds raw data in a static array for tests.")
 * )
 */
#[StorageClient(
  id: 'memory_test',
  label: new TranslatableMarkup('In-memory test client'),
  description: new TranslatableMarkup('Holds raw data in a static array for tests.'),
)]
class MemoryStorageClient extends StorageClientBase {

  /**
   * Raw data stores, keyed by store name, then by entity id.
   *
   * Multiple named stores allow tests to back several external entity types
   * with independent data sets (configure the client with a "store" key).
   *
   * @var array
   */
  protected static array $stores = [];

  /**
   * Returns the store name used by this client instance.
   */
  protected function getStoreName(): string {
    return $this->configuration['store'] ?? 'default';
  }

  /**
   * Returns a reference to the store of this client instance.
   */
  protected function &store(): array {
    static::$stores[$this->getStoreName()] ??= [];
    return static::$stores[$this->getStoreName()];
  }

  /**
   * Number of loadMultiple() calls that actually queried the source.
   *
   * @var int
   */
  protected static int $loadMultipleCalls = 0;

  /**
   * Number of save() calls that wrote to the source.
   *
   * @var int
   */
  protected static int $saveCalls = 0;

  /**
   * Replaces the in-memory raw data store.
   *
   * @param array $data
   *   Raw data keyed by entity id.
   * @param string $store
   *   The named store to replace.
   */
  public static function setData(array $data, string $store = 'default'): void {
    static::$stores[$store] = $data;
  }

  /**
   * Returns the current in-memory raw data store.
   *
   * @return array
   *   Raw data keyed by entity id.
   */
  public static function getData(string $store = 'default'): array {
    return static::$stores[$store] ?? [];
  }

  /**
   * Resets the store and the call counter.
   */
  public static function reset(): void {
    static::$stores = [];
    static::$loadMultipleCalls = 0;
    static::$saveCalls = 0;
  }

  /**
   * Returns the number of loadMultiple() invocations since the last reset.
   *
   * @return int
   *   The call count.
   */
  public static function getLoadMultipleCallCount(): int {
    return static::$loadMultipleCalls;
  }

  /**
   * Returns the number of save() invocations since the last reset.
   *
   * @return int
   *   The call count.
   */
  public static function getSaveCallCount(): int {
    return static::$saveCalls;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(?array $ids = NULL): array {
    static::$loadMultipleCalls++;
    $store = &$this->store();
    if ($ids === NULL) {
      return $store;
    }
    $result = [];
    foreach ($ids as $id) {
      if (array_key_exists($id, $store)) {
        $result[$id] = $store[$id];
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function save(ExternalEntityInterface $entity): int {
    static::$saveCalls++;
    $store = &$this->store();
    $raw_data = $entity->toRawData();
    $id = $raw_data['id'];
    $is_new = !array_key_exists($id, $store);
    $store[$id] = $raw_data;
    return $is_new ? SAVED_NEW : SAVED_UPDATED;
  }

  /**
   * {@inheritdoc}
   */
  public function delete(ExternalEntityInterface $entity) {
    $store = &$this->store();
    unset($store[$entity->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function querySource(
    array $parameters = [],
    array $sorts = [],
    $start = NULL,
    $length = NULL,
  ): array {
    // Respect paging: callers like the Search API tracker page through the
    // source and rely on an empty result to stop.
    return array_slice(array_values($this->store()), $start ?? 0, $length);
  }

  /**
   * {@inheritdoc}
   */
  public function transliterateDrupalFilters(
    array $parameters,
    array $context = [],
  ): array {
    // Everything is handled on the Drupal side for this simple test client.
    return [
      'source' => [],
      'drupal' => $parameters,
    ];
  }

}
