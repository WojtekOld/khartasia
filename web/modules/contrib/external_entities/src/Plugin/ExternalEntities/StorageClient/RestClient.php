<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\StorageClient;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\Token;
use Drupal\Core\State\StateInterface;
use Drupal\external_entities\Entity\ExternalEntityInterface;
use Drupal\external_entities\GuzzleHttp\DebugClientFactory;
use Drupal\external_entities\Plugin\PluginFormTrait;
use Drupal\external_entities\ResponseDecoder\ResponseDecoderFactoryInterface;
use Drupal\external_entities\StorageClient\StorageClientBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Response;
use JsonPath\InvalidJsonException;
use JsonPath\InvalidJsonPathException;
use JsonPath\JsonObject;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Yaml\Yaml;
use Drupal\external_entities\Attribute\StorageClient;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * External entities storage client based on a REST API.
 *
 * @StorageClient(
 *   id = "rest",
 *   label = @Translation("REST"),
 *   description = @Translation("Retrieves external entities from a REST API.")
 * )
 */
#[StorageClient(
  id: 'rest',
  label: new TranslatableMarkup('REST'),
  description: new TranslatableMarkup('Retrieves external entities from a REST API.'),
)]
class RestClient extends StorageClientBase implements RestClientInterface {

  use PluginFormTrait;
  use MessengerTrait;
  use QueryLimitationTrait;

  /**
   * Field prefix used to specify when a field must be repeated.
   */
  const REPEAT_PREFIX = '&';

  /**
   * Waiting time between checks for query limitations (200ms).
   */
  const WAIT_CHECK_QLIMIT = 200000;

  /**
   * The response decoder factory.
   *
   * @var \Drupal\external_entities\ResponseDecoder\ResponseDecoderFactoryInterface
   */
  protected $responseDecoderFactory;

  /**
   * The HTTP client to fetch the files with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Used to keep original HTTP client when running in debug mode.
   *
   * @var ?\GuzzleHttp\ClientInterface
   */
  protected $originalHttpClient;

  /**
   * Data static cache.
   *
   * First level key are endpoints, second level are item numbers.
   *
   * @var array
   */
  protected static $cachedData;

  /**
   * Default maximum number of entities to fetch in queries.
   *
   * @var int|null
   */
  protected $maxEntities;

  /**
   * Drupal state key for dynamic entity count.
   *
   * @var string
   */
  protected $countKey;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The debug client factory.
   *
   * @var \Drupal\external_entities\GuzzleHttp\DebugClientFactory
   */
  protected $debugClientFactory;

  /**
   * Constructs a Rest object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Logger\LoggerInterface $logger
   *   The logger channel factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   * @param \Drupal\Core\Utility\Token $token_service
   *   The token service.
   * @param \Drupal\external_entities\ResponseDecoder\ResponseDecoderFactoryInterface $response_decoder_factory
   *   The response decoder factory service.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   A Guzzle client object.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\external_entities\GuzzleHttp\DebugClientFactory $debug_client_factory
   *   The debug HTTP client factory.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    TranslationInterface $string_translation,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    Token $token_service,
    ResponseDecoderFactoryInterface $response_decoder_factory,
    ClientInterface $http_client,
    EventDispatcherInterface $event_dispatcher,
    StateInterface $state,
    DebugClientFactory $debug_client_factory,
  ) {
    $this->httpClient = $http_client;
    $this->responseDecoderFactory = $response_decoder_factory;
    $this->state = $state;
    $this->debugClientFactory = $debug_client_factory;
    $this->maxEntities = 2 * static::DEFAULT_PAGE_LENGTH;
    parent::__construct($configuration, $plugin_id, $plugin_definition, $string_translation, $logger, $entity_type_manager, $entity_field_manager, $token_service, $event_dispatcher);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) :static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('string_translation'),
      $container->get('logger.factory')->get('external_entities'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('token'),
      $container->get('external_entities.response_decoder_factory'),
      $container->get('http_client'),
      $container->get('event_dispatcher'),
      $container->get('state'),
      $container->get('external_entities.rest.http_client_factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setDebugLevel(int $debug_level = 1) {
    $this->debugLevel = $debug_level;
    if ($debug_level) {
      // Keep regular client.
      $this->originalHttpClient ??= $this->httpClient;
      // Set debug client.
      $this->httpClient = $this->debugClientFactory->fromOptions();
      $this->httpClient->setLogger($this->logger);
      $this->httpClient->setDebugLevel($debug_level);
    }
    elseif (!empty($this->originalHttpClient)) {
      // Restore regular client.
      $this->httpClient = $this->originalHttpClient;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getResponseDecoderFactory() :ResponseDecoderFactoryInterface {
    return $this->responseDecoderFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function setResponseDecoderFactory(ResponseDecoderFactoryInterface $response_decoder_factory) :self {
    $this->responseDecoderFactory = $response_decoder_factory;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() :array {
    return [
      'endpoint' => '',
      'endpoint_options' => [
        'single' => '',
        'count' => '',
        'count_mode' => 'entities',
        'cache' => FALSE,
        'limit_qcount' => 0,
        'limit_qtime' => 0,
        'concurrency' => 5,
        'requests_by_user' => FALSE,
      ],
      'response_format' => 'json',
      'data_path' => [
        'list' => '',
        'single' => '',
        'keyed_by_id' => FALSE,
        'count' => '',
      ],
      'pager' => [
        'default_limit' => 0,
        'type' => 'pagination',
        'page_parameter' => '',
        'page_parameter_type' => 'pagenum',
        'page_start_one' => FALSE,
        'always_query' => FALSE,
        'page_size_parameter' => '',
        'page_size_parameter_type' => 'pagesize',
      ],
      'api_key' => [
        'type' => 'none',
        'header_name' => '',
        'key' => '',
      ],
      'http' => [
        'headers' => '',
      ],
      'parameters' => [
        'list' => '',
        'list_param_mode' => 'query',
        'single' => '',
        'single_param_mode' => 'query',
      ],
      'filtering' => [
        'drupal' => FALSE,
        'basic' => FALSE,
        'basic_fields' => [],
        'list_support' => 'none',
        'list_join' => '',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) :array {
    $sc_id = ($form['#attributes']['id'] ??= uniqid('sc', TRUE));

    // Check if we got endpoint options to expand or not options.
    $got_ep_options =
      !empty($this->configuration['endpoint_options']['cache'])
      || !empty($this->configuration['endpoint_options']['single'])
      || !empty($this->configuration['endpoint_options']['count']);

    // Get available response decoders.
    $formats = $this->responseDecoderFactory->supportedFormats();

    // Get additional parameters.
    $list_parameters = $this->getParametersFormDefaultValue('list');
    $single_parameters = $this->getParametersFormDefaultValue('single');

    $form = NestedArray::mergeDeep(
      $form,
      [
        'endpoint' => [
          '#type' => 'textfield',
          '#title' => $this->t('Endpoint URL'),
          '#maxlength' => 2048,
          '#required' => TRUE,
          '#description' => $this->t('Main endpoint URL used to list all available entities. See "Token Documentation" below.'),
          '#default_value' => $this->configuration['endpoint'] ?? '',
          '#attributes' => [
            'placeholder' => $this->t('ex.: https://www.server.org/service/v1/entities/'),
          ],
        ],
        'endpoint_options' => [
          '#type' => 'details',
          '#title' => $this->t('Endpoint options'),
          '#open' => $got_ep_options,
          // Pseudo-cache.
          'cache' => [
            '#type' => 'checkbox',
            '#title' => $this->t('The main endpoint provides full entities in a list'),
            '#description' => $this->t('If the main endpoint URL used to list entities provides full entities (same fields as the ones provided by the endpoint URL used to load a single entity), checking this box will improve performances (caching listed entities).'),
            '#return_value' => TRUE,
            '#default_value' => $this->configuration['endpoint_options']['cache'] ?? FALSE,
          ],
          // Endpoint to load a single entity.
          'single' => [
            '#type' => 'textfield',
            '#title' => $this->t('Endpoint URL for a single entity'),
            '#maxlength' => 2048,
            '#required' => FALSE,
            '#description' => $this->t('Endpoint URL used to access a single entity if different from the main endpoint given above. This URL should contain a "{id}" placeholder that will be replaced by the entity identifier. If left blank, a single entity\'s URL will be built on the main endpoint URL with the "/entity_id" appended. Note: entity identifiers are URL-encoded. See also "Token Documentation" below.'),
            '#default_value' => $this->configuration['endpoint_options']['single'] ?? '',
            '#attributes' => [
              'placeholder' => $this->t('ex.: https://www.server.org/service/v1/entities/{id}/details'),
            ],
          ],
          // Endpoint to count elements.
          'count' => [
            '#type' => 'textfield',
            '#title' => $this->t('Endpoint URL to get the total number of available entities or pages'),
            '#maxlength' => 2048,
            '#required' => FALSE,
            '#description' => $this->t('Endpoint URL used to return the total number of entities (or pages) that can be fetched through the main endpoint if the later does not return a count. The endpoint should return either a numeric value, an array containing a numeric value, a list containing a number of items equal to the total number of entities or a complex structure that will be processed by a JSONPath leading to the total number of available entities specified below. It is also possible to directly specify a static total number (as integer) of entities instead of a URL. See also "Token Documentation" below.'),
            '#default_value' => $this->configuration['endpoint_options']['count'] ?? '',
            '#attributes' => [
              'placeholder' => $this->t('ex.: "https://www.server.org/service/v1/count/entities" or "25000"'),
            ],
          ],
          // Count mode.
          'count_mode' => [
            '#type' => 'radios',
            '#title' => $this->t('Type of total number of items returned'),
            '#options' => [
              'entities' => $this->t('Number of entities'),
              'pages' => $this->t('Number of pages'),
            ],
            '#default_value' => $this->configuration['endpoint_options']['count_mode'] ?? 'entities',
          ],
          // Dynamic count.
          'dynamic_count' => [
            '#type' => 'checkbox',
            '#title' => $this->t('Use dynamic count based on pagination'),
            '#description' => $this->t('If checked, the total number of entities will be determined dynamically by fetching pages until a page returns fewer items than the page size. This is useful for REST services that do not provide a total count but use next/previous page links. Note: This only works for unfiltered queries.'),
            '#default_value' => $this->configuration['endpoint_options']['dynamic_count'] ?? FALSE,
          ],
          // Handles query limitations.
          'limit_qcount' => [
            '#type' => 'number',
            '#title' => $this->t('Maximum number of query per amount of time given below'),
            '#description' => $this->t('Some endpoints restrict the number of queries for a certain amount of time. While this setting will not guarantee the limitation will be respected (since multiple users could access the endpoint from this site in parallel sessions), it could help limiting abuses. Leave this field empty or set to 0 if there are no known limitations.'),
            '#default_value' => $this->configuration['endpoint_options']['limit_qcount'] ?? 0,
            '#min' => 0,
          ],
          'limit_qtime' => [
            '#type' => 'number',
            '#title' => $this->t('Amount of time given to limit queries (in seconds)'),
            '#description' => $this->t('If your endpoint provides a time value in minutes our even hours, convert it into seconds.'),
            '#default_value' => $this->configuration['endpoint_options']['limit_qtime'] ?? 0,
            '#min' => 0,
            '#field_suffix' => $this->t('seconds'),
          ],
          'concurrency' => [
            '#type' => 'number',
            '#title' => $this->t('Concurrent requests'),
            '#description' => $this->t('Number of HTTP requests to send in parallel when loading multiple entities individually (i.e. when the main endpoint does not provide full entities). Higher values improve performance but may overwhelm the remote API. Set to 1 for sequential requests (original behavior).'),
            '#default_value' => $this->configuration['endpoint_options']['concurrency'] ?? 5,
            '#min' => 1,
            '#max' => 50,
          ],
          // @todo Option planned but not supported yet.
          // This will require a REST server check upon form validation to make
          // sure it allows CORS requests from this Drupal server.
          // Then the requests would rely on ajax: a list of paged requests
          // would be provided to the user browser which will issue them and in
          // return will provide the raw result to this server that will process
          // data and display it. Security concern: we need to tag requests to
          // make sure results provided by the user browser come from a real
          // request originating from this server which has not been fullfilled
          // yet.
          'requests_by_user' => [
            '#type' => 'hidden',
            '#title' => $this->t('Run REST request from user browser'),
            '#description' => $this->t('If number of queries by user is restricted on REST server side and it allows CORS requests from this server, enabling this option would report the restrictions on the final client rather than on this server, preventing everybody from being locked by one abusing user.'),
            '#default_value' => FALSE,
            '#value' => FALSE,
          ],
        ],
        // Response decoder to use.
        'response_format' => [
          '#type' => 'select',
          '#title' => $this->t('Endpoint data encoding format'),
          '#options' => array_combine($formats, $formats),
          '#required' => TRUE,
          '#default_value' => $this->configuration['response_format'] ?? '',
        ],
        // Data path options.
        'data_path' => [
          '#type' => 'details',
          '#title' => $this->t('Data path settings'),
          '#description' => $this->t('Sometimes REST endpoints return their entities with metadata. Entity lists and entities may therefore not be found at the first level of the response. The following settings help to handle thoses cases. They are based on <a href="https://goessner.net/articles/JsonPath/">JSONPath</a> (<a href="https://github.com/Galbar/JsonPath-PHP">PHP implementation</a>) to locate data items.'),
          '#open' => (bool) $this->configuration['data_path']['list'],
          // Data path for list.
          'list' => [
            '#type' => 'textfield',
            '#title' => $this->t('JSONPath leading to the list of entities'),
            '#description' => $this->t('Use this field when entities are not returned in a simple list but rather in a more complex structure. Ex.: if the REST endpoint returns entities wrapped in a structure like <code>{..., "data": [{object1},{object2},...]}</code>, use the JSON path <code>"$.data.*"</code>. If the path may return a scalar or an associative array rather than an array of elements, you can force the result to be an array using the syntax "[$...]" where "$..." is replaced by your JSON path.'),
            '#default_value' => $this->configuration['data_path']['list'] ?? '',
            '#attributes' => [
              'placeholder' => $this->t('ex.: $.data.*'),
            ],
          ],
          // Data path for a single element.
          'single' => [
            '#type' => 'textfield',
            '#title' => $this->t('JSONPath leading to the content of a single entity'),
            '#description' => $this->t('Use this field when entities are not returned directly but rather in a sub-structure. Ex.: if the REST endpoint returns the entity wrapped in a structure like <code>{..., "item": {object1}}</code>, use the JSON path <code>"$.item"</code>.'),
            '#default_value' => $this->configuration['data_path']['single'] ?? '',
            '#attributes' => [
              'placeholder' => $this->t('ex.: $.data.0'),
            ],
          ],
          // Elements keyed by ids.
          'keyed_by_id' => [
            '#type' => 'checkbox',
            '#title' => $this->t('Entities are keyed by their ID in a list'),
            '#description' => $this->t('Check this box if the endpoints returns a list of entities keyed by their identifiers (note: not RESTful compliant, will not work for data editing). If the returned entities do not have an "id" field, an "id" field will be set with the corresponding key value.'),
            '#return_value' => TRUE,
            '#default_value' => $this->configuration['data_path']['keyed_by_id'] ?? FALSE,
          ],
          // How to get the count.
          'count' => [
            '#type' => 'textfield',
            '#title' => $this->t('JSONPath leading to the total number of available entities'),
            '#description' => $this->t('This JSONPath will be used against the result of the count query provided above or against the list endpoint otherwise.'),
            '#default_value' => $this->configuration['data_path']['count'] ?? '',
            '#attributes' => [
              'placeholder' => $this->t('ex.: $.metadata.total'),
            ],
          ],
        ],
        // Paging options.
        'pager' => [
          '#type' => 'details',
          '#title' => $this->t('Pager settings'),
          '#open' => TRUE,
          // Default number of items per page.
          'default_limit' => [
            '#type' => 'number',
            '#title' => $this->t('Default number of items per page used by the endpoint or maximum number of entities supported in a single query'),
            '#description' => $this->t('This number will be used to compute paging on the Drupal side. It has nothing to deal with the number of entities displayed on Drupal entity list pages.'),
            '#default_value' => $this->configuration['pager']['default_limit'] ?? '',
            '#min' => 0,
          ],
          // @todo Support the "previous page/next page/last page" paging type.
          //   This is usually handled by links either in the response metadata
          //   or in the HTTP header ('link: <https://some.url/...>; rel="next",
          //   ...').
          //   Sometimes, the metadata does not contain a full link but rather a
          //   token to use in the next query with some parameter name.
          //   So if we add "Page parameter type" called "previous/next/last
          //   paging":
          //   - we should add a new 'data_path' setting for 'next page data'
          //     that would allow to choose between a JSONPath or an HTTP header
          //     link with the relation name of the link that holds the next
          //     page
          //   - if 'page_parameter' is not set and the token value begins with
          //     http, we should treate the token as the URL to use to get the
          //     next set of results
          //   - we should handle 'page_parameter' if it is set, as the
          //     parameter name to append to the query with the token obtained
          //     from a 'next page data' setting
          //   All those settings would be used in ::getPagingQueryParameters()
          //   to load the apprioriate pages but it will need caching as we
          //   would loose the last token from one Drupal page query to another.
          // Type of paging.
          'type' => [
            '#type' => 'hidden',
            '#title' => $this->t('Paging type'),
            '#options' => [
              'pager' => $this->t('a pager using only previous/next navigation system'),
              'pagination' => $this->t('a pagination with page numbers'),
            ],
            '#default_value' => 'pagination',
            '#value' => 'pagination',
          ],
          // Page parameter name.
          'page_parameter' => [
            '#type' => 'textfield',
            '#title' => $this->t('Page parameter'),
            '#default_value' => $this->configuration['pager']['page_parameter'] ?? '',
          ],
          // Page parameter type.
          'page_parameter_type' => [
            '#type' => 'radios',
            '#title' => $this->t('Page parameter type'),
            '#options' => [
              'pagenum' => $this->t('Page number'),
              'startitem' => $this->t('Starting item'),
            ],
            '#description' => $this->t('Use "Page number" when the pager uses page numbers to determine the item to start at, use "Starting item" when the pager uses the item number to start at.'),
            '#default_value' => $this->configuration['pager']['page_parameter_type'] ?? '',
          ],
          // Page size parameter name.
          'page_size_parameter' => [
            '#type' => 'textfield',
            '#title' => $this->t('Page size parameter'),
            '#default_value' => $this->configuration['pager']['page_size_parameter'] ?? '',
          ],
          // Page size parameter type.
          'page_size_parameter_type' => [
            '#type' => 'radios',
            '#title' => $this->t('Page size parameter type'),
            '#options' => [
              'pagesize' => $this->t('Number of items per page'),
              'enditem' => $this->t('Ending item'),
            ],
            '#description' => $this->t('Use "Number of items per pager" when the pager uses this parameter to determine the amount of items on each page, use "Ending item when the pager uses this parameter to determine the number of the last item on the page.'),
            '#default_value' => $this->configuration['pager']['page_size_parameter_type'] ?? '',
          ],
          // Start from 1 or 0?
          'page_start_one' => [
            '#type' => 'checkbox',
            '#title' => $this->t('Page numbers and entity numbers start from one'),
            '#description' => $this->t('Check this box if the first page number or entity number is 1, uncheck it if they start from 0.'),
            '#return_value' => TRUE,
            '#default_value' => $this->configuration['pager']['page_start_one'] ?? FALSE,
          ],
          // Set in query.
          'always_query' => [
            '#type' => 'checkbox',
            '#title' => $this->t('Page parameters are provided in query string'),
            '#description' => $this->t('If not checked, pager parameters will be provided according to the HTTP method used for the call (ie. in body for POST).'),
            '#return_value' => TRUE,
            '#default_value' => $this->configuration['pager']['always_query'] ?? FALSE,
          ],
        ],
        // API authentication.
        'api_key' => [
          '#type' => 'details',
          '#title' => $this->t('Authentication'),
          '#open' => (!empty($this->configuration['api_key']['type'])
              && ('none' != $this->configuration['api_key']['type'])),
          // Authentication type.
          'type' => [
            '#type' => 'radios',
            '#title' => $this->t('Authentication type'),
            '#default_value' => $this->configuration['api_key']['type'] ?? 'none',
            '#options' => [
              'none'   => $this->t('No authentication needed'),
              'bearer' => $this->t('Bearer authentication (also called token authentication, often used by OAuth)'),
              'custom' => $this->t('Custom header'),
              'query' => $this->t('Using a URL query parameter'),
              // @todo Add support for authentication plugins and add their
              // options:
              // "'plugin_id' =>
              // $this->t('Using authentication <plugin_name>'),".
            ],
            '#attributes' => [
              'data-xnttrest-selector' => 'atype' . $sc_id,
            ],
          ],
          // Header.
          'header_name' => [
            '#type' => 'textfield',
            '#title' => $this->t('Header name/key name'),
            '#description' => $this->t('The HTTP header name or the URL query parameter name for the API key. See also "Token Documentation" below.'),
            '#default_value' => $this->configuration['api_key']['header_name'] ?? '',
            '#attributes' => [
              'placeholder' => $this->t('ex.: "WWW-Authenticate" or "apikey"'),
            ],
            '#states' => [
              'visible' => [
                'input[data-xnttrest-selector="atype' . $sc_id . '"]' => [
                  ['value' => 'custom'],
                  'or',
                  ['value' => 'query'],
                ],
              ],
            ],
          ],
          // Key.
          'key' => [
            '#type' => 'textfield',
            '#title' => $this->t('API key'),
            '#description' => $this->t('The API key needed to communicate with the entered endpoint. See also "Token Documentation" below.'),
            '#default_value' => $this->configuration['api_key']['key'] ?? '',
            '#states' => [
              'invisible' => [
                'input[data-xnttrest-selector="atype' . $sc_id . '"]' => ['value' => 'none'],
              ],
            ],
          ],
        ],
        // HTTP protocol settings.
        'http' => [
          '#type' => 'details',
          '#title' => $this->t('HTTP protocol'),
          '#open' => FALSE,
          // Headers.
          'headers' => [
            '#type' => 'textarea',
            '#title' => $this->t('Additional HTTP headers'),
            '#description' => $this->t('You may specify hear additional HTTP headers to include in the queries. See also "Token Documentation" below.'),
            '#default_value' => $this->configuration['http']['headers'] ?? '',
            '#attributes' => [
              'placeholder' => $this->t("ex.:\nContent-Type: application/json\nAccept: application/json"),
            ],
          ],
        ],
        // Parameters.
        'parameters' => [
          '#type' => 'details',
          '#title' => $this->t('Additional parameters'),
          '#description' => $this->t('You may specify here additional URL query or to POST body parameters that will be used when listing entities or fetching a single entity. When using body (POST) method, it is possible to specify structured values using the "json:" prefix followed by a single line stringified JSON strucure. To force plain text, use the "plain:" prefix instead. In the case of a single entity query, the placeholder "{id}" will be replaced by the entity identifier in values. Please note that you can also specify a "query" API key using the authentication section above (prefered way) which will automatically add the key to all types of URL queries. For both query types, Drupal tokens will be replaced. See "Token Documentation" below.'),
          '#open' => (!empty($list_parameters) || !empty($single_parameters)),
          // List parameters.
          'list' => [
            '#type' => 'textarea',
            '#title' => $this->t('List parameters'),
            '#description' => $this->t('Enter the parameters to add to the endpoint URL or to POST body data when loading the list of entities. One per line in the format "parameter_name=parameter_value".'),
            '#default_value' => $list_parameters,
          ],
          // List parameters mode.
          'list_param_mode' => [
            '#type' => 'radios',
            '#title' => $this->t('Add list parameters to'),
            '#default_value' => $this->configuration['parameters']['list_param_mode'] ?? 'query',
            '#options' => [
              'query' => $this->t('query string (default)'),
              'body' => $this->t('body (using selected endpoint response format)'),
              'body_query' => $this->t('body for list and query string for count'),
            ],
            '#description' => $this->t('Sometimes, additional parameters need to be sent in the request body when searching/filtering entities. When set to "body", list and count queries will use "POST" HTTP method instead of "GET". It is possible to mix both by specifying query parameters in the endpoint URL and specify body parameters here.'),
            '#attributes' => [
              'data-xnttrest-selector' => 'lpmode' . $sc_id,
            ],
          ],
          // Single element parameters.
          'single' => [
            '#type' => 'textarea',
            '#title' => $this->t('Single entity parameters'),
            '#description' => $this->t('Enter the parameters to add to the endpoint URL when loading a single entity. One per line in the format "parameter_name=parameter_value". Placeholder "{id}" in parameter values will be replaced by the entity identifier.'),
            '#default_value' => $single_parameters,
          ],
          // Single element parameters mode.
          'single_param_mode' => [
            '#type' => 'radios',
            '#title' => $this->t('Add single entity parameters to'),
            '#default_value' => $this->configuration['parameters']['single_param_mode'] ?? 'query',
            '#options' => [
              'query' => $this->t('query string (default)'),
              'body' => $this->t('body (using selected endpoint response format)'),
            ],
            '#description' => $this->t('Sometimes, additional parameters need to be sent in the request body when saving an entity (POST, PUT). It is possible to mix both by specifying query parameters in the endpoint URL and specify body parameters here.'),
            '#attributes' => [
              'data-xnttrest-selector' => 'spmode' . $sc_id,
            ],
          ],
        ],
        // Filtering options.
        'filtering' => [
          '#type' => 'details',
          '#title' => $this->t('Filtering'),
          '#description' => $this->t('This section handles source filtering configuration.'),
          '#open' => FALSE,
          // Drupal-side filtering.
          'drupal' => [
            '#type' => 'checkbox',
            '#title' => $this->t('Enable Drupal-side filtering'),
            '#description' => $this->t('Check this box if you want to allow Drupal to handle filters not supported by the remote source. Note: if not checked, use of unsupported filters will lead to empty result sets. WARNING: it often means that Drupal will fetch all external entities from the REST service wich may lead to timeouts. Use with caution.'),
            '#return_value' => TRUE,
            '#default_value' => $this->configuration['filtering']['drupal'] ?? FALSE,
          ],
          // Basic service-side filtering support.
          'basic' => [
            '#type' => 'checkbox',
            '#title' => $this->t('Basic filtering'),
            '#description' => $this->t('Check this box if the endpoint can filter entities using mapped source field names with a value to test (with an equal operator). For instance, you mapped Drupal field "field_foo" to source raw data field "foo" and the endpoint can filter entities having a "foo" value of 42 using the GET query string "?foo=42" or POST equivalent.'),
            '#return_value' => TRUE,
            '#default_value' => $this->configuration['filtering']['basic'] ?? FALSE,
            '#attributes' => [
              'data-xnttrest-selector' => 'ftbas' . $sc_id,
            ],
          ],
          // Field supported by the basic filtering.
          'basic_fields' => [
            '#type' => 'textfield',
            '#title' => $this->t('List of supported fields'),
            '#description' => $this->t('Enter a coma-sparated list of fields supported by basic filtering or leave empty if all fields are supposed to be supported.'),
            '#default_value' => $this->configuration['filtering']['basic_fields'] ?? '',
            '#states' => [
              'enabled' => [
                'input[data-xnttrest-selector="ftbas' . $sc_id . '"]' => ['checked' => TRUE],
              ],
            ],
          ],
          // List support.
          'list_support' => [
            '#type' => 'radios',
            '#title' => $this->t('List support'),
            '#description' => $this->t('Define the supported behavior when a given field can be tested against a list of values.'),
            '#options' => [
              'none' => 'Not supported',
              'repeat' => 'Repeat parameter for each value (ie. "param=...&param=...") (GET)',
              'indexed' => 'Repeat and append "[index]" to the parameter name for each value (ie. "param[0]=...&param[1]=...") (GET)',
              'unindexed' => 'Repeat and append "[]" to the parameter name for each value (ie. "param[]=...&param[]=...") (GET)',
              'implode' => 'Join elements using a join string (GET)',
              'post' => 'Provide the array of values (POST)',
            ],
            '#default_value' => $this->configuration['filtering']['list_support'] ?? 'none',
            '#attributes' => [
              'data-xnttrest-selector' => 'lssup' . $sc_id,
            ],
            '#states' => [
              'enabled' => [
                'input[data-xnttrest-selector="ftbas' . $sc_id . '"]' => ['checked' => TRUE],
              ],
            ],
          ],
          // How to joing list of elements.
          'list_join' => [
            '#type' => 'textfield',
            '#title' => $this->t('Join string for lists'),
            '#default_value' => $this->configuration['filtering']['list_join'] ?? ',',
            '#states' => [
              'visible' => [
                'input[data-xnttrest-selector="ftbas' . $sc_id . '"]' => ['checked' => TRUE],
                0 => 'and',
                'input[data-xnttrest-selector="lssup' . $sc_id . '"]' => ['value' => 'implode'],
              ],
            ],
          ],
        ],
      ]
    );

    // Remove 'Bearer ' part automatically added by form submition for 'bearer'
    // mode.
    if (!empty($this->configuration['api_key']['type'])
        && ('bearer' == $this->configuration['api_key']['type'])
    ) {
      $form['api_key']['key']['#default_value'] = str_replace(
        'Bearer ',
        '',
        $form['api_key']['key']['#default_value']
      );
    }

    $this->buildTokenInfo($form);

    return $form;
  }

  /**
   * Helper function to convert a parameter collection to a string.
   *
   * @param string $type
   *   The type of parameters (eg. 'list' or 'single').
   *
   * @return string
   *   A string to be used as default value, an empty string if no parameters.
   */
  protected function getParametersFormDefaultValue(string $type) :string {
    $default_value = '';

    if (!empty($this->configuration['parameters'][$type])) {
      $lines = [];
      foreach ($this->configuration['parameters'][$type] as $key => $value) {
        if (isset($value) && ('' != $value)) {
          $lines[] = "$key=$value";
        }
        else {
          $lines[] = "$key";
        }
      }
      $default_value = implode("\n", $lines);
    }

    return $default_value;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // We need to get parameters value to check single endpoint requirements.
    $parameters = $form_state->getValue('parameters') ?? [];

    $endpoint = $form_state->getValue('endpoint');
    $ep_options = $form_state->getValue('endpoint_options') ?? [];
    // Check {id} placeholder.
    if (!empty($ep_options['single'])
        && !preg_match('~\{id\}~', $ep_options['single'])
        && (empty($parameters['single'])
          || !preg_match('~\{id\}~', $parameters['single']))
    ) {
      // Warn the single enpoint URL may not be correct because of missing {id}
      // placeholder both in URL and parameters.
      $this->messenger()->addWarning(
        'The endpoint for a single entity does not contain a "{id}" placeholder ('
        . $ep_options['single']
        . ').'
      );
    }

    // Check for parameters set at the wrong place.
    [$url, $query_string] = explode('?', $endpoint, 2) + [NULL, NULL];
    if (!empty($query_string)) {
      // Remove query string from endpoint URL and put parameters at the
      // correct place.
      $endpoint = $url;
      $form_state->setValue('endpoint', $endpoint);
      // Parse query string into parameters.
      $value = $parameters['list'] ?? '';
      if (is_string($value)) {
        $parameters['list'] =
          $value
          . "\n"
          . str_replace('&', "\n", $query_string);
      }
      elseif (is_array($value)) {
        $new_parameters = [];
        foreach (explode('&', $query_string) as $param_line) {
          [$key, $value] = explode('=', $param_line, 2);
          $new_parameters[$key] = $value ?? '';
        }
        $parameters['list'] = array_merge($value, $new_parameters);
      }
    }
    if (!empty($ep_options['single'])) {
      [$url, $query_string] =
        explode('?', $ep_options['single'], 2)
        + [NULL, NULL];
      if (!empty($query_string)) {
        // Remove query string from endpoint URL and put parameters at the
        // correct place.
        $ep_options['single'] = $url;
        // Parse query string into parameters.
        $value = $parameters['single'] ?? '';
        if (is_string($value)) {
          $parameters['single'] =
            $value
            . "\n"
            . str_replace('&', "\n", $query_string);
        }
        elseif (is_array($value)) {
          $new_parameters = [];
          foreach (explode('&', $query_string) as $param_line) {
            [$query_key, $query_value] = explode('=', $param_line, 2);
            $new_parameters[$query_key] = $query_value ?? '';
          }
          $parameters['single'] = array_merge($value, $new_parameters);
        }
      }
    }

    if (!is_numeric($ep_options['limit_qcount'])) {
      $ep_options['limit_qcount'] = 0;
    }

    if (!is_numeric($ep_options['limit_qtime'])) {
      $ep_options['limit_qtime'] = 0;
    }

    if (!is_numeric($ep_options['concurrency'] ?? 0) || ($ep_options['concurrency'] ?? 0) < 1) {
      $ep_options['concurrency'] = 5;
    }

    // Rearrange parameters.
    foreach (['list', 'single'] as $type) {
      $value = $parameters[$type] ?? '';
      // Make sure the parameters have not been already processed by a child
      // module.
      if (is_string($value)) {
        $separator = '|';
        $pipe_pos = strpos($value, '|');
        $equal_pos = strpos($value, '=');
        if ((FALSE === $pipe_pos)
          || ((FALSE !== $equal_pos) && ($equal_pos < $pipe_pos))
        ) {
          $separator = '=';
        }
        $lines = explode("\n", $value);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines, 'strlen');
        $parameters[$type] = [];
        foreach ($lines as $line) {
          $exploded = explode($separator, $line, 2);
          if ('' != $exploded[0]) {
            $parameters[$type][$exploded[0]] = $exploded[1] ?? '';
          }
        }
      }
    }

    $api_key = $form_state->getValue('api_key');

    // Bearer: prefix key if needed and set header name.
    if (!empty($api_key['type'])
        && ('bearer' == $api_key['type'])
        && !empty($api_key['key'])
        && (@substr_compare($api_key['key'], 'Bearer', 0, 6) !== 0)
    ) {
      $api_key['key'] = 'Bearer ' . $api_key['key'];
      $api_key['header_name'] = 'Authorization';
    }

    // Check authentication settings.
    if (!empty($api_key['type'])
        && ('none' != $api_key['type'])
    ) {
      if (empty($api_key['key']) || empty($api_key['header_name'])) {
        // Warn for incorrect setting.
        $this->messenger()->addWarning(
          'Authentication mode set but no authentication key properly set. Authentication disabled.'
        );
        $api_key['type'] = 'none';
      }
      // Check for secure protocol.
      if (!preg_match('#^https://#i', $endpoint)
        || (!empty($ep_options['single'])
            && !preg_match('#^https://#i', $ep_options['single']))
        || (!empty($ep_options['count'])
            && !preg_match('#^https://#i', $ep_options['count']))
      ) {
        // Warn for security issues.
        $this->messenger()->addWarning(
          'Authentication mode set but endpoint URL(s) are not using secure protocol (https). The API key may be intercepted by others. Consider switching to https unless the key is not secret.'
        );
      }
    }
    // Save endpoint options settings.
    $form_state->setValue('endpoint_options', $ep_options);

    // Save authentication settings.
    $form_state->setValue('api_key', $api_key);

    // Save parameters rearrangement.
    $form_state->setValue('parameters', $parameters);

    // Adjust filtering parameters.
    $basic_fields =
      $form_state->getValue(['filtering', 'basic_fields'], '')
      ?: [];
    if (!empty($basic_fields) && is_string($basic_fields)) {
      $basic_fields = array_map('trim', explode(',', $basic_fields));
    }
    $form_state->setValue(
      ['filtering', 'basic_fields'],
      $basic_fields
    );

    // Clear dynamic count.
    $this->deleteDynamicCount();

    $this->setConfiguration($form_state->getValues());
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(?array $ids = NULL) :array {
    $data = [];

    if (!isset($ids)) {
      // Try to load all remote entries but we should not risk to load all and
      // get a memory crash so we limit at $this->maxEntities.
      $count = $this->getDefaultMaxEntitiesToFetch();
      $query_results = $this->query([], [], 0, $count);
      $field_mapper = $this->externalEntityType->getFieldMapper('id');
      $ids = [];
      foreach ($query_results as $query_result) {
        $id = $field_mapper->extractFieldValuesFromRawData((array) $query_result)[0]['value'] ?? NULL;
        if (!empty($id)) {
          $ids[] = $id;
        }
      }
    }

    if (!empty($ids)) {
      // Check if entities could be loaded all at once.
      if (!empty($this->configuration['endpoint_options']['cache'])
          && (1 < count($ids))
      ) {
        // Cache option set, self::querySource() can load all entities at once.
        $conditions = [
          [
            'field' => 'id',
            'value' => $ids,
            'operator' => 'IN',
          ],
        ];
        // Make sure we can filter on a list of ids.
        $trans_conditions = $this->transliterateDrupalFilters($conditions, ['caller' => 'loadMultiple']);
        if (empty($trans_conditions['drupal'])
            && !empty($trans_conditions['source'])
        ) {
          $all_entities = $this->querySource($trans_conditions['source']);
          // Remap by id.
          foreach ($all_entities as $entity) {
            $pr_id = $this->getProcessedId($entity);
            $data[$pr_id] = $entity;
          }
        }
      }
      // If no data loaded yet, load entities individually. Uses concurrent
      // HTTP requests when concurrency > 1 for better performance.
      if (empty($data)) {
        $concurrency = (int) ($this->configuration['endpoint_options']['concurrency'] ?? 5);
        if ($concurrency > 1) {
          $data = $this->loadMultipleConcurrent($ids, $concurrency);
        }
        else {
          foreach ($ids as $id) {
            $endpoint_data = $this->load($id);
            if (!isset($endpoint_data)) {
              continue;
            }
            $data[$id] = $endpoint_data;
          }
        }
      }
    }

    return $data;
  }

  /**
   * Loads one entity.
   *
   * @param mixed $id
   *   The ID of the entity to load.
   *
   * @return array|null
   *   A raw data array, empty array if no data returned.
   */
  public function load(string|int $id) :array|null {
    // Discard empty id (nb. but '0' could be valid id).
    if ('' == $id) {
      return NULL;
    }
    // Check static cache.
    if (!empty(static::$cachedData[$this->configuration['endpoint']][$id])) {
      return static::$cachedData[$this->configuration['endpoint']][$id];
    }
    $parameters = ['id' => $id];
    $endpoint = $this->getEndpointUrl('read', $parameters);
    $method = $this->getRequestMethod('read', $endpoint, $parameters);
    $req_parameters = $this->getRequestParameters(
      'read',
      $method,
      $endpoint,
      $parameters
    );
    try {
      $this->ensureQueryLimits();
      $response = $this->httpClient->request(
        $method,
        $endpoint,
        $req_parameters
      );
    }
    catch (GuzzleException $exception) {
      $this->logger->error('Error in Rest::load. Message: ' . $exception->getMessage());
      return NULL;
    }

    $result = $this->getEntityArrayFromResponse(
      $response,
      $method,
      $endpoint,
      $req_parameters,
      $id
    );

    // Update cache.
    if (isset($id)
        && !empty($result)
        && !empty($this->configuration['endpoint_options']['cache'])
    ) {
      static::$cachedData[$this->configuration['endpoint']][$id] = $result;
    }

    return $result;
  }

  /**
   * Loads multiple entities using concurrent HTTP requests.
   *
   * Uses Guzzle Pool to send requests in parallel, improving performance
   * compared to sequential loading when loading entities one by one.
   *
   * @param array $ids
   *   An array of entity IDs to load.
   * @param int $concurrency
   *   Maximum number of concurrent requests.
   *
   * @return array
   *   An associative array of raw entity data keyed by ID.
   */
  protected function loadMultipleConcurrent(array $ids, int $concurrency): array {
    $data = [];
    $uncached_ids = [];

    // Check static cache first.
    foreach ($ids as $id) {
      if ('' == $id) {
        continue;
      }
      if (!empty(static::$cachedData[$this->configuration['endpoint']][$id])) {
        $data[$id] = static::$cachedData[$this->configuration['endpoint']][$id];
      }
      else {
        $uncached_ids[] = $id;
      }
    }

    if (empty($uncached_ids)) {
      return $data;
    }

    // Pre-compute request parameters for each uncached ID.
    $request_map = [];
    foreach ($uncached_ids as $id) {
      $parameters = ['id' => $id];
      $endpoint = $this->getEndpointUrl('read', $parameters);
      $method = $this->getRequestMethod('read', $endpoint, $parameters);
      $req_parameters = $this->getRequestParameters(
        'read',
        $method,
        $endpoint,
        $parameters
      );
      $request_map[$id] = [
        'method' => $method,
        'endpoint' => $endpoint,
        'parameters' => $req_parameters,
      ];
    }

    // Build a generator of closures for Guzzle Pool.
    // Each closure calls ensureQueryLimits() before returning an async promise,
    // so rate limiting is respected even with concurrent requests.
    $responses = [];
    $requests = function () use ($request_map) {
      foreach ($request_map as $id => $req) {
        yield $id => function () use ($req) {
          $this->ensureQueryLimits();
          return $this->httpClient->requestAsync(
            $req['method'],
            $req['endpoint'],
            $req['parameters']
          );
        };
      }
    };

    $pool = new Pool($this->httpClient, $requests(), [
      'concurrency' => $concurrency,
      'fulfilled' => function (ResponseInterface $response, $id) use (&$responses) {
        $responses[$id] = $response;
      },
      'rejected' => function ($reason, $id) {
        $message = $reason instanceof \Throwable ? $reason->getMessage() : (string) $reason;
        $this->logger->error('Error loading external entity @id: @message', [
          '@id' => $id,
          '@message' => $message,
        ]);
      },
    ]);

    $pool->promise()->wait();

    // Process responses into entity data.
    foreach ($responses as $id => $response) {
      $req = $request_map[$id];
      $result = $this->getEntityArrayFromResponse(
        $response,
        $req['method'],
        $req['endpoint'],
        $req['parameters'],
        $id
      );

      if (isset($result)) {
        $data[$id] = $result;
        // Update static cache.
        if (!empty($this->configuration['endpoint_options']['cache'])) {
          static::$cachedData[$this->configuration['endpoint']][$id] = $result;
        }
      }
    }

    return $data;
  }

  /**
   * Returns an entity raw structure from an HTTP response.
   *
   * @param \GuzzleHttp\Psr7\Response $response
   *   A request response.
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
   * @param mixed $id
   *   The ID of the entity to load.
   *
   * @return array|null
   *   A raw data array, empty array if no data returned.
   */
  protected function getEntityArrayFromResponse(
    Response $response,
    string $method,
    string $endpoint,
    array $parameters,
    string|int|null $id = NULL,
  ) :array|null {
    $body = $this->getResponseBody(
      $response,
      'read',
      $method,
      $endpoint,
      $parameters
    );

    $result = NULL;
    try {
      $result = $this
        ->getResponseDecoderFactory()
        ->getDecoder($this->configuration['response_format'])
        ->decode($body);
    }
    catch (InvalidDataTypeException $exception) {
      $this->logger->error('Error in decoding result. Message: ' . $exception->getMessage());
    }

    // Check if we must use a JSON Path to get data.
    if (!empty($this->configuration['data_path']['single'])) {
      try {
        $json_object = new JsonObject($result, TRUE);
        $result = $json_object->get($this->configuration['data_path']['single']);
        // JsonObject v3 returns empty arrays when no match.
        // However, since we are using "smart get" we could get a FALSE.
        if ((FALSE === $result)
          || (is_array($result) && empty($result))
        ) {
          $result = NULL;
        }
      }
      catch (InvalidJsonException | InvalidJsonPathException $exception) {
        $this->logger->error(
          'Failed to parse JSON Path for single object ('
          . $this->configuration['data_path']['single']
          . '): '
          . $exception
        );
      }
    }

    // Check if entities are keyed by their ids.
    if (!empty($this->configuration['data_path']['keyed_by_id'])) {
      // If no id was provided (new entity), get the id.
      if (!isset($id) || ('' === $id)) {
        reset($result);
        $id = key($result);
      }
      if (!empty($result[$id])
          || ('0' === $result[$id])
          || (0 === $result[$id])
          || (0. === $result[$id])
      ) {
        $result = $result[$id];
        // Here we save the identifier in the mapped 'id' field in the raw
        // data if it is not there, to make it available in the raw structure.
        $id_field = $this->getSourceIdFieldName() ?? 'id';
        $result[$id_field] ??= $id;
      }
      else {
        $result = NULL;
      }
    }
    elseif (isset($id) && ('' !== $id) && !empty($result)) {
      $id_field = $this->getSourceIdFieldName() ?? 'id';
      if (!isset($result[$id_field])
          || ('' === $result[$id_field])
      ) {
        // Here we save the identifier in the mapped 'id' field in the raw
        // data if it is not there, to make it available in the raw structure.
        $result[$id_field] = $id;
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function save(ExternalEntityInterface $entity) :int {
    $result = 0;
    // @todo Manage when an entity is new but has an ID. We should get a 404 and
    // fallback to a new entity creation. If, meanwhile, the entity has been
    // created (race condition), we should fallback into an update again. If it
    // fails again, we stop here.
    try {
      $id = $entity->id();
      // Check for a provided ID, including '0'.
      if (!empty($id) || strlen($id ?? '')) {
        // Got an ID, check if the entity exists.
        $parameters = [
          'id' => $id,
          'data' => $entity->toRawData(),
          'original' => $entity->getOriginalRawData(),
        ];
        $endpoint = $this->getEndpointUrl('update', $parameters);
        $method = $this->getRequestMethod('update', $endpoint, $parameters);
        $req_parameters = $this->getRequestParameters(
          'update',
          $method,
          $endpoint,
          $parameters
        );
        $this->ensureQueryLimits();
        $response = $this->httpClient->request(
          $method,
          $endpoint,
          $req_parameters
        );
        $result = SAVED_UPDATED;
      }
      else {
        // No ID, new entity.
        $parameters = [
          'data' => $entity->toRawData(),
        ];
        $endpoint = $this->getEndpointUrl('create', $parameters);
        $method = $this->getRequestMethod('create', $endpoint, $parameters);
        $req_parameters = $this->getRequestParameters(
          'create',
          $method,
          $endpoint,
          $parameters
        );
        $this->ensureQueryLimits();
        $response = $this->httpClient->request(
          $method,
          $endpoint,
          $req_parameters
        );
        $result = SAVED_NEW;
      }
    }
    catch (GuzzleException $exception) {
      $this->logger->error(
        'Error in Rest::save. Message: '
        . $exception->getMessage()
      );
      $result = 0;
    }

    // Get returned entity data and update entity.
    if ($result && !empty($response)) {
      $entity_data = $this->getEntityArrayFromResponse(
        $response,
        $method,
        $endpoint,
        $req_parameters,
        $id
      );
      if (!empty($entity_data)) {
        $source_id_field = $this->getSourceIdFieldName();
        if (empty($source_id_field)) {
          // Not available, need to map.
          if (!empty($this->externalEntityType)
              && (!empty($id_field_mapper = $this->externalEntityType->getFieldMapper('id')))
          ) {
            $id = $id_field_mapper
              ->extractFieldValuesFromRawData($entity_data)['id'][0]['value'];
          }
        }
        else {
          $id = $entity_data[$source_id_field];
        }
        // Set id. Allow 0 as a valid id for remote data.
        if (!empty($id) || ('0' === $id) || (0 === $id) || (0. === $id)) {
          $entity->set('id', $id);
        }
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function delete(ExternalEntityInterface $entity) {
    $parameters = ['id' => $entity->id()];
    $endpoint = $this->getEndpointUrl('delete', $parameters);
    $method = $this->getRequestMethod('delete', $endpoint, $parameters);
    try {
      $req_parameters = $this->getRequestParameters(
        'delete',
        $method,
        $endpoint,
        $parameters
      );
      $this->ensureQueryLimits();
      $this->httpClient->request(
        $method,
        $endpoint,
        $req_parameters
      );
    }
    catch (GuzzleException $exception) {
      $this->logger->error(
        'Error in Rest::delete. Message: '
        . $exception->getMessage()
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function querySource(
    array $parameters = [],
    array $sorts = [],
    ?int $start = NULL,
    ?int $length = NULL,
  ) :array {
    $all_results = [];
    $filter_parameters = [
      'filters' => $parameters,
      'sorts' => $sorts,
      'start' => $start,
      'length' => $length,
    ];
    if (isset($start) && !empty($length)) {
      $filter_parameters['page'] =
        empty($this->configuration['pager']['page_start_one'])
        ? (int) floor($start / $length) + 0
        : (int) floor($start / $length) + 1;
    }
    else {
      $filter_parameters['page'] =
        empty($this->configuration['pager']['page_start_one']) ? 0 : 1;
    }
    // @todo Maybe use a batch process as it can take time for many pages.
    // Is it only possible here? How?
    if (2 <= $this->getDebugLevel()) {
      $this->logger->debug(
        "RestClient::querySource():\nparameters:\n@parameters\nSorts:\n@sorts\nStart: @start\nLenght: @length",
        [
          '@parameters' => print_r($parameters, TRUE),
          '@sorts' => print_r($sorts, TRUE),
          '@start' => $start ?? 0,
          '@length' => $length ?? 'n/a',
        ]
      );
    }

    foreach ($this->getPagingQueryParameters($start, $length, $parameters) as $i => $paging) {
      $filter_parameters['paging'] = $paging;
      $endpoint = $this->getEndpointUrl('list', $filter_parameters);
      $method = $this->getRequestMethod('list', $endpoint, $filter_parameters);
      if (2 <= $this->getDebugLevel()) {
        $this->logger->debug(
          "RestClient::querySource() page @page:\n@paging",
          [
            '@page' => $i,
            '@paging' => print_r($paging, TRUE),
          ]
        );
      }

      try {
        $req_parameters = $this->getRequestParameters(
          'list',
          $method,
          $endpoint,
          [
            'filters' => $parameters,
            'paging' => $paging['parameters'],
          ]
        );
        // Manage delays between queries.
        $this->ensureQueryLimits();
        $response = $this->httpClient->request(
          $method,
          $endpoint,
          $req_parameters
        );
      }
      catch (GuzzleException $exception) {
        $this->logger->error(
          'Error in Rest::query. Message: '
          . "method: '$method', endpoint: '$endpoint', req_parameters: " . print_r($req_parameters, TRUE) . "\n"

          . $exception->getMessage()
        );
        return [];
      }

      $body = $this->getResponseBody(
        $response,
        'list',
        $method,
        $endpoint,
        $req_parameters
      );

      try {
        $results = $this
          ->getResponseDecoderFactory()
          ->getDecoder($this->configuration['response_format'])
          ->decode($body);
      }
      catch (InvalidDataTypeException $exception) {
        $this->logger->error(
          'Error in decoding result. Message: '
          . $exception->getMessage()
        );
        return [];
      }

      // So far, do not force array.
      $to_array = FALSE;
      // Check if we must use a JSON Path to get data.
      if (!empty($this->configuration['data_path']['list'])) {
        $data_path = $this->configuration['data_path']['list'];
        if (preg_match('/^\[.*\]$/', $this->configuration['data_path']['list'])) {
          // Force array if result is a single object.
          $data_path = substr($data_path, 1, -1);
          $to_array = TRUE;
        }
        try {
          $json_object = new JsonObject($results, TRUE);
          $results = $json_object->get($data_path);
          if (FALSE === $results) {
            $results = [];
          }
          elseif ($to_array
              && !array_key_exists(0, $all_results)
              && !array_key_exists(1, $all_results)
          ) {
            // Turn into array if not an arry of results.
            $results = [$results];
          }
        }
        catch (InvalidJsonException | InvalidJsonPathException $e) {
          $this->logger->error(
            'Failed to parse JSON Path for listing ('
            . $this->configuration['data_path']['list']
            . '): '
            . $e
          );
        }
      }

      $extract = array_slice($results, $paging['start'], $paging['length']);
      if (2 <= $this->getDebugLevel()) {
        $this->logger->debug(
          "RestClient::querySource()\nCurrent entities: @entities\nLoaded: @results\nAdded: @added",
          [
            '@entities' => count($all_results),
            '@results' => count($results),
            '@added' => count($extract),
          ]
        );
      }
      $all_results = array_merge($all_results, $extract);
    }

    // Update dynamic count if needed.
    if (!empty($this->configuration['endpoint_options']['dynamic_count'])
      && empty($parameters)
    ) {
      // We try to get current count according to current result set. If it is
      // empty, it means we got beyond the maximum number of elements. Then,
      // either current guessed count is above and we can reduce it, or it is
      // below and we keep it as is. If the result set is not empty, we
      // compute how much element there should be with current result set. Then,
      // either we have more elements than current guessed count and we can
      // update it, or it is lower and we keep the count as is.
      $page_length = $length ?? static::DEFAULT_PAGE_LENGTH;
      $current_total = $this->getDynamicCount() ?? 0;
      $actual_total = ($start ?? 0) + count($all_results);
      // We check if the query returned no result, meaning we reached the end.
      // We could also check if we got less result than expected (page length)
      // but some API like Drupal JSON API sservice at
      // https://www.drupal.org/jsonapi/ do not guarantee to return the exact
      // number of requested element, even if such element should be available.
      if (0 < count($results)) {
        // We still have elements.
        if ($actual_total >= $current_total) {
          $this->setDynamicCount(($start ?? 0) + $page_length + 1);
        }
      }
      else {
        // We have no more elements.
        if ($current_total > $actual_total) {
          $this->setDynamicCount($actual_total);
        }
      }
    }

    // Check if entities are keyed by their ids.
    if (!empty($this->configuration['data_path']['keyed_by_id'])) {
      $remapped_results = [];
      $invalid_array = FALSE;
      foreach ($all_results as $id => $raw_data) {
        if (is_array($raw_data)) {
          $raw_data['id'] ??= $id;
          $remapped_results[] = $raw_data;
        }
        else {
          $invalid_array = TRUE;
        }
      }
      if ($invalid_array && (2 <= $this->getDebugLevel())) {
        $this->logger->debug(
          "RestClient::querySource()\nReturned data is not a set of arrays keyed by their id:\n@data",
          [
            '@data' => print_r($all_results, TRUE),
          ]
        );
      }
      $all_results = $remapped_results;
    }

    // Check if results are just ids.
    if (!empty($all_results[0])
        && (is_string($all_results[0]) || is_numeric($all_results[0]))
    ) {
      $source_id_field = $this->getSourceIdFieldName() ?? 'id';
      $all_results = array_map(
        function ($item) use ($source_id_field) {
          // Get the source identifier field name.
          return [
            $source_id_field => $item,
          ];
        },
        $all_results
      );
    }

    if (!empty($all_results) && !empty($this->configuration['endpoint_options']['cache'])) {
      $field_mapper = $this->externalEntityType->getFieldMapper('id');
      static::$cachedData[$this->configuration['endpoint']] ??= [];
      foreach ($all_results as $raw_data) {
        if (!empty($raw_data)) {
          $id = $field_mapper->extractFieldValuesFromRawData((array) $raw_data)[0]['value'] ?? NULL;
          if (isset($id)) {
            // @todo A problem with this static cache is that it is based on the
            // endpoint URI and not other parameters. If, for instance, we have
            // 2 different storage clients, with the same endpoint URI, but one
            // has an extra parameter to load a different language from the
            // other one, only the first language instance will be loaded for
            // both clients. It could ne the same problem if an HTTP header
            // setting can alter the result for a given id.
            // The endpoint is not enough to use as key: maybe a
            // hash of URL parameters nd HTTP headers should be added?
            // @todo Here, we store external data from the given endpoint into
            // static memory. It is usefull if the current program execution
            // tries to load the corresponding external entities after listing
            // them through the query. However, it could be even more efficient
            // if we store remote data into local cache to avoid REST calls.
            // Then, any other request, even from another thread, to load
            // that external data would be loaded from the cache. That cache
            // would be different from the external entity instance cache as it
            // would only contain raw data from storage client while the
            // external entity instance cache stores the whole entity data which
            // may use aggregation with other storage clients.
            // @todo See also issue #3511947.
            // https://www.drupal.org/project/external_entities/issues/3511947
            static::$cachedData[$this->configuration['endpoint']][$id] = $raw_data;
          }
        }
      }
    }

    return $all_results;
  }

  /**
   * {@inheritdoc}
   */
  public function countQuerySource(array $parameters = []) :int {
    // Handle dynamic count for unfiltered queries.
    if (!empty($this->configuration['endpoint_options']['dynamic_count'])) {
      if (empty($parameters)) {
        $count = $this->getDynamicCount();
        if (isset($count)) {
          return $count;
        }
      }
      // If no count cached yet or filters are used, return -1.
      return -1;
    }

    if (!empty($this->configuration['endpoint_options']['count'])
        && is_numeric($this->configuration['endpoint_options']['count'])
    ) {
      // Static count.
      return $this->configuration['endpoint_options']['count'];
    }

    $filter_parameters = ['filters' => $parameters];
    $endpoint = $this->getEndpointUrl('count', $filter_parameters);
    $method = $this->getRequestMethod('count', $endpoint, $filter_parameters);
    try {
      $req_parameters = $this->getRequestParameters(
        'count',
        $method,
        $endpoint,
        $filter_parameters
      );
      $this->ensureQueryLimits();
      $response = $this->httpClient->request(
        $method,
        $endpoint,
        $req_parameters
      );
    }
    catch (GuzzleException $exception) {
      $this->logger->error(
        'Error in Rest::countQuery. Message: '
        . $exception->getMessage()
      );
      return 0;
    }

    $body = $this->getResponseBody(
      $response,
      'count',
      $method,
      $endpoint,
      $req_parameters
    );

    try {
      $results = $this
        ->getResponseDecoderFactory()
        ->getDecoder($this->configuration['response_format'])
        ->decode($body);
    }
    catch (InvalidDataTypeException $exception) {
      $this->logger->error(
        'Error in decoding result. Message: '
        . $exception->getMessage()
      );
      return 0;
    }

    // Check if we must use a JSON Path to get data.
    if (!empty($this->configuration['data_path']['count'])) {
      try {
        $json_object = new JsonObject($results, TRUE);
        $results = $json_object->get($this->configuration['data_path']['count']);
        if (FALSE === $results) {
          $results = [];
        }
      }
      catch (InvalidJsonException | InvalidJsonPathException $e) {
        $this->logger->error(
          'Failed to parse JSON Path for count ('
          . $this->configuration['data_path']['count']
          . '): '
          . $e
        );
      }
    }

    // Manage array case.
    if (is_array($results)) {
      // Get first value of an array of a single numeric value.
      if ((1 == count($results))
          && (!empty($results[0]))
          && is_numeric($results[0])
      ) {
        $results = $results[0];
      }
      else {
        // Otherwise, assume we got an array of items to count.
        $results = count($results);
      }
    }

    // Check if the given value is numeric or a link including a count/end page.
    if (!is_numeric($results)) {
      // Check if we got a URL with a page/start item parameter.
      $page_param = $this->configuration['pager']['page_parameter'];
      if (!empty($page_param)
          && preg_match("#^(?:http|/).*[?&;]$page_param=(\d+)#i", $results, $matches)
      ) {
        $results = $matches[1];
      }
      elseif (preg_match('#(\d+)\D*$#', $results, $matches)) {
        // Otherwise, try to catch the last number.
        $results = $matches[1];
      }
      else {
        // Otherwise, we don't have a number.
        $results = 0;
      }
    }

    // Check count type.
    switch ($this->configuration['endpoint_options']['count_mode']) {
      case 'pages':
        // Page mode, multiply number by page size.
        $page_size =
          $this->configuration['pager']['default_limit']
          ?? static::DEFAULT_PAGE_LENGTH;
        $results *= $page_size;
        break;

      case 'entities':
      default:
        break;
    }

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function isCountable() :bool {
    return empty($this->configuration['endpoint_options']['dynamic_count']);
  }

  /**
   * Gets the Drupal state dynamic count key.
   *
   * @return string
   *   The Drupal state count key.
   */
  protected function getCountKey(): string {
    // We compute a key based on the external entity type and the REST endpoint
    // as a same external entity may have several REST clients and a same
    // endpoint may be used by several external entities as well.
    // A problem remains: if the same endpoint is used several time by the same
    // external entity type. Maybe REST client "list parameters" should also be
    // taken into account for unicity? We will fix it if someone reports such a
    // problem.
    $this->countKey ??=
      'external_entities_'
      . md5(
        $this->externalEntityType->id()
        . $this->getEndpointUrl()
      )
      . '_rest_count';
    return $this->countKey;
  }

  /**
   * Gets the cached dynamic count for this entity type.
   *
   * @return int|null
   *   The cached count or NULL if not set.
   */
  protected function getDynamicCount(): ?int {
    $count = $this->state->get($this->getCountKey());
    if (!isset($count)
      && !empty($this->configuration['endpoint_options']['count'])
      && is_numeric($this->configuration['endpoint_options']['count'])
    ) {
      // Static count.
      $count = $this->configuration['endpoint_options']['count'];
    }
    return $count;
  }

  /**
   * Sets the dynamic count for this entity type.
   *
   * @param int $count
   *   The count to cache.
   */
  protected function setDynamicCount(int $count): void {
    $this->state->set($this->getCountKey(), $count);
  }

  /**
   * Removes the dynamic count for this entity type.
   */
  protected function deleteDynamicCount(): void {
    $this->state->delete($this->getCountKey());
  }

  /**
   * {@inheritdoc}
   *
   * For children classes, it is possible to override this method to support
   * other operators than '=' if possible and needed. For instance, if the
   * endpoint supports the 'STARTS_WITH' operator for the endpoint field
   * "blabla" using the query parameter "blablastartswith=", and that field
   * "blabla" is mapped to a Drupal field "field_bla", the override would remove
   * from $parameters filters that use "blabla" with the operator 'STARTS_WITH'
   * before passing it to parent::transliterateDrupalFilters() to map the rest
   * of parameters and add to the returned parameters the parameter
   * "blablastartswith" with the given value. In terms of code:
   * @code
   *   // child::transliterateDrupalFilters() input $parameters:
   *   [
   *     [
   *       'field' => 'field_bla',
   *       'value' => 'value_1',
   *       'operator' => 'STARTS_WITH',
   *     ],
   *     [
   *       'field' => 'field_other',
   *       'value' => ['value_2', 'value_3'],
   *       'operator' => 'IN',
   *     ],
   *     [
   *       'field' => 'field_yetanother',
   *       'value' => 'value_4',
   *       'operator' => '=',
   *     ],
   *     [
   *       'field' => 'field_op_not_supported',
   *       'value' => ['value_5', 'value_6'],
   *       'operator' => 'BETWEEN',
   *     ],
   *   ]
   *   // The child class could call Rest::transliterateDrupalFilters()
   *   // with new input $parameters:
   *   [
   *     [
   *       'field' => 'field_other',
   *       'value' => ['value_2', 'value_3'],
   *       'operator' => 'IN',
   *     ],
   *     [
   *       'field' => 'field_yetanother',
   *       'value' => 'value_4',
   *       'operator' => '=',
   *     ],
   *     [
   *       'field' => 'field_op_not_supported',
   *       'value' => ['value_5', 'value_6'],
   *       'operator' => 'BETWEEN',
   *     ],
   *   ]
   *   // The child class would get in return (assuming Drupal field
   *   // "field_other" is mapped to "other_source_name" and "field_yetanother"
   *   // to "yaf"):
   *   [
   *     'source' => [
   *       [
   *         'field' => 'other_source_name',
   *         'value' => 'value_2,value_3',
   *         'operator' => '=',
   *       ],
   *       [
   *         'field' => 'yaf',
   *         'value' => 'value_4',
   *         'operator' => '=',
   *       ],
   *     ],
   *     'drupal' => [
   *       [
   *         'field' => 'field_op_not_supported',
   *         'value' => ['value_5', 'value_6'],
   *         'operator' => 'BETWEEN',
   *       ],
   *     ],
   *   ]
   *   // That will be merged to the mapped "blabla" field and the child class
   *   // would return:
   *   [
   *     'source' => [
   *       [
   *         'field' => 'blablastartswith',
   *         'value' => 'value_1',
   *         'operator' => '=',
   *       ],
   *       [
   *         'field' => 'other_source_name',
   *         'value' => 'value_2,value_3',
   *         'operator' => '=',
   *       ],
   *       [
   *         'field' => 'yaf',
   *         'value' => 'value_4',
   *         'operator' => '=',
   *       ],
   *     ],
   *     'drupal' => [
   *       [
   *         'field' => 'field_op_not_supported',
   *         'value' => ['value_5', 'value_6'],
   *         'operator' => 'BETWEEN',
   *       ],
   *     ],
   *   ]
   *   // Note: since 'BETWEEN' operator is not supported by source, we don't
   *   // care which field 'field_op_not_supported' is mapped to since it will
   *   // be handled on the Drupal side using its own name.
   * @endcode
   * Note: a child class can transliterate a filter element into multiple
   * parameters if needed and vice-versa.
   * For an exemple of implementation, see
   * JsonAPI::transliterateDrupalFilters().
   * @see \Drupal\external_entities\Plugin\ExternalEntities\StorageClient\JsonAPI::transliterateDrupalFilters()
   *
   * @param array $parameters
   *   (optional) An array of Drupal-side field name parameter filtering like:
   *   @code
   *   [
   *     [
   *       'field' => 'field_name_1',
   *       'value' => 'value_1',
   *       'operator' => '=',
   *     ],
   *     [
   *       'field' => 'field_name_2',
   *       'value' => ['value_2', 'value_3',],
   *       'operator' => 'IN',
   *     ],
   *     ...
   *   ]
   *   @endcode
   * @param array $context
   *   A context array containing information such as the calling method name
   *   set for the 'caller' key.
   *
   * @return array
   *   A 2 keys array with transliterated and not transliterated parameters
   *   array stored respectively under the keys 'source' and 'drupal'.
   */
  public function transliterateDrupalFilters(
    array $parameters,
    array $context = [],
  ) :array {
    if (1 <= $this->getDebugLevel()) {
      $this->logger->debug(
        "RestClient::transliterateDrupalFilters():\n@parameters",
        [
          '@parameters' => print_r($parameters, TRUE),
        ]
      );
    }
    $trans_filters = [];
    // Use a do-while structure trick to break and avoid many "if".
    do {
      // Skip empty filters.
      if (empty($parameters)) {
        $trans_filters = ['source' => [], 'drupal' => []];
        break;
      }

      if (empty($this->configuration['filtering']['basic'])) {
        // No operator supported by endpoint by default.
        if (empty($this->configuration['filtering']['drupal'])) {
          // Drupal side filtering not allowed.
          if (1 <= $this->getDebugLevel()) {
            $this->logger->debug("No basic filtering nor Drupal-side filtering allowed.");
          }
          break;
        }
        // Drupal-side filtering allowed.
        $trans_filters = ['source' => [], 'drupal' => $parameters];
        if (1 <= $this->getDebugLevel()) {
          $this->logger->debug(
            "No basic filtering supported. Returning:\n@filters",
            [
              '@filters' => print_r($trans_filters, TRUE),
            ]
          );
        }
        break;
      }

      if (empty($this->externalEntityType)) {
        // If no external entity is available, no way to map fields.
        if (empty($this->configuration['filtering']['drupal'])) {
          // Drupal side filtering not allowed.
          if (1 <= $this->getDebugLevel()) {
            $this->logger->debug("No external entity type available to map fields and Drupal-side filtering allowed.");
          }
          break;
        }
        // Drupal-side filtering allowed.
        $trans_filters = ['source' => [], 'drupal' => $parameters];
        if (1 <= $this->getDebugLevel()) {
          $this->logger->debug(
            "No external entity type available to map fields. Returning:\n@filters",
            [
              '@filters' => print_r($trans_filters, TRUE),
            ]
          );
        }
        break;
      }

      $source_filters = [];
      $drupal_filters = [];
      $basic_fields = $this->configuration['filtering']['basic_fields'] ?? [];
      foreach ($parameters as $parameter) {
        if (!isset($parameter['field'])
          || !isset($parameter['value'])
          || !is_string($parameter['field'])
          || (FALSE !== strpos($parameter['field'], '.'))
        ) {
          $drupal_filters[] = $parameter;
          if (1 <= $this->getDebugLevel()) {
            $this->logger->debug("Complex filter passed to Drupal-side filtering");
          }
          continue;
        }

        $field_mapper = $this->externalEntityType->getFieldMapper($parameter['field']);
        if ($field_mapper) {
          $source_field = $field_mapper->getMappedSourceFieldName();
        }
        if (!isset($source_field)) {
          $drupal_filters[] = $parameter;
          if (1 <= $this->getDebugLevel()) {
            $this->logger->debug(
              "No reversible mapping for Drupal field '@field' passed to Drupal-side filtering",
              [
                '@field' => $parameter['field'],
              ]
            );
          }
          continue;
        }

        if (!empty($basic_fields)
            && (is_array($basic_fields))
            && (!in_array($source_field, $basic_fields))
        ) {
          $drupal_filters[] = $parameter;
          if (1 <= $this->getDebugLevel()) {
            $this->logger->debug(
              "Source field '@field' not supported by basic filtering passed to Drupal-side filtering",
              [
                '@field' => $source_field,
              ]
            );
          }
          continue;
        }

        $parameter['operator'] ??= '=';
        if (!in_array($parameter['operator'], ['=', 'IN'])) {
          $drupal_filters[] = $parameter;
          if (1 <= $this->getDebugLevel()) {
            $this->logger->debug(
              "Operator '@operator' not supported by basic filtering passed to Drupal-side filtering",
              [
                '@operator' => $parameter['operator'],
              ]
            );
          }
          continue;
        }
        if ('IN' == $parameter['operator']) {
          if (!is_array($parameter['value'])) {
            // Invalid parameter.
            $this->logger->warning(
              'Filter parameter using "IN" without an array of values (skipped): @param',
              ['@param' => print_r($parameter, TRUE)]
            );
            continue;
          }
          if (1 >= count($parameter['value'])) {
            // Turn list of one value into a single value test.
            $parameter['value'] = current($parameter['value']);
          }
          elseif (empty($this->configuration['filtering']['list_support'])
            || ('none' == $this->configuration['filtering']['list_support'])
          ) {
            // Lists not supported.
            $drupal_filters[] = $parameter;
            if (1 <= $this->getDebugLevel()) {
              $this->logger->debug(
                "List filtering not supported passed to Drupal-side filtering"
              );
            }
            continue;
          }
          elseif ('repeat' == $this->configuration['filtering']['list_support']) {
            // This special case will be handled by self::getQueryParameters().
            $source_field = static::REPEAT_PREFIX . $source_field;
          }
          elseif ('indexed' == $this->configuration['filtering']['list_support']) {
            foreach ($parameter['value'] as $index => $value) {
              $source_filters[] = [
                'field' => $source_field . '[' . $index . ']',
                'value' => $value,
                'operator' => '=',
              ];
            }
            if (1 <= $this->getDebugLevel()) {
              $this->logger->debug(
                "List filtering for source field '@field' turned into a series of values",
                [
                  '@field' => $source_field,
                ]
              );
            }
            continue;
          }
          elseif ('implode' == $this->configuration['filtering']['list_support']) {
            $list_join = $this->configuration['filtering']['list_join'] ?? ',';
            $parameter['value'] = implode($list_join, $parameter['value']);
          }
          elseif ('unindexed' == $this->configuration['filtering']['list_support']) {
            // Nothing to change.
          }
          elseif ('post' == $this->configuration['filtering']['list_support']) {
            // Nothing to change.
          }
          else {
            // Unsupported setting.
            $this->logger->warning(
              'Invalid setting (@setting) for "IN" filter parameter support (skipped): @param',
              [
                '@setting' => $this->configuration['filtering']['list_support'],
                '@param' => print_r($parameter, TRUE),
              ]
            );
          }
          // Turn 'IN' into '='.
          $parameter['operator'] = '=';
        }
        $parameter['field'] = $source_field;
        $source_filters[] = $parameter;
        if (1 <= $this->getDebugLevel()) {
          $this->logger->debug(
            "Filter on source field '@field' added to source filtering.",
            [
              '@field' => $source_field,
            ]
          );
        }
      }

      if (empty($this->configuration['filtering']['drupal'])
          && !empty($drupal_filters)
      ) {
        // No Drupal side filtering while needed, do not allow filtering.
        if (1 <= $this->getDebugLevel()) {
          $this->logger->debug(
            "Some filters require Drupal-side filtering while it is not allowed: @filters",
            [
              '@filters' => print_r($drupal_filters, TRUE),
            ]
          );
        }
        break;
      }
      if (1 <= $this->getDebugLevel()) {
        $this->logger->debug(
          "transliteration done. Returning:\n@filters",
          [
            '@filters' => print_r(
              ['source' => $source_filters, 'drupal' => $drupal_filters],
              TRUE
            ),
          ]
        );
      }

      $trans_filters = ['source' => $source_filters, 'drupal' => $drupal_filters];
    } while (FALSE);

    return $this->transliterateDrupalFiltersAlter(
      $trans_filters,
      $parameters,
      $context
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transliterateDrupalSorts(
    array $sorts,
    array $context = [],
  ) :array {
    if (!empty($this->configuration['filtering']['drupal'])) {
      $trans_sorts = [
        'drupal' => $sorts,
        'source' => [],
      ];
      if (1 <= $this->getDebugLevel()) {
        $this->logger->debug(
          "Drupal-side filtering is allowed: sorting will also be performed on Drupal-side. Returning:\n@trans_sorts",
          [
            '@trans_sorts' => print_r($trans_sorts, TRUE),
          ]
        );
      }
    }
    else {
      $trans_sorts = [
        'drupal' => [],
        'source' => [],
        'unhandled' => $sorts,
      ];
      if (1 <= $this->getDebugLevel()) {
        $this->logger->debug(
          "Drupal-side filtering is not allowed: no sorting performed. Returning:\n@trans_sorts",
          [
            '@trans_sorts' => print_r($trans_sorts, TRUE),
          ]
        );
      }
    }

    return $this->transliterateDrupalSortsAlter(
      $trans_sorts,
      $sorts,
      $context,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpointUrl(
    string $request_type = 'list',
    array $parameters = [],
  ) :string {
    $endpoint_url = '';
    switch ($request_type) {
      case 'create':
      case 'read':
      case 'update':
      case 'delete':
        $id = $parameters['id'] ?? '';
        // Get single entity URL either from a given specific URL or the general
        // endpoint URL.
        $endpoint_url =
          $this->configuration['endpoint_options']['single']
          ?: (rtrim($this->configuration['endpoint'], '/') . '/' . urlencode($id));

        // Replace any {id} placeholder with the given identifier (URL-encoded).
        $endpoint_url = str_replace('{id}', urlencode($id), $endpoint_url);
        break;

      case 'list':
        $endpoint_url = $this->configuration['endpoint'];
        break;

      case 'count':
        $endpoint_url =
          $this->configuration['endpoint_options']['count']
          ?: $this->configuration['endpoint'];
        break;

      default:
        // We don't log unsupported request types as they might be handled by
        // extensions.s.
    }

    // Prepare tokens.
    $tokens = $this->initTokens(
      [],
      [
        'operation' => $request_type,
        'endpoint' => $endpoint_url,
      ]
      + $parameters
    );
    // Strip query string part as it will be managed by
    // getListQueryParameters().
    $endpoint_url = strstr(
      $this->tokenService->replacePlain($endpoint_url, $tokens) . '?',
      '?',
      TRUE
    );

    return $endpoint_url;
  }

  /**
   * {@inheritdoc}
   */
  public function getHttpHeaders(
    string $endpoint = '',
    array $parameters = [],
  ) :array {
    $headers = [];

    $tokens = $this->initTokens(
      [],
      [
        'endpoint' => $endpoint,
      ]
      + $parameters
    );

    if (!empty($this->configuration['http']['headers'])) {
      $raw_headers = $this->configuration['http']['headers'];
      $header_lines = explode(
        "\n",
        $this->tokenService->replacePlain($raw_headers, $tokens)
      );
      foreach ($header_lines as $header_line) {
        @[$header_name, $value] = explode(':', $header_line, 2);
        $value ??= '';
        $headers[$header_name] = trim($value);
      }
    }

    if (!empty($this->configuration['api_key']['type'])
        && (in_array(
            $this->configuration['api_key']['type'],
            ['bearer', 'custom']
          )
        )
        && !empty($this->configuration['api_key']['header_name'])
        && !empty($this->configuration['api_key']['key'])
    ) {
      $headers[$this->configuration['api_key']['header_name']] =
        $this->tokenService->replacePlain(
          $this->configuration['api_key']['key'],
          $tokens
        );
    }

    return $headers;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestMethod(
    string $request_type,
    string $endpoint = '',
    array $parameters = [],
  ) :string {
    // Default ot GET.
    $method = 'GET';
    switch ($request_type) {
      case 'create':
        $method = 'POST';
        break;

      case 'read':
        if ('body' == $this->configuration['parameters']['single_param_mode']) {
          // Use POST.
          $method = 'POST';
        }
        break;

      case 'update':
        $method = 'PUT';
        break;

      case 'delete':
        $method = 'DELETE';
        break;

      case 'list':
        // Use POST for both 'body' and 'body_query' modes.
        if (0 === strpos($this->configuration['parameters']['list_param_mode'], 'body')) {
          $method = 'POST';
        }
        break;

      case 'count':
        // Will not use POST for count if set to 'body_query'.
        if ('body' == $this->configuration['parameters']['list_param_mode']) {
          $method = 'POST';
        }
        break;

      default:
        // We don't log unsupported request types as they might be handled by
        // extensions.s.
    }

    return $method;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryParameters(
    string $endpoint = '',
    array $parameters = [],
  ) :array|string {
    $query_parameters = [];
    $request_type = $parameters['operation'] ?? 'list';

    switch ($request_type) {
      case 'create':
      case 'read':
      case 'update':
      case 'delete':
        $query_parameters = $this->getSingleQueryParameters(
          $parameters['id'] ?? ''
        );
        break;

      case 'list':
        if (!empty($this->configuration['pager']['always_query'])
          || ('GET' == $parameters['method'])
        ) {
          $query_parameters = $parameters['paging'] ?? [];
        }
        $query_parameters += $this->getListQueryParameters(
          $parameters['filters'] ?? []
        );
        break;

      case 'count':
        $query_parameters = $this->getCountQueryParameters(
          $parameters['filters'] ?? []
        );
        break;

      default:
        // We don't log unsupported request types as they might be handled by
        // extensions.s.
    }

    // Add authentication key to parameters if needed.
    if (!empty($this->configuration['api_key']['type'])
        && ('query' == $this->configuration['api_key']['type'])
        && !empty($this->configuration['api_key']['header_name'])
    ) {
      $query_parameters[$this->configuration['api_key']['header_name']] = $this->configuration['api_key']['key'];
    }

    if (!empty($this->configuration['filtering']['list_support'])
      && ('repeat' == $this->configuration['filtering']['list_support'])
    ) {
      $has_repeat = FALSE;
      // Handle repeated parameters.
      $serializable_param = [];
      $multi_param = [];
      foreach ($query_parameters as $param => $value) {
        if (static::REPEAT_PREFIX === $param[0]) {
          if (!is_array($value)) {
            $value = [$value];
          }
          foreach ($value as $subval) {
            $multi_param[] =
              urlencode(substr($param, 1)) . '=' . urlencode($subval);
          }
          $has_repeat = TRUE;
        }
        else {
          $serializable_param[$param] = $value;
        }
      }
      if ($has_repeat) {
        $query_parameters =
          http_build_query($serializable_param, '', '&', PHP_QUERY_RFC3986);
        $multi_param = implode('&', $multi_param);
        if (('' === $query_parameters) || ('' === $multi_param)) {
          $query_parameters = $query_parameters . $multi_param;
        }
        else {
          $query_parameters = $query_parameters . '&' . $multi_param;
        }
      }
    }

    $tokens = $this->initTokens(
      [],
      [
        'endpoint' => $endpoint,
      ]
      + $parameters
    );
    $query_parameters = $this->recursiveReplaceTokens($query_parameters, $tokens);

    return $query_parameters;
  }

  /**
   * Prepares and returns parameters used for list queries.
   *
   * This method is called by querySource() and countQuerySource(). The provided
   * $filters parameter is supposed to contain only storage source compatible
   * parameters obtained from self::transliterateDrupalFilters().
   * This method will then add optional config-provided parameters (
   * authentication and custom parameters). The returned array will hold query
   * parameter name as keys and their associated values as strings (array values
   * are imploded with comas into strings).
   *
   * @param array $filters
   *   (optional) An array of field filtering parameters like:
   *   @code
   *   [
   *     [
   *       'field' => 'name_1',
   *       'value' => 'value_1',
   *       'operator' => '=',
   *     ],
   *     [
   *       'field' => 'name_2',
   *       'value' => ['value_2', 'value_3', ],
   *       'operator' => 'IN',
   *     ],
   *     [
   *       'field' => 'name_3',
   *       'value' => 'value_4',
   *       'operator' => '<',
   *     ],
   *     ...
   *   ]
   *   @endcode
   *
   * @return array
   *   An associative array of query parameters keyed by parameter names with
   *   their associated values.
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
  protected function getListQueryParameters(array $filters = []) :array {
    $query_parameters = [];
    if ('query' == ($this->configuration['parameters']['list_param_mode'] ?? '')) {
      if (!empty($this->configuration['filtering']['list_support'])
        && ('implode' == $this->configuration['filtering']['list_support'])
      ) {
        $query_parameters = $this->toQueryParameters(
          $filters,
          $this->configuration['filtering']['list_join'] ?? ''
        );
      }
      else {
        $query_parameters = $this->toQueryParameters($filters, NULL);
      }

      if (!empty($this->configuration['parameters']['list'])) {
        $query_parameters += $this->configuration['parameters']['list'];
      }
    }
    // Get query string from endpoint URL if specified.
    $endpoint = strstr($this->configuration['endpoint'], '?');
    if ($endpoint) {
      // Note: we don't consider ';' as a query parameter separator.
      preg_match_all('#([^&=]+)(?:=([^&=]*))?#', substr($endpoint, 1), $matches);
      $query_parameters += array_combine($matches[1], $matches[2]);
    }

    return $query_parameters;
  }

  /**
   * Prepares and returns parameters used for count queries.
   *
   * @param array $filters
   *   (optional) An array of Drupal-side field name parameter filtering. See
   *   getListQueryParameters() $parameters parameter for details.
   *
   * @return array
   *   An associative array of parameters.
   */
  protected function getCountQueryParameters(array $filters = []) :array {
    $query_parameters = [];
    if (FALSE !== strpos($this->configuration['parameters']['list_param_mode'], 'query')) {
      if (!empty($this->configuration['filtering']['list_support'])
        && ('implode' == $this->configuration['filtering']['list_support'])
      ) {
        $query_parameters = $this->toQueryParameters(
          $filters,
          $this->configuration['filtering']['list_join'] ?? ''
        );
      }
      else {
        $query_parameters = $this->toQueryParameters($filters, NULL);
      }

      if (!empty($this->configuration['parameters']['list'])) {
        $query_parameters += $this->configuration['parameters']['list'];
      }
    }

    // Get query string from endpoint URL if specified.
    $endpoint =
      $this->configuration['endpoint_options']['count']
      ?: $this->configuration['endpoint'];
    $endpoint = strstr($endpoint, '?');
    if ($endpoint) {
      // Note: we don't consider ';' as a query parameter separator.
      preg_match_all('#([^&=]+)(?:=([^&=]*))?#', substr($endpoint, 1), $matches);
      $query_parameters += array_combine($matches[1], $matches[2]);
    }

    return $query_parameters;
  }

  /**
   * Prepares and returns parameters used for single item queries.
   *
   * This method is called by load() and will provide optional config-provided
   * parameters (authentication and custom parameters). "{id}" placeholders in
   * custom parameter values are replaced by the provided entity identifier
   * value ($id). The returned array will hold query parameter name as keys and
   * their associated values as strings.
   *
   * @param int|string|null $id
   *   The item id being fetched.
   *
   * @return array
   *   An associative array of parameters.
   */
  protected function getSingleQueryParameters(int|string|null $id) :array {
    $query_parameters = [];

    // Get query string from endpoint URL if specified.
    $endpoint =
      $this->configuration['endpoint_options']['single']
      ?: $this->configuration['endpoint'];
    $endpoint = strstr($endpoint, '?');
    if ($endpoint) {
      // Note: we don't consider ';' as a query parameter separator.
      preg_match_all('#([^&=]+)(?:=([^&=]*))?#', substr($endpoint, 1), $matches);
      $query_parameters += array_combine($matches[1], $matches[2]);
    }

    if ((!empty($this->configuration['parameters']['single']))
      && ('query' == $this->configuration['parameters']['single_param_mode'])
    ) {
      if (!empty($id) || ('0' === $id) || (0 === $id) || (0. === $id)) {
        $query_parameters += array_map(
          function ($value) use ($id) {
            return str_replace('{id}', $id ?? '', $value);
          },
          $this->configuration['parameters']['single']
        );
      }
      else {
        $query_parameters += $this->configuration['parameters']['single'];
      }
    }
    return $query_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function getPagingQueryParameters(
    ?int $start = NULL,
    ?int $length = NULL,
    array $parameters = [],
  ) :array {
    $paging_parameters = [];
    // Check parameters.
    $start ??= 0;
    if (0 > $start) {
      $start = 0;
    }
    if (!isset($length)) {
      // Default to default page size.
      $length = static::DEFAULT_PAGE_LENGTH;
    }
    elseif (0 > $length) {
      $length = 0;
    }

    // Only apply paging if we have the parameters for it.
    if (!empty($this->configuration['pager']['page_parameter'])
        && !empty($this->configuration['pager']['page_size_parameter'])
    ) {
      $one_offset = empty($this->configuration['pager']['page_start_one'])
        ? 0
        : 1;
      $end_item_mode = ($this->configuration['pager']['page_size_parameter_type'] === 'enditem');
      $pagenum_mode = ($this->configuration['pager']['page_parameter_type'] === 'pagenum');

      $max_page_size = ($this->configuration['pager']['default_limit'] ?? 0)
        ?: static::DEFAULT_PAGE_LENGTH;
      $length = $length ?: $max_page_size;
      $page_size = min($max_page_size, ($start + $length));
      $keep_start = 0;
      $keep_length = $page_size;
      $ending_pos = $start + $length - 1;

      if ($pagenum_mode) {
        // Convert start item into a page number.
        $keep_start = $start % $max_page_size;
        $start = (int) floor($start / $max_page_size);
        $keep_length = min(
          $page_size - $keep_start,
          $ending_pos + 1 - ($start * $max_page_size)
        );
        if ($end_item_mode) {
          $page_size = min($ending_pos, ($start + 1) * $max_page_size - 1) + $one_offset;
        }
      }
      else {
        // Start item mode.
        $keep_length = min($length, $max_page_size);
        if ($end_item_mode) {
          $page_size = min($ending_pos, $start + $max_page_size - 1) + $one_offset;
        }
      }

      while (0 < $keep_length) {
        $paging_parameters[] = [
          'parameters' => [
            $this->configuration['pager']['page_parameter'] => $start + $one_offset,
            $this->configuration['pager']['page_size_parameter'] => $page_size,
          ],
          'start' => $keep_start,
          'length' => $keep_length,
        ];

        // Compute next paging data...
        $keep_start = 0;
        if ($pagenum_mode) {
          // Page number, got to next page.
          ++$start;
          // Get current page end position.
          $current_page_end = (($start + 1) * $max_page_size) - 1;

          // No need to update $page_size for "number of items per page" mode.
          if ($end_item_mode) {
            // Update end item to either the final position or the last position
            // in current page.
            $page_size = min($ending_pos, $current_page_end) + $one_offset;
          }

          // Update $keep_length to $max_page_size unless we reached last page.
          $keep_length = min(
            $max_page_size,
            $ending_pos - $keep_start + 1 - ($start * $max_page_size)
          );
        }
        else {
          // Paging in starting item mode, go forward for $max_page_size items.
          $start += $max_page_size;

          // Update $page_size.
          if ($end_item_mode) {
            // Update end item to either the final position or the last position
            // in current page.
            $page_size = min($ending_pos, $start + $max_page_size - 1) + $one_offset;
          }
          else {
            // Update end item to either the final position or the maximum
            // number of item in a page.
            $page_size = min($ending_pos + 1 - $start, $max_page_size);
          }

          // Update $keep_length to $max_page_size unless we reached last page.
          $keep_length = min(
            $max_page_size,
            $ending_pos + 1 - $start
          );
        }
      }
    }
    else {
      // No paging parameters.
      // Set a default length.
      $length = $length ?: static::DEFAULT_PAGE_LENGTH;
      // Limit length if needed.
      if (!empty($this->configuration['pager']['default_limit'])) {
        $length = min($length, $this->configuration['pager']['default_limit'] - $start);
      }
      $paging_parameters = [
        [
          'parameters' => [],
          'start' => $start,
          'length' => $length,
        ],
      ];
    }

    return $paging_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultMaxEntitiesToFetch() :?int {
    return $this->maxEntities;
  }

  /**
   * {@inheritdoc}
   */
  public function setDefaultMaxEntitiesToFetch(?int $max_entitites) :self {
    $this->maxEntities = $max_entitites;
    return $this;
  }

  /**
   * Convert Drupal filter parameters to query parameters.
   *
   * @param array $parameters
   *   An array of filter parameters of the form:
   *   @code
   *     [
   *       [
   *         'field' => '<field_name>',
   *         'operator' => '=',
   *         'value' => '<field_value>',
   *       ],
   *       ...
   *     ]
   *   @endcode
   * @param string|null $implode
   *   Flatten array values into a string using $implode as glue element. If
   *   set to NULL, arrays are kept as they are. Default: ','.
   *
   * @return array
   *   An array with query parameter name as key and a string as their
   *   associated values.
   */
  protected function toQueryParameters(
    array $parameters,
    ?string $implode = ',',
  ) :array {
    $query_parameters = [];
    foreach ($parameters as $parameter) {
      if (!isset($parameter['field'])
        || !isset($parameter['value'])
        || (!empty($parameter['operator'])
            && ('=' != $parameter['operator'])
            && ('IN' != $parameter['operator']))
      ) {
        $this->logger->warning(
          'Unsupported filter parameter (skipped): @param',
          ['@param' => print_r($parameter, TRUE)]
        );
        continue;
      }
      $value = $parameter['value'];
      if (isset($implode)
        && is_array($value)
        && (static::REPEAT_PREFIX != $parameter['field'][0])
      ) {
        $value = implode($implode, $value);
      }
      $query_parameters[$parameter['field']] = $value;
    }
    return $query_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestParameters(
    string $request_type,
    string $method,
    string $endpoint = '',
    array $parameters = [],
  ) :array {
    if (empty($parameters)) {
      return [];
    }

    $req_parameters = [];
    $body_data = [];
    $parameters['operation'] = $request_type;
    $parameters['method'] = $method;
    switch ($request_type) {
      case 'read':
        $body_data = $this->getSingleRequestBodyData($parameters);
        break;

      case 'create':
        $body_data = $this->getSingleRequestBodyData($parameters);
        break;

      case 'update':
        $body_data = $this->getSingleRequestBodyData($parameters);
        break;

      case 'delete':
        $body_data = $this->getSingleRequestBodyData($parameters);
        break;

      case 'list':
        $body_data = $this->getListRequestBodyData($parameters);
        break;

      case 'count':
        $body_data = $this->getCountRequestBodyData($parameters);
        break;

      default:
        // We don't log unsupported request types as they might be handled by
        // extensions.s.
    }

    // Set mode.
    if (!empty($body_data)) {
      $tokens = $this->initTokens(
        [],
        [
          'endpoint' => $endpoint,
        ]
        + $parameters
      );
      // Apply token replacement and try to decode any JSON-encoded value to get
      // a structure when needed.
      foreach ($body_data as &$value) {
        $value = $this->recursiveReplaceTokens($value, $tokens);
        if (is_string($value)) {
          if (str_starts_with($value, 'json:')) {
            $value = substr($value, 5);
            $value = Json::decode($value, TRUE) ?? $value;
          }
          elseif (str_starts_with($value, 'plain:')) {
            $value = substr($value, 6);
          }
        }
      }
      switch ($this->configuration['response_format']) {
        case 'json':
          // The content type "application/json" is automatically set by Guzzle.
          $req_parameters = ['json' => $body_data];
          break;

        case 'serialize':
          // PHP serialized data.
          $req_parameters = [
            'headers' => ['Content-Type' => 'application/vnd.php.serialized'],
            'body' => serialize($body_data),
          ];
          break;

        // XML data, not supported at this time.
        // @code
        // case 'xml':
        //   $parameters = [
        //     'headers' => ['Content-Type' => 'application/xml'],
        //     'body' => ...($parameters),
        //   ];
        //   break;
        // @endcode
        case 'yml':
          $req_parameters = [
            'headers' => ['Content-Type' => 'application/x-yaml'],
            'body' => Yaml::dump($body_data),
          ];
          break;

        case 'urlencoded':
        default:
          // Guzzle will set content type to
          // "application/x-www-form-urlencoded".
          $req_parameters = ['form_params' => $body_data];

          // Note for extensions: if you need to use files, use the body type
          // "'multipart' => $parameters" that will set content type to
          // "multipart/form-data".
          // @see https://docs.guzzlephp.org/en/stable/request-options.html#multipart
      }
    }

    // Add general parameters.
    $req_parameters = NestedArray::mergeDeep(
      $req_parameters,
      [
        'headers' => $this->getHttpHeaders(
          $endpoint,
          $parameters
        ),
        'query' => $this->getQueryParameters(
          $endpoint,
          $parameters
        ),
      ]
    );

    return $req_parameters;
  }

  /**
   * Returns request body data to use for list.
   *
   * @param array $parameters
   *   (optional) An array of parameters with supported keys:
   *   - 'filters': contains Drupal-side field name parameter filtering. See
   *     getListQueryParameters() $filters parameter for details.
   *   - 'paging': contains paging parameters that would be used if settings
   *     require so.
   *   - 'operation': should be set to 'list'.
   *
   * @return array
   *   See getRequestParameters().
   */
  protected function getListRequestBodyData(array $parameters = []) :array {
    $filters = $parameters['filters'] ?? [];
    $body_parameters = $this->toQueryParameters($filters, NULL);
    // Add pager parameters if needed.
    if (empty($this->configuration['pager']['always_query'])
        && !empty($parameters['paging'])
        && ('GET' != $parameters['method'])
    ) {
      $body_parameters += $parameters['paging'];
    }
    // Use list parameters if mode set to 'body' or 'body_query'.
    if ((!empty($this->configuration['parameters']['list']))
      && (0 === strpos($this->configuration['parameters']['list_param_mode'], 'body'))
    ) {
      $body_parameters += $this->configuration['parameters']['list'];
    }

    return $body_parameters;
  }

  /**
   * Returns request body data to use for count.
   *
   * @param array $parameters
   *   (optional) An array of parameters with supported keys:
   *   - 'filters': contains Drupal-side field name parameter filtering. See
   *     getListQueryParameters() $filters parameter for details.
   *   - 'operation': should be set to 'count'.
   *
   * @return array
   *   See getRequestParameters().
   */
  protected function getCountRequestBodyData(array $parameters = []) :array {
    $filters = $parameters['filters'] ?? [];
    $body_parameters = $this->toQueryParameters($filters, NULL);
    // Do not use list parameters if mode set to 'query' or 'body_query'.
    if ((!empty($this->configuration['parameters']['list']))
      && ('body' == $this->configuration['parameters']['list_param_mode'])
    ) {
      $body_parameters += $this->configuration['parameters']['list'];
    }

    return $body_parameters;
  }

  /**
   * Returns body data to use for single entity request.
   *
   * @param array $parameters
   *   An array of parameters with the following supported keys:
   *   - 'id': the item id being fetched.
   *   - 'data': extra data values to add to the body. For instance, entity
   *     field values when saving an entity.
   *   - 'operation': the type of request. One of 'create', 'read', 'update'
   *     or 'delete'.
   *
   * @return array
   *   An associative array of parameters.
   */
  protected function getSingleRequestBodyData(array $parameters) :array {
    $body_data = [];
    $id = $parameters['id'] ?? '';
    $data = $parameters['data'] ?? [];
    if (!empty($this->configuration['parameters']['single'])
      && ('body' == $this->configuration['parameters']['single_param_mode'])
    ) {
      // Replace entity identifier if needed.
      if (!empty($id) || ('0' === $id) || (0 === $id) || (0. === $id)) {
        $body_data += array_map(
          function ($value) use ($id) {
            return str_replace('{id}', $id ?? '', $value);
          },
          $this->configuration['parameters']['single']
        );
      }
      else {
        $body_data += $this->configuration['parameters']['single'];
      }
    }

    // Add extra data if needed.
    if (!empty($data)) {
      $body_data += $data;
    }

    return $body_data;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponseBody(
    ResponseInterface $response,
    string $request_type,
    string $method,
    string $endpoint,
    array $req_parameters = [],
  ) :string {
    if (200 == $response->getStatusCode()) {
      // Force string.
      $body = $response->getBody() . '';
      // Remove UTF8 BOM added by some server which may intefer with data
      // parsers.
      $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);
    }
    else {
      // Don't know how to handle non-200 answers: return an empty string.
      $body = '';
    }

    return $body;
  }

  /**
   * Initializes REST tokens.
   *
   * Maps REST tokens to standard external entity token structure.
   *
   * @param array $tokens
   *   The initial storage client tokens. See ExternalEntityHooks::tokenInfo()
   *   for structure details.
   * @param array $parameters
   *   REST query parameters.
   *
   * @return array
   *   The initialized token array with additional tokens provided by other
   *   modules.
   *
   * @see \Drupal\external_entities\Hook\ExternalEntityHooks::tokenInfo()
   */
  public function initTokens(array $tokens = [], array $parameters = []) :array {
    // Prepare tokens.
    $tokens = NestedArray::mergeDeep(
      [
        'sc' => [
          'operation' => $parameters['operation'] ?? '',
        ],
      ],
      $tokens
    );
    // Entity tokens.
    if (0 < strlen($parameters['id'] ?? '')) {
      $tokens['entity']['id'] = $parameters['id'];
    }
    if (!empty($parameters['data'])) {
      $tokens['entity']['data'] = $parameters['data'];
    }
    if (!empty($parameters['original'])) {
      $tokens['entity']['original'] = $parameters['original'];
    }
    // List tokens.
    if (!empty($parameters['filters'])) {
      $tokens['sc']['list']['filters'] = $parameters['filters'];
    }
    if (!empty($parameters['sorts'])) {
      $tokens['sc']['list']['sorts'] = $parameters['sorts'];
    }
    if (0 < strlen($parameters['start'] ?? '')) {
      $tokens['sc']['list']['start'] = $parameters['start'];
    }
    if (0 < strlen($parameters['length'] ?? '')) {
      $tokens['sc']['list']['length'] = $parameters['length'];
    }
    if (0 < strlen($parameters['page'] ?? '')) {
      $tokens['sc']['list']['page'] = $parameters['page'];
    }
    // Other unnormalized tokens.
    $tokens['sc']['extra'] = array_diff_key(
      $parameters,
      array_flip(
        [
          'operation',
          'id',
          'data',
          'original',
          'filters',
          'sorts',
          'start',
          'length',
          'page',
        ]
      )
    );

    $tokens = parent::initTokens($tokens);
    return $tokens;
  }

  /**
   * Recursively replace tokens in a mixed value.
   *
   * @param mixed $value
   *   The value to process (array or scalar).
   * @param array $tokens
   *   The external entity tokens.
   *
   * @return mixed
   *   The value with tokens replaced.
   */
  public function recursiveReplaceTokens($value, array $tokens = []) {
    if (is_array($value)) {
      foreach ($value as &$item) {
        $item = $this->recursiveReplaceTokens($item, $tokens);
      }
    }
    elseif (is_scalar($value)) {
      $value = $this->tokenService->replacePlain($value, $tokens);
    }
    return $value;
  }

}
