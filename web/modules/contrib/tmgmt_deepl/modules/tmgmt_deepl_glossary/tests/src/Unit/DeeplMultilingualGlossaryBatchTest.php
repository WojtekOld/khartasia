<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Unit;

use DeepL\MultilingualGlossaryDictionaryInfo;
use DeepL\MultilingualGlossaryInfo;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApi;
use Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryBatch;
use Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryHelper;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the DeeplMultilingualGlossaryBatch service.
 *
 * @covers \Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryBatch
 * @group tmgmt_deepl_glossary
 */
class DeeplMultilingualGlossaryBatchTest extends UnitTestCase {

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
   * Tests mergeMultipleIntoSingleGlossary with a single glossary.
   */
  public function testMergeMultipleIntoSingleGlossarySingle(): void {
    $class = $this->createClassMock();

    $creation_time = new \DateTime();
    $glossary_info = new MultilingualGlossaryInfo('glossary-uuid', 'Test Glossary', $creation_time, []);

    $glossary_data = [
      'glossary_1' => [
        'glossary' => $glossary_info,
        'dictionaries' => [
          [
            'source_lang' => 'en',
            'target_lang' => 'de',
            'entries' => [
              ['subject' => 'Hello', 'definition' => 'Hallo'],
              ['subject' => 'World', 'definition' => 'Welt'],
            ],
          ],
        ],
      ],
    ];

    $result = $class->mergeMultipleIntoSingleGlossaryPublic($glossary_data);
    $this->assertCount(1, $result['dictionaries']);
    $this->assertCount(2, $result['dictionaries'][0]['entries']);
    $this->assertSame('Test Glossary', $result['glossary']->name);
  }

  /**
   * Tests mergeMultipleIntoSingleGlossary with multiple glossaries.
   */
  public function testMergeMultipleIntoSingleGlossaryMultiple(): void {
    $class = $this->createClassMock();

    $creation_time = new \DateTime();
    $glossary_1 = new MultilingualGlossaryInfo('glossary-1', 'Glossary 1', $creation_time, []);
    $glossary_2 = new MultilingualGlossaryInfo('glossary-2', 'Glossary 2', $creation_time, []);

    $glossary_data = [
      'g1' => [
        'glossary' => $glossary_1,
        'dictionaries' => [
          [
            'source_lang' => 'en',
            'target_lang' => 'de',
            'entries' => [
              ['subject' => 'Hello', 'definition' => 'Hallo'],
              ['subject' => 'Goodbye', 'definition' => 'Tschüss'],
            ],
          ],
        ],
      ],
      'g2' => [
        'glossary' => $glossary_2,
        'dictionaries' => [
          [
            'source_lang' => 'en',
            'target_lang' => 'de',
            'entries' => [
              ['subject' => 'Hello', 'definition' => 'Hallo'],
              ['subject' => 'World', 'definition' => 'Welt'],
            ],
          ],
        ],
      ],
    ];

    $result = $class->mergeMultipleIntoSingleGlossaryPublic($glossary_data);
    $this->assertSame('Merged Glossary', $result['glossary']->name);
    $this->assertCount(1, $result['dictionaries']);
    $this->assertCount(3, $result['dictionaries'][0]['entries']);
    $subjects = array_column($result['dictionaries'][0]['entries'], 'subject');
    $this->assertContains('Hello', $subjects);
    $this->assertContains('World', $subjects);
    $this->assertContains('Goodbye', $subjects);
  }

  /**
   * Tests the method ::buildBatch.
   */
  public function testBuildBatch(): void {
    // Mock the batch builder.
    $batch_builder = $this->createMock(BatchBuilder::class);
    $batch_builder->expects($this->once())
      ->method('toArray')
      ->willReturn([]);

    // Mock translators.
    $translator_01 = $this->createMock(Translator::class);
    $translator_02 = $this->createMock(Translator::class);

    // Mock the glossary helper.
    $glossary_helper = $this->createMock(DeeplMultilingualGlossaryHelper::class);
    $glossary_helper->expects($this->once())
      ->method('getAllowedTranslators')
      ->willReturn([
        $translator_01,
        $translator_02,
      ]);

    // Mock the class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryBatch_Test&MockObject $class */
    $class = $this->createClassMock([
      'createBatchBuilder',
      'addBatchOperations',
      'setBatch',
    ]);

    $class->setGlossaryHelper($glossary_helper);

    // Mocking method calls on the DeeplTranslatorBatch instance.
    $class->expects($this->once())
      ->method('createBatchBuilder')
      ->willReturn($batch_builder);

    $class->expects($this->once())
      ->method('addBatchOperations')
      ->with($batch_builder);

    $class->expects($this->once())
      ->method('setBatch')
      ->with([]);

    $class->buildBatch();
  }

  /**
   * Tests the method ::buildBatch with no translators.
   */
  public function testBuildBatchEmptyTranslators(): void {
    $glossary_helper = $this->createMock(DeeplMultilingualGlossaryHelper::class);
    $glossary_helper->expects($this->once())
      ->method('getAllowedTranslators')
      ->willReturn([]);

    $class = $this->createClassMock([
      'createBatchBuilder',
      'addBatchOperations',
      'setBatch',
    ]);
    $class->setGlossaryHelper($glossary_helper);

    $class->expects($this->never())->method('createBatchBuilder');
    $class->expects($this->never())->method('addBatchOperations');
    $class->expects($this->never())->method('setBatch');

    $class->buildBatch();
  }

  /**
   * Tests the method ::saveMultilingualGlossaryDictionaryOperation.
   */
  public function testSaveMultilingualGlossaryDictionaryOperation(): void {

    // Mock translator.
    $translator = $this->createMock(TranslatorInterface::class);

    // Mock MultilingualGlossaryInfo.
    $glossary = new MultilingualGlossaryInfo(
      'test_glossary',
      'Test Glossary',
      new \DateTime(),
      [],
    );

    // Mock dictionary data.
    $dictionary = [
      'source_lang' => 'en',
      'target_lang' => 'de',
      'entries' => [
        'hello' => 'hallo',
        'world' => 'welt',
      ],
    ];

    // Mock context.
    $context = [];

    // Mock the glossary helper.
    $glossary_helper = $this->createMock(DeeplMultilingualGlossaryHelper::class);
    $glossary_helper->expects($this->once())
      ->method('saveGlossaryDictionary')
      ->with($glossary, $dictionary, $translator);

    // Mock the class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryBatch_Test&MockObject $class */
    $class = $this->createClassMock();

    $class->setGlossaryHelper($glossary_helper);

    // Run the test.
    $class->saveMultilingualGlossaryDictionaryOperation($translator, $glossary, $dictionary, $context);

    // Assert context message and results.
    $this->assertArrayHasKey('message', $context);
    $this->assertArrayHasKey('results', $context);

    assert(is_array($context['results']));
    $this->assertArrayHasKey('glossaries', $context['results']);

    assert(is_array($context['results']['glossaries']));
    $this->assertCount(1, $context['results']['glossaries']);
    $this->assertArrayHasKey('test_glossary', $context['results']['glossaries']);

    assert(is_array($context['results']['glossaries']['test_glossary']));
    $this->assertCount(1, $context['results']['glossaries']['test_glossary']);

    assert(is_array($context['results']['glossaries']['test_glossary'][0]));
    $this->assertEquals('Test Glossary Dictionary en -> de', $context['results']['glossaries']['test_glossary'][0]['name']);
    $this->assertEquals(2, $context['results']['glossaries']['test_glossary'][0]['entry_count']);
  }

  /**
   * Tests the method ::syncMergedMultilingualGlossaryDictionaryOperation.
   */
  public function testSyncMergedMultilingualGlossaryDictionaryOperation(): void {
    // Mock translator.
    $translator = $this->createMock(TranslatorInterface::class);

    // Mock MultilingualGlossaryInfo.
    $glossary = new MultilingualGlossaryInfo(
      'test_glossary',
      'Test Glossary',
      new \DateTime(),
      [],
    );

    // Mock dictionary data.
    $dictionary = [
      'source_lang' => 'en',
      'target_lang' => 'de',
      'entries' => [
        ['subject' => 'hello', 'definition' => 'hallo'],
        ['subject' => 'world', 'definition' => 'welt'],
      ],
    ];

    // Mock context.
    $context = [];

    // Mock the glossary api.
    $deepl_glossary_api = $this->createMock(DeeplMultilingualGlossaryApi::class);
    $deepl_glossary_api->expects($this->once())
      ->method('setTranslator')
      ->with($translator);

    $deepl_glossary_api->expects($this->once())
      ->method('replaceMultilingualGlossaryDictionary')
      ->with('test_glossary', 'en', 'de', ['hello' => 'hallo', 'world' => 'welt']);

    // Mock the class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryBatch_Test&MockObject $class */
    $class = $this->createClassMock();

    $class->setDeeplGlossaryApi($deepl_glossary_api);

    // Run the test.
    $class->syncMergedMultilingualGlossaryDictionaryOperation($translator, $glossary, $dictionary, $context);
  }

  /**
   * Tests the method ::cleanUpOperation.
   */
  public function testCleanUpOperation(): void {
    // Define DeepL glossaries from the API.
    $deepl_glossaries = [
      ['glossaryId' => 'glossary1'],
      ['glossaryId' => 'glossary2'],
    ];

    // Machine name of translator for testing.
    $translator = 'translator1';

    // Mock context.
    $context = [];

    // Create mock glossary entities to represent different scenarios.
    // Scenario 1: Glossary exists in both DeepL API and local storage.
    $glossary_01 = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary_01->expects($this->once())
      ->method('get')
      ->with('glossary_id')
      ->willReturn((object) ['value' => 'glossary1']);
    $glossary_01->expects($this->never())
      ->method('delete');

    // Scenario 2: Glossary exists in local storage but not in DeepL API.
    $glossary_02 = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary_02->expects($this->once())
      ->method('get')
      ->with('glossary_id')
      ->willReturn((object) ['value' => 'glossary3']);
    $glossary_02->expects($this->once())
      ->method('delete');

    // Mock the glossary storage.
    $glossary_storage = $this->createMock(EntityStorageInterface::class);
    $glossary_storage->expects($this->once())
      ->method('loadByProperties')
      ->with(['tmgmt_translator' => $translator])
      ->willReturn([$glossary_01, $glossary_02]);

    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryBatch_Test&MockObject $class */
    $class = $this->createClassMock();
    $class->setGlossaryStorage($glossary_storage);

    $class->cleanUpOperation($deepl_glossaries, $translator, $context);
  }

  /**
   * Tests ::cleanUpOperation deletes a local glossary that has no glossary_id.
   */
  public function testCleanUpOperationDeletesGlossaryWithoutId(): void {
    $deepl_glossaries = [['glossaryId' => 'glossary1']];
    $translator = 'translator1';
    $context = [];

    // Glossary without a glossary_id (e.g. a failed creation) must be deleted.
    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->expects($this->once())
      ->method('get')
      ->with('glossary_id')
      ->willReturn((object) ['value' => NULL]);
    $glossary->expects($this->once())->method('delete');

    $glossary_storage = $this->createMock(EntityStorageInterface::class);
    $glossary_storage->expects($this->once())
      ->method('loadByProperties')
      ->with(['tmgmt_translator' => $translator])
      ->willReturn([$glossary]);

    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryBatch_Test&MockObject $class */
    $class = $this->createClassMock();
    $class->setGlossaryStorage($glossary_storage);

    $class->cleanUpOperation($deepl_glossaries, $translator, $context);
  }

  /**
   * Data provider for testFinishedOperation.
   *
   * @return array
   *   Array of test data.
   */
  public static function dataProviderTestFinishedOperation(): array {
    // @phpcs:disable
    return [
      'Operation with success' => [
        'message_type' => 'addStatus',
        'results' => [
          'glossaries' => [
            'glossary1' => [
              ['name' => 'Glossary 1', 'entry_count' => 10],
            ],
            'glossary2' => [
              ['name' => 'Glossary 2', 'entry_count' => 5],
            ],
          ],
        ],
        'expected_message' => 'DeepL glossaries were synced successfully.',
        'success' => TRUE,
      ],
      'Operation with warning' => [
        'message_type' => 'addWarning',
        'results' => [],
        'expected_message' => 'Could not find any glossary for syncing.',
        'success' => TRUE,
      ],
      'Operation with error' => [
        'message_type' => 'addError',
        'results' => [],
        'expected_message' => 'An error occurred while syncing glossaries.',
        'success' => FALSE,
      ],
    ];
    // @phpcs:enable
  }

  /**
   * Tests the method ::finishedOperation.
   *
   * @param string $message_type
   *   The type of message to add to the messenger.
   * @param array $results
   *   The results of the operation.
   * @param string $expected_message
   *   The expected message to add to the messenger.
   * @param bool $success
   *   The success status of the operation.
   *
   * @dataProvider dataProviderTestFinishedOperation
   */
  public function testFinishedOperation(string $message_type, array $results, string $expected_message, bool $success = TRUE): void {
    $operations = [];

    // Mock the translation.
    $translation = $this->createMock(TranslationInterface::class);

    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())
      ->method($message_type)
      // @codingStandardsIgnoreStart
      ->with($this->equalTo(new TranslatableMarkup($expected_message, [], [], $translation)));
      // @codingStandardsIgnoreEnd

    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryBatch_Test&MockObject $class */
    $class = $this->createClassMock();
    $class->setMessenger($messenger);

    $class->finishedOperation($success, $results, $operations);
  }

  /**
   * Data provider for testAddBatchOperations.
   *
   * @return array
   *   Array of test data.
   */
  public static function dataProviderTestAddBatchOperations(): array {
    return [
      'Free account with single glossary' => [
        'is_free_account' => TRUE,
        'glossary_count' => 1,
        'expected_sync_calls' => 0,
      ],
      'Free account with multiple glossaries' => [
        'is_free_account' => TRUE,
        'glossary_count' => 2,
        'expected_sync_calls' => 4,
      ],
      'Paid account' => [
        'is_free_account' => FALSE,
        'glossary_count' => 1,
        'expected_sync_calls' => 0,
      ],
    ];
  }

  /**
   * Tests the method ::addBatchOperations.
   *
   * @param bool $is_free_account
   *   Whether it's a free account.
   * @param int $glossary_count
   *   Number of glossaries.
   * @param int $expected_sync_calls
   *   Expected number of sync calls.
   *
   * @dataProvider dataProviderTestAddBatchOperations
   */
  public function testAddBatchOperations(bool $is_free_account, int $glossary_count, int $expected_sync_calls): void {
    // Mock the batch builder.
    $batch_builder = $this->createMock(BatchBuilder::class);
    // Mock translators.
    $deepl_translators = ['translator1' => []];

    // Create multinational glossaries.
    $glossaries = [];
    for ($i = 0; $i < $glossary_count; $i++) {
      $glossary_id = 'test_glossary_' . $i;
      $dictionary_01 = new MultilingualGlossaryDictionaryInfo('source_lang_1', 'target_lang_1', 2);
      $dictionary_02 = new MultilingualGlossaryDictionaryInfo('source_lang_2', 'target_lang_2', 2);
      $glossary = new MultilingualGlossaryInfo($glossary_id, 'Test Glossary ' . $i, new \DateTime(), [
        $dictionary_01,
        $dictionary_02,
      ]);
      $glossaries[] = $glossary;
    }

    // Mock the translator.
    $translator = $this->createMock(TranslatorInterface::class);
    $translator->method('id')->willReturn('translator1');

    // Mock the glossary storage.
    $translator_storage = $this->createMock(EntityStorageInterface::class);
    $translator_storage->expects($this->once())
      ->method('load')
      ->with('translator1')
      ->willReturn($translator);

    // Mock the entity type manager.
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects($this->once())
      ->method('getStorage')
      ->with('tmgmt_translator')
      ->willReturn($translator_storage);

    // Mock the glossary api.
    $deepl_glossary_api = $this->createMock(DeeplMultilingualGlossaryApi::class);
    $deepl_glossary_api->expects($this->once())
      ->method('getMultilingualGlossaries')
      ->willReturn($glossaries);

    $deepl_glossary_api->expects($this->once())
      ->method('setTranslator')
      ->with($translator);

    $deepl_glossary_api->expects($this->exactly($glossary_count * 2))
      ->method('getMultilingualGlossaryEntries')
      ->willReturn([
        ['subject' => 'hello', 'definition' => 'hallo'],
        ['subject' => 'world', 'definition' => 'welt'],
      ]);

    // All calls to isFreeAccount will return the test value.
    $deepl_glossary_api->method('isFreeAccount')
      ->willReturn($is_free_account);

    // Mock the class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryBatch_Test&MockObject $class */
    $class = $this->createClassMock([
      'mergeMultipleIntoSingleGlossary',
    ]);

    $class->setEntityTypeManager($entity_type_manager);
    $class->setDeeplGlossaryApi($deepl_glossary_api);

    $merged_glossary = new MultilingualGlossaryInfo('merged_glossary', 'Merged Glossary', new \DateTime(), []);

    // Expected calls building logic.
    $expected_calls = [];
    if (!$is_free_account) {
      // For paid accounts: direct save for each glossary and dictionary.
      foreach ($glossaries as $glossary) {
        $glossary_data = [
          'glossary' => $glossary,
          'dictionaries' => [
            [
              'source_lang' => 'source_lang_1',
              'target_lang' => 'target_lang_1',
              'entries' => [
                ['subject' => 'hello', 'definition' => 'hallo'],
                ['subject' => 'world', 'definition' => 'welt'],
              ],
            ],
            [
              'source_lang' => 'source_lang_2',
              'target_lang' => 'target_lang_2',
              'entries' => [
                ['subject' => 'hello', 'definition' => 'hallo'],
                ['subject' => 'world', 'definition' => 'welt'],
              ],
            ],
          ],
        ];

        foreach ($glossary_data['dictionaries'] as $dictionary) {
          $expected_calls[] = [
            [$class, 'saveMultilingualGlossaryDictionaryOperation'],
            [$translator, $glossary, $dictionary],
          ];
        }
      }
    }
    else {
      // For free accounts: merge and then save/sync.
      $merged_dictionaries = [
        [
          'source_lang' => 'source_lang_1',
          'target_lang' => 'target_lang_1',
          'entries' => [
            ['subject' => 'hello', 'definition' => 'hallo'],
          ],
        ],
        [
          'source_lang' => 'source_lang_2',
          'target_lang' => 'target_lang_2',
          'entries' => [
            ['subject' => 'world', 'definition' => 'welt'],
          ],
        ],
      ];

      $class->expects($this->once())
        ->method('mergeMultipleIntoSingleGlossary')
        ->willReturn([
          'glossary' => $merged_glossary,
          'dictionaries' => $merged_dictionaries,
        ]);

      foreach ($merged_dictionaries as $dictionary) {
        $expected_calls[] = [
          [$class, 'saveMultilingualGlossaryDictionaryOperation'],
          [$translator, $merged_glossary, $dictionary],
        ];
        if ($glossary_count > 1) {
          $expected_calls[] = [
            [$class, 'syncMergedMultilingualGlossaryDictionaryOperation'],
            [$translator, $merged_glossary, $dictionary],
          ];
        }
      }
    }

    // Add cleanup operation.
    $expected_calls[] = [
      [$class, 'cleanUpOperation'],
      [$glossaries, 'translator1'],
    ];

    $callIndex = 0;
    $batch_builder->expects($this->exactly(count($expected_calls)))
      ->method('addOperation')
      ->willReturnCallback(function ($method, $args) use (&$callIndex, $expected_calls) {
        [$expected_method, $expected_args] = $expected_calls[$callIndex];
        $this->assertEquals($expected_method, $method);
        $this->assertEquals($expected_args, $args);
        $callIndex++;
      });

    $class->addBatchOperations($batch_builder, $deepl_translators);
  }

  /**
   * Tests the method ::createBatchBuilder.
   */
  public function testCreateBatchBuilder(): void {
    // Mock the class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryBatch_Test&MockObject $class */
    $class = $this->createClassMock();
    $batch_builder = $class->createBatchBuilder();

    // @phpstan-ignore-next-line.
    $this->assertInstanceOf(BatchBuilder::class, $batch_builder);
  }

  /**
   * Creates and returns a test class mock.
   *
   * @param list<non-empty-string> $only_methods
   *   An array of names for methods to be configurable.
   *
   * @return \Drupal\Tests\tmgmt_deepl_glossary\Unit\DeeplMultilingualGlossaryBatch_Test|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked class.
   */
  protected function createClassMock(array $only_methods = []): DeeplMultilingualGlossaryBatch_Test|MockObject {
    return $this->getMockBuilder(DeeplMultilingualGlossaryBatch_Test::class)
      ->disableOriginalConstructor()
      ->onlyMethods($only_methods)
      ->getMock();
  }

}

// @codingStandardsIgnoreStart

/**
 * Mocked DeeplMultilingualGlossaryBatch class for tests.
 */
class DeeplMultilingualGlossaryBatch_Test extends DeeplMultilingualGlossaryBatch {

  /**
   * {@inheritDoc}
   */
  public function createBatchBuilder(): BatchBuilder {
    return parent::createBatchBuilder();
  }

  /**
   * {@inheritDoc}
   */
  public function addBatchOperations(BatchBuilder $batch_builder, array $deepl_translators): void {
    parent::addBatchOperations($batch_builder, $deepl_translators);
  }

  /**
   * Set the glossary helper for tests.
   */
  public function setGlossaryHelper(DeeplMultilingualGlossaryHelper $glossary_helper): void {
    $this->glossaryHelper = $glossary_helper;
  }

  /**
   * Set the Deepl glossary api for tests.
   */
  public function setDeeplGlossaryApi(DeeplMultilingualGlossaryApi $deepl_glossary_api): void {
    $this->deeplGlossaryApi = $deepl_glossary_api;
  }

  /**
   * Set the glossary storage for tests.
   */
  public function setGlossaryStorage(EntityStorageInterface $glossary_ml_storage): void {
    $this->glossaryMlStorage = $glossary_ml_storage;
  }

  /**
   * Set the messenger for tests.
   */
  public function setMessenger(MessengerInterface $messenger): void {
    $this->messenger = $messenger;
  }

  /**
   * Set the entity type manager for tests.
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager): void {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Public wrapper for mergeMultipleIntoSingleGlossary.
   *
   * @param array $glossary_data
   *   The glossary data array.
   *
   * @return array{glossary: \DeepL\MultilingualGlossaryInfo, dictionaries: list<array{source_lang: string, target_lang: string, entries: array}>}
   *   The merged glossary data.
   */
  public function mergeMultipleIntoSingleGlossaryPublic(array $glossary_data): array {
    return $this->mergeMultipleIntoSingleGlossary($glossary_data);
  }

}
// @codingStandardsIgnoreEnd
