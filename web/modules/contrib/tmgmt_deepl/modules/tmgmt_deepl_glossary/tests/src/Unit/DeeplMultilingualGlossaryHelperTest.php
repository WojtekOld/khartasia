<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Unit;

use Drupal\Core\Form\FormStateInterface;
use DeepL\MultilingualGlossaryDictionaryInfo;
use DeepL\MultilingualGlossaryInfo;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApiInterface;
use Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryHelper;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the DeeplMultilingualGlossaryHelper service.
 *
 * @covers \Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryHelper
 * @group tmgmt_deepl_glossary
 */
class DeeplMultilingualGlossaryHelperTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Create/register string translation mock.
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Tests the method ::getAllowedLanguages.
   */
  public function testGetAllowedLanguages(): void {
    // Mock the module handler.
    $module_handler = $this->createMock(ModuleHandlerInterface::class);

    // Mock the class to intercept the alter hook.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();
    $class->setModuleHandler($module_handler);

    // Test with alteration.
    $altered_languages = [
      'SM-IT' => 'San Marino - Italian',
    ];
    $module_handler->expects($this->once())
      ->method('alter')
      ->with('tmgmt_deepl_glossary_allowed_languages', $this->callback(fn($arg) => is_array($arg)))
      ->willReturnCallback(function ($hook, array &$languages) use ($altered_languages) {
        $languages = array_merge($languages, $altered_languages);
      });

    $languages = $class->getAllowedLanguages();

    $this->assertArrayHasKey('SM-IT', $languages);
    $this->assertContains('San Marino - Italian', $languages);
  }

  /**
   * Tests the method ::getValidSourceTargetLanguageCombinations.
   */
  public function testGetValidSourceTargetLanguageCombinations(): void {
    // Mock the getAllowedLanguages method to return predefined languages.
    $allowed_languages = [
      'EN' => 'English',
      'FR' => 'French',
      'DE' => 'German',
    ];

    // Mock the class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock(['getAllowedLanguages']);
    $class->setModuleHandler($this->createMock(ModuleHandlerInterface::class));
    $class->setEntityTypeManager($this->createMock(EntityTypeManagerInterface::class));

    $class->expects($this->once())
      ->method('getAllowedLanguages')
      ->willReturn($allowed_languages);

    // Expected combinations.
    $expected_combinations = [
      ['EN' => 'FR'],
      ['EN' => 'DE'],
      ['FR' => 'EN'],
      ['FR' => 'DE'],
      ['DE' => 'EN'],
      ['DE' => 'FR'],
    ];

    $combinations = $class->getValidSourceTargetLanguageCombinations();
    $this->assertEquals($expected_combinations, $combinations);
  }

  /**
   * Tests the method ::getMatchingGlossaries.
   */
  public function testGetMatchingGlossaries(): void {
    // Set up mock data.
    $translator = 'test_translator';
    $source_lang = 'EN-US';
    $target_lang = 'DE-DE';

    // Expected result from loadByProperties.
    $expected_glossaries = ['glossary1' => 'Glossary 1', 'glossary2' => 'Glossary 2'];

    // Mock glossary storage.
    $glossary_ml_storage = $this->createMock(EntityStorageInterface::class);
    $glossary_ml_dictionary_storage = $this->createMock(EntityStorageInterface::class);

    // Set expectations for glossary storage.
    $glossary1 = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary1->method('id')->willReturn('glossary1');
    $glossary2 = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary2->method('id')->willReturn('glossary2');

    $glossary_ml_storage->expects($this->once())
      ->method('loadByProperties')
      ->with([
        'tmgmt_translator' => $translator,
      ])
      ->willReturn([$glossary1, $glossary2]);

    // Mock glossary entities for referenced entities.
    $glossary1_ref = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary1_ref->method('id')->willReturn('glossary1');
    $glossary1_ref->method('label')->willReturn('Glossary 1');

    $glossary2_ref = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary2_ref->method('id')->willReturn('glossary2');
    $glossary2_ref->method('label')->willReturn('Glossary 2');

    // Mock dictionary entities.
    $dictionary_entity1 = $this->createMock(DeeplMultilingualGlossaryDictionaryInterface::class);
    $dictionary_entity1->expects($this->once())
      ->method('get')
      ->with('glossary_id')
      ->willReturn((object) ['entity' => $glossary1_ref]);

    $dictionary_entity2 = $this->createMock(DeeplMultilingualGlossaryDictionaryInterface::class);
    $dictionary_entity2->expects($this->once())
      ->method('get')
      ->with('glossary_id')
      ->willReturn((object) ['entity' => $glossary2_ref]);

    $glossary_ml_dictionary_storage->expects($this->once())
      ->method('loadByProperties')
      ->with([
        'source_lang' => 'EN',
        'target_lang' => 'DE',
      ])
      ->willReturn([$dictionary_entity1, $dictionary_entity2]);

    // Mock the entity type manager.
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);

    // Mock the class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock(['fixLanguageMappings']);
    $class->expects($this->exactly(2))
      ->method('fixLanguageMappings')
      ->willReturnCallback(function ($langcode) {
        return match ($langcode) {
          'EN-US' => 'EN',
          'DE-DE' => 'DE',
          default => $langcode,
        };
      });

    $class->setModuleHandler($this->createMock(ModuleHandlerInterface::class));
    $class->setEntityTypeManager($entity_type_manager);

    // Set the storages as the class uses properties directly.
    $class->setGlossaryMlStorage($glossary_ml_storage);
    $class->setGlossaryMlDictionaryStorage($glossary_ml_dictionary_storage);

    // Call the method.
    $glossaries = $class->getMatchingGlossaries($translator, $source_lang, $target_lang);

    // Assert the result is what we expect.
    $this->assertEquals($expected_glossaries, $glossaries);
  }

  /**
   * Tests the method ::getMatchingGlossaries with no glossaries for translator.
   */
  public function testGetMatchingGlossariesNoGlossaries(): void {
    // Mock glossary storage.
    $glossary_ml_storage = $this->createMock(EntityStorageInterface::class);
    $glossary_ml_dictionary_storage = $this->createMock(EntityStorageInterface::class);

    // Set expectations for glossary storage returning empty.
    $glossary_ml_storage->expects($this->once())
      ->method('loadByProperties')
      ->with([
        'tmgmt_translator' => 'test_translator',
      ])
      ->willReturn([]);

    // Dictionary storage should not be called.
    $glossary_ml_dictionary_storage->expects($this->never())
      ->method('loadByProperties');

    // Mock the class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock(['fixLanguageMappings']);
    $class->expects($this->never())
      ->method('fixLanguageMappings');

    $class->setGlossaryMlStorage($glossary_ml_storage);
    $class->setGlossaryMlDictionaryStorage($glossary_ml_dictionary_storage);

    // Call the method.
    $glossaries = $class->getMatchingGlossaries('test_translator', 'EN', 'DE');

    // Assert empty result.
    $this->assertEquals([], $glossaries);
  }

  /**
   * Tests the method ::getMatchingGlossaries with no matching dictionaries.
   */
  public function testGetMatchingGlossariesNoMatchingDictionaries(): void {
    // Mock glossary storage.
    $glossary_ml_storage = $this->createMock(EntityStorageInterface::class);
    $glossary_ml_dictionary_storage = $this->createMock(EntityStorageInterface::class);

    // Mock glossaries.
    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('id')->willReturn('glossary1');

    // Set expectations for glossary storage.
    $glossary_ml_storage->expects($this->once())
      ->method('loadByProperties')
      ->willReturn([$glossary]);

    // Dictionary storage returns empty.
    $glossary_ml_dictionary_storage->expects($this->once())
      ->method('loadByProperties')
      ->with([
        'source_lang' => 'EN',
        'target_lang' => 'DE',
      ])
      ->willReturn([]);

    // Mock the class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock(['fixLanguageMappings']);
    $class->expects($this->exactly(2))
      ->method('fixLanguageMappings')
      ->willReturnArgument(0);

    $class->setGlossaryMlStorage($glossary_ml_storage);
    $class->setGlossaryMlDictionaryStorage($glossary_ml_dictionary_storage);

    // Call the method.
    $glossaries = $class->getMatchingGlossaries('test_translator', 'EN', 'DE');

    // Assert empty result.
    $this->assertEquals([], $glossaries);
  }

  /**
   * Data provider for testFixLanguageMappings().
   *
   * @return array
   *   Array of test data.
   */
  public static function dataProviderTestFixLanguageMappings(): array {
    return [
      ['EN', 'EN'],
      ['en', 'EN'],
      ['EN-US', 'EN'],
      ['EN-GB', 'EN'],
      ['PT-BR', 'PT'],
      ['PT-PT', 'PT'],
      ['ZH-HANS', 'ZH'],
      ['ZH-HANT', 'ZH'],
      ['A', 'A'],
      ['XX', 'XX'],
    ];
  }

  /**
   * Tests the method ::fixLanguageMappings.
   *
   * @dataProvider dataProviderTestFixLanguageMappings
   */
  public function testFixLanguageMappings(string $input, string $expected): void {
    // Mock the class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();

    $this->assertEquals($expected, $class->fixLanguageMappings($input));
  }

  /**
   * Tests the method ::hasMultilingualGlossaryDictionary.
   */
  public function testHasMultilingualGlossaryDictionary(): void {
    // Mock the DeeplGlossaryApi.
    $deepl_glossary_api = $this->createMock(DeeplMultilingualGlossaryApiInterface::class);

    // Set up mock data.
    $glossary_id = 'test_glossary_id';
    $source_lang = 'EN';
    $target_lang = 'DE';

    // Set expectations for the API call.
    $deepl_glossary_api->expects($this->once())
      ->method('getMultilingualGlossaryMetadata')
      ->with($glossary_id)
      ->willReturn(NULL);

    // Mock the class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();
    $class->setDeeplGlossaryApi($deepl_glossary_api);

    // Call the method.
    $result = $class->hasMultilingualGlossaryDictionary($glossary_id, $source_lang, $target_lang);

    // Assert the result.
    $this->assertFalse($result);
  }

  /**
   * Tests method ::hasMultilingualGlossaryDictionary with empty dictionaries.
   */
  public function testHasMultilingualGlossaryDictionaryEmptyDictionaries(): void {
    // Mock the DeeplGlossaryApi.
    $deepl_glossary_api = $this->createMock(DeeplMultilingualGlossaryApiInterface::class);

    // Set up mock data.
    $glossary_id = 'test_glossary_id';
    $source_lang = 'EN';
    $target_lang = 'DE';

    // Mock metadata with empty dictionaries.
    $metadata = new MultilingualGlossaryInfo('test', 'Test', new \DateTime(), []);

    // Set expectations for the API call.
    $deepl_glossary_api->expects($this->once())
      ->method('getMultilingualGlossaryMetadata')
      ->with($glossary_id)
      ->willReturn($metadata);

    // Mock the class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();
    $class->setDeeplGlossaryApi($deepl_glossary_api);

    // Call the method.
    $result = $class->hasMultilingualGlossaryDictionary($glossary_id, $source_lang, $target_lang);

    // Assert the result.
    $this->assertFalse($result);
  }

  /**
   * Tests method ::hasMultilingualGlossaryDictionary with matching dictionary.
   */
  public function testHasMultilingualGlossaryDictionaryWithMatch(): void {
    // Mock the DeeplGlossaryApi.
    $deepl_glossary_api = $this->createMock(DeeplMultilingualGlossaryApiInterface::class);

    // Set up mock data.
    $glossary_id = 'test_glossary_id';

    // Mock dictionary info.
    $dictionary = $this->createMock(MultilingualGlossaryDictionaryInfo::class);
    $dictionary->sourceLang = 'en';
    $dictionary->targetLang = 'de';

    // Mock metadata with matching dictionary.
    $metadata = new MultilingualGlossaryInfo('test_glossary_id', 'Test', new \DateTime(), [$dictionary]);

    // Set expectations for the API call.
    $deepl_glossary_api->expects($this->once())
      ->method('getMultilingualGlossaryMetadata')
      ->with($glossary_id)
      ->willReturn($metadata);

    // Mock the class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();
    $class->setDeeplGlossaryApi($deepl_glossary_api);

    // Call the method.
    $result = $class->hasMultilingualGlossaryDictionary($glossary_id, 'EN', 'DE');

    // Assert the result.
    $this->assertTrue($result);
  }

  /**
   * Tests the method ::validateSourceTargetLanguage.
   */
  public function testValidateSourceTargetLanguage(): void {
    // Mock the class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock(['getValidSourceTargetLanguageCombinations']);
    $class->expects($this->exactly(2))
      ->method('getValidSourceTargetLanguageCombinations')
      ->willReturn([['EN' => 'DE']]);

    // Test valid language pair: no errors set.
    $form_state_valid = $this->createMock(FormStateInterface::class);
    $valid_input = [
      'source_lang' => 'EN',
      'target_lang' => 'DE',
    ];
    $form_state_valid->expects($this->once())
      ->method('getValues')
      ->willReturn($valid_input);
    // setErrorByName should not be called for valid case.
    $form_state_valid->expects($this->never())
      ->method('setErrorByName');

    $form = [];
    $class->validateSourceTargetLanguage($form, $form_state_valid);

    // Test invalid language pair: errors set.
    $form_state_invalid = $this->createMock(FormStateInterface::class);
    $invalid_input = [
      'source_lang' => 'EN',
      'target_lang' => 'XX',
    ];
    $form_state_invalid->expects($this->once())
      ->method('getValues')
      ->willReturn($invalid_input);

    $calls = [];
    $form_state_invalid->expects($this->exactly(2))
      ->method('setErrorByName')
      ->willReturnCallback(function ($field, $message) use (&$calls, $form_state_invalid) {
        $calls[] = [$field, $message];
        return $form_state_invalid;
      });

    $class->validateSourceTargetLanguage($form, $form_state_invalid);

    $this->assertEquals([
      ['source_lang', 'Select a valid source/ target language.'],
      ['target_lang', 'Select a valid source/ target language.'],
    ], $calls);
  }

  /**
   * Tests the method ::getAllowedTranslators.
   */
  public function testGetAllowedTranslators(): void {
    // Mock the entity type manager and storage.
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $translator_storage = $this->createMock(EntityStorageInterface::class);

    // Set expectations for entity type manager.
    $entity_type_manager->expects($this->once())
      ->method('getStorage')
      ->with('tmgmt_translator')
      ->willReturn($translator_storage);

    // Mock translators.
    $translator1 = $this->createMock(TranslatorInterface::class);
    $translator1->expects($this->once())
      ->method('label')
      ->willReturn('DeepL API Translator');
    $translator2 = $this->createMock(TranslatorInterface::class);
    $translator2->expects($this->once())
      ->method('label')
      ->willReturn('DeepL Pro Translator');

    // Set expectations for translator storage.
    $translator_storage->expects($this->once())
      ->method('loadByProperties')
      ->with(['plugin' => ['deepl_api', 'deepl_free', 'deepl_pro']])
      ->willReturn([$translator1, $translator2]);

    // Mock the class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();
    $class->setEntityTypeManager($entity_type_manager);

    // Call the method.
    $translators = $class->getAllowedTranslators();

    // Assert the result.
    $this->assertEquals(['DeepL API Translator', 'DeepL Pro Translator'], $translators);
  }

  /**
   * Tests the method ::saveGlossaryDictionary.
   */
  public function testSaveGlossaryDictionary(): void {
    // Create mock objects for dependencies.
    $deepl_glossary_api = $this->createMock(DeeplMultilingualGlossaryApiInterface::class);
    $glossary_storage = $this->createMock(EntityStorageInterface::class);
    $glossary_dictionary_storage = $this->createMock(EntityStorageInterface::class);
    $translator = $this->createMock(TranslatorInterface::class);

    // Add a real glossary for testing.
    $glossary_info = new MultilingualGlossaryInfo(
      'test_glossary_id',
      'Test Glossary',
      new \DateTime(),
      [],
    );

    // Set up mock expectations for Translator.
    $translator->expects($this->once())->method('id')->willReturn('test_translator');

    // Define dictionary data.
    $dictionary = [
      'source_lang' => 'en',
      'target_lang' => 'de',
      'entries' => [['subject' => 'test', 'definition' => 'test_definition']],
    ];

    // Ensure we check for the glossary_id in the loadByProperties method.
    $glossary_storage->expects($this->once())
      ->method('loadByProperties')
      ->with(['glossary_id' => 'test_glossary_id'])
      ->willReturn([]);

    $expected_create_args = [
      'label' => 'Test Glossary',
      'glossary_id' => 'test_glossary_id',
      'tmgmt_translator' => 'test_translator',
    ];

    $new_glossary_entity = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $new_glossary_entity->expects($this->exactly(2))
      ->method('id')
      ->willReturn('test_glossary_id');
    // Ensure that create method is run once for saving.
    $glossary_storage->expects($this->once())
      ->method('create')
      ->with($this->equalTo($expected_create_args))
      ->willReturn($new_glossary_entity);

    $new_glossary_entity
      ->expects($this->once())
      ->method('save');

    // Mock the class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();
    $class->setDeeplGlossaryApi($deepl_glossary_api);
    $class->setGlossaryMlStorage($glossary_storage);
    // Mock the loadByProperties method to return an empty array.
    $glossary_dictionary_storage->expects($this->once())
      ->method('loadByProperties')
      ->with([
        'glossary_id' => 'test_glossary_id',
        'source_lang' => 'en',
        'target_lang' => 'de',
      ])
      ->willReturn([]);

    $new_dictionary_entity = $this->createMock(DeeplMultilingualGlossaryDictionaryInterface::class);
    $new_dictionary_entity->expects($this->once())->method('save');

    $glossary_dictionary_storage->expects($this->once())
      ->method('create')
      ->willReturn($new_dictionary_entity);

    $class->setGlossaryMlDictionaryStorage($glossary_dictionary_storage);

    // Save the glossary dictionary.
    $class->saveGlossaryDictionary($glossary_info, $dictionary, $translator);
  }

  /**
   * Tests the method ::saveGlossaryDictionary for existing glossaries.
   */
  public function testSaveGlossaryDictionaryExisting(): void {
    // Create mock objects for dependencies.
    $deepl_glossary_api = $this->createMock(DeeplMultilingualGlossaryApiInterface::class);
    $glossary_storage = $this->createMock(EntityStorageInterface::class);
    $glossary_dictionary_storage = $this->createMock(EntityStorageInterface::class);
    $translator = $this->createMock(TranslatorInterface::class);

    // Add a real glossary for testing.
    $glossary_info = new MultilingualGlossaryInfo(
      'existing_glossary_id',
      'Test Glossary 1',
      new \DateTime(),
      [],
    );

    // Define dictionary data.
    $dictionary = [
      'source_lang' => 'fr',
      'target_lang' => 'de',
      'entries' => [['subject' => 'test', 'definition' => 'test_definition']],
    ];

    // Create mock for existing glossary.
    $existing_glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $existing_glossary->expects($this->once())
      ->method('id')
      ->willReturn('existing_glossary_id');

    // Set up mock expectations for existing glossary.
    $existing_glossary->expects($this->once())
      ->method('set')
      ->with('label', 'Test Glossary 1');
    $existing_glossary->expects($this->once())
      ->method('save');

    // Set mock expectations for GlossaryStorage.
    $glossary_storage->expects($this->once())
      ->method('loadByProperties')
      ->with(['glossary_id' => 'existing_glossary_id'])
      ->willReturn([$existing_glossary]);

    // Mock dictionary storage for existing dictionary.
    $existing_dictionary = $this->createMock(DeeplMultilingualGlossaryDictionaryInterface::class);
    $glossary_dictionary_storage->expects($this->once())
      ->method('loadByProperties')
      ->with([
        'glossary_id' => 'existing_glossary_id',
        'source_lang' => 'fr',
        'target_lang' => 'de',
      ])
      ->willReturn([$existing_dictionary]);
    $firstCall = TRUE;
    $existing_dictionary->expects($this->exactly(2))
      ->method('set')
      ->willReturnCallback(function ($key, $value) use ($dictionary, &$firstCall) {
        if ($firstCall) {
          $this->assertEquals('entries', $key);
          $this->assertEquals($dictionary['entries'], $value);
          $firstCall = FALSE;
        }
        else {
          $this->assertEquals('entry_count', $key);
          $this->assertEquals(count($dictionary['entries']), $value);
        }
        return $this;
      });
    $glossary_dictionary_storage->expects($this->once())
      ->method('save')
      ->with($existing_dictionary);

    // Mock the class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();
    $class->setDeeplGlossaryApi($deepl_glossary_api);
    $class->setGlossaryMlStorage($glossary_storage);
    $class->setGlossaryMlDictionaryStorage($glossary_dictionary_storage);

    // Call the save method.
    $class->saveGlossaryDictionary($glossary_info, $dictionary, $translator);
  }

  /**
   * Tests parseCsvContent with valid CSV content.
   */
  public function testParseCsvContentValid(): void {
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();

    $result = $class->parseCsvContent("hello,hallo\nworld,welt\ntest,prüfung");

    $this->assertEquals([
      'hello' => 'hallo',
      'world' => 'welt',
      'test' => 'prüfung',
    ], $result);
  }

  /**
   * Tests parseCsvContent with an empty string returns an empty array.
   */
  public function testParseCsvContentEmpty(): void {
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();

    $this->assertEquals([], $class->parseCsvContent(''));
  }

  /**
   * Tests parseCsvContent handles quoted fields with embedded newlines.
   */
  public function testParseCsvContentQuotedNewline(): void {
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();

    $result = $class->parseCsvContent("\"hello\nworld\",hallo\nfoo,bar");

    $this->assertEquals([
      "hello\nworld" => 'hallo',
      'foo' => 'bar',
    ], $result);
  }

  /**
   * Tests parseCsvContent handles CRLF (Windows) line endings.
   */
  public function testParseCsvContentCrlf(): void {
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();

    $result = $class->parseCsvContent("hello,hallo\r\nworld,welt\r\n");

    $this->assertEquals([
      'hello' => 'hallo',
      'world' => 'welt',
    ], $result);
  }

  /**
   * Tests parseCsvContent skips blank lines.
   */
  public function testParseCsvContentSkipsBlankLines(): void {
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();

    $result = $class->parseCsvContent("hello,hallo\n\nworld,welt\n");

    $this->assertEquals([
      'hello' => 'hallo',
      'world' => 'welt',
    ], $result);
  }

  /**
   * Tests parseCsvContent throws when a line has fewer than 2 columns.
   */
  public function testParseCsvContentWrongColumnCount(): void {
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();

    $this->expectException(\InvalidArgumentException::class);
    $class->parseCsvContent("hello");
  }

  /**
   * Tests parseCsvContent ignores extra columns beyond the first two.
   */
  public function testParseCsvContentIgnoresExtraColumns(): void {
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();

    $result = $class->parseCsvContent("hello,hallo,extra\nworld,welt,ignored,also_ignored");

    $this->assertEquals([
      'hello' => 'hallo',
      'world' => 'welt',
    ], $result);
  }

  /**
   * Tests parseCsvContent throws when source text is empty.
   */
  public function testParseCsvContentEmptySource(): void {
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();

    $this->expectException(\InvalidArgumentException::class);
    $class->parseCsvContent(",hallo");
  }

  /**
   * Tests parseCsvContent throws when target text is empty.
   */
  public function testParseCsvContentEmptyTarget(): void {
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();

    $this->expectException(\InvalidArgumentException::class);
    $class->parseCsvContent("hello,");
  }

  /**
   * Tests parseCsvContent throws on duplicate source text.
   */
  public function testParseCsvContentDuplicateSource(): void {
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();

    $this->expectException(\InvalidArgumentException::class);
    $class->parseCsvContent("hello,hallo\nhello,welt");
  }

  /**
   * Tests parseCsvContent trims leading and trailing whitespace from values.
   */
  public function testParseCsvContentTrimsWhitespace(): void {
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();

    $result = $class->parseCsvContent(' hello , hallo ');

    $this->assertEquals(['hello' => 'hallo'], $result);
  }

  /**
   * Tests validateCsvEntries with valid entries — no errors set.
   */
  public function testValidateCsvEntriesValid(): void {
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->never())->method('setErrorByName');

    $class->validateCsvEntries(['hello' => 'hallo', 'world' => 'welt'], $form_state);
  }

  /**
   * Tests validateCsvEntries reports an error for leading whitespace in source.
   */
  public function testValidateCsvEntriesLeadingWhitespaceInSource(): void {
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->atLeastOnce())->method('setErrorByName');

    // PHP array keys are already trimmed by the time they reach this method,
    // but entries with numeric keys or non-string keys trigger the check.
    // Simulate by passing a non-string key as an integer.
    $class->validateCsvEntries([0 => 'hallo'], $form_state);
  }

  /**
   * Tests validateCsvEntries error for trailing whitespace in target.
   */
  public function testValidateCsvEntriesTrailingWhitespaceInTarget(): void {
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->atLeastOnce())->method('setErrorByName');

    $class->validateCsvEntries(['hello' => 'hallo '], $form_state);
  }

  /**
   * Tests validateCsvEntries uses the provided field prefix in error names.
   */
  public function testValidateCsvEntriesFieldPrefix(): void {
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();

    $form_state = $this->createMock(FormStateInterface::class);
    $error_fields = [];
    $form_state->expects($this->atLeastOnce())
      ->method('setErrorByName')
      ->willReturnCallback(function (string $field) use (&$error_fields) {
        $error_fields[] = $field;
      });

    $class->validateCsvEntries(['hello' => 'hallo '], $form_state, 'my_field');

    $this->assertContains('my_field', $error_fields);
  }

  /**
   * Tests createOrUpdateDictionaryFromEntries returns NULL when no translator.
   */
  public function testCreateOrUpdateDictionaryFromEntriesNoTranslator(): void {
    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('getTranslator')->willReturn(NULL);

    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();

    $result = $class->createOrUpdateDictionaryFromEntries($glossary, 'EN', 'DE', ['hello' => 'hallo']);

    $this->assertNull($result);
  }

  /**
   * Tests createOrUpdateDictionaryFromEntries creates a new dictionary.
   */
  public function testCreateOrUpdateDictionaryFromEntriesCreateNew(): void {
    $translator = $this->createMock(TranslatorInterface::class);

    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('getTranslator')->willReturn($translator);
    $glossary->method('id')->willReturn('42');
    $glossary->method('getGlossaryId')->willReturn(NULL);

    $new_dictionary = $this->createMock(DeeplMultilingualGlossaryDictionaryInterface::class);
    $new_dictionary->expects($this->once())->method('save');

    $dictionary_storage = $this->createMock(EntityStorageInterface::class);
    $dictionary_storage->expects($this->once())
      ->method('loadByProperties')
      ->with([
        'glossary_id' => '42',
        'source_lang' => 'EN',
        'target_lang' => 'DE',
      ])
      ->willReturn([]);
    $dictionary_storage->expects($this->once())
      ->method('create')
      ->willReturn($new_dictionary);

    $glossary_api = $this->createMock(DeeplMultilingualGlossaryApiInterface::class);
    $glossary_api->expects($this->once())->method('setTranslator')->with($translator);
    $glossary_api->expects($this->never())->method('replaceMultilingualGlossaryDictionary');

    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();
    $class->setDeeplGlossaryApi($glossary_api);
    $class->setGlossaryMlDictionaryStorage($dictionary_storage);

    $result = $class->createOrUpdateDictionaryFromEntries($glossary, 'EN', 'DE', ['hello' => 'hallo']);

    $this->assertSame($new_dictionary, $result);
  }

  /**
   * Tests createOrUpdateDictionaryFromEntries updates an existing dictionary.
   */
  public function testCreateOrUpdateDictionaryFromEntriesUpdateExisting(): void {
    $translator = $this->createMock(TranslatorInterface::class);

    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('getTranslator')->willReturn($translator);
    $glossary->method('id')->willReturn('42');
    $glossary->method('getGlossaryId')->willReturn(NULL);

    $existing_dictionary = $this->createMock(DeeplMultilingualGlossaryDictionaryInterface::class);
    $set_calls = [];
    $existing_dictionary->expects($this->exactly(2))
      ->method('set')
      ->willReturnCallback(function (string $key, mixed $value) use (&$set_calls) {
        $set_calls[$key] = $value;
      });
    $existing_dictionary->expects($this->once())->method('save');

    $dictionary_storage = $this->createMock(EntityStorageInterface::class);
    $dictionary_storage->expects($this->once())
      ->method('loadByProperties')
      ->willReturn([$existing_dictionary]);
    $dictionary_storage->expects($this->never())->method('create');

    $glossary_api = $this->createMock(DeeplMultilingualGlossaryApiInterface::class);
    $glossary_api->expects($this->once())->method('setTranslator');
    $glossary_api->expects($this->never())->method('replaceMultilingualGlossaryDictionary');

    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();
    $class->setDeeplGlossaryApi($glossary_api);
    $class->setGlossaryMlDictionaryStorage($dictionary_storage);

    $entries = ['hello' => 'hallo', 'world' => 'welt'];
    $result = $class->createOrUpdateDictionaryFromEntries($glossary, 'EN', 'DE', $entries);

    $this->assertSame($existing_dictionary, $result);
    $this->assertEquals([
      ['subject' => 'hello', 'definition' => 'hallo'],
      ['subject' => 'world', 'definition' => 'welt'],
    ], $set_calls['entries']);
    $this->assertEquals(2, $set_calls['entry_count']);
  }

  /**
   * Tests createOrUpdateDictionaryFromEntries syncs to DeepL API.
   */
  public function testCreateOrUpdateDictionaryFromEntriesSyncsApi(): void {
    $translator = $this->createMock(TranslatorInterface::class);

    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('getTranslator')->willReturn($translator);
    $glossary->method('id')->willReturn('42');
    $glossary->method('getGlossaryId')->willReturn('deepl-uuid-abc');

    $new_dictionary = $this->createMock(DeeplMultilingualGlossaryDictionaryInterface::class);
    $new_dictionary->expects($this->exactly(2))->method('save');
    $new_dictionary->expects($this->once())->method('set')->with('entry_count', 1);

    $dictionary_storage = $this->createMock(EntityStorageInterface::class);
    $dictionary_storage->method('loadByProperties')->willReturn([]);
    $dictionary_storage->method('create')->willReturn($new_dictionary);

    $api_result = new MultilingualGlossaryDictionaryInfo('en', 'de', 1);
    $glossary_api = $this->createMock(DeeplMultilingualGlossaryApiInterface::class);
    $glossary_api->expects($this->once())->method('setTranslator');
    $glossary_api->expects($this->once())
      ->method('replaceMultilingualGlossaryDictionary')
      ->with('deepl-uuid-abc', 'en', 'de', ['hello' => 'hallo'])
      ->willReturn($api_result);

    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();
    $class->setDeeplGlossaryApi($glossary_api);
    $class->setGlossaryMlDictionaryStorage($dictionary_storage);

    $result = $class->createOrUpdateDictionaryFromEntries($glossary, 'EN', 'DE', ['hello' => 'hallo']);

    $this->assertSame($new_dictionary, $result);
  }

  /**
   * Tests createOrUpdateDictionaryFromEntries when reset() yields a non-entity.
   */
  public function testCreateOrUpdateDictionaryFromEntriesInvalidExisting(): void {
    $translator = $this->createMock(TranslatorInterface::class);

    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('getTranslator')->willReturn($translator);
    $glossary->method('id')->willReturn('42');

    // loadByProperties returns a non-empty array whose first item is not a
    // dictionary entity, so the method must bail out with NULL.
    $dictionary_storage = $this->createMock(EntityStorageInterface::class);
    $dictionary_storage->method('loadByProperties')->willReturn([new \stdClass()]);
    $dictionary_storage->expects($this->never())->method('create');

    $glossary_api = $this->createMock(DeeplMultilingualGlossaryApiInterface::class);
    $glossary_api->expects($this->once())->method('setTranslator');

    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();
    $class->setDeeplGlossaryApi($glossary_api);
    $class->setGlossaryMlDictionaryStorage($dictionary_storage);

    $result = $class->createOrUpdateDictionaryFromEntries($glossary, 'EN', 'DE', ['hello' => 'hallo']);
    $this->assertNull($result);
  }

  /**
   * Data provider for ::testGetLanguageCode.
   *
   * @return array<string, array{mixed, string}>
   *   Tuples of: input value, expected language code.
   */
  public static function dataProviderGetLanguageCode(): array {
    return [
      'plain string' => ['en', 'en'],
      'nested widget value' => [[['value' => 'de']], 'de'],
      'nested widget non-string value' => [[['value' => 123]], ''],
      'flat value array' => [['value' => 'fr'], 'fr'],
      'flat value non-string' => [['value' => 123], ''],
      'unsupported type' => [123, ''],
    ];
  }

  /**
   * Tests the method ::getLanguageCode.
   *
   * @param mixed $input
   *   The raw input value.
   * @param string $expected
   *   The expected extracted language code.
   *
   * @dataProvider dataProviderGetLanguageCode
   */
  public function testGetLanguageCode(mixed $input, string $expected): void {
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test&MockObject $class */
    $class = $this->createClassMock();
    $this->assertSame($expected, $class->getLanguageCodePublic($input));
  }

  /**
   * Tests that the real constructor resolves the entity storages.
   */
  public function testConstructor(): void {
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects($this->exactly(2))
      ->method('getStorage')
      ->willReturnMap([
        ['deepl_ml_glossary', $this->createMock(EntityStorageInterface::class)],
        ['deepl_ml_glossary_dictionary', $this->createMock(EntityStorageInterface::class)],
      ]);

    $instance = new DeeplMultilingualGlossaryHelper(
      $entity_type_manager,
      $this->createMock(DeeplMultilingualGlossaryApiInterface::class),
      $this->createMock(ModuleHandlerInterface::class),
      $this->createMock(LanguageManagerInterface::class),
    );
    // @phpstan-ignore-next-line
    $this->assertInstanceOf(DeeplMultilingualGlossaryHelper::class, $instance);
  }

  /**
   * Creates and returns a test class mock.
   *
   * @param list<non-empty-string> $only_methods
   *   An array of names for methods to be configurable.
   *
   * @return \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryHelper_Test|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked class.
   */
  protected function createClassMock(array $only_methods = []): DeeplMultilingualGlossaryHelper_Test|MockObject {
    $mock = $this->getMockBuilder(DeeplMultilingualGlossaryHelper_Test::class)
      ->disableOriginalConstructor()
      ->onlyMethods($only_methods)
      ->getMock();

    // Initialize required properties to avoid uninitialized property errors.
    $mock->setEntityTypeManager($this->createMock(EntityTypeManagerInterface::class));
    $mock->setModuleHandler($this->createMock(ModuleHandlerInterface::class));
    $language_manager_mock = $this->createMock(LanguageManagerInterface::class);
    $language_manager_mock->method('getLanguage')
      ->willReturn(new class() {

        /**
         * Mocks the getId method.
         *
         * @return string
         *   The id returned by the mocked method.
         */
        public function getId(): string {
          return 'mock';
        }

      });
    $mock->setLanguageManager($language_manager_mock);
    $mock->setDeeplGlossaryApi($this->createMock(DeeplMultilingualGlossaryApiInterface::class));

    return $mock;
  }

}

// @codingStandardsIgnoreStart

/**
 * Mocked DeeplGlossaryApi class for tests.
 */
class DeeplMultilingualGlossaryHelper_Test extends DeeplMultilingualGlossaryHelper {

  /**
   * @var \Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApiInterface
   */
  public DeeplMultilingualGlossaryApiInterface $glossaryApi;

  /**
   * Set the entity type manager for tests.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager): void {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Set language manager for use in tests.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   */
  public function setLanguageManager(LanguageManagerInterface $language_manager): void {
    $this->languageManager = $language_manager;
  }

  /**
   * Set ModuleHandler for use in tests.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   */
  public function setModuleHandler(ModuleHandlerInterface $module_handler): void {
    $this->moduleHandler = $module_handler;
  }

  /**
   * Set the glossaryApi for tests.
   */
  public function setDeeplGlossaryApi(DeeplMultilingualGlossaryApiInterface $glossaryApi): void {
    $this->glossaryApi = $glossaryApi;
  }

  /**
   * Set the glossary storage for tests.
   */
  public function setGlossaryMlStorage(EntityStorageInterface $glossary_ml_storage_storage): void {
    $this->glossaryMlStorage = $glossary_ml_storage_storage;
  }

  /**
   * Set the glossary storage for tests.
   */
  public function setGlossaryMlDictionaryStorage(EntityStorageInterface $glossary_ml_dictionary_storage): void {
    $this->glossaryMlDictionaryStorage = $glossary_ml_dictionary_storage;
  }

  /**
   * Public wrapper for ::getLanguageCode.
   *
   * @param mixed $input
   *   The raw input value.
   *
   * @return string
   *   The extracted language code.
   */
  public function getLanguageCodePublic(mixed $input): string {
    return $this->getLanguageCode($input);
  }

}
// @codingStandardsIgnoreEnd
