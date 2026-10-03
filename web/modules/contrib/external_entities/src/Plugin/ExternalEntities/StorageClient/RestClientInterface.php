<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\StorageClient;

use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\external_entities\ResponseDecoder\ResponseDecoderFactoryInterface;
use Drupal\external_entities\StorageClient\StorageClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * External entities REST API storage client interface.
 */
interface RestClientInterface extends StorageClientInterface, PluginFormInterface {

  /**
   * Default page length for endpoint queries if not specified.
   *
   * (default set to 50 as it's Drupal entity list default size)
   * See \Drupal\Core\Entity\EntityListBuilder::$limit.
   *
   * @var int
   */
  public const DEFAULT_PAGE_LENGTH = 50;

  /**
   * Returns the response decoder factory.
   *
   * @return \Drupal\external_entities\ResponseDecoder\ResponseDecoderFactoryInterface
   *   The response decoder factory.
   */
  public function getResponseDecoderFactory() :ResponseDecoderFactoryInterface;

  /**
   * Sets the response decoder factory.
   *
   * @param \Drupal\external_entities\ResponseDecoder\ResponseDecoderFactoryInterface $response_decoder_factory
   *   A response decoder factory.
   *
   * @return \Drupal\external_entities\Plugin\ExternalEntities\StorageClient\RestClientInterface
   *   Returns current instance.
   */
  public function setResponseDecoderFactory(ResponseDecoderFactoryInterface $response_decoder_factory) :self;

  /**
   * Loads raw data for one or more entities.
   *
   * As loading all entities from a remote end point could lead to a timeout or
   * a memory crash, the behavior if this method is different from the original
   * StorageClientInterface as using NULL for $ids parameter will not load all
   * values but limit them to self::getDefaultMaxEntitiesToFetch() instead.
   * If REST clients need to be able to load all that way, they would need to
   * use the setter self::setDefaultMaxEntitiesToFetch(NULL).
   *
   * @param array|null $ids
   *   An array of IDs, or NULL to load self::getDefaultMaxEntitiesToFetch()
   *   entities.
   *
   * @return array
   *   An array of raw data arrays indexed by their IDs.
   */
  public function loadMultiple(?array $ids = NULL) :array;

  /**
   * Returns the endpoint URL corresponding to the requested endpoint.
   *
   * @param string $request_type
   *   The request type. Should be one of 'create', 'read', 'update', 'delete',
   *   'list' or 'count'.
   * @param array $parameters
   *   An associative array of parameters specific or not to each request type.
   *   - For 'create/read/update/delete' request type, the key 'id' should
   *     contain an entity identifier. It may not be set when creating a new
   *     entity.
   *
   * @return string
   *   The endpoint URL to use to fetch data. If the requested endpoint has a
   *   query string in the settings, it will be removed. For 'create', 'read',
   *   'update' or 'delete' request types, any '{id}' placeholder will be
   *   replaced by the given identifier. If the settings provide a single entity
   *   URL without any '{id}' placeholder, that URL will be returned as is. If
   *   no single entity URL was provided, the list endpoint will be used with an
   *   appended '/' + the provided identifier.
   */
  public function getEndpointUrl(
    string $request_type = 'list',
    array $parameters = [],
  ) :string;

  /**
   * Gets the HTTP headers to add to a request.
   *
   * @param string $endpoint
   *   Endpoint URL used for the call.
   * @param array $parameters
   *   An associative array of parameters specific or not to each request type.
   *
   * @return array
   *   Associative array of headers to add to the request.
   */
  public function getHttpHeaders(
    string $endpoint = '',
    array $parameters = [],
  ) :array;

  /**
   * Returns the HTTP method to use for the given endpoint and request type.
   *
   * @param string $request_type
   *   The request type. Should be one of 'create', 'read', 'update', 'delete',
   *   'list' or 'count'.
   * @param string $endpoint
   *   Endpoint URL used for the call.
   * @param array $parameters
   *   An associative array of parameters specific or not to each request type.
   *
   * @return string
   *   The HTTP method to use.
   */
  public function getRequestMethod(
    string $request_type,
    string $endpoint = '',
    array $parameters = [],
  ) :string;

  /**
   * Provides URL query parameters for a given type of request and endpoint.
   *
   * @param string $endpoint
   *   The used endpoint.
   * @param array $parameters
   *   An associative array of parameters specific or not to each request type.
   *   - For 'list' request types, the key 'paging' should contain pager data.
   *     See self::getPagingQueryParameters() for details.
   *   - For 'list' and 'count' request types, the key 'filters' should contain
   *     query filters. See self::getListQueryParameters() for details.
   *   - For 'create/read/update/delete' request types, the key 'id' should
   *     contain an entity identifier. It may not be set when creating a new
   *     entity.
   *   - For all, the key 'operation' should contain the request type. Should
   *     be one of 'create', 'read', 'update', 'delete', 'list' or 'count'.
   *
   * @return array|string
   *   An associative array of query parameters keyed by parameter names with
   *   their associated values or a serialized query string.
   *   Ex.:
   *   @code
   *   [
   *     'name_1' => 'value_1',
   *     'name_2' => 'value_2,value_3',
   *   ]
   *   @endcode
   *   Note: a child class could also implement the transliteration of the third
   *   example field if the source supports it that way, like this for instance:
   *   @code
   *     'name_3-lighter-than' => 'value_4',
   *   @endcode
   *   or even (it really depends how the source storage works and what
   *   self::transliterateDrupalFilters() implementation returned)
   *   @code
   *     'filter-1-field' => 'name_3',
   *     'filter-1-op'    => '>',
   *     'filter-1-value' => 'value_4',
   *   @endcode
   */
  public function getQueryParameters(
    string $endpoint = '',
    array $parameters = [],
  ) :array|string;

  /**
   * Gets the paging query parameters based on the configuration.
   *
   * Since the number of requested entities may be higher than the maximum
   * number of entities supported by a single endpoint listing call, this
   * function will return an array of paging parameters for subsequent requests
   * to list all the requested entities.
   *
   * Also, since the REST service imposes a page size, we will have to deal with
   * it. For instance, consider a set of 10 entities with a REST service
   * allowing a maximum page size of 4 entities and using a page number
   * parameter. If the query requests 3 entities starting from 2  (zero-based
   * indexing), we will have to query the service 2 times. The first call will
   * get page 0 with entities 0 to 3 and the second call will get entities from
   * 4 to 7. Doing so, we will get a set of 8 entities from 0 to 7 while the
   * query was only expecting 3 entities starting from 2. Therefore, this method
   * will return an array with 2 sub-arrays. Each sub-array will hold the query
   * parameters in a 'parameters' key and the entities to keep in the query
   * result set using the 'start' en 'length' keys. The method caller will have
   * the responsability to filter the requested entities.
   * Example of the resulting array:
   * @code
   *   [
   *     [
   *       'parameters' => ['page' => 0, 'pagesize' => 4, ],
   *       'start' => 2,
   *       'length' => 2,
   *     ],
   *     [
   *       'parameters' => ['page' => 1, 'pagesize' => 4, ],
   *       'start' => 0,
   *       'length' => 1,
   *     ],
   *   ];
   * @endcode
   * But this methods also handles the use of starting item instead of page
   * number and ending item instead of number of items per page. The returned
   * arrays will have the same structure. If the previous example used a start
   * item and an ending item parameters with the same page size limitation, it
   * would look like this:
   * @code
   *   [
   *     [
   *       'parameters' => ['from' => 2, 'to' => 4, ],
   *       'start' => 0,
   *       'length' => 3,
   *     ],
   *   ];
   * @endcode
   * However, if it requested 5 elements instead of 3, it would look like this:
   * @code
   *   [
   *     [
   *       'parameters' => ['from' => 2, 'to' => 5, ],
   *       'start' => 0,
   *       'length' => 4,
   *     ],
   *     [
   *       'parameters' => ['from' => 6, 'to' => 6, ],
   *       'start' => 0,
   *       'length' => 1,
   *     ],
   *   ];
   * @endcode
   *
   * Note: ::countQuerySource() must not call ::querySource() to count the
   *   number of returned entities as ::querySource() calls
   *   ::getPagingQueryParameters() which itself calls ::countQuerySource() and
   *   we would go into an infinite loop.
   *   To protect against derived class that may ignore this rule, we use a
   *   member to tell if the method is called while it is already running.
   *
   * @param int $start
   *   (optional) Item index to start with. Default to 0.
   * @param int $length
   *   (optional) Amount of items to return. Default to ::DEFAULT_PAGE_LENGTH.
   * @param array $parameters
   *   (optional) Array of parameters as in self::querySource(). Only needed
   *   when no $length is provided to get the maximum number of results.
   *
   * @return array
   *   An array of paging parameters. An empty array if no paging is available.
   *   Each paging parameter contains a key 'parameters' with an associative
   *   array of query parameters for paging, a key 'start' containing the index
   *   of the first element to keep from the query and a key 'length' indicating
   *   how many elements to keep from the query.
   *
   * @thows \LogicException
   *   If ::getPagingQueryParameters() is called recursively (infinite loop).
   */
  public function getPagingQueryParameters(
    ?int $start = NULL,
    ?int $length = NULL,
    array $parameters = [],
  ) :array;

  /**
   * Returns the default maximum number of entities that could be fetched.
   *
   * Drupal default behavior when using ::loadMultiple() or ::query() is to
   * return all entities. However, with REST endpoints, this can be problematic
   * because either the endpoint has limitations or because it could take a very
   * long time to fecth everything. Therefore, the REST client sets a default
   * maximum number of entities to return but it can be changed on purpose.
   *
   * @return int|null
   *   The default maximum number of entities that could be fetched by a
   *   self::loadMultiple() or self::query() call. If NULL, loads all.
   */
  public function getDefaultMaxEntitiesToFetch() :?int;

  /**
   * Sets the default maximum number of entities that could be fetched.
   *
   * Drupal default behavior when using ::loadMultiple() or ::query() is to
   * return all entities. However, with REST endpoints, this can be problematic
   * because either the endpoint has limitations or because it could take a very
   * long time to fecth everything. Therefore, the REST client sets a default
   * maximum number of entities to return but it can be changed on purpose.
   *
   * @param int|null $max_entitites
   *   The default maximum number of entities that could be fetched by a
   *   self::loadMultiple() or self::query() call. If NULL, loads all.
   *
   * @return self
   *   Current instance.
   */
  public function setDefaultMaxEntitiesToFetch(?int $max_entitites) :self;

  /**
   * Returns Guzzle request parameter structure.
   *
   * @param string $request_type
   *   The request type. Should be one of 'create', 'read', 'update', 'delete',
   *   'list' or 'count'.
   * @param string $method
   *   The HTTP method to use. Should be one of 'GET', 'POST', 'PUT', 'DELETE'.
   * @param string $endpoint
   *   The used endpoint.
   * @param array $parameters
   *   An associative array of parameters specific or not to each request type.
   *   - For 'list' and 'count' request types, the key 'filters' should contain
   *     query filters. See self::getListQueryParameters() for details.
   *   - For 'create/read/update/delete' request types, the key 'id' should
   *     contain an entity identifier. It may not be set when creating a new
   *     entity.
   *   - For 'create/update' request types, the key 'data' should
   *     contain entity data.
   *
   * @return array
   *   An array with that should contain a key setting the type of body (one of
   *   'body', 'json', 'form_params' or 'multipart'), the adapted parameter
   *   value structure and an eventual 'header' key setting the content type or
   *   an empty array if nothing needed and a 'query' key qith query string
   *   parameters.
   */
  public function getRequestParameters(
    string $request_type,
    string $method,
    string $endpoint = '',
    array $parameters = [],
  ) :array;

  /**
   * Returns the raw body content of the response.
   *
   * This method can be used by extensiosn to alter the body when needed or to
   * handle specific response codes like 202, 204 or 206 for instance.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The HTTP response object.
   * @param string $request_type
   *   The request type. Should be one of 'create', 'read', 'update', 'delete',
   *   'list' or 'count'.
   * @param string $method
   *   HTTP request method to use. Could be one of 'GET', 'POST', 'PUT',
   *   'DELETE', 'PATCH', etc. See also getRequestMethod().
   * @param string $endpoint
   *   Endpoint URL used for the call.
   * @param array $req_parameters
   *   Array of parameters/options used for the request.
   *
   * @return string
   *   The raw body in a string, ready to be parsed.
   */
  public function getResponseBody(
    ResponseInterface $response,
    string $request_type,
    string $method,
    string $endpoint,
    array $req_parameters = [],
  ) :string;

}
