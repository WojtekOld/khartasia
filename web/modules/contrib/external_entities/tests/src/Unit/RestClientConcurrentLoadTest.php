<?php

declare(strict_types=1);

namespace Drupal\Tests\external_entities\Unit;

use Drupal\Core\State\StateInterface;
use Drupal\external_entities\GuzzleHttp\DebugClientFactory;
use PHPUnit\Framework\Attributes\Group;
use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\Token;
use Drupal\Tests\UnitTestCase;
use Drupal\external_entities\Plugin\ExternalEntities\StorageClient\RestClient;
use Drupal\external_entities\ResponseDecoder\ResponseDecoderFactoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Tests the concurrent loading feature of RestClient::loadMultiple().
 *
 * @coversDefaultClass \Drupal\external_entities\Plugin\ExternalEntities\StorageClient\RestClient
 * @group ExternalEntities
 */
#[Group('ExternalEntities')]
class RestClientConcurrentLoadTest extends UnitTestCase {

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

    $entity_type_manager = $this->createStub(EntityTypeManagerInterface::class);
    $container->set('entity_type.manager', $entity_type_manager);

    $debug_client_factory = $this->createStub(DebugClientFactory::class);
    $container->set('external_entities.rest.http_client_factory', $debug_client_factory);

    $entity_field_manager = $this->createStub(EntityFieldManagerInterface::class);
    $container->set('entity_field.manager', $entity_field_manager);

    // Passive for successful loads; the failure test re-registers a mock.
    $logger_factory = $this->createStub(LoggerChannelFactoryInterface::class);
    $logger_factory
      ->method('get')
      ->willReturn($this->createStub(LoggerChannelInterface::class));
    $container->set('logger.factory', $logger_factory);

    $database = $this->createStub(Connection::class);
    $container->set('database', $database);

    $container->set('state', $this->createStub(StateInterface::class));

    $event_dispatcher = $this->createStub(EventDispatcher::class);
    $container->set('event_dispatcher', $event_dispatcher);

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
   * Creates a RestClient with a Guzzle MockHandler.
   *
   * @param array $responses
   *   Array of Response objects for MockHandler.
   * @param array $config_overrides
   *   Configuration overrides.
   *
   * @return \Drupal\external_entities\Plugin\ExternalEntities\StorageClient\RestClient
   *   The configured RestClient.
   */
  protected function createRestClientWithMockHttp(array $responses, array $config_overrides = []): RestClient {
    $mock = new MockHandler($responses);
    $handler = HandlerStack::create($mock);
    $http_client = new Client(['handler' => $handler]);

    // The decoder must be a concrete class because decode() is static
    // on SerializationInterface but called as instance method by RestClient.
    $decoder = new class implements SerializationInterface {

      /**
       * {@inheritdoc}
       */
      public static function decode($raw) {
        return json_decode($raw, TRUE);
      }

      /**
       * {@inheritdoc}
       */
      public static function encode($data) {
        return json_encode($data);
      }

      /**
       * {@inheritdoc}
       */
      public static function getFileExtension() {
        return 'json';
      }

    };

    $decoder_factory = $this->createStub(ResponseDecoderFactoryInterface::class);
    $decoder_factory->method('getDecoder')
      ->willReturn($decoder);

    $this->container->set('external_entities.response_decoder_factory', $decoder_factory);
    $this->container->set('http_client', $http_client);

    $configuration = array_merge([
      'endpoint' => 'https://api.example.com/entities',
      'endpoint_options' => [
        'single' => 'https://api.example.com/entities/{id}',
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
        'default_limit' => 50,
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
    ], $config_overrides);

    return RestClient::create($this->container, $configuration, 'rest_test', []);
  }

  /**
   * Tests that loadMultiple uses concurrent requests.
   *
   * @covers ::loadMultiple
   */
  public function testLoadMultipleConcurrentBasic(): void {
    $responses = [
      new Response(200, [], json_encode(['id' => 'ext-1', 'title' => 'Entity 1'])),
      new Response(200, [], json_encode(['id' => 'ext-2', 'title' => 'Entity 2'])),
      new Response(200, [], json_encode(['id' => 'ext-3', 'title' => 'Entity 3'])),
    ];

    $client = $this->createRestClientWithMockHttp($responses);
    $result = $client->loadMultiple(['ext-1', 'ext-2', 'ext-3']);

    $this->assertCount(3, $result);
    $this->assertEquals('Entity 1', $result['ext-1']['title']);
    $this->assertEquals('Entity 2', $result['ext-2']['title']);
    $this->assertEquals('Entity 3', $result['ext-3']['title']);
  }

  /**
   * Tests that failed requests are handled gracefully.
   *
   * @covers ::loadMultiple
   */
  public function testLoadMultipleConcurrentWithFailure(): void {
    $responses = [
      new Response(200, [], json_encode(['id' => 'ext-1', 'title' => 'Entity 1'])),
      new Response(500, [], 'Internal Server Error'),
      new Response(200, [], json_encode(['id' => 'ext-3', 'title' => 'Entity 3'])),
    ];

    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->atLeastOnce())
      ->method('error');
    $logger_factory = $this->createStub(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $this->container->set('logger.factory', $logger_factory);

    $client = $this->createRestClientWithMockHttp($responses);
    $result = $client->loadMultiple(['ext-1', 'ext-2', 'ext-3']);

    // ext-1 and ext-3 should load, ext-2 should fail gracefully.
    $this->assertCount(2, $result);
    $this->assertArrayHasKey('ext-1', $result);
    $this->assertArrayNotHasKey('ext-2', $result);
    $this->assertArrayHasKey('ext-3', $result);
    $this->assertEquals('Entity 1', $result['ext-1']['title']);
    $this->assertEquals('Entity 3', $result['ext-3']['title']);
  }

  /**
   * Tests that concurrency=1 uses sequential loading (original behavior).
   *
   * @covers ::loadMultiple
   */
  public function testLoadMultipleSequentialFallback(): void {
    $responses = [
      new Response(200, [], json_encode(['id' => 'ext-1', 'title' => 'Entity 1'])),
      new Response(200, [], json_encode(['id' => 'ext-2', 'title' => 'Entity 2'])),
    ];

    $client = $this->createRestClientWithMockHttp($responses, [
      'endpoint_options' => [
        'single' => 'https://api.example.com/entities/{id}',
        'count' => '',
        'count_mode' => 'entities',
        'cache' => FALSE,
        'limit_qcount' => 0,
        'limit_qtime' => 0,
        'concurrency' => 1,
        'requests_by_user' => FALSE,
      ],
    ]);
    $result = $client->loadMultiple(['ext-1', 'ext-2']);

    $this->assertCount(2, $result);
    $this->assertEquals('Entity 1', $result['ext-1']['title']);
    $this->assertEquals('Entity 2', $result['ext-2']['title']);
  }

  /**
   * Tests that empty ID list returns empty array.
   *
   * @covers ::loadMultiple
   */
  public function testLoadMultipleConcurrentEmpty(): void {
    $client = $this->createRestClientWithMockHttp([]);
    $result = $client->loadMultiple([]);

    $this->assertEmpty($result);
  }

  /**
   * Tests that default concurrency configuration is 5.
   *
   * @covers ::defaultConfiguration
   */
  public function testDefaultConcurrencyConfig(): void {
    $client = $this->createRestClientWithMockHttp([]);
    $config = $client->defaultConfiguration();

    $this->assertEquals(5, $config['endpoint_options']['concurrency']);
  }

}
