<?php

namespace Drupal\Tests\tmgmt_deepl\Unit;

use DeepL\DeepLException;
use DeepL\DocumentStatus;
use DeepL\DocumentTranslationException;
use DeepL\TextResult;
use DeepL\Translator as DeepLTranslator;
use DeepL\Usage;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\file\FileInterface;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt_deepl\DeepLClientFactoryInterface;
use Drupal\tmgmt_deepl\DeeplTranslatorApi;

/**
 * Tests the DeeplTranslatorAPI class.
 *
 * @covers \Drupal\tmgmt_deepl\DeeplTranslatorApi
 * @group tmgmt_deepl
 */
class DeeplTranslatorApiTest extends UnitTestCase {

  const string KEY_ID = 'test_deepl_key_entity_id';
  const string KEY_VALUE = 'valid-deepl-api-key-from-mock';

  /**
   * Creates a DeeplTranslatorApi with all dependencies mocked.
   *
   * @param array $config
   *   Optional overrides:
   *   - auth_key_entity: string (default: self::KEY_ID)
   *   - omit_partner_id: bool (default: TRUE)
   *   - version: string (default: '2.3.0')
   *   - key_value: string (default: self::KEY_VALUE)
   *
   * @return \Drupal\tmgmt_deepl\DeeplTranslatorApi
   *   The configured API instance.
   */
  private function createApi(array $config = []): DeeplTranslatorApi {
    $config += [
      'auth_key_entity' => self::KEY_ID,
      'omit_partner_id' => TRUE,
      'version' => '2.3.0',
      'key_value' => self::KEY_VALUE,
    ];

    $translator = $this->createMock(TranslatorInterface::class);
    $translator->method('getSetting')->willReturnCallback(
      fn(string $setting) => match ($setting) {
        'auth_key_entity' => $config['auth_key_entity'],
        'omit_partner_id' => $config['omit_partner_id'],
        default => '',
      },
    );

    $key_entity = $this->createMock(KeyInterface::class);
    $key_entity->method('getKeyValue')->willReturn($config['key_value']);

    $key_repository = $this->createMock(KeyRepositoryInterface::class);
    $key_repository->method('getKey')->with(self::KEY_ID)->willReturn($key_entity);

    $module_extension_list = $this->createMock(ModuleExtensionList::class);
    $module_extension_list->method('getExtensionInfo')
      ->with('tmgmt_deepl')
      ->willReturn(['version' => $config['version']]);

    $api = new DeeplTranslatorApi(
      $this->createMock(MessengerInterface::class),
      $key_repository,
      $this->createMock(FileSystemInterface::class),
      $module_extension_list,
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(DeepLClientFactoryInterface::class),
    );
    $api->setTranslator($translator);

    return $api;
  }

  /**
   * Creates an API whose getTranslator() returns a mock DeepL SDK translator.
   *
   * The returned SDK translator has the given methods pre-configured.
   *
   * @param array $sdk_methods
   *   Map of method name → return value or Throwable to throw.
   * @param array $config
   *   Optional key/value overrides: auth_key_entity, omit_partner_id, version,
   *   key_value.
   * @param array $services
   *   Optional service overrides: 'messenger', 'file_system',
   *   'entity_type_manager'.
   *
   * @phpstan-param array{messenger?: \Drupal\Core\Messenger\MessengerInterface, file_system?: \Drupal\Core\File\FileSystemInterface, entity_type_manager?: \Drupal\Core\Entity\EntityTypeManagerInterface} $services
   *
   * @return array{
   *   \Drupal\tmgmt_deepl\DeeplTranslatorApi,
   *   \PHPUnit\Framework\MockObject\MockObject&\DeepL\Translator,
   *   } Tuple of [DeeplTranslatorApi, DeepL\Translator mock].
   */
  private function createApiWithSdk(array $sdk_methods = [], array $config = [], array $services = []): array {
    $sdk = $this->createMock(DeepLTranslator::class);
    foreach ($sdk_methods as $method => $value) {
      if ($value instanceof \Throwable) {
        $sdk->method($method)->willThrowException($value);
      }
      else {
        $sdk->method($method)->willReturn($value);
      }
    }

    $factory = $this->createMock(DeepLClientFactoryInterface::class);
    $factory->method('create')->willReturn($sdk);

    $config['auth_key_entity'] ??= self::KEY_ID;
    $config['omit_partner_id'] ??= TRUE;
    $config['version'] ??= '2.3.0';
    $config['key_value'] ??= self::KEY_VALUE;

    $translator = $this->createMock(TranslatorInterface::class);
    $translator->method('getSetting')->willReturnCallback(
      fn(string $setting) => match ($setting) {
        'auth_key_entity' => $config['auth_key_entity'],
        'omit_partner_id' => $config['omit_partner_id'],
        default => '',
      },
    );

    $key_entity = $this->createMock(KeyInterface::class);
    $key_entity->method('getKeyValue')->willReturn($config['key_value']);

    $key_repository = $this->createMock(KeyRepositoryInterface::class);
    $key_repository->method('getKey')->with(self::KEY_ID)->willReturn($key_entity);

    $module_extension_list = $this->createMock(ModuleExtensionList::class);
    $module_extension_list->method('getExtensionInfo')
      ->with('tmgmt_deepl')
      ->willReturn(['version' => $config['version']]);

    $messenger = $services['messenger'] ?? $this->createMock(MessengerInterface::class);
    $file_system = $services['file_system'] ?? $this->createMock(FileSystemInterface::class);
    $entity_type_manager = $services['entity_type_manager'] ?? $this->createMock(EntityTypeManagerInterface::class);

    $api = new DeeplTranslatorApi(
      $messenger,
      $key_repository,
      $file_system,
      $module_extension_list,
      $entity_type_manager,
      $factory,
    );
    $api->setTranslator($translator);

    return [$api, $sdk];
  }

  /**
   * Tests that getTranslator() returns a DeepL SDK translator.
   */
  public function testGetTranslator(): void {
    [$api, $sdk] = $this->createApiWithSdk();
    $this->assertSame($sdk, $api->getTranslator());
  }

  /**
   * Tests getTranslator() throws when the translator has no key configured.
   */
  public function testGetTranslatorException(): void {
    $api = $this->createApi(['auth_key_entity' => '']);
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('API key not configured.');
    $api->getTranslator();
  }

  /**
   * Tests getTranslator() throws when setTranslator() was never called.
   */
  public function testGetTranslatorExceptionNoTranslatorSet(): void {
    $api = new DeeplTranslatorApi(
      $this->createMock(MessengerInterface::class),
      $this->createMock(KeyRepositoryInterface::class),
      $this->createMock(FileSystemInterface::class),
      $this->createMock(ModuleExtensionList::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(DeepLClientFactoryInterface::class),
    );
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Translator not set.');
    $api->getTranslator();
  }

  /**
   * Tests setTranslator() stores the translator for subsequent use.
   */
  public function testSetTranslator(): void {
    $translator = $this->createMock(TranslatorInterface::class);
    $translator->method('getSetting')->willReturnMap([
      ['auth_key_entity', 'test_key'],
      ['omit_partner_id', TRUE],
    ]);

    $key_entity = $this->createMock(KeyInterface::class);
    $key_entity->method('getKeyValue')->willReturn('value');

    $key_repository = $this->createMock(KeyRepositoryInterface::class);
    $key_repository->method('getKey')->with('test_key')->willReturn($key_entity);

    $sdk = $this->createMock(DeepLTranslator::class);

    $factory = $this->createMock(DeepLClientFactoryInterface::class);
    $factory->method('create')->willReturn($sdk);

    $api = new DeeplTranslatorApi(
      $this->createMock(MessengerInterface::class),
      $key_repository,
      $this->createMock(FileSystemInterface::class),
      $this->createMock(ModuleExtensionList::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $factory,
    );
    $api->setTranslator($translator);

    $this->assertSame($sdk, $api->getTranslator());
  }

  /**
   * Tests translate() delegates to the DeepL SDK.
   */
  public function testTranslate(): void {
    $text = ['Hello World'];
    $source_language = 'DE';
    $target_language = 'EN';
    $options = ['option1' => 'value1'];
    $translation_context = 'Sample context text.';

    $text_result = $this->createMock(TextResult::class);
    $sdk_methods = [
      'translateText' => $text_result,
    ];

    [$api, $sdk] = $this->createApiWithSdk($sdk_methods);

    $sdk->expects($this->once())
      ->method('translateText')
      ->with($text, $source_language, $target_language, array_merge($options, ['context' => $translation_context]))
      ->willReturn($text_result);

    $translator = $this->createMock(Translator::class);
    $result = $api->translate($translator, $text, $source_language, $target_language, $options, $translation_context);
    $this->assertSame($text_result, $result);
  }

  /**
   * Tests that translate() does not add a context option when none is provided.
   */
  public function testTranslateWithoutContext(): void {
    $text = ['Hello World'];
    $source_language = 'DE';
    $target_language = 'EN';
    $options = ['option1' => 'value1'];

    $text_result = $this->createMock(TextResult::class);

    [$api, $sdk] = $this->createApiWithSdk();

    $sdk->expects($this->once())
      ->method('translateText')
      ->with($text, $source_language, $target_language, $options)
      ->willReturn($text_result);

    $translator = $this->createMock(Translator::class);
    $result = $api->translate($translator, $text, $source_language, $target_language, $options);
    $this->assertSame($text_result, $result);
  }

  /**
   * Tests translate() catches a DeepL exception and returns NULL.
   */
  public function testTranslateException(): void {
    $text = ['Hello World'];
    $source_language = 'DE';
    $target_language = 'EN';

    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())
      ->method('addMessage')
      ->with('Translate error', 'error');

    [$api] = $this->createApiWithSdk(
      ['translateText' => new DeepLException('Translate error')],
      [],
      ['messenger' => $messenger],
    );

    $t = $this->createMock(Translator::class);
    $result = $api->translate($t, $text, $source_language, $target_language);
    $this->assertNull($result);
  }

  /**
   * Tests translate() handles a RuntimeException from getTranslator().
   */
  public function testTranslateHandlesRuntimeExceptionFromGetTranslator(): void {
    $api = $this->createApi(['auth_key_entity' => '']);
    $translator = $this->createMock(Translator::class);

    $result = $api->translate($translator, ['Hello World'], 'EN', 'DE');
    $this->assertNull($result);
  }

  /**
   * Tests translateDocument() handles RuntimeException from getTranslator().
   */
  public function testTranslateDocumentHandlesRuntimeExceptionFromGetTranslator(): void {
    $api = $this->createApi(['auth_key_entity' => '']);

    $translator = $this->createMock(Translator::class);
    $file = $this->createMock(FileInterface::class);
    $result = $api->translateDocument($translator, $file, 'EN', 'DE');
    $this->assertNull($result);
  }

  /**
   * Tests translateDocument() returns NULL when realpath() fails.
   */
  public function testTranslateDocumentRealpathFails(): void {
    $file = $this->createMock(FileInterface::class);
    $file->method('getFilename')->willReturn('source.txt');
    $file->method('getFileUri')->willReturn('public://source.txt');

    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->method('dirname')->willReturn('public://');
    $file_system->method('getDestinationFilename')
      ->willReturn('public://source_de.txt');
    $file_system->method('realpath')->willReturn(FALSE);

    [$api] = $this->createApiWithSdk([], [], ['file_system' => $file_system]);

    $t = $this->createMock(Translator::class);
    $result = $api->translateDocument($t, $file, 'en', 'de', []);
    $this->assertNull($result);
  }

  /**
   * Tests getUsage() returns the Usage object from the DeepL SDK.
   */
  public function testGetUsage(): void {
    $usage = $this->createMock(Usage::class);

    [$api] = $this->createApiWithSdk(['getUsage' => $usage]);

    $this->assertSame($usage, $api->getUsage());
  }

  /**
   * Tests getUsage() returns the error message string on exception.
   */
  public function testGetUsageException(): void {
    [$api] = $this->createApiWithSdk([
      'getUsage' => new DeepLException('Usage error'),
    ]);

    $this->assertSame('Usage error', $api->getUsage());
  }

  /**
   * Tests getSourceLanguages() returns an associative array.
   */
  public function testGetSourceLanguages(): void {
    $source_language_mock_de = new LanguageDefinitionMock('DE', 'German');
    $source_language_mock_en = new LanguageDefinitionMock('EN', 'English');

    [$api] = $this->createApiWithSdk([
      'getSourceLanguages' => [$source_language_mock_de, $source_language_mock_en],
    ]);

    $this->assertEquals(['DE' => 'German', 'EN' => 'English'], $api->getSourceLanguages());
  }

  /**
   * Tests getSourceLanguages() returns empty array on exception.
   */
  public function testGetSourceLanguagesException(): void {
    [$api] = $this->createApiWithSdk([
      'getSourceLanguages' => new \RuntimeException('Source languages error'),
    ]);

    $this->assertEmpty($api->getSourceLanguages());
  }

  /**
   * Tests getTargetLanguages() returns an associative array.
   */
  public function testGetTargetLanguages(): void {
    $target_language_mock_de = new LanguageDefinitionMock('DE', 'German');
    $target_language_mock_en = new LanguageDefinitionMock('EN', 'English');

    [$api] = $this->createApiWithSdk([
      'getTargetLanguages' => [$target_language_mock_de, $target_language_mock_en],
    ]);

    $this->assertEquals(['DE' => 'German', 'EN' => 'English'], $api->getTargetLanguages());
  }

  /**
   * Tests getTargetLanguages() returns empty array on exception.
   */
  public function testGetTargetLanguagesException(): void {
    [$api] = $this->createApiWithSdk([
      'getTargetLanguages' => new \RuntimeException('Target languages error'),
    ]);

    $this->assertEmpty($api->getTargetLanguages());
  }

  /**
   * Data provider for testFixSourceLanguageMappings.
   */
  public static function dataProviderFixSourceLanguageMappings(): array {
    return [
      'EN-GB maps to EN' => ['EN-GB', 'EN'],
      'EN-US maps to EN' => ['EN-US', 'EN'],
      'PT-BR maps to PT' => ['PT-BR', 'PT'],
      'PT-PT maps to PT' => ['PT-PT', 'PT'],
      'unmapped language passes through' => ['FR', 'FR'],
    ];
  }

  /**
   * Tests fixSourceLanguageMappings().
   *
   * @dataProvider dataProviderFixSourceLanguageMappings
   */
  public function testFixSourceLanguageMappings(string $input, string $expected): void {
    $this->assertSame($expected, $this->createApi()->fixSourceLanguageMappings($input));
  }

  /**
   * Tests translateDocument() returns NULL when translate_documents is FALSE.
   */
  public function testTranslateDocumentDisabled(): void {
    $translator = $this->createMock(Translator::class);
    $file = $this->createMock(FileInterface::class);
    $result = $this->createApi()->translateDocument($translator, $file, 'en', 'de', ['translate_documents' => FALSE]);
    $this->assertNull($result);
  }

  /**
   * Tests translateDocument() returns NULL when the document is not done.
   */
  public function testTranslateDocumentNotDone(): void {
    $file = $this->createMock(FileInterface::class);
    $file->method('getFilename')->willReturn('source.txt');
    $file->method('getFileUri')->willReturn('public://source.txt');

    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->method('dirname')->willReturn('public://');
    $file_system->method('getDestinationFilename')->willReturn('public://source_de.txt');
    $file_system->method('realpath')->willReturnMap([
      ['public://source.txt', '/tmp/source.txt'],
      ['public://source_de.txt', '/tmp/source_de.txt'],
    ]);

    $status = $this->createMock(DocumentStatus::class);
    $status->method('done')->willReturn(FALSE);

    [$api, $sdk] = $this->createApiWithSdk([], [], ['file_system' => $file_system]);
    $sdk->expects($this->once())
      ->method('translateDocument')
      ->with('/tmp/source.txt', '/tmp/source_de.txt', 'en', 'de', [])
      ->willReturn($status);

    $t = $this->createMock(Translator::class);
    $result = $api->translateDocument($t, $file, 'en', 'de', []);
    $this->assertNull($result);
  }

  /**
   * Tests translateDocument() catches a DocumentTranslationException.
   */
  public function testTranslateDocumentException(): void {
    $file = $this->createMock(FileInterface::class);
    $file->method('getFilename')->willReturn('source.txt');
    $file->method('getFileUri')->willReturn('public://source.txt');

    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->method('dirname')->willReturn('public://');
    $file_system->method('getDestinationFilename')->willReturn('public://source_de.txt');
    $file_system->method('realpath')->willReturn('/tmp/source.txt');

    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())->method('addError')->with('Document error');

    [$api, $sdk] = $this->createApiWithSdk(
      [],
      [],
      ['file_system' => $file_system, 'messenger' => $messenger],
    );
    $sdk->method('translateDocument')
      ->willThrowException(new DocumentTranslationException('Document error'));

    $t = $this->createMock(Translator::class);
    $result = $api->translateDocument($t, $file, 'en', 'de', []);
    $this->assertNull($result);
  }

  /**
   * Tests translateDocument() creates a file entity when translation succeeds.
   */
  public function testTranslateDocumentDone(): void {
    $file = $this->createMock(FileInterface::class);
    $file->method('getFilename')->willReturn('source.txt');
    $file->method('getFileUri')->willReturn('public://source.txt');

    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->method('dirname')->willReturn('public://');
    $file_system->method('getDestinationFilename')->willReturn('public://source_de.txt');
    $file_system->method('realpath')->willReturnMap([
      ['public://source.txt', '/tmp/source.txt'],
      ['public://source_de.txt', '/tmp/source_de.txt'],
    ]);

    $status = $this->createMock(DocumentStatus::class);
    $status->method('done')->willReturn(TRUE);

    $translated_file = $this->createMock(FileInterface::class);
    $translated_file->method('save')->willReturn(1);

    $file_storage = $this->createMock(EntityStorageInterface::class);
    $file_storage->expects($this->once())
      ->method('create')
      ->with($this->callback(fn(array $values): bool => isset($values['uri'])))
      ->willReturn($translated_file);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects($this->once())
      ->method('getStorage')
      ->with('file')
      ->willReturn($file_storage);

    [$api, $sdk] = $this->createApiWithSdk(
      [],
      [],
      ['file_system' => $file_system, 'entity_type_manager' => $entity_type_manager],
    );
    $sdk->expects($this->once())
      ->method('translateDocument')
      ->with('/tmp/source.txt', '/tmp/source_de.txt', 'en', 'de', [])
      ->willReturn($status);

    $t = $this->createMock(Translator::class);
    $result = $api->translateDocument($t, $file, 'en', 'de', []);
    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertSame($translated_file, $result);
  }

}

// @codingStandardsIgnoreStart
/**
 * Simple mock class to represent a language definition.
 */
class LanguageDefinitionMock {

  public string $code;

  public string $name;

  public function __construct(string $code, string $name) {
    $this->code = $code;
    $this->name = $name;
  }

}
// @codingStandardsIgnoreEnd
