<?php

namespace Drupal\Tests\external_entities\Unit;

use Drupal\Core\State\StateInterface;
use Drupal\external_entities\GuzzleHttp\DebugClientFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\Token;
use Drupal\Tests\UnitTestCase;
use Drupal\external_entities\Entity\ConfigurableExternalEntityTypeInterface;
use Drupal\external_entities\Entity\ExternalEntityTypeInterface;
use Drupal\external_entities\Plugin\ExternalEntities\FieldMapper\GenericFieldMapper;
use Drupal\external_entities\Plugin\ExternalEntities\StorageClient\JsonApi;
use Drupal\external_entities\Plugin\ExternalEntities\StorageClient\RestClient;
use Drupal\external_entities\ResponseDecoder\ResponseDecoderFactoryInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * The class to test Rest client.
 *
 * @group ExternalEntities
 */
#[Group('ExternalEntities')]
class RestClientUnitTest extends UnitTestCase {

  /**
   * Test container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * The mocked token service.
   *
   * @var \Drupal\Core\Utility\Token|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $tokenServiceMock;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();
    $string_translation = $this->createStub(TranslationInterface::class);
    $container->set('string_translation', $string_translation);

    $response_decoder_factory = $this->createStub(ResponseDecoderFactoryInterface::class);
    $container->set('external_entities.response_decoder_factory', $response_decoder_factory);

    $debug_client_factory = $this->createStub(DebugClientFactory::class);
    $container->set('external_entities.rest.http_client_factory', $debug_client_factory);

    $http_client = $this->createStub(ClientInterface::class);
    $container->set('http_client', $http_client);

    $entity_type_manager = $this->createStub(EntityTypeManagerInterface::class);
    $container->set('entity_type.manager', $entity_type_manager);

    $entity_field_manager = $this->createStub(EntityFieldManagerInterface::class);
    $container->set('entity_field.manager', $entity_field_manager);

    $logger = $this->createStub(LoggerChannelInterface::class);
    $logger_factory = $this->createStub(LoggerChannelFactoryInterface::class);
    $logger_factory
      ->method('get')
      ->willReturn($logger);
    $container->set('logger.factory', $logger_factory);

    $database = $this->createStub(Connection::class);
    $container->set('database', $database);

    $container->set('state', $this->createStub(StateInterface::class));

    $event_dispatcher = $this->createStub(EventDispatcher::class);
    $event_dispatcher
      ->method('dispatch');
    $container->set('event_dispatcher', $event_dispatcher);

    // Create a mock for the token service.
    $this->tokenServiceMock = $this->createStub(Token::class);
    $this->tokenServiceMock->method('replacePlain')
      ->willReturnCallback(function ($string, array $data, array $options) {
        return $string;
      });
    $container->set('token', $this->tokenServiceMock);

    \Drupal::setContainer($container);
    $this->container = $container;
  }

  /**
   * Data provider for testRestPaging().
   *
   * Structure:
   * - expected paging
   * - pager config
   * - pager request
   * - test name.
   */
  public static function provideTestRestPagingNone() {
    return [
      // Check 1 complete page.
      [
        [
          [
            'parameters' => [],
            'start' => 2,
            'length' => 21,
          ],
        ],
        [
          'default_limit' => '',
          'type' => 'pagination',
          'page_parameter' => '',
          'page_parameter_type' => 'pagenum',
          'page_start_one' => FALSE,
          'page_size_parameter' => '',
          'page_size_parameter_type' => 'pagesize',
        ],
        ['start' => 2, 'length' => 21],
        '1 page no pager',
      ],
      // Check 1 page exeeding limits.
      [
        [
          [
            'parameters' => [],
            'start' => 2,
            'length' => 8,
          ],
        ],
        [
          'default_limit' => 10,
          'type' => 'pagination',
          'page_parameter' => '',
          'page_parameter_type' => 'startitem',
          'page_start_one' => TRUE,
          'page_size_parameter' => '',
          'page_size_parameter_type' => 'enditem',
        ],
        ['start' => 2, 'length' => 21],
        '1 page no pager exeeding limits',
      ],
    ];
  }

  /**
   * Data provider for testRestPaging().
   *
   * Structure:
   * - expected paging
   * - pager config
   * - pager request
   * - test name.
   */
  public static function provideTestRestPagingStartZero() {
    return [
      // Check 1 complete page.
      [
        [
          [
            'parameters' => [
              'p' => 0,
              'ps' => 50,
            ],
            'start' => 0,
            'length' => 50,
          ],
        ],
        [
          'default_limit' => 50,
          'type' => 'pagination',
          'page_parameter' => 'p',
          'page_parameter_type' => 'pagenum',
          'page_start_one' => FALSE,
          'page_size_parameter' => 'ps',
          'page_size_parameter_type' => 'pagesize',
        ],
        ['start' => 0, 'length' => 50],
        '1 complete page',
      ],
      // Check 1 partial page.
      [
        [
          [
            'parameters' => [
              'page' => 0,
              'pageSize' => 30,
            ],
            'start' => 5,
            'length' => 25,
          ],
        ],
        [
          'default_limit' => 50,
          'type' => 'pagination',
          'page_parameter' => 'page',
          'page_parameter_type' => 'pagenum',
          'page_start_one' => FALSE,
          'page_size_parameter' => 'pageSize',
          'page_size_parameter_type' => 'pagesize',
        ],
        ['start' => 5, 'length' => 25],
        '1 partial page',
      ],
      // Check 1 complete page + 1 item.
      [
        [
          [
            'parameters' => [
              'p' => 0,
              'ps' => 20,
            ],
            'start' => 0,
            'length' => 20,
          ],
          [
            'parameters' => [
              'p' => 1,
              'ps' => 20,
            ],
            'start' => 0,
            'length' => 1,
          ],
        ],
        [
          'default_limit' => 20,
          'type' => 'pagination',
          'page_parameter' => 'p',
          'page_parameter_type' => 'pagenum',
          'page_start_one' => FALSE,
          'page_size_parameter' => 'ps',
          'page_size_parameter_type' => 'pagesize',
        ],
        ['start' => 0, 'length' => 21],
        '1 complete page + 1 item',
      ],
      // Check 2 complete pages.
      [
        [
          [
            'parameters' => [
              'p' => 0,
              'ps' => 50,
            ],
            'start' => 0,
            'length' => 50,
          ],
          [
            'parameters' => [
              'p' => 1,
              'ps' => 50,
            ],
            'start' => 0,
            'length' => 50,
          ],
        ],
        [
          'default_limit' => 50,
          'type' => 'pagination',
          'page_parameter' => 'p',
          'page_parameter_type' => 'pagenum',
          'page_start_one' => FALSE,
          'page_size_parameter' => 'ps',
          'page_size_parameter_type' => 'pagesize',
        ],
        ['start' => 0, 'length' => 100],
        '2 complete pages',
      ],
      // Check 1 set over 2 pages.
      [
        [
          [
            'parameters' => [
              'p' => 0,
              'ps' => 50,
            ],
            'start' => 20,
            'length' => 30,
          ],
          [
            'parameters' => [
              'p' => 1,
              'ps' => 50,
            ],
            'start' => 0,
            'length' => 12,
          ],
        ],
        [
          'default_limit' => 50,
          'type' => 'pagination',
          'page_parameter' => 'p',
          'page_parameter_type' => 'pagenum',
          'page_start_one' => FALSE,
          'page_size_parameter' => 'ps',
          'page_size_parameter_type' => 'pagesize',
        ],
        ['start' => 20, 'length' => 42],
        '1 set over 2 pages',
      ],
      // Check 1 partial page starting on second page.
      [
        [
          [
            'parameters' => [
              'p' => 1,
              'ps' => 50,
            ],
            'start' => 0,
            'length' => 7,
          ],
        ],
        [
          'default_limit' => 50,
          'type' => 'pagination',
          'page_parameter' => 'p',
          'page_parameter_type' => 'pagenum',
          'page_start_one' => FALSE,
          'page_size_parameter' => 'ps',
          'page_size_parameter_type' => 'pagesize',
        ],
        ['start' => 50, 'length' => 7],
        '1 partial page starting on second page',
      ],
      // Check 4 result pages starting on second page.
      [
        [
          [
            'parameters' => [
              'p' => 1,
              'ps' => 50,
            ],
            'start' => 49,
            'length' => 1,
          ],
          [
            'parameters' => [
              'p' => 2,
              'ps' => 50,
            ],
            'start' => 0,
            'length' => 50,
          ],
          [
            'parameters' => [
              'p' => 3,
              'ps' => 50,
            ],
            'start' => 0,
            'length' => 50,
          ],
          [
            'parameters' => [
              'p' => 4,
              'ps' => 50,
            ],
            'start' => 0,
            'length' => 18,
          ],
        ],
        [
          'default_limit' => 50,
          'type' => 'pagination',
          'page_parameter' => 'p',
          'page_parameter_type' => 'pagenum',
          'page_start_one' => FALSE,
          'page_size_parameter' => 'ps',
          'page_size_parameter_type' => 'pagesize',
        ],
        ['start' => 99, 'length' => 119],
        '1 partial page starting on second page',
      ],
    ];
  }

  /**
   * Data provider for testRestPaging().
   *
   * Structure:
   * - expected paging
   * - pager config
   * - pager request
   * - test name.
   */
  public static function provideTestRestPagingStartOne() {
    return [
      // Check 1 complete page.
      [
        [
          [
            'parameters' => [
              'p' => 1,
              'ps' => 50,
            ],
            'start' => 0,
            'length' => 50,
          ],
        ],
        [
          'default_limit' => 50,
          'type' => 'pagination',
          'page_parameter' => 'p',
          'page_parameter_type' => 'pagenum',
          'page_start_one' => TRUE,
          'page_size_parameter' => 'ps',
          'page_size_parameter_type' => 'pagesize',
        ],
        ['start' => 0, 'length' => 50],
        '1 complete page, start 1',
      ],
      // Check 1 partial page.
      [
        [
          [
            'parameters' => [
              'p' => 1,
              'ps' => 30,
            ],
            'start' => 5,
            'length' => 25,
          ],
        ],
        [
          'default_limit' => 50,
          'type' => 'pagination',
          'page_parameter' => 'p',
          'page_parameter_type' => 'pagenum',
          'page_start_one' => TRUE,
          'page_size_parameter' => 'ps',
          'page_size_parameter_type' => 'pagesize',
        ],
        ['start' => 5, 'length' => 25],
        '1 partial page, start 1',
      ],
      // Check 1 complete page + 1 item.
      [
        [
          [
            'parameters' => [
              'p' => 1,
              'ps' => 20,
            ],
            'start' => 0,
            'length' => 20,
          ],
          [
            'parameters' => [
              'p' => 2,
              'ps' => 20,
            ],
            'start' => 0,
            'length' => 1,
          ],
        ],
        [
          'default_limit' => 20,
          'type' => 'pagination',
          'page_parameter' => 'p',
          'page_parameter_type' => 'pagenum',
          'page_start_one' => TRUE,
          'page_size_parameter' => 'ps',
          'page_size_parameter_type' => 'pagesize',
        ],
        ['start' => 0, 'length' => 21],
        '1 complete page + 1 item, start 1',
      ],
      // Check 2 complete pages.
      [
        [
          [
            'parameters' => [
              'p' => 1,
              'ps' => 50,
            ],
            'start' => 0,
            'length' => 50,
          ],
          [
            'parameters' => [
              'p' => 2,
              'ps' => 50,
            ],
            'start' => 0,
            'length' => 50,
          ],
        ],
        [
          'default_limit' => 50,
          'type' => 'pagination',
          'page_parameter' => 'p',
          'page_parameter_type' => 'pagenum',
          'page_start_one' => TRUE,
          'page_size_parameter' => 'ps',
          'page_size_parameter_type' => 'pagesize',
        ],
        ['start' => 0, 'length' => 100],
        '2 complete pages, start 1',
      ],
      // Check 1 set over 2 pages.
      [
        [
          [
            'parameters' => [
              'p' => 1,
              'ps' => 50,
            ],
            'start' => 20,
            'length' => 30,
          ],
          [
            'parameters' => [
              'p' => 2,
              'ps' => 50,
            ],
            'start' => 0,
            'length' => 12,
          ],
        ],
        [
          'default_limit' => 50,
          'type' => 'pagination',
          'page_parameter' => 'p',
          'page_parameter_type' => 'pagenum',
          'page_start_one' => TRUE,
          'page_size_parameter' => 'ps',
          'page_size_parameter_type' => 'pagesize',
        ],
        ['start' => 20, 'length' => 42],
        '1 set over 2 pages, start 1',
      ],
      // Check 1 partial page starting on second page.
      [
        [
          [
            'parameters' => [
              'p' => 2,
              'ps' => 50,
            ],
            'start' => 0,
            'length' => 7,
          ],
        ],
        [
          'default_limit' => 50,
          'type' => 'pagination',
          'page_parameter' => 'p',
          'page_parameter_type' => 'pagenum',
          'page_start_one' => TRUE,
          'page_size_parameter' => 'ps',
          'page_size_parameter_type' => 'pagesize',
        ],
        ['start' => 50, 'length' => 7],
        '1 partial page starting on second page, start 1',
      ],
      // Check 1 set over 3 pages starting on second page, start 1, end item.
      [
        [
          [
            'parameters' => [
              'p' => 2,
              'end' => 70,
            ],
            'start' => 5,
            'length' => 30,
          ],
          [
            'parameters' => [
              'p' => 3,
              'end' => 105,
            ],
            'start' => 0,
            'length' => 35,
          ],
          [
            'parameters' => [
              'p' => 4,
              'end' => 120,
            ],
            'start' => 0,
            'length' => 15,
          ],
        ],
        [
          'default_limit' => 35,
          'type' => 'pagination',
          'page_parameter' => 'p',
          'page_parameter_type' => 'pagenum',
          'page_start_one' => TRUE,
          'page_size_parameter' => 'end',
          'page_size_parameter_type' => 'enditem',
        ],
        ['start' => 40, 'length' => 80],
        '1 set over 3 pages starting on second page, start 1, end item',
      ],
    ];
  }

  /**
   * Data provider for testRestPaging().
   *
   * Structure:
   * - expected paging
   * - pager config
   * - pager request
   * - test name.
   */
  public static function provideTestRestPagingStartItem() {
    return [
      // Check 1 complete page.
      [
        [
          [
            'parameters' => [
              'at' => 0,
              'ps' => 50,
            ],
            'start' => 0,
            'length' => 50,
          ],
        ],
        [
          'default_limit' => 50,
          'type' => 'pagination',
          'page_parameter' => 'at',
          'page_parameter_type' => 'startitem',
          'page_start_one' => FALSE,
          'page_size_parameter' => 'ps',
          'page_size_parameter_type' => 'pagesize',
        ],
        ['start' => 0, 'length' => 50],
        '1 complete page, start item',
      ],
      // Check 1 partial page.
      [
        [
          [
            'parameters' => [
              'at' => 5,
              'ps' => 30,
            ],
            'start' => 0,
            'length' => 25,
          ],
        ],
        [
          'default_limit' => 50,
          'type' => 'pagination',
          'page_parameter' => 'at',
          'page_parameter_type' => 'startitem',
          'page_start_one' => FALSE,
          'page_size_parameter' => 'ps',
          'page_size_parameter_type' => 'pagesize',
        ],
        ['start' => 5, 'length' => 25],
        '1 partial page, start item',
      ],
      // Check 1 complete page, start 1.
      [
        [
          [
            'parameters' => [
              'at' => 1,
              'ps' => 50,
            ],
            'start' => 0,
            'length' => 50,
          ],
        ],
        [
          'default_limit' => 50,
          'type' => 'pagination',
          'page_parameter' => 'at',
          'page_parameter_type' => 'startitem',
          'page_start_one' => TRUE,
          'page_size_parameter' => 'ps',
          'page_size_parameter_type' => 'pagesize',
        ],
        ['start' => 0, 'length' => 50],
        '1 complete page, start item, start 1',
      ],
      // Check 1 set over 2 pages, start item, start 1.
      [
        [
          [
            'parameters' => [
              'at' => 21,
              'ps' => 45,
            ],
            'start' => 0,
            'length' => 45,
          ],
          [
            'parameters' => [
              'at' => 66,
              'ps' => 3,
            ],
            'start' => 0,
            'length' => 3,
          ],
        ],
        [
          'default_limit' => 45,
          'type' => 'pagination',
          'page_parameter' => 'at',
          'page_parameter_type' => 'startitem',
          'page_start_one' => TRUE,
          'page_size_parameter' => 'ps',
          'page_size_parameter_type' => 'pagesize',
        ],
        ['start' => 20, 'length' => 48],
        '1 set over 2 pages, start item, start 1',
      ],
      // Check 1 set over 2 pages, start item.
      [
        [
          [
            'parameters' => [
              'at' => 20,
              'ps' => 45,
            ],
            'start' => 0,
            'length' => 45,
          ],
          [
            'parameters' => [
              'at' => 65,
              'ps' => 3,
            ],
            'start' => 0,
            'length' => 3,
          ],
        ],
        [
          'default_limit' => 45,
          'type' => 'pagination',
          'page_parameter' => 'at',
          'page_parameter_type' => 'startitem',
          'page_start_one' => FALSE,
          'page_size_parameter' => 'ps',
          'page_size_parameter_type' => 'pagesize',
        ],
        ['start' => 20, 'length' => 48],
        '1 set over 2 pages, start item',
      ],
      // Check 1 set over 3 pages, start item.
      [
        [
          [
            'parameters' => [
              'at' => 20,
              'ps' => 45,
            ],
            'start' => 0,
            'length' => 45,
          ],
          [
            'parameters' => [
              'at' => 65,
              'ps' => 45,
            ],
            'start' => 0,
            'length' => 45,
          ],
          [
            'parameters' => [
              'at' => 110,
              'ps' => 5,
            ],
            'start' => 0,
            'length' => 5,
          ],
        ],
        [
          'default_limit' => 45,
          'type' => 'pagination',
          'page_parameter' => 'at',
          'page_parameter_type' => 'startitem',
          'page_start_one' => FALSE,
          'page_size_parameter' => 'ps',
          'page_size_parameter_type' => 'pagesize',
        ],
        ['start' => 20, 'length' => 95],
        '1 set over 3 pages, start item',
      ],
      // Check 1 set over 3 pages, start item, end item.
      [
        [
          [
            'parameters' => [
              'at' => 20,
              'end' => 64,
            ],
            'start' => 0,
            'length' => 45,
          ],
          [
            'parameters' => [
              'at' => 65,
              'end' => 109,
            ],
            'start' => 0,
            'length' => 45,
          ],
          [
            'parameters' => [
              'at' => 110,
              'end' => 114,
            ],
            'start' => 0,
            'length' => 5,
          ],
        ],
        [
          'default_limit' => 45,
          'type' => 'pagination',
          'page_parameter' => 'at',
          'page_parameter_type' => 'startitem',
          'page_start_one' => FALSE,
          'page_size_parameter' => 'end',
          'page_size_parameter_type' => 'enditem',
        ],
        ['start' => 20, 'length' => 95],
        '1 set over 3 pages, start item, end item',
      ],
      // Check 1 set over 3 pages, start item, end item, start 1.
      [
        [
          [
            'parameters' => [
              'at' => 21,
              'end' => 65,
            ],
            'start' => 0,
            'length' => 45,
          ],
          [
            'parameters' => [
              'at' => 66,
              'end' => 110,
            ],
            'start' => 0,
            'length' => 45,
          ],
          [
            'parameters' => [
              'at' => 111,
              'end' => 115,
            ],
            'start' => 0,
            'length' => 5,
          ],
        ],
        [
          'default_limit' => 45,
          'type' => 'pagination',
          'page_parameter' => 'at',
          'page_parameter_type' => 'startitem',
          'page_start_one' => TRUE,
          'page_size_parameter' => 'end',
          'page_size_parameter_type' => 'enditem',
        ],
        ['start' => 20, 'length' => 95],
        '1 set over 3 pages, start item, end item, start 1',
      ],
    ];
  }

  /**
   * Test value extraction from raw data using given mappings.
   *
   * @dataProvider provideTestRestPagingNone
   * @dataProvider provideTestRestPagingStartZero
   * @dataProvider provideTestRestPagingStartOne
   * @dataProvider provideTestRestPagingStartItem
   */
  #[DataProvider('provideTestRestPagingNone')]
  #[DataProvider('provideTestRestPagingStartZero')]
  #[DataProvider('provideTestRestPagingStartOne')]
  #[DataProvider('provideTestRestPagingStartItem')]
  public function testRestPaging($expected, $pager_config, $pager_request, $message) {
    global $base_url;
    $configuration = [
      'endpoint' => $base_url . '/external-entities-test/test',
      'endpoint_single' => '',
      'endpoint_count' => 1000,
      'endpoint_cache' => FALSE,
      'response_format' => 'json',
      'data_path' => [
        'list' => '',
        'single' => '',
        'keyed_by_id' => FALSE,
        'count' => '',
      ],
      'pager' => $pager_config,
      'api_key' => [
        'type' => 'none',
        'header_name' => '',
        'key' => '',
      ],
      'http' => [
        'headers' => '',
      ],
      'parameters' => [
        'list' => [],
        'list_param_mode' => 'query',
        'single' => [],
        'single_param_mode' => 'query',
      ],
      'filtering' => [
        'drupal' => TRUE,
        'basic' => FALSE,
        'basic_fields' => [],
        'list_support' => 'none',
        'list_join' => '',
      ],
    ];
    $plugin_id = 'rest_test';
    $plugin_definition = [];

    $rest_client = RestClient::create($this->container, $configuration, $plugin_id, $plugin_definition);
    $paging = $rest_client->getPagingQueryParameters($pager_request['start'], $pager_request['length']);
    $this->assertEquals($expected, $paging, 'Failed with ' . $message);
  }

  /**
   * Data provider for testRestQueryParameters().
   *
   * Structure:
   * - expected transliteration array
   * - expected query array
   * - query config
   * - api_config
   * - filtering_config
   * - id
   * - more_parameters
   * - test name.
   */
  public static function provideTestRestQueryParameters() {
    return [
      // No query.
      [
        [
          'source' => [],
          'drupal' => [],
        ],
        [
          'list' => [],
          'single' => [],
        ],
        [
          'list' => [],
          'list_param_mode' => 'query',
          'single' => [],
          'single_param_mode' => 'query',
        ],
        [
          'type' => 'none',
          'header_name' => '',
          'key' => '',
        ],
        [
          'drupal' => TRUE,
          'basic' => FALSE,
          'basic_fields' => [],
          'list_support' => 'none',
          'list_join' => '',
        ],
        NULL,
        [],
        'no queries',
      ],
      // Check other parameters only.
      [
        [
          'source' => [
            [
              'field' => 'testprop',
              'value' => 'some_value',
              'operator' => '=',
            ],
          ],
          'drupal' => [],
        ],
        [
          'list' => [
            'testprop' => 'some_value',
          ],
          'single' => [],
        ],
        [
          'list' => [],
          'list_param_mode' => 'query',
          'single' => [],
          'single_param_mode' => 'query',
        ],
        [
          'type' => 'none',
          'header_name' => '',
          'key' => '',
        ],
        [
          'drupal' => TRUE,
          'basic' => TRUE,
          'basic_fields' => [],
          'list_support' => 'none',
          'list_join' => '',
        ],
        NULL,
        [
          [
            'field' => 'field_test',
            'value' => 'some_value',
            'operator' => '=',
          ],
        ],
        'other parameters only',
      ],
      // Check other parameters only no source filtering.
      [
        [
          'source' => [],
          'drupal' => [
            [
              'field' => 'field_test',
              'value' => 'some_value',
              'operator' => '=',
            ],
          ],
        ],
        [
          'list' => [],
          'single' => [],
        ],
        [
          'list' => [],
          'list_param_mode' => 'query',
          'single' => [],
          'single_param_mode' => 'query',
        ],
        [
          'type' => 'none',
          'header_name' => '',
          'key' => '',
        ],
        [
          'drupal' => TRUE,
          'basic' => FALSE,
          'basic_fields' => [],
          'list_support' => 'none',
          'list_join' => '',
        ],
        NULL,
        [
          [
            'field' => 'field_test',
            'value' => 'some_value',
            'operator' => '=',
          ],
        ],
        'other parameters only no source filtering',
      ],
      // Check other parameters only with 2 fields.
      [
        [
          'source' => [
            [
              'field' => 'testprop',
              'value' => 'some_value',
              'operator' => '=',
            ],
            [
              'field' => 'testprop2',
              'value' => '5|6|7',
              'operator' => '=',
            ],
          ],
          'drupal' => [],
        ],
        [
          'list' => ['testprop' => 'some_value', 'testprop2' => '5|6|7'],
          'single' => [],
        ],
        [
          'list' => [],
          'list_param_mode' => 'query',
          'single' => [],
          'single_param_mode' => 'query',
        ],
        [
          'type' => 'none',
          'header_name' => '',
          'key' => '',
        ],
        [
          'drupal' => TRUE,
          'basic' => TRUE,
          'basic_fields' => [],
          'list_support' => 'implode',
          'list_join' => '|',
        ],
        NULL,
        [
          [
            'field' => 'field_test',
            'value' => 'some_value',
            'operator' => '=',
          ],
          [
            'field' => 'field_other',
            'value' => [5, 6, 7],
            'operator' => 'IN',
          ],
        ],
        'other parameters only with 2 fields',
      ],
      // Check other parameters only with 2 fields and limited basic fields.
      [
        [
          'source' => [
            [
              'field' => 'testprop2',
              'value' => 'some_other_value',
              'operator' => '=',
            ],
          ],
          'drupal' => [
            [
              'field' => 'field_test',
              'value' => 'some_value',
              'operator' => '=',
            ],
          ],
        ],
        [
          'list' => [
            'testprop2' => 'some_other_value',
          ],
          'single' => [],
        ],
        [
          'list' => [],
          'list_param_mode' => 'query',
          'single' => [],
          'single_param_mode' => 'query',
        ],
        [
          'type' => 'none',
          'header_name' => '',
          'key' => '',
        ],
        [
          'drupal' => TRUE,
          'basic' => TRUE,
          'basic_fields' => ['testprop2'],
          'list_support' => 'none',
          'list_join' => '',
        ],
        NULL,
        [
          [
            'field' => 'field_other',
            'value' => 'some_other_value',
            'operator' => '=',
          ],
          [
            'field' => 'field_test',
            'value' => 'some_value',
            'operator' => '=',
          ],
        ],
        'other parameters only with 2 fields and limited basic fields',
      ],
      // Check other parameters only with 2 fields no source filtering.
      [
        [
          'source' => [],
          'drupal' => [
            [
              'field' => 'field_test',
              'value' => 'some_value',
              'operator' => '=',
            ],
            [
              'field' => 'field_other',
              'value' => [5, 6, 7],
              'operator' => 'IN',
            ],
          ],
        ],
        [
          'list' => [],
          'single' => [],
        ],
        [
          'list' => [],
          'list_param_mode' => 'query',
          'single' => [],
          'single_param_mode' => 'query',
        ],
        [
          'type' => 'none',
          'header_name' => '',
          'key' => '',
        ],
        [
          'drupal' => TRUE,
          'basic' => FALSE,
          'basic_fields' => [],
          'list_support' => 'none',
          'list_join' => '',
        ],
        NULL,
        [
          [
            'field' => 'field_test',
            'value' => 'some_value',
            'operator' => '=',
          ],
          [
            'field' => 'field_other',
            'value' => [5, 6, 7],
            'operator' => 'IN',
          ],
        ],
        'other parameters only with 2 fields no source filtering',
      ],
      // Check single parameters only.
      [
        [
          'source' => [],
          'drupal' => [],
        ],
        [
          'list' => [],
          'single' => ['field_name_1' => 'some_value', 'field_test' => 'other_value'],
        ],
        [
          'list' => [],
          'list_param_mode' => 'query',
          'single' => ['field_name_1' => 'some_value', 'field_test' => 'other_value'],
          'single_param_mode' => 'query',
        ],
        [
          'type' => 'none',
          'header_name' => '',
          'key' => '',
        ],
        [
          'drupal' => TRUE,
          'basic' => FALSE,
          'basic_fields' => [],
          'list_support' => 'none',
          'list_join' => '',
        ],
        NULL,
        [],
        'single parameters only',
      ],
      // Check list parameters only.
      [
        [
          'source' => [],
          'drupal' => [],
        ],
        [
          'list' => ['field_name_1' => 'some_value', 'field_test' => 'other_value'],
          'single' => [],
        ],
        [
          'list' => ['field_name_1' => 'some_value', 'field_test' => 'other_value'],
          'list_param_mode' => 'query',
          'single' => [],
          'single_param_mode' => 'query',
        ],
        [
          'type' => 'none',
          'header_name' => '',
          'key' => '',
        ],
        [
          'drupal' => TRUE,
          'basic' => FALSE,
          'basic_fields' => [],
          'list_support' => 'none',
          'list_join' => '',
        ],
        NULL,
        [],
        'list parameters only',
      ],
      // Check list and single parameters.
      [
        [
          'source' => [],
          'drupal' => [],
        ],
        [
          'list' => ['field_name_1' => 'some_value'],
          'single' => ['field_name_2' => 'other_value'],
        ],
        [
          'list' => ['field_name_1' => 'some_value'],
          'list_param_mode' => 'query',
          'single' => ['field_name_2' => 'other_value'],
          'single_param_mode' => 'query',
        ],
        [
          'type' => 'none',
          'header_name' => 'bla',
          'key' => 'bli',
        ],
        [
          'drupal' => TRUE,
          'basic' => FALSE,
          'basic_fields' => [],
          'list_support' => 'none',
          'list_join' => '',
        ],
        NULL,
        [],
        'list and single parameters',
      ],
      // Check other parameters with single and unsupported list parameters.
      [
        [
          'source' => [
            [
              'field' => 'testprop',
              'value' => 'some_value',
              'operator' => '=',
            ],
          ],
          'drupal' => [
            [
              'field' => 'field_other',
              'value' => [5, 6, 7],
              'operator' => 'IN',
            ],
          ],
        ],
        [
          'list' => [
            'testprop' => 'some_value',
            'field_name_1' => 'some_value',
            'field_other' => 'other_value',
          ],
          'single' => [
            'field_name_2' => 'some_value',
            'field_other' => 'other_value',
          ],
        ],
        [
          'list' => [
            'field_name_1' => 'some_value',
            'field_other' => 'other_value',
          ],
          'list_param_mode' => 'query',
          'single' => [
            'field_name_2' => 'some_value',
            'field_other' => 'other_value',
          ],
          'single_param_mode' => 'query',
        ],
        [
          'type' => 'custom',
          'header_name' => 'bla',
          'key' => 'bli',
        ],
        [
          'drupal' => TRUE,
          'basic' => TRUE,
          'basic_fields' => [],
          'list_support' => 'none',
          'list_join' => '',
        ],
        NULL,
        [
          [
            'field' => 'field_test',
            'value' => 'some_value',
            'operator' => '=',
          ],
          [
            'field' => 'field_other',
            'value' => [5, 6, 7],
            'operator' => 'IN',
          ],
        ],
        'other parameters with single and unsupported list parameters',
      ],
      // Check other parameters with single and repeated list parameters.
      [
        [
          'source' => [
            [
              'field' => 'testprop',
              'value' => 'some_value',
              'operator' => '=',
            ],
            [
              'field' => '&testprop2',
              'value' => [5, 6, 7],
              'operator' => '=',
            ],
          ],
          'drupal' => [],
        ],
        [
          'list' => 'testprop=some_value&field_name_1=some_value&field_other=other_value&testprop2=5&testprop2=6&testprop2=7',
          'single' => [
            'field_name_2' => 'some_value',
            'field_other' => 'other_value',
          ],
        ],
        [
          'list' => [
            'field_name_1' => 'some_value',
            'field_other' => 'other_value',
          ],
          'list_param_mode' => 'query',
          'single' => [
            'field_name_2' => 'some_value',
            'field_other' => 'other_value',
          ],
          'single_param_mode' => 'query',
        ],
        [
          'type' => 'custom',
          'header_name' => 'bla',
          'key' => 'bli',
        ],
        [
          'drupal' => TRUE,
          'basic' => TRUE,
          'basic_fields' => [],
          'list_support' => 'repeat',
          'list_join' => '',
        ],
        NULL,
        [
          [
            'field' => 'field_test',
            'value' => 'some_value',
            'operator' => '=',
          ],
          [
            'field' => 'field_other',
            'value' => [5, 6, 7],
            'operator' => 'IN',
          ],
        ],
        'other parameters with single and repeated list parameters',
      ],
      // Check other parameters with single and indexed list parameters.
      [
        [
          'source' => [
            [
              'field' => 'testprop',
              'value' => 'some_value',
              'operator' => '=',
            ],
            [
              'field' => 'testprop2[0]',
              'value' => 5,
              'operator' => '=',
            ],
            [
              'field' => 'testprop2[1]',
              'value' => 6,
              'operator' => '=',
            ],
            [
              'field' => 'testprop2[2]',
              'value' => 7,
              'operator' => '=',
            ],
          ],
          'drupal' => [],
        ],
        [
          'list' => [
            'testprop' => 'some_value',
            'field_name_1' => 'some_value',
            'field_other' => 'other_value',
            'testprop2[0]' => 5,
            'testprop2[1]' => 6,
            'testprop2[2]' => 7,
          ],
          'single' => [
            'field_name_2' => 'some_value',
            'field_other' => 'other_value',
          ],
        ],
        [
          'list' => [
            'field_name_1' => 'some_value',
            'field_other' => 'other_value',
          ],
          'list_param_mode' => 'query',
          'single' => [
            'field_name_2' => 'some_value',
            'field_other' => 'other_value',
          ],
          'single_param_mode' => 'query',
        ],
        [
          'type' => 'custom',
          'header_name' => 'bla',
          'key' => 'bli',
        ],
        [
          'drupal' => TRUE,
          'basic' => TRUE,
          'basic_fields' => [],
          'list_support' => 'indexed',
          'list_join' => '',
        ],
        NULL,
        [
          [
            'field' => 'field_test',
            'value' => 'some_value',
            'operator' => '=',
          ],
          [
            'field' => 'field_other',
            'value' => [5, 6, 7],
            'operator' => 'IN',
          ],
        ],
        'other parameters with single and indexed list parameters',
      ],
      // Check other parameters with single and unindexed list parameters.
      [
        [
          'source' => [
            [
              'field' => 'testprop',
              'value' => 'some_value',
              'operator' => '=',
            ],
            [
              'field' => 'testprop2',
              'value' => [5, 6, 7],
              'operator' => '=',
            ],
          ],
          'drupal' => [],
        ],
        [
          'list' => [
            'testprop' => 'some_value',
            'field_name_1' => 'some_value',
            'field_other' => 'other_value',
            'testprop2' => [5, 6, 7],
          ],
          'single' => [
            'field_name_2' => 'some_value',
            'field_other' => 'other_value',
          ],
        ],
        [
          'list' => [
            'field_name_1' => 'some_value',
            'field_other' => 'other_value',
          ],
          'list_param_mode' => 'query',
          'single' => [
            'field_name_2' => 'some_value',
            'field_other' => 'other_value',
          ],
          'single_param_mode' => 'query',
        ],
        [
          'type' => 'custom',
          'header_name' => 'bla',
          'key' => 'bli',
        ],
        [
          'drupal' => TRUE,
          'basic' => TRUE,
          'basic_fields' => [],
          'list_support' => 'unindexed',
          'list_join' => '',
        ],
        NULL,
        [
          [
            'field' => 'field_test',
            'value' => 'some_value',
            'operator' => '=',
          ],
          [
            'field' => 'field_other',
            'value' => [5, 6, 7],
            'operator' => 'IN',
          ],
        ],
        'other parameters with single and unindexed list parameters',
      ],
      // Check other parameters with single and post list parameters.
      [
        [
          'source' => [
            [
              'field' => 'testprop',
              'value' => 'some_value',
              'operator' => '=',
            ],
            [
              'field' => 'testprop2',
              'value' => [5, 6, 7],
              'operator' => '=',
            ],
          ],
          'drupal' => [],
        ],
        [
          'list' => [
            'testprop' => 'some_value',
            'field_name_1' => 'some_value',
            'field_other' => 'other_value',
            'testprop2' => [5, 6, 7],
          ],
          'single' => [
            'field_name_2' => 'some_value',
            'field_other' => 'other_value',
          ],
        ],
        [
          'list' => [
            'field_name_1' => 'some_value',
            'field_other' => 'other_value',
          ],
          'list_param_mode' => 'query',
          'single' => [
            'field_name_2' => 'some_value',
            'field_other' => 'other_value',
          ],
          'single_param_mode' => 'query',
        ],
        [
          'type' => 'custom',
          'header_name' => 'bla',
          'key' => 'bli',
        ],
        [
          'drupal' => TRUE,
          'basic' => TRUE,
          'basic_fields' => [],
          'list_support' => 'post',
          'list_join' => '',
        ],
        NULL,
        [
          [
            'field' => 'field_test',
            'value' => 'some_value',
            'operator' => '=',
          ],
          [
            'field' => 'field_other',
            'value' => [5, 6, 7],
            'operator' => 'IN',
          ],
        ],
        'other parameters with single and post list parameters',
      ],
      // Check other parameters with single and list parameters no source
      // filtering.
      [
        [
          'source' => [],
          'drupal' => [
            [
              'field' => 'field_test',
              'value' => 'some_value',
              'operator' => '=',
            ],
            [
              'field' => 'field_other',
              'value' => [5, 6, 7],
              'operator' => 'IN',
            ],
          ],
        ],
        [
          'list' => [
            'field_name_1' => 'some_value',
            'field_other' => 'other_value',
          ],
          'single' => [
            'field_name_2' => 'some_value',
            'field_other' => 'other_value',
          ],
        ],
        [
          'list' => [
            'field_name_1' => 'some_value',
            'field_other' => 'other_value',
          ],
          'list_param_mode' => 'query',
          'single' => [
            'field_name_2' => 'some_value',
            'field_other' => 'other_value',
          ],
          'single_param_mode' => 'query',
        ],
        [
          'type' => 'custom',
          'header_name' => 'bla',
          'key' => 'bli',
        ],
        [
          'drupal' => TRUE,
          'basic' => FALSE,
          'basic_fields' => [],
          'list_support' => 'none',
          'list_join' => '',
        ],
        NULL,
        [
          [
            'field' => 'field_test',
            'value' => 'some_value',
            'operator' => '=',
          ],
          [
            'field' => 'field_other',
            'value' => [5, 6, 7],
            'operator' => 'IN',
          ],
        ],
        'other parameters with single and list parameters no source filtering',
      ],
      // Check other, single and list parameters with authentication.
      [
        [
          'source' => [
            [
              'field' => 'testprop',
              'value' => 'some_value',
              'operator' => '=',
            ],
          ],
          'drupal' => [
            [
              'field' => 'field_other',
              'value' => [5, 6, 7],
              'operator' => 'IN',
            ],
          ],
        ],
        [
          'list' => [
            'testprop' => 'some_value',
            'field_name_1' => 'some_value',
            'field_other' => 'other_value',
            'api-key' => '0123456789ABCDEF',
          ],
          'single' => [
            'field_name_2' => 'some_value',
            'field_other' => 'other_value',
            'api-key' => '0123456789ABCDEF',
          ],
        ],
        [
          'list' => ['field_name_1' => 'some_value', 'field_other' => 'other_value'],
          'list_param_mode' => 'query',
          'single' => ['field_name_2' => 'some_value', 'field_other' => 'other_value'],
          'single_param_mode' => 'query',
        ],
        [
          'type' => 'query',
          'header_name' => 'api-key',
          'key' => '0123456789ABCDEF',
        ],
        [
          'drupal' => TRUE,
          'basic' => TRUE,
          'basic_fields' => [],
          'list_support' => 'none',
          'list_join' => '',
        ],
        NULL,
        [
          [
            'field' => 'field_test',
            'value' => 'some_value',
            'operator' => '=',
          ],
          [
            'field' => 'field_other',
            'value' => [5, 6, 7],
            'operator' => 'IN',
          ],
        ],
        'other, single and list parameters with authentication',
      ],
      // Check other, single and list parameters with authentication no source
      // filtering.
      [
        [
          'source' => [],
          'drupal' => [
            [
              'field' => 'field_test',
              'value' => 'some_value',
              'operator' => '=',
            ],
            [
              'field' => 'field_other',
              'value' => [5, 6, 7],
              'operator' => 'IN',
            ],
          ],
        ],
        [
          'list' => [
            'field_name_1' => 'some_value',
            'field_other' => 'other_value',
            'api-key' => '0123456789ABCDEF',
          ],
          'single' => [
            'field_name_2' => 'some_value',
            'field_other' => 'other_value',
            'api-key' => '0123456789ABCDEF',
          ],
        ],
        [
          'list' => ['field_name_1' => 'some_value', 'field_other' => 'other_value'],
          'list_param_mode' => 'query',
          'single' => ['field_name_2' => 'some_value', 'field_other' => 'other_value'],
          'single_param_mode' => 'query',
        ],
        [
          'type' => 'query',
          'header_name' => 'api-key',
          'key' => '0123456789ABCDEF',
        ],
        [
          'drupal' => TRUE,
          'basic' => FALSE,
          'basic_fields' => [],
          'list_support' => 'none',
          'list_join' => '',
        ],
        NULL,
        [
          [
            'field' => 'field_test',
            'value' => 'some_value',
            'operator' => '=',
          ],
          [
            'field' => 'field_other',
            'value' => [5, 6, 7],
            'operator' => 'IN',
          ],
        ],
        'other, single and list parameters with authentication no source filtering',
      ],
      // Check authentication only.
      [
        [
          'source' => [],
          'drupal' => [],
        ],
        [
          'list' => ['api-key' => '0123456789ABCDEF'],
          'single' => ['api-key' => '0123456789ABCDEF'],
        ],
        [
          'list' => [],
          'list_param_mode' => 'query',
          'single' => [],
          'single_param_mode' => 'query',
        ],
        [
          'type' => 'query',
          'header_name' => 'api-key',
          'key' => '0123456789ABCDEF',
        ],
        [
          'drupal' => TRUE,
          'basic' => FALSE,
          'basic_fields' => [],
          'list_support' => 'none',
          'list_join' => '',
        ],
        NULL,
        [],
        'authentication only',
      ],
      // Check single parameters with id.
      [
        [
          'source' => [],
          'drupal' => [],
        ],
        [
          'list' => [],
          'single' => ['field_name_1' => 'some_value', 'field_test' => 'other_value', 'id' => 'entity-42'],
        ],
        [
          'list' => [],
          'list_param_mode' => 'query',
          'single' => ['field_name_1' => 'some_value', 'field_test' => 'other_value', 'id' => 'entity-{id}'],
          'single_param_mode' => 'query',
        ],
        [
          'type' => 'none',
          'header_name' => '',
          'key' => '',
        ],
        [
          'drupal' => TRUE,
          'basic' => FALSE,
          'basic_fields' => [],
          'list_support' => 'none',
          'list_join' => '',
        ],
        42,
        [],
        'single parameters with id',
      ],
    ];
  }

  /**
   * Test query parameters generation.
   *
   * @dataProvider provideTestRestQueryParameters
   */
  #[DataProvider('provideTestRestQueryParameters')]
  public function testRestQueryParameters($exp_transliteration, $expected, $query_config, $api_config, $filtering_config, $id, $more_parameters, $message) {
    global $base_url;
    // Field field_test mapping.
    $field_mapper1 = $this->createStub(GenericFieldMapper::class);
    $field_mapper1
      ->method('getMappedSourceFieldName')
      ->willReturn('testprop');

    // Field field_other mapping.
    $field_mapper2 = $this->createStub(GenericFieldMapper::class);
    $field_mapper2
      ->method('getMappedSourceFieldName')
      ->willReturn('testprop2');

    // Test external entity.
    $xntt = $this->createStub(ConfigurableExternalEntityTypeInterface::class);
    $xntt
      ->method('getFieldMapper')
      ->willReturnCallback(function (string $field_name) use ($field_mapper1, $field_mapper2) {
        return match ($field_name) {
          'field_test' => $field_mapper1,
          'field_other' => $field_mapper2,
          default => throw new \LogicException()
        };
      });

    $configuration = [
      ExternalEntityTypeInterface::XNTT_TYPE_PROP => $xntt,
      'endpoint' => $base_url . '/external-entities-test/test',
      'endpoint_single' => '',
      'endpoint_count' => 1000,
      'endpoint_cache' => FALSE,
      'response_format' => 'json',
      'data_path' => [
        'list' => '',
        'single' => '',
        'keyed_by_id' => FALSE,
        'count' => '',
      ],
      'pager' => [
        'default_limit' => 50,
        'type' => 'pagination',
        'page_parameter' => 'p',
        'page_parameter_type' => 'pagenum',
        'page_start_one' => FALSE,
        'page_size_parameter' => 'ps',
        'page_size_parameter_type' => 'pagesize',
      ],
      'api_key' => $api_config,
      'http' => [
        'headers' => '',
      ],
      'parameters' => $query_config,
      'filtering' => $filtering_config,
    ];
    $plugin_id = 'rest_test';
    $plugin_definition = [];

    $rest_client = RestClient::create($this->container, $configuration, $plugin_id, $plugin_definition);

    $trans_parameters = $rest_client->transliterateDrupalFilters($more_parameters, ['caller' => 'query']);
    $this->assertEquals($exp_transliteration, $trans_parameters, 'Failed for transliteration with ' . $message);

    $list_query = $rest_client->getQueryParameters(
      '',
      [
        'operation' => 'list',
        'method' => 'GET',
        'filters' => $trans_parameters['source'],
        'paging' => [],
      ]
    );
    $this->assertEquals($expected['list'], $list_query, 'Failed for list query with ' . $message);

    $single_query = $rest_client->getQueryParameters(
      '',
      [
        'operation' => 'read',
        'method' => 'GET',
        'id' => $id,
        'data' => $trans_parameters['source'],
      ]
    );
    $this->assertEquals($expected['single'], $single_query, 'Failed for single query with ' . $message);
  }

  /**
   * Data provider for testRestHttpHeaders().
   *
   * Structure:
   * - expected header array
   * - API config
   * - header config
   * - test name.
   */
  public static function provideTestRestHttpHeaders() {
    return [
      // No headers.
      [
        [],
        [
          'type' => 'none',
          'header_name' => '',
          'key' => '',
        ],
        [
          'headers' => '',
        ],
        'no headers',
      ],
      // Check 2 lines header.
      [
        [
          'x-api-version' => '2.0',
          'Authorization' => 'ApiKey key="FEDCBA9876543210"',
        ],
        [
          'type' => 'none',
          'header_name' => '',
          'key' => '',
        ],
        [
          'headers' => "x-api-version: 2.0\nAuthorization: ApiKey key=\"FEDCBA9876543210\"",
        ],
        '2 lines header',
      ],
      // Check bearer header.
      [
        [
          'Authorization' => 'Bearer FEDCBA9876543210',
        ],
        [
          'type' => 'bearer',
          'header_name' => 'Authorization',
          'key' => 'Bearer FEDCBA9876543210',
        ],
        [
          'headers' => '',
        ],
        'bearer header',
      ],
      // Check 1 line header with bearer header.
      [
        [
          'x-api-version' => '2.0',
          'Authorization' => 'Bearer FEDCBA9876543210',
        ],
        [
          'type' => 'bearer',
          'header_name' => 'Authorization',
          'key' => 'Bearer FEDCBA9876543210',
        ],
        [
          'headers' => "x-api-version: 2.0",
        ],
        '1 line header with bearer header',
      ],
      // Check 2 lines header with duplicate authentication header.
      [
        [
          'x-api-version' => '2.0',
          'Authorization' => 'Bearer FEDCBA9876543210',
        ],
        [
          'type' => 'bearer',
          'header_name' => 'Authorization',
          'key' => 'Bearer FEDCBA9876543210',
        ],
        [
          'headers' => "x-api-version: 2.0\nAuthorization: ApiKey key=\"FEDCBA9876543210\"",
        ],
        '2 lines header with duplicate authentication header',
      ],
      // Check custom authentication header.
      [
        [
          'WWW-Authenticate' => 'Basic realm=someone@somewhere.org',
        ],
        [
          'type' => 'custom',
          'header_name' => 'WWW-Authenticate',
          'key' => 'Basic realm=someone@somewhere.org',
        ],
        [
          'headers' => '',
        ],
        'custom authentication header',
      ],
      // Check query authentication with no header.
      [
        [],
        [
          'type' => 'query',
          'header_name' => 'key',
          'key' => 'FEDCBA9876543210',
        ],
        [
          'headers' => '',
        ],
        'query authentication with no header',
      ],
    ];
  }

  /**
   * Test HTTP headers generation.
   *
   * @dataProvider provideTestRestHttpHeaders
   */
  #[DataProvider('provideTestRestHttpHeaders')]
  public function testRestHttpHeaders($expected, $api_config, $header_config, $message) {
    global $base_url;
    $configuration = [
      'endpoint' => $base_url . '/external-entities-test/test',
      'endpoint_single' => '',
      'endpoint_count' => 1000,
      'endpoint_cache' => FALSE,
      'response_format' => 'json',
      'data_path' => [
        'list' => '',
        'single' => '',
        'keyed_by_id' => FALSE,
        'count' => '',
      ],
      'pager' => [
        'default_limit' => 50,
        'type' => 'pagination',
        'page_parameter' => 'p',
        'page_parameter_type' => 'pagenum',
        'page_start_one' => FALSE,
        'page_size_parameter' => 'ps',
        'page_size_parameter_type' => 'pagesize',
      ],
      'api_key' => $api_config,
      'http' => $header_config,
      'parameters' => [
        'list' => [],
        'single' => [],
      ],
      'filtering' => [
        'drupal' => TRUE,
        'basic' => FALSE,
        'basic_fields' => [],
        'list_support' => 'none',
        'list_join' => '',
      ],
    ];
    $plugin_id = 'rest_test';
    $plugin_definition = [];

    $rest_client = RestClient::create($this->container, $configuration, $plugin_id, $plugin_definition);

    $headers = $rest_client->getHttpHeaders();
    $this->assertEquals($expected, $headers, 'Failed with ' . $message);
  }

  /**
   * Tests the id filter added to single JSON:API requests.
   *
   * @dataProvider provideTestJsonApiSingleQueryParameters
   */
  #[DataProvider('provideTestJsonApiSingleQueryParameters')]
  public function testJsonApiSingleQueryParameters($expected, $id_source_field, $single_parameters, $id, $message) {
    $xntt = $this->createStub(ConfigurableExternalEntityTypeInterface::class);
    $xntt->method('isNew')->willReturn(FALSE);
    $id_mapper = NULL;
    if ($id_source_field !== NULL) {
      $id_mapper = $this->createStub(GenericFieldMapper::class);
      $id_mapper->method('getMappedSourceFieldName')->willReturn($id_source_field);
    }
    $xntt->method('getFieldMapper')->willReturn($id_mapper);

    $configuration = [
      ExternalEntityTypeInterface::XNTT_TYPE_PROP => $xntt,
      'endpoint' => 'https://example.com/jsonapi/node/article',
      'response_format' => 'json',
      'parameters' => [
        'single' => $single_parameters,
        'single_param_mode' => 'query',
        'list' => [],
      ],
      'filtering' => [
        'drupal' => TRUE,
        'list_support' => 'none',
      ],
    ];

    $client = JsonApi::create($this->container, $configuration, 'rest_test', []);

    $single_query = $client->getQueryParameters(
      '',
      [
        'operation' => 'read',
        'method' => 'GET',
        'id' => $id,
      ]
    );
    $this->assertEquals($expected, $single_query, 'Failed for ' . $message);
  }

  /**
   * Data provider for testJsonApiSingleQueryParameters().
   *
   * Structure:
   * - expected single query parameters
   * - the source field name mapped to the id (NULL when unmapped)
   * - user configured single parameters
   * - the requested entity id
   * - assertion message.
   *
   * @return array
   *   Test cases.
   */
  public static function provideTestJsonApiSingleQueryParameters() {
    return [
      [
        ['filter[uuid]' => 'abc-123'],
        'uuid',
        [],
        'abc-123',
        'id filter is added for a single request',
      ],
      [
        ['filter[uuid]' => 'abc-123'],
        'attributes.uuid',
        [],
        'abc-123',
        'attributes. prefix is stripped from the id field',
      ],
      [
        ['filter[uuid]' => 'abc-123'],
        'uuid',
        ['filter[uuid]' => '{id}'],
        'abc-123',
        'id filter is not doubled when already configured',
      ],
      [
        ['filter[id]' => 'abc-123'],
        NULL,
        [],
        'abc-123',
        'id field falls back to "id" when unmapped',
      ],
    ];
  }

}
