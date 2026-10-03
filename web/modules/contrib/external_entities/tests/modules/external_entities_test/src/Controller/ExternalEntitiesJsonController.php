<?php

namespace Drupal\external_entities_test\Controller;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\external_entities\ResponseDecoder\ResponseDecoderFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * An simple JSON controller.
 *
 * This controller mimics a basic REST endpoint that can be queried for:
 * - listing datasets: URI(GET)=external-entities-test
 * - Add a new dataset: URI(POST)=external-entities-test/{dataset}
 *   POST data may contain an array of entities keyed by their identifier.
 *   If no POST data is provided, it may get it from the query string.
 *   If the dataset already exists, it will return a 409 Conflict response.
 * - Remove a dataset: URI(DELETE)=external-entities-test/{dataset}
 * - counting dataset items: URI(GET)=external-entities-test/count/{dataset}
 *   It will return a JSON array with a single 'count' key containing the number
 *   of items in the dataset.
 * - listing dataset items: URI(GET)=external-entities-test/{dataset}
 *   Listing supports basic filtering (ie. field_name=value or
 *   field_name=value1,value2,value3 to match at least one of a set of values)
 *   and pagging using 'page' for page number starting from 0 and 'pageSize' for
 *   page size (default to 50).
 * - getting an entity: URI(GET)=external-entities-test/{dataset}/{entity_id}
 * - creating/updating an entity:
 *   URI(PATCH/POST/PUT)=external-entities-test/{dataset}/{entity_id}
 *   The provided JSON data contains the entity data (not keyed by the id).
 * - removing an entity:
 *   URI(DELETE)=external-entities-test/{dataset}/{entity_id}
 *
 * Datasets can be added through setRawData($dataset, $data) where $dataset is a
 * dataset name and $data is an array of raw entities keyed by their identifier.
 * Ex.:
 * @code
 *   $xntt_json_controller = $this
 *     ->container
 *     ->get('controller_resolver')
 *     ->getControllerFromDefinition(
 *       '\Drupal\external_entities_test\Controller\ExternalEntitiesJsonController::listItems'
 *     )[0];
 *   $xntt_json_controller->setRawData('ref', [
 *     'ref1' => [
 *       'id' => 'ref1',
 *       'label' => 'Term 1',
 *     ],
 *     'ref2' => [
 *       'id' => 'ref2',
 *       'label' => 'Term 2',
 *     ],
 *   ]);
 * @endcode
 *
 * This controller also supports authentication using an authorization token.
 * The token can be added using addToken($token) and removed using
 * removeToken($token). The token can be provided either in the Authorization
 * header (as a Bearer token) or in the query string (as apikey).
 * If no token or an empty string token is provided, the controller will allow
 * unauthenticated requests.
 *
 * If authentication is enabled, it will be required for adding, removing or
 * updating datasets and items, but not for listing datasets or items.
 * However, Drupal users with the permission 'manage external entity test
 * datasets' will be allowed to perform all operations without providing a
 * token.
 */
class ExternalEntitiesJsonController extends ControllerBase {

  /**
   * Page size.
   */
  const PAGE_SIZE = 50;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The response decoder factory.
   *
   * @var \Drupal\external_entities\ResponseDecoder\ResponseDecoderFactoryInterface
   */
  protected $decoderFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new ExternalEntitiesJsonController object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\external_entities\ResponseDecoder\ResponseDecoderFactoryInterface $decoderFactory
   *   The response decoder factory.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user performing the request.
   */
  public function __construct(
    StateInterface $state,
    ResponseDecoderFactoryInterface $decoderFactory,
    AccountInterface $current_user,
  ) {
    $this->state = $state;
    $this->decoderFactory = $decoderFactory;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create($container) {
    return new static(
      $container->get('state'),
      $container->get('external_entities.response_decoder_factory'),
      $container->get('current_user')
    );
  }

  /**
   * Get data from a dataset.
   */
  public function getRawData(string $dataset) :?array {
    $datasets = $this->state()->get('external_entities_test.data') ?? [];
    return $datasets[$dataset] ?? NULL;
  }

  /**
   * Set a dataset.
   */
  public function setRawData(string $dataset, array $data = []) {
    $datasets = $this->state()->get('external_entities_test.data') ?? [];
    $datasets[$dataset] = $data;
    $this->state()->set('external_entities_test.data', $datasets);
    return $datasets[$dataset];
  }

  /**
   * Remove a dataset.
   */
  public function removeRawData(string $dataset) {
    $datasets = $this->state()->get('external_entities_test.data') ?? [];
    unset($datasets[$dataset]);
    $this->state()->set('external_entities_test.data', $datasets);
  }

  /**
   * Adds an authorization token.
   *
   * @param string $token
   *   The token to add. If the token already exists, it will not be added
   *   again. If the token is an empty string, it will allow any unauthenticated
   *   request (ie. it disables authentication).
   */
  public function addToken(string $token) {
    $tokens = $this->state()->get('external_entities_test.tokens') ?? [];
    if (!in_array($token, $tokens)) {
      $tokens[] = $token;
    }
    $this->state()->set('external_entities_test.tokens', $tokens);
  }

  /**
   * Removes an authorization token.
   *
   * @param string $token
   *   The token to remove.
   */
  public function removeToken(string $token) {
    $tokens = $this->state()->get('external_entities_test.tokens') ?? [];
    if (in_array($token, $tokens)) {
      $tokens = array_filter($tokens, function ($value) use ($token) {
        return $value !== $token;
      });
    }
    $this->state()->set('external_entities_test.tokens', $tokens);
  }

  /**
   * Checks an authorization token.
   *
   * @param string $token
   *   The token to check.
   *
   * @return bool
   *   TRUE if the token is valid, FALSE otherwise.
   */
  public function isValidToken(string $token) {
    $tokens = $this->state()->get('external_entities_test.tokens') ?? [];
    return empty($tokens)
      || in_array($token, $tokens)
      || in_array('', $tokens);
  }

  /**
   * Returns a list of dataset as JSON.
   */
  public function listDatasets(Request $request) {
    // Set the default cache.
    $cache = new CacheableMetadata();
    $cache->addCacheTags([
      'external_entities_test',
      'external_entities_test_datasets',
    ]);

    // Add the cache contexts for the request parameters.
    $cache->addCacheContexts([
      'url',
    ]);

    $datasets = $this->state()->get('external_entities_test.data') ?? [];

    $response = new CacheableJsonResponse(array_keys($datasets), 200);
    $response->addCacheableDependency($cache);
    return $response;
  }

  /**
   * Adds a new dataset and returns its content.
   */
  public function addDataset(string $dataset, Request $request) {
    if (!$this->currentUser->hasPermission('manage external entity test datasets')
        && !$this->isValidToken($this->getRequestToken($request))
    ) {
      throw new AccessDeniedHttpException('You do not have permission to manage external entity test datasets.');
    }
    // Set the default cache.
    $cache = new CacheableMetadata();
    $cache->addCacheTags([
      'external_entities_test',
      'external_entities_test_dataset_' . $dataset,
    ]);

    // Add the cache contexts for the request parameters.
    $cache->addCacheContexts([
      'url',
    ]);

    $data = $this->getRawData($dataset);
    if (isset($data)) {
      // Indicate a conflict.
      return new JsonResponse([], 409);
    }

    $data = [];
    $params = $this->getRequestContent($request) ?? [];
    if (is_array($params) && !empty($params)) {
      $data = $params;
    }
    $this->setRawData($dataset, $data);

    Cache::invalidateTags([
      'external_entities_test',
      'external_entities_test_datasets',
    ]);

    $response = new CacheableJsonResponse(array_values($data), 200);
    $response->addCacheableDependency($cache);
    return $response;
  }

  /**
   * Removes a dataset.
   */
  public function removeDataset(string $dataset, Request $request) {
    if (!$this->currentUser->hasPermission('manage external entity test datasets')
        && !$this->isValidToken($this->getRequestToken($request))
    ) {
      throw new AccessDeniedHttpException('You do not have permission to manage external entity test datasets.');
    }
    // Set the default cache.
    $cache = new CacheableMetadata();
    $cache->addCacheTags([
      'external_entities_test',
      'external_entities_test_dataset_' . $dataset,
    ]);

    // Add the cache contexts for the request parameters.
    $cache->addCacheContexts([
      'url',
    ]);

    $data = $this->getRawData($dataset);
    if (!isset($data)) {
      return new JsonResponse([], 404);
    }

    $this->removeRawData($dataset);

    Cache::invalidateTags([
      'external_entities_test',
      'external_entities_test_datasets',
    ]);

    $response = new CacheableJsonResponse($data, 200);
    $response->addCacheableDependency($cache);
    return $response;
  }

  /**
   * Returns the total number of items for the given dataset as JSON.
   */
  public function countItems(string $dataset, Request $request) {
    $data = $this->getRawData($dataset) ?? [];
    $response = new CacheableJsonResponse(['count' => count($data)], 200);
    return $response;
  }

  /**
   * Returns a list of items for the given dataset as JSON.
   */
  public function listItems(string $dataset, Request $request) {
    // Set the default cache.
    $cache = new CacheableMetadata();
    $cache->addCacheTags([
      'external_entities_test',
      'external_entities_test_dataset_' . $dataset,
    ]);

    // Add the cache contexts for the request parameters.
    $cache->addCacheContexts([
      'url',
      'url.query_args',
    ]);

    $data = $this->getRawData($dataset);
    if (!isset($data)) {
      return new JsonResponse([], 404);
    }

    // Process parameters.
    $params = $request->query->all();
    // Pagging.
    $page_size = static::PAGE_SIZE;
    if (!empty($params['pageSize']) && is_numeric($params['pageSize'])) {
      $page_size = $params['pageSize'];
      unset($params['pageSize']);
    }
    $page = 0;
    if (isset($params['page']) && is_numeric($params['page'])) {
      $page = $params['page'];
      unset($params['page']);
    }

    // Check for filters.
    if (!empty($params)) {
      foreach ($params as $field => $values) {
        if (!is_array($values)) {
          $values = explode(',', $values);
        }
        foreach ($data as $id => $record) {
          if (!in_array($record[$field], $values)) {
            unset($data[$id]);
          }
        }
      }
    }

    // Pagging management.
    $start = $page * $page_size;
    $data = array_slice($data, $start, $page_size);

    $response = new CacheableJsonResponse(array_values($data), 200);
    $response->addCacheableDependency($cache);
    return $response;
  }

  /**
   * Returns the requested item as JSON.
   */
  public function getItem(string $dataset, string|int $id, Request $request) {
    $data = $this->getRawData($dataset) ?? [];
    if (!isset($data[$id])) {
      return new JsonResponse([], 404);
    }

    // Set the default cache.
    $cache = new CacheableMetadata();
    $cache->addCacheTags([
      'external_entities_test',
      'external_entities_test_dataset_' . $dataset,
      'external_entities_test_dataset_' . $dataset . ':' . $id,
    ]);

    // Add the cache contexts for the request parameters.
    $cache->addCacheContexts([
      'url',
    ]);

    $response = new CacheableJsonResponse($data[$id], 200);
    $response->addCacheableDependency($cache);
    return $response;
  }

  /**
   * Sets a given item.
   */
  public function setItem(string $dataset, string|int|null $id, Request $request) {
    if (!$this->currentUser->hasPermission('manage external entity test datasets')
        && !$this->isValidToken($this->getRequestToken($request))
    ) {
      throw new AccessDeniedHttpException('You do not have permission to manage external entity test datasets.');
    }
    $data = $this->getRawData($dataset);
    if (!isset($data)) {
      return new JsonResponse([], 404);
    }
    $params = $this->getRequestContent($request) ?? [];
    if (empty($id) && (0 !== $id) && (0. !== $id) && ('0' !== $id)) {
      if (isset($params['id'])) {
        $id = $params['id'];
      }
      else {
        $id = uniqid('', TRUE);
        $params['id'] = $id;
      }
    }
    $data[$id] = $params;
    $this->setRawData($dataset, $data);

    // Invalidate cache.
    Cache::invalidateTags([
      'external_entities_test',
      'external_entities_test_dataset_' . $dataset,
      'external_entities_test_dataset_' . $dataset . ':' . $id,
    ]);

    $response = new JsonResponse($data[$id], 200);
    return $response;
  }

  /**
   * Deletes a given item.
   */
  public function deleteItem(string $dataset, string|int|null $id, Request $request) {
    if (!$this->currentUser->hasPermission('manage external entity test datasets')
        && !$this->isValidToken($this->getRequestToken($request))
    ) {
      throw new AccessDeniedHttpException('You do not have permission to manage external entity test datasets.');
    }
    $data = $this->getRawData($dataset);
    if (!isset($data[$id])) {
      return new JsonResponse([], 404);
    }

    unset($data[$id]);
    $this->setRawData($dataset, $data);

    // Invalidate cache.
    Cache::invalidateTags([
      'external_entities_test',
      'external_entities_test_dataset_' . $dataset,
      'external_entities_test_dataset_' . $dataset . ':' . $id,
    ]);

    $response = new JsonResponse([], 200);
    return $response;
  }

  /**
   * Get the request content.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API Request.
   *
   * @return array
   *   Request content.
   */
  public function getRequestContent(Request $request) {
    $body = $request->getContent();
    $json_decoder = $this->decoderFactory->getDecoder('json');
    if ($json_decoder) {
      $content = $json_decoder->decode($body);
    }
    // If the JSON content is empty, try to get data from query string.
    if (empty($content)) {
      $content = [];
      parse_str($request->getQueryString() ?? '', $content);
    }
    return $content;
  }

  /**
   * Get the request token.
   *
   * The token can be retrieved either from the Authorization header (Bearer) or
   * from the query string (apikey). If not token is provided, an empty string
   * is returned.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API Request.
   *
   * @return string
   *   The token or an empty string.
   */
  public function getRequestToken(Request $request) {
    $token = '';
    if ($request->headers->has('Authorization')) {
      $authorization = $request->headers->get('Authorization');
      $token = str_replace('Bearer ', '', $authorization);
    }
    if (empty($token) && $request->query->has('apikey')) {
      $token = $request->query->get('apikey');
    }
    return $token;
  }

}
