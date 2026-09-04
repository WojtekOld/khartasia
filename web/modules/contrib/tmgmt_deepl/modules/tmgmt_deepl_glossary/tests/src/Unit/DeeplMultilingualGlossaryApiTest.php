<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Unit;

use DeepL\DeepLClient;
use DeepL\DeepLException;
use DeepL\MultilingualGlossaryDictionaryEntries;
use DeepL\MultilingualGlossaryDictionaryInfo;
use DeepL\MultilingualGlossaryInfo;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApi;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Tests the DeeplMultilingualGlossaryApi class.
 *
 * @covers \Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApi
 * @group tmgmt_deepl_glossary
 */
class DeeplMultilingualGlossaryApiTest extends UnitTestCase {

  /**
   * Tests the method ::setTranslator.
   */
  public function testSetTranslator(): void {
    $translator = $this->createMock(TranslatorInterface::class);

    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryApi_Test&\PHPUnit\Framework\MockObject\MockObject $class */
    $class = $this->createClassMock();
    $class->setTranslator($translator);

    // @phpstan-ignore-next-line.
    $this->assertInstanceOf(TranslatorInterface::class, $class->translator);
  }

  /**
   * Tests the method ::getDeeplClient.
   */
  public function testGetDeeplClient(): void {
    $key_id = 'test_deepl_key_entity_id';
    $api_key_value = 'valid-deepl-api-key-from-mock';

    $translator = $this->createMock(TranslatorInterface::class);
    $translator->expects($this->exactly(2))
      ->method('getSetting')
      ->willReturnCallback(function ($setting) use ($key_id) {
        switch ($setting) {
          case 'auth_key_entity':
            return $key_id;

          case 'omit_partner_id':
            return TRUE;

          default:
            return '';
        }
      });

    // Mock a key entity and related methods.
    $key_entity_mock = $this->createMock(KeyInterface::class);
    $key_entity_mock->expects($this->once())
      ->method('getKeyValue')
      ->willReturn($api_key_value);

    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryApi_Test&MockObject $class */
    $class = $this->createClassMock();

    // Mock key repository and related methods.
    $key_repository_mock = $this->createMock(KeyRepositoryInterface::class);
    $key_repository_mock->expects($this->once())
      ->method('getKey')
      ->with($key_id)
      ->willReturn($key_entity_mock);

    // Set dependencies for the test class.
    $class->setKeyRepository($key_repository_mock);
    $class->translator = $translator;

    $actual = $class->getDeeplClient();

    // @phpstan-ignore-next-line.
    $this->assertInstanceOf(DeepLClient::class, $actual);
  }

  /**
   * Tests the custom exception for getDeeplClient.
   */
  public function testGetDeeplClientException(): void {
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryApi_Test&MockObject $class */
    $class = $this->createClassMock();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('DeepL client not set.');

    $class->getDeeplClient();
  }

  /**
   * Data provider for testIsFreeAccount.
   *
   * @return array<string, array{string, bool}>
   *   Tuples of: API key value, expected isFreeAccount result.
   */
  public static function dataProviderIsFreeAccount(): array {
    return [
      'free account key (ends with :fx)' => ['test-free-key:fx', TRUE],
      'pro account key (no :fx suffix)' => ['test-pro-key', FALSE],
    ];
  }

  /**
   * Tests the method ::isFreeAccount.
   *
   * @param string $api_key_value
   *   The API key value to test.
   * @param bool $expected
   *   Whether isFreeAccount should return true.
   *
   * @dataProvider dataProviderIsFreeAccount
   */
  public function testIsFreeAccount(string $api_key_value, bool $expected): void {
    $key_id = 'test_deepl_key_entity_id';

    $translator = $this->createMock(TranslatorInterface::class);
    $translator->expects($this->once())
      ->method('getSetting')
      ->with('auth_key_entity')
      ->willReturn($key_id);

    $key_entity_mock = $this->createMock(KeyInterface::class);
    $key_entity_mock->expects($this->once())
      ->method('getKeyValue')
      ->willReturn($api_key_value);

    $key_repository_mock = $this->createMock(KeyRepositoryInterface::class);
    $key_repository_mock->expects($this->once())
      ->method('getKey')
      ->with($key_id)
      ->willReturn($key_entity_mock);

    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryApi_Test&MockObject $class */
    $class = $this->createClassMock();
    $class->setKeyRepository($key_repository_mock);
    $class->translator = $translator;

    $this->assertSame($expected, $class->isFreeAccount());
  }

  /**
   * Tests ::isFreeAccount returns FALSE when no translator is set.
   */
  public function testIsFreeAccountNoTranslator(): void {
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryApi_Test&MockObject $class */
    $class = $this->createClassMock();
    $this->assertFalse($class->isFreeAccount());
  }

  /**
   * Tests the method ::createMultilingualGlossary.
   */
  public function testCreateMultilingualGlossary(): void {
    $name = 'Test Multilingual Glossary';
    $dictionaries = [
      [
        'source_lang' => 'en',
        'target_lang' => 'de',
        'entries' => ['hello' => 'hallo', 'world' => 'welt'],
      ],
    ];

    // Mock the MultilingualGlossaryInfo object.
    $glossary_info = $this->createMock(MultilingualGlossaryInfo::class);

    // Mock a translator object.
    $translator = $this->createMock(TranslatorInterface::class);

    // Mock the DeepL client.
    $deepl_client = $this->createMock(DeepLClient::class);
    $deepl_client->expects($this->once())
      ->method('createMultilingualGlossary')
      ->with(
        $name,
        $this->callback(function (array $ml_dictionaries) {
          // Check that the dictionary entries are converted to the
          // DeepL object type.
          $this->assertCount(1, $ml_dictionaries);
          $this->assertInstanceOf(MultilingualGlossaryDictionaryEntries::class, $ml_dictionaries[0]);
          return TRUE;
        })
      )
      ->willReturn($glossary_info);

    // Mock the DeeplMultilingualGlossaryApi class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryApi_Test&MockObject $class */
    $class = $this->createClassMock(['getDeeplClient']);

    $class->setTranslator($translator);

    $class->expects($this->once())
      ->method('getDeeplClient')
      ->willReturn($deepl_client);

    $result = $class->createMultilingualGlossary($name, $dictionaries);
    // Check for the expected result.
    $this->assertInstanceOf(MultilingualGlossaryInfo::class, $result);
  }

  /**
   * Tests the method ::createMultilingualGlossary with a DeepLException.
   */
  public function testCreateMultilingualGlossaryException(): void {
    $name = 'Test Multilingual Glossary';
    // Use valid entries so the internal DeepL client validation passes,
    // allowing the execution to reach the actual mocked API call.
    $dictionaries = [
      [
        'source_lang' => 'en',
        'target_lang' => 'de',
        'entries' => ['valid' => 'gültig'],
      ],
    ];

    // Create a DeepLException, which is what the service is designed to catch
    // for actual DeepL API response errors.
    $exception = new DeepLException('API Response Error');

    // Mock a translator object.
    $translator = $this->createMock(TranslatorInterface::class);

    // Mock the DeepL client library.
    $deepl_client = $this->createMock(DeepLClient::class);
    // This expectation is for the API call *after* input validation.
    $deepl_client->expects($this->once())
      ->method('createMultilingualGlossary')
      ->willThrowException($exception);

    // Mock the messenger.
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())
      ->method('addMessage')
      ->with($exception->getMessage(), 'error');

    // Mock the logger.
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with($exception);

    // Mock the DeeplMultilingualGlossaryApi class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryApi_Test&MockObject $class */
    $class = $this->createClassMock(['getDeeplClient']);

    $class->setTranslator($translator);
    $class->setLogger($logger);
    $class->setMessenger($messenger);

    // Mock getDeeplClient to return the DeepL client throwing API exception.
    $class->expects($this->once())
      ->method('getDeeplClient')
      ->willReturn($deepl_client);

    // The execution should now pass validation and reach the mocked API call.
    $result = $class->createMultilingualGlossary($name, $dictionaries);

    // Check for the expected result.
    $this->assertNull($result);
  }

  /**
   * Tests the method ::createMultilingualGlossary when input entries are empty.
   */
  public function testCreateMultilingualGlossaryInputValidationError(): void {
    $name = 'Test Multilingual Glossary';
    // Use valid entries so the internal DeepL client validation passes,
    // allowing the execution to reach the actual mocked API call.
    $dictionaries = [
      [
        'source_lang' => 'en',
        'target_lang' => 'de',
        'entries' => [],
      ],
    ];

    // Create a DeepLException, which is what the service is designed to catch
    // for actual DeepL API response errors.
    $exception = new DeepLException('Input contains no entries');

    // Mock a translator object.
    $translator = $this->createMock(TranslatorInterface::class);

    // Mock the DeepL client library.
    $deepl_client = $this->createMock(DeepLClient::class);

    // Mock the messenger.
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())
      ->method('addMessage')
      ->with($exception->getMessage(), 'error');

    // Mock the logger.
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with($exception);

    // Mock the DeeplMultilingualGlossaryApi class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryApi_Test&MockObject $class */
    $class = $this->createClassMock(['getDeeplClient']);

    $class->setTranslator($translator);
    $class->setLogger($logger);
    $class->setMessenger($messenger);

    // Mock getDeeplClient to return the DeepL client throwing the exception.
    $class->expects($this->once())
      ->method('getDeeplClient')
      ->willReturn($deepl_client);

    // The execution should successfully pass and reach the mocked API call.
    $result = $class->createMultilingualGlossary($name, $dictionaries);

    // Check for the expected result.
    $this->assertNull($result);
  }

  /**
   * Tests the method ::updateMultilingualGlossary.
   */
  public function testUpdateMultilingualGlossary(): void {
    $glossary_id = 'test_glossary_id';
    $new_name = 'Updated Test Glossary';
    $dictionaries = [
      ['source_lang' => 'en', 'target_lang' => 'de', 'entries' => ['update' => 'aktualisierung']],
    ];

    // Mock updated MultilingualGlossaryInfo.
    $updated_glossary_info = $this->createMock(MultilingualGlossaryInfo::class);

    // Mock a translator object.
    $translator = $this->createMock(TranslatorInterface::class);

    // Mock the DeepL client.
    $deepl_client = $this->createMock(DeepLClient::class);
    $deepl_client->expects($this->once())
      ->method('getMultilingualGlossary')
      ->with($glossary_id)
      ->willReturn($this->createMock(MultilingualGlossaryInfo::class));
    $deepl_client->expects($this->once())
      ->method('updateMultilingualGlossary')
      ->willReturn($updated_glossary_info);

    // Mock the DeeplMultilingualGlossaryApi class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryApi_Test&MockObject $class */
    $class = $this->createClassMock(['getDeeplClient']);

    $class->setTranslator($translator);

    $class->expects($this->once())
      ->method('getDeeplClient')
      ->willReturn($deepl_client);

    $result = $class->updateMultilingualGlossary($glossary_id, $new_name, $dictionaries);

    $this->assertInstanceOf(MultilingualGlossaryInfo::class, $result);
  }

  /**
   * Tests the method ::updateMultilingualGlossary with DeepLException.
   */
  public function testUpdateMultilingualGlossaryException(): void {
    $glossary_id = 'test_glossary_id';
    $new_name = 'Updated Test Glossary';
    $dictionaries = [];

    // Create DeepLException.
    $exception = new DeepLException('Update error');

    // Mock a translator object.
    $translator = $this->createMock(TranslatorInterface::class);

    // Mock the DeepL client.
    $deepl_client = $this->createMock(DeepLClient::class);
    $deepl_client->expects($this->once())
      ->method('updateMultilingualGlossary')
      ->willThrowException($exception);

    // Mock the messenger.
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())
      ->method('addMessage')
      ->with($exception->getMessage(), 'error');

    // Mock the logger.
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with($exception);

    // Mock the DeeplMultilingualGlossaryApi class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryApi_Test&MockObject $class */
    $class = $this->createClassMock(['getDeeplClient']);

    $class->setTranslator($translator);
    $class->setLogger($logger);
    $class->setMessenger($messenger);

    $class->expects($this->once())
      ->method('getDeeplClient')
      ->willReturn($deepl_client);

    $result = $class->updateMultilingualGlossary($glossary_id, $new_name, $dictionaries);

    $this->assertNull($result);
  }

  /**
   * Tests the method ::getMultilingualGlossaryMetadata.
   */
  public function testGetMultilingualGlossaryMetadata(): void {
    $glossary_id = 'test_glossary_id';

    // Mock glossary info.
    $glossary_info = $this->createMock(MultilingualGlossaryInfo::class);

    // Mock translator.
    $translator = $this->createMock(TranslatorInterface::class);

    // Mock DeepL client.
    $deepl_client = $this->createMock(DeepLClient::class);
    $deepl_client->expects($this->once())
      ->method('getMultilingualGlossary')
      ->with($glossary_id)
      ->willReturn($glossary_info);

    // Mock class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryApi_Test&MockObject $class */
    $class = $this->createClassMock(['getDeeplClient']);

    $class->setTranslator($translator);
    $class->expects($this->once())
      ->method('getDeeplClient')
      ->willReturn($deepl_client);

    $result = $class->getMultilingualGlossaryMetadata($glossary_id);

    $this->assertEquals($glossary_info, $result);
  }

  /**
   * Tests the method ::getMultilingualGlossaryMetadata with DeepLException.
   */
  public function testGetMultilingualGlossaryMetadataException(): void {
    $glossary_id = 'test_glossary_id';

    // Create exception.
    $exception = new DeepLException('Metadata error');

    // Mock translator.
    $translator = $this->createMock(TranslatorInterface::class);

    // Mock client.
    $deepl_client = $this->createMock(DeepLClient::class);
    $deepl_client->expects($this->once())
      ->method('getMultilingualGlossary')
      ->willThrowException($exception);

    // Mock messenger.
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())
      ->method('addMessage')
      ->with($exception->getMessage(), 'error');

    // Mock logger.
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with($exception);

    // Mock class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryApi_Test&MockObject $class */
    $class = $this->createClassMock(['getDeeplClient']);

    $class->setTranslator($translator);
    $class->setLogger($logger);
    $class->setMessenger($messenger);
    $class->expects($this->once())
      ->method('getDeeplClient')
      ->willReturn($deepl_client);

    $result = $class->getMultilingualGlossaryMetadata($glossary_id);

    $this->assertNull($result);
  }

  /**
   * Tests the method ::replaceMultilingualGlossaryDictionary.
   */
  public function testReplaceMultilingualGlossaryDictionary(): void {
    $glossary_id = 'test_glossary_id';
    $source_lang = 'en';
    $target_lang = 'de';
    $entries = ['replace' => 'ersetzen'];

    // Mock replaced dictionary.
    $replaced_dictionary = $this->createMock(MultilingualGlossaryDictionaryInfo::class);

    // Mock translator.
    $translator = $this->createMock(TranslatorInterface::class);

    // Mock client.
    $deepl_client = $this->createMock(DeepLClient::class);
    $deepl_client->expects($this->once())
      ->method('replaceMultilingualGlossaryDictionary')
      ->willReturn($replaced_dictionary);

    // Mock class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryApi_Test&MockObject $class */
    $class = $this->createClassMock(['getDeeplClient']);

    $class->setTranslator($translator);
    $class->expects($this->once())
      ->method('getDeeplClient')
      ->willReturn($deepl_client);

    $result = $class->replaceMultilingualGlossaryDictionary($glossary_id, $source_lang, $target_lang, $entries);

    $this->assertEquals($replaced_dictionary, $result);
  }

  /**
   * Tests method ::replaceMultilingualGlossaryDictionary with DeepLException.
   */
  public function testReplaceMultilingualGlossaryDictionaryException(): void {
    $glossary_id = 'test_glossary_id';
    $source_lang = 'en';
    $target_lang = 'de';
    $entries = ['replace' => 'ersetzen'];

    $exception = new DeepLException('Replace dictionary error');

    $translator = $this->createMock(TranslatorInterface::class);

    $deepl_client = $this->createMock(DeepLClient::class);
    $deepl_client->expects($this->once())
      ->method('replaceMultilingualGlossaryDictionary')
      ->willThrowException($exception);

    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())
      ->method('addMessage')
      ->with($exception->getMessage(), 'error');

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with($exception);

    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryApi_Test&MockObject $class */
    $class = $this->createClassMock(['getDeeplClient']);

    $class->setTranslator($translator);
    $class->setLogger($logger);
    $class->setMessenger($messenger);
    $class->expects($this->once())
      ->method('getDeeplClient')
      ->willReturn($deepl_client);

    $result = $class->replaceMultilingualGlossaryDictionary($glossary_id, $source_lang, $target_lang, $entries);

    $this->assertNull($result);
  }

  /**
   * Tests the method ::deleteMultilingualGlossaryDictionary.
   */
  public function testDeleteMultilingualGlossaryDictionary(): void {
    $glossary_id = 'test_glossary_id';
    $source_lang = 'en';
    $target_lang = 'de';

    // Mock translator.
    $translator = $this->createMock(TranslatorInterface::class);

    // Mock client.
    $deepl_client = $this->createMock(DeepLClient::class);
    $deepl_client->expects($this->once())
      ->method('deleteMultilingualGlossaryDictionary')
      ->with($glossary_id, NULL, $source_lang, $target_lang);

    // Mock class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryApi_Test&MockObject $class */
    $class = $this->createClassMock(['getDeeplClient']);

    $class->setTranslator($translator);
    $class->expects($this->once())
      ->method('getDeeplClient')
      ->willReturn($deepl_client);

    $result = $class->deleteMultilingualGlossaryDictionary($glossary_id, $source_lang, $target_lang);

    $this->assertTrue($result);
  }

  /**
   * Tests method ::deleteMultilingualGlossaryDictionary with DeepLException.
   */
  public function testDeleteMultilingualGlossaryDictionaryException(): void {
    $glossary_id = 'test_glossary_id';
    $source_lang = 'en';
    $target_lang = 'de';

    // Create exception.
    $exception = new DeepLException('Delete dictionary error');

    // Mock translator.
    $translator = $this->createMock(TranslatorInterface::class);

    // Mock client.
    $deepl_client = $this->createMock(DeepLClient::class);
    $deepl_client->expects($this->once())
      ->method('deleteMultilingualGlossaryDictionary')
      ->willThrowException($exception);

    // Mock messenger.
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())
      ->method('addMessage')
      ->with($exception->getMessage(), 'error');

    // Mock logger.
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with($exception);

    // Mock class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryApi_Test&MockObject $class */
    $class = $this->createClassMock(['getDeeplClient']);

    $class->setTranslator($translator);
    $class->setLogger($logger);
    $class->setMessenger($messenger);
    $class->expects($this->once())
      ->method('getDeeplClient')
      ->willReturn($deepl_client);

    $result = $class->deleteMultilingualGlossaryDictionary($glossary_id, $source_lang, $target_lang);

    $this->assertFalse($result);
  }

  /**
   * Tests the method ::getMultilingualGlossaries.
   */
  public function testGetMultilingualGlossaries(): void {
    $mock_glossaries = [
      $this->createMock(MultilingualGlossaryInfo::class),
      $this->createMock(MultilingualGlossaryInfo::class),
    ];

    // Mock a translator object.
    $translator = $this->createMock(TranslatorInterface::class);

    // Mock the DeepL client.
    $deepl_client = $this->createMock(DeepLClient::class);
    $deepl_client->expects($this->once())
      ->method('listMultilingualGlossaries')
      ->willReturn($mock_glossaries);

    // Mock the DeeplMultilingualGlossaryApi class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryApi_Test&MockObject $class */
    $class = $this->createClassMock(['getDeeplClient']);

    $class->setTranslator($translator);

    $class->expects($this->once())
      ->method('getDeeplClient')
      ->willReturn($deepl_client);

    $result = $class->getMultilingualGlossaries();

    $this->assertEquals($mock_glossaries, $result);
  }

  /**
   * Tests the method ::getMultilingualGlossaries with a DeepLException.
   */
  public function testGetMultilingualGlossariesException(): void {
    // Create an DeepLException.
    $exception = new DeepLException('API Error');

    // Mock a translator object.
    $translator = $this->createMock(TranslatorInterface::class);

    // Mock the DeepL client.
    $deepl_client = $this->createMock(DeepLClient::class);
    $deepl_client->expects($this->once())
      ->method('listMultilingualGlossaries')
      ->willThrowException($exception);

    // Mock the messenger.
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())
      ->method('addMessage')
      ->with($exception->getMessage(), 'error');

    // Mock the logger.
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with($exception);

    // Mock the DeeplMultilingualGlossaryApi class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryApi_Test&MockObject $class */
    $class = $this->createClassMock(['getDeeplClient']);

    $class->setTranslator($translator);
    $class->setLogger($logger);
    $class->setMessenger($messenger);

    $class->expects($this->once())
      ->method('getDeeplClient')
      ->willReturn($deepl_client);

    $result = $class->getMultilingualGlossaries();

    // Check for the expected result.
    $this->assertEmpty($result);
  }

  /**
   * Tests the method ::deleteMultilingualGlossary.
   */
  public function testDeleteMultilingualGlossary(): void {
    $glossary_id = 'test_multilingual_glossary_id';

    // Mock a translator object.
    $translator = $this->createMock(TranslatorInterface::class);

    // Mock the DeepL client.
    $deepl_client = $this->createMock(DeepLClient::class);
    $deepl_client->expects($this->once())
      ->method('deleteMultilingualGlossary')
      ->with($glossary_id);

    // Mock the DeeplMultilingualGlossaryApi class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryApi_Test&MockObject $class */
    $class = $this->createClassMock(['getDeeplClient']);

    $class->setTranslator($translator);

    $class->expects($this->once())
      ->method('getDeeplClient')
      ->willReturn($deepl_client);
    $result = $class->deleteMultilingualGlossary($glossary_id);
    $this->assertTrue($result);
  }

  /**
   * Tests the method ::deleteMultilingualGlossary with a DeepLException.
   */
  public function testDeleteMultilingualGlossaryException(): void {
    $glossary_id = 'test_multilingual_glossary_id';
    // Create an DeepLException.
    $exception = new DeepLException('API Error');

    // Mock a translator object.
    $translator = $this->createMock(TranslatorInterface::class);

    // Mock the DeepL client.
    $deepl_client = $this->createMock(DeepLClient::class);
    $deepl_client->expects($this->once())
      ->method('deleteMultilingualGlossary')
      ->willThrowException($exception);

    // Mock the messenger.
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())
      ->method('addMessage')
      ->with($exception->getMessage(), 'error');

    // Mock the logger.
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with($exception);

    // Mock the DeeplMultilingualGlossaryApi class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryApi_Test&MockObject $class */
    $class = $this->createClassMock(['getDeeplClient']);

    $class->setTranslator($translator);
    $class->setLogger($logger);
    $class->setMessenger($messenger);

    $class->expects($this->once())
      ->method('getDeeplClient')
      ->willReturn($deepl_client);

    $result = $class->deleteMultilingualGlossary($glossary_id);

    // Check for the expected result.
    $this->assertFalse($result);
  }

  /**
   * Tests the method ::getMultilingualGlossaryEntries.
   */
  public function testGetMultilingualGlossaryEntries(): void {
    $glossary_id = 'test_multilingual_glossary_id';
    $source_lang = 'en';
    $target_lang = 'de';
    $mock_entries_map = ['hello' => 'hallo', 'world' => 'welt'];
    $expected_entries = [
      ['subject' => 'hello', 'definition' => 'hallo'],
      ['subject' => 'world', 'definition' => 'welt'],
    ];

    $dictionary_entries = $this->createMock(MultilingualGlossaryDictionaryEntries::class);
    $dictionary_entries->entries = $mock_entries_map;

    // Mock a translator object.
    $translator = $this->createMock(TranslatorInterface::class);

    // Mock the DeepL client.
    $deepl_client = $this->createMock(DeepLClient::class);
    $deepl_client->expects($this->once())
      ->method('getMultilingualGlossaryEntries')
      ->with($glossary_id, $source_lang, $target_lang)
      ->willReturn([$dictionary_entries]);

    // Mock the DeeplMultilingualGlossaryApi class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryApi_Test&MockObject $class */
    $class = $this->createClassMock(['getDeeplClient']);

    $class->setTranslator($translator);

    $class->expects($this->once())
      ->method('getDeeplClient')
      ->willReturn($deepl_client);

    $result = $class->getMultilingualGlossaryEntries($glossary_id, $source_lang, $target_lang);
    $this->assertEquals($expected_entries, $result);
  }

  /**
   * Tests the method ::getMultilingualGlossaryEntries with a DeepLException.
   */
  public function testGetMultilingualGlossaryEntriesException(): void {
    $glossary_id = 'test_glossary_id';
    $source_lang = 'en';
    $target_lang = 'de';
    // Create an DeepLException.
    $exception = new DeepLException('API Error');

    // Mock a translator object.
    $translator = $this->createMock(TranslatorInterface::class);

    // Mock the DeepL client.
    $deepl_client = $this->createMock(DeepLClient::class);
    $deepl_client->expects($this->once())
      ->method('getMultilingualGlossaryEntries')
      ->willThrowException($exception);

    // Mock the messenger.
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())
      ->method('addMessage')
      ->with($exception->getMessage(), 'error');

    // Mock the logger.
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with($exception);

    // Mock the DeeplMultilingualGlossaryApi class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryApi_Test&MockObject $class */
    $class = $this->createClassMock(['getDeeplClient']);

    $class->setTranslator($translator);
    $class->setLogger($logger);
    $class->setMessenger($messenger);

    $class->expects($this->once())
      ->method('getDeeplClient')
      ->willReturn($deepl_client);

    $result = $class->getMultilingualGlossaryEntries($glossary_id, $source_lang, $target_lang);

    // Check for the expected result.
    $this->assertEmpty($result);
  }

  /**
   * Creates and returns a test class mock.
   *
   * @param list<non-empty-string> $only_methods
   *   An array of names for methods to be configurable.
   *
   * @return \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryApi_Test|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked class.
   */
  protected function createClassMock(array $only_methods = []): DeeplMultilingualGlossaryApi_Test|MockObject {
    return $this->getMockBuilder(DeeplMultilingualGlossaryApi_Test::class)
      ->disableOriginalConstructor()
      ->onlyMethods($only_methods)
      ->getMock();
  }

}

// @codingStandardsIgnoreStart
/**
 * Mocked DeeplMultilingualGlossaryApi class for tests.
 *
 * It extends the real class but provides public setters for injected services
 * and public access to protected properties for testing.
 */
class DeeplMultilingualGlossaryApi_Test extends DeeplMultilingualGlossaryApi {

  /**
   * {@inheritdoc}
   */
  public LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  public MessengerInterface $messenger;

  /**
   * Public access to the protected property.
   *
   * @var \Drupal\tmgmt\TranslatorInterface
   */
  public TranslatorInterface $translator;

  /**
   * Set Messenger for use in tests.
   */
  public function setMessenger(MessengerInterface $messenger): void {
    $this->messenger = $messenger;
  }

  /**
   * Set Logger for use in tests.
   */
  public function setLogger(LoggerInterface $logger): void {
    $this->logger = $logger;
  }

  /**
   * Set Key repository for use in tests.
   */
  public function setKeyRepository(KeyRepositoryInterface $key_repository): void {
    $this->keyRepository = $key_repository;
  }

}
// @codingStandardsIgnoreEnd
