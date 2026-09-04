<?php

namespace Drupal\Tests\tmgmt_deepl\Unit\Plugin\KeyType;

use DeepL\DeepLException;
use DeepL\Translator as DeepLTranslator;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tmgmt_deepl\DeepLClientFactoryInterface;
use Drupal\tmgmt_deepl\Plugin\KeyType\DeepLApiKeyType;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests the DeepLApiKeyType plugin.
 *
 * @covers \Drupal\tmgmt_deepl\Plugin\KeyType\DeepLApiKeyType
 * @group tmgmt_deepl
 */
class DeepLApiKeyTypeTest extends UnitTestCase {

  /**
   * Creates the plugin under test.
   *
   * @return \Drupal\tmgmt_deepl\Plugin\KeyType\DeepLApiKeyType
   *   The plugin instance.
   */
  protected function createPlugin(): DeepLApiKeyType {
    $plugin = new DeepLApiKeyType(
      [],
      'deepl_api_key',
      ['id' => 'deepl_api_key'],
      $this->createMock(LoggerInterface::class),
      $this->createMock(DeepLClientFactoryInterface::class),
    );
    $plugin->setStringTranslation($this->getStringTranslationStub());
    return $plugin;
  }

  /**
   * The data provider for testValidateKeyValueEmpty.
   *
   * @return array<string, array{string|null}>
   *   Array of test data.
   */
  public static function dataProviderEmptyKeyValues(): array {
    return [
      'empty string' => [''],
      'null value' => [NULL],
    ];
  }

  /**
   * Tests ::validateKeyValue with empty key values.
   *
   * @dataProvider dataProviderEmptyKeyValues
   */
  public function testValidateKeyValueEmpty(?string $key_value): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('setErrorByName')
      ->with('key_value');
    $form = [];
    $this->createPlugin()->validateKeyValue($form, $form_state, $key_value);
  }

  /**
   * Tests ::validateKeyValue with a non-empty key triggers an API error.
   */
  public function testValidateKeyValueApiError(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with($this->stringContains('DeepL API key validation failed'));

    $deepl_translator = $this->createMock(DeepLTranslator::class);
    $deepl_translator->expects($this->once())
      ->method('getUsage')
      ->willThrowException(new DeepLException('Invalid API key'));

    $factory = $this->createMock(DeepLClientFactoryInterface::class);
    $factory->expects($this->once())
      ->method('create')
      ->with('invalid-key-for-testing', 'tmgmt_deepl', '')
      ->willReturn($deepl_translator);

    $plugin = new DeepLApiKeyType(
      [],
      'deepl_api_key',
      ['id' => 'deepl_api_key'],
      $logger,
      $factory,
    );
    $plugin->setStringTranslation($this->getStringTranslationStub());

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('setErrorByName')
      ->with('key_value');
    $form = [];
    $plugin->validateKeyValue($form, $form_state, 'invalid-key-for-testing');
  }

  /**
   * Tests the ::create static factory method.
   */
  public function testCreate(): void {
    $container = $this->createMock(ContainerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory->method('get')->with('tmgmt_deepl')->willReturn($logger);
    $client_factory = $this->createMock(DeepLClientFactoryInterface::class);
    $container->method('get')->willReturnCallback(function ($service_id) use ($logger_factory, $client_factory) {
      return match ($service_id) {
        'logger.factory' => $logger_factory,
        'tmgmt_deepl.client_factory' => $client_factory,
        default => NULL,
      };
    });

    $plugin = DeepLApiKeyType::create($container, [], 'deepl_api_key', ['id' => 'deepl_api_key']);
    // @phpstan-ignore-next-line
    $this->assertInstanceOf(DeepLApiKeyType::class, $plugin);
  }

}
