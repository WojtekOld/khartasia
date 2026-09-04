<?php

namespace Drupal\Tests\tmgmt_deepl\Unit;

use DeepL\TextResult;
use Drupal\Component\Utility\Html;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tmgmt\Data;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt\TranslatorPluginBase;
use Drupal\tmgmt_deepl\DeeplTranslatorApi;
use Drupal\tmgmt_deepl\DeeplTranslatorBatch;
use Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator;
use Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslatorInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests the DeeplTranslatorBatch service.
 *
 * @covers \Drupal\tmgmt_deepl\DeeplTranslatorBatch
 * @group tmgmt_deepl
 */
class DeeplTranslatorBatchTest extends UnitTestCase {

  /**
   * The job object.
   *
   * @var \Drupal\tmgmt\Entity\Job|\PHPUnit\Framework\MockObject\MockObject
   */
  protected Job|MockObject $job;

  /**
   * The job item object.
   *
   * @var \Drupal\tmgmt\JobItemInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected JobItemInterface|MockObject $jobItem;

  /**
   * The translator object.
   *
   * @var \Drupal\tmgmt\Entity\Translator|\PHPUnit\Framework\MockObject\MockObject
   */
  protected Translator|MockObject $translator;

  /**
   * The DeepL translator plugin object.
   *
   * @var \Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslatorInterface|\PHPUnit\Framework\MockObject\MockObject|\Drupal\tmgmt\TranslatorPluginBase
   */
  protected DeeplTranslatorInterface|MockObject|TranslatorPluginBase $translatorPlugin;

  /**
   * Creates a DeeplTranslatorBatch with mocked dependencies.
   *
   * @param array $overrides
   *   Optional overrides for the mocked deps.
   *
   * @phpstan-param array{deepl_api?: \Drupal\tmgmt_deepl\DeeplTranslatorApiInterface, data?: \Drupal\tmgmt\Data, module_handler?: \Drupal\Core\Extension\ModuleHandlerInterface, file_usage?: \Drupal\file\FileUsage\FileUsageInterface} $overrides
   *
   * @return \Drupal\tmgmt_deepl\DeeplTranslatorBatch
   *   The configured batch service instance.
   */
  private function createBatch(array $overrides = []): DeeplTranslatorBatch {
    return new DeeplTranslatorBatch(
      $overrides['deepl_api'] ?? $this->createMock(DeeplTranslatorApi::class),
      $overrides['data'] ?? $this->createMock(Data::class),
      $overrides['module_handler'] ?? $this->createMock(ModuleHandlerInterface::class),
      $overrides['file_usage'] ?? $this->createMock(FileUsageInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->job = $this->createMock(Job::class);
    $this->jobItem = $this->createMock(JobItemInterface::class);
    $this->translatorPlugin = $this->createMock(DeeplTranslator::class);
    $this->translator = $this->createMock(Translator::class);
  }

  /**
   * Tests buildBatch() creates and sets up a batch.
   *
   * BuildBatch() calls batch_set() internally, which is defined in
   * Drupal's form.inc. That global function is not available in unit test
   * context, so we verify the orchestration through addBatchOperations()
   * and createBatchBuilder() instead.
   */
  public function testBuildBatch(): void {
    $batch = $this->createBatch();

    $builder = $this->invokeProtectedMethod($batch, 'createBatchBuilder');
    $this->assertInstanceOf(BatchBuilder::class, $builder);
    $this->assertSame('Translating job items', $builder->toArray()['title']);

    $q = ['text1', 'text2', 'text3', 'text4', 'text5', 'text6'];
    $keys_sequence = ['key1', 'key2', 'key3', 'key4', 'key5', 'key6'];
    $this->invokeProtectedMethod($batch, 'addBatchOperations', [
      $builder,
      $this->job,
      $this->jobItem,
      $q,
      $keys_sequence,
    ]);

    $operations = $builder->toArray()['operations'];
    $this->assertIsArray($operations);
    // 2 translate chunks (5 + 1) + 1 beforeFinishedOperation.
    $this->assertCount(3, $operations);
  }

  /**
   * Tests createBatchBuilder() returns a BatchBuilder instance.
   */
  public function testCreateBatchBuilder(): void {
    $batch = $this->createBatch();
    $result = $this->invokeProtectedMethod($batch, 'createBatchBuilder');
    $this->assertInstanceOf(BatchBuilder::class, $result);
  }

  /**
   * Data provider for testTranslateOperation.
   */
  public static function dataProviderTranslateOperation(): array {
    return [
      'Without context' => [
        'context_setting' => NULL,
        'expected_context' => NULL,
      ],
      'With context' => [
        'context_setting' => 'Sample context text.',
        'expected_context' => 'Sample context text.',
      ],
    ];
  }

  /**
   * Tests translateOperation() translates text and merges results.
   *
   * @param string|null $context_setting
   *   The context setting for the job.
   * @param string|null $expected_context
   *   The expected context passed to the translator API.
   *
   * @dataProvider dataProviderTranslateOperation
   */
  public function testTranslateOperation(?string $context_setting, ?string $expected_context): void {
    $text = ['text1', 'text2'];
    $keys_sequence = ['key1', 'key2'];
    $context = [];

    $this->translator->expects($this->atLeastOnce())
      ->method('getPlugin')
      ->willReturn($this->translatorPlugin);

    $this->translatorPlugin->expects($this->once())
      ->method('getDefaultSettings')
      ->with($this->translator)
      ->willReturn([]);

    $this->translatorPlugin->expects($this->exactly(2))
      ->method('unescapeText')
      ->willReturnOnConsecutiveCalls('translated_text1', 'translated_text2');

    // The real getTranslationContext() runs; set up enable_context accordingly.
    $enable_context = $context_setting !== NULL;
    $this->translator->method('getSetting')
      ->willReturnMap([
        ['enable_context', $enable_context],
      ]);
    if ($enable_context) {
      $this->job->method('getSetting')
        ->with('context')
        ->willReturn($expected_context);
    }

    $translated_text1 = $this->createMock(TextResult::class);
    $translated_text2 = $this->createMock(TextResult::class);

    $this->job->expects($this->any())
      ->method('getTranslator')
      ->willReturn($this->translator);
    $this->job->expects($this->once())
      ->method('getRemoteSourceLanguage')
      ->willReturn('EN-GB');
    $this->job->expects($this->once())
      ->method('getRemoteTargetLanguage')
      ->willReturn('DE');

    $deepl_api = $this->createMock(DeeplTranslatorApi::class);
    $deepl_api->expects($this->once())
      ->method('setTranslator')
      ->with($this->translator);
    $deepl_api->expects($this->once())
      ->method('fixSourceLanguageMappings')
      ->with('EN-GB')
      ->willReturn('EN');
    $deepl_api->expects($this->once())
      ->method('translate')
      ->with($this->translator, $text, 'EN', 'DE', [], $expected_context)
      ->willReturn([$translated_text1, $translated_text2]);

    $module_handler = $this->createMock(ModuleHandlerInterface::class);

    $batch = $this->createBatch([
      'deepl_api' => $deepl_api,
      'module_handler' => $module_handler,
    ]);

    $batch->translateOperation($this->job, $text, $keys_sequence, $context);

    $this->assertIsArray($context['results']);
    $this->assertEquals([
      'key1' => ['#text' => 'translated_text1'],
      'key2' => ['#text' => 'translated_text2'],
    ], $context['results']['translation']);
  }

  /**
   * Tests beforeFinishedOperation() stores the job item in context.
   */
  public function testBeforeFinishedOperation(): void {
    $context = [];
    $batch = $this->createBatch();
    $batch->beforeFinishedOperation($this->jobItem, $context);

    $this->assertIsArray($context['results']);
    $this->assertEquals($this->jobItem, $context['results']['job_item']);
  }

  /**
   * Tests finishedOperation() adds translated data and writes messages.
   */
  public function testFinishedOperation(): void {
    $this->jobItem->expects($this->once())
      ->method('addTranslatedData')
      ->with(['key1' => ['#text' => 'translated_text1']]);
    $this->jobItem->expects($this->once())
      ->method('getJob')
      ->willReturn($this->job);

    $results = [
      'job_item' => $this->jobItem,
      'translation' => ['key1' => ['#text' => 'translated_text1']],
    ];

    $tmgmt_data = $this->createMock(Data::class);
    $tmgmt_data->expects($this->once())
      ->method('unflatten')
      ->with(['key1' => ['#text' => 'translated_text1']])
      ->willReturn(['key1' => ['#text' => 'translated_text1']]);

    // Use an anonymous subclass to stub out the procedural TMGMT function call,
    // keeping this a pure unit test without filesystem dependencies.
    $batch = new class(
      $this->createMock(DeeplTranslatorApi::class),
      $tmgmt_data,
      $this->createMock(ModuleHandlerInterface::class),
      $this->createMock(FileUsageInterface::class),
    ) extends DeeplTranslatorBatch {

      /**
       * {@inheritdoc}
       */
      protected function tmgmtWriteRequestMessages(JobInterface $job): void {}

    };

    $batch->finishedOperation(TRUE, $results, []);
  }

  /**
   * Tests finishedOperation() skips processing when the batch failed.
   */
  public function testFinishedOperationOnFailureSkipsProcessing(): void {
    $this->jobItem->expects($this->never())->method('addTranslatedData');

    $results = [
      'job_item' => $this->jobItem,
      'translation' => ['key1' => ['#text' => 'translated_text1']],
    ];

    $batch = $this->createBatch();
    $batch->finishedOperation(FALSE, $results, []);
  }

  /**
   * Data provider for mergeTranslations.
   */
  public static function dataProviderTestMergeTranslations(): array {
    return [
      'with available context' => [
        [
          'results' => [
            'translation' => [
              'existing_translation_1',
              'existing_translation_2',
            ],
          ],
        ],
        [
          'existing_translation_1',
          'existing_translation_2',
          'new_translation_1',
          'new_translation_2',
        ],
      ],
      'with empty context' => [
        [],
        ['new_translation_1', 'new_translation_2'],
      ],
    ];
  }

  /**
   * Tests mergeTranslations().
   *
   * @param array $context
   *   The given translation context.
   * @param array $expected_result
   *   The expected result.
   *
   * @dataProvider dataProviderTestMergeTranslations
   */
  public function testMergeTranslations(array $context, array $expected_result): void {
    $translation = ['new_translation_1', 'new_translation_2'];
    $batch = $this->createBatch();

    $result = $this->invokeProtectedMethod($batch, 'mergeTranslations', [$context, $translation]);
    $this->assertEquals($expected_result, $result);
  }

  /**
   * Data provider for decodeAndUnescapeText.
   */
  public static function dataProviderTestDecodeAndUnescapeText(): array {
    return [
      'xml' => ['xml', 'translated &amp; text', 'translated & text'],
      'html' => ['html', 'translated &amp; text', 'translated & text'],
      'off' => ['0', 'translated text', 'translated text'],
    ];
  }

  /**
   * Tests decodeAndUnescapeText().
   *
   * @param string $tag_handling
   *   Type of tag handling.
   * @param string $text
   *   The text to decode.
   * @param string $expected_result
   *   The expected result.
   *
   * @dataProvider dataProviderTestDecodeAndUnescapeText
   */
  public function testDecodeAndUnescapeText(string $tag_handling, string $text, string $expected_result): void {
    $this->translator->expects($this->once())
      ->method('getSetting')
      ->with('tag_handling')
      ->willReturn($tag_handling);

    $translated_text = $this->createMock(TextResult::class);
    $translated_text->text = $text;

    if ($tag_handling === 'xml' || $tag_handling === 'html') {
      $text = Html::decodeEntities($text);
    }

    $this->translatorPlugin->expects($this->once())
      ->method('unescapeText')
      ->with($text)
      ->willReturn($expected_result);

    $batch = $this->createBatch();
    $result = $this->invokeProtectedMethod($batch, 'decodeAndUnescapeText', [
      $this->translator,
      $this->translatorPlugin,
      $translated_text,
    ]);
    $this->assertEquals($expected_result, $result);
  }

  /**
   * Tests processTranslatedTexts() calls decodeAndUnescapeText for each result.
   */
  public function testProcessTranslatedTexts(): void {
    $text1 = $this->createMock(TextResult::class);
    $text1->text = 'decoded text 1';
    $text2 = $this->createMock(TextResult::class);
    $text2->text = 'decoded text 2';

    $translated_texts = [$text1, $text2];
    $keys_sequence = ['key1', 'key2'];
    /** @var int $i */
    $i = 0;

    $this->translator->expects($this->any())
      ->method('getSetting')
      ->with('tag_handling')
      ->willReturn('0');

    $this->translatorPlugin->expects($this->any())
      ->method('unescapeText')
      ->willReturnArgument(0);

    $batch = $this->createBatch();

    $reflection = new \ReflectionMethod($batch, 'processTranslatedTexts');
    $args = [$this->translatorPlugin, $this->translator, $translated_texts, $keys_sequence, &$i];
    $result = $reflection->invokeArgs($batch, $args);

    $this->assertSame(2, $i);
    $this->assertEquals([
      'key1' => ['#text' => 'decoded text 1'],
      'key2' => ['#text' => 'decoded text 2'],
    ], $result);
  }

  /**
   * Tests processTranslatedTexts() returns empty string for non-TextResult.
   */
  public function testProcessTranslatedTextsNonTextResult(): void {
    $non_text = new \stdClass();
    $translated_texts = [$non_text];
    $keys_sequence = ['key1'];
    /** @var int $i */
    $i = 0;

    $batch = $this->createBatch();

    $reflection = new \ReflectionMethod($batch, 'processTranslatedTexts');
    $args = [$this->translatorPlugin, $this->translator, $translated_texts, $keys_sequence, &$i];
    $result = $reflection->invokeArgs($batch, $args);

    $this->assertIsArray($result);
    $this->assertIsArray($result['key1']);
    $this->assertSame('', $result['key1']['#text']);
    $this->assertSame(1, $i);
  }

  /**
   * Data provider for ensureArray.
   */
  public static function dataProviderTestEnsureArray(): array {
    return [
      'string' => ['hello world', ['hello world']],
      'array' => [['hello world', 'hello'], ['hello world', 'hello']],
    ];
  }

  /**
   * Tests ensureArray().
   *
   * @param mixed $input
   *   The input value.
   * @param array $expected_result
   *   The expected result.
   *
   * @dataProvider dataProviderTestEnsureArray
   */
  public function testEnsureArray(mixed $input, array $expected_result): void {
    $batch = $this->createBatch();
    $result = $this->invokeProtectedMethod($batch, 'ensureArray', [$input]);
    $this->assertEquals($expected_result, $result);
  }

  /**
   * Data provider for getTranslationContext.
   */
  public static function dataProviderGetTranslationContext(): array {
    return [
      'enabled with value' => [TRUE, 'Sample context text.', 'Sample context text.'],
      'enabled with empty value' => [TRUE, '', NULL],
      'disabled' => [FALSE, 'Sample context text.', NULL],
    ];
  }

  /**
   * Tests getTranslationContext().
   *
   * @param bool $enable_context
   *   Whether the context setting is enabled.
   * @param string $context_value
   *   The context value on the job.
   * @param string|null $expected
   *   The expected return value.
   *
   * @dataProvider dataProviderGetTranslationContext
   */
  public function testGetTranslationContext(bool $enable_context, string $context_value, ?string $expected): void {
    $translator = $this->createMock(TranslatorInterface::class);
    $translator->method('getSetting')
      ->with('enable_context')
      ->willReturn($enable_context);

    $job = $this->createMock(Job::class);
    $job->method('getTranslator')->willReturn($translator);

    if ($enable_context) {
      $job->expects($this->once())
        ->method('getSetting')
        ->with('context')
        ->willReturn($context_value);
    }
    else {
      $job->expects($this->never())->method('getSetting');
    }

    $batch = $this->createBatch();
    $this->assertSame($expected, $this->invokeProtectedMethod($batch, 'getTranslationContext', [$job]));
  }

  /**
   * Tests getTranslationOptions() merges defaults with job settings and alters.
   */
  public function testGetTranslationOptions(): void {
    $default_options = ['option1' => 'value1', 'option2' => 'value2'];
    $altered_options = [
      'option1' => 'value1',
      'option2' => 'value2',
      'altered' => 'value3',
    ];

    $this->translator->expects($this->once())
      ->method('getPlugin')
      ->willReturn($this->translatorPlugin);

    $job = $this->createMock(Job::class);
    $job->method('getSetting')
      ->willReturnMap([
        ['option1', 'value1'],
        ['option2', 'value2'],
      ]);

    $this->translatorPlugin->expects($this->once())
      ->method('getDefaultSettings')
      ->with($this->translator)
      ->willReturn($default_options);

    $module_handler = $this->createMock(ModuleHandlerInterface::class);
    $module_handler->expects($this->once())
      ->method('alter')
      ->with('tmgmt_deepl_translate_options', $job, $default_options)
      ->willReturnCallback(function ($hook, $translation_job, &$options) use ($altered_options) {
        $options = $altered_options;
      });

    $batch = $this->createBatch([
      'module_handler' => $module_handler,
    ]);

    $result = $this->invokeProtectedMethod($batch, 'getTranslationOptions', [$job, $this->translator]);
    $this->assertEquals($altered_options, $result);
  }

  /**
   * Data provider for initializeContext.
   */
  public static function dataProviderTestInitializeContext(): array {
    return [
      'no results given' => [['results' => []], 0],
      'results given' => [['results' => ['i' => 3]], 3],
    ];
  }

  /**
   * Tests initializeContext().
   *
   * @param array $context
   *   The context array.
   * @param int $expected_result
   *   The expected i value.
   *
   * @dataProvider dataProviderTestInitializeContext
   */
  public function testInitializeContext(array $context, int $expected_result): void {
    $batch = $this->createBatch();
    $result = $this->invokeProtectedMethod($batch, 'initializeContext', [$context]);

    $this->assertIsArray($result);
    $this->assertArrayHasKey('results', $result);
    $this->assertIsArray($result['results']);
    $this->assertArrayHasKey('i', $result['results']);
    $this->assertEquals($expected_result, $result['results']['i']);
  }

  /**
   * Tests addBatchOperations() splits text into chunks and adds operations.
   */
  public function testAddBatchOperations(): void {
    $q = ['text1', 'text2', 'text3', 'text4', 'text5', 'text6', 'text7'];
    $keys_sequence = ['key1', 'key2', 'key3', 'key4', 'key5', 'key6', 'key7'];

    $batch = $this->createBatch();
    $batch_builder = $this->createMock(BatchBuilder::class);

    $expected_calls = [
      [
        [$batch, 'translateOperation'],
        [
          $this->job,
          ['text1', 'text2', 'text3', 'text4', 'text5'],
          ['key1', 'key2', 'key3', 'key4', 'key5'],
        ],
      ],
      [
        [$batch, 'translateOperation'],
        [$this->job, ['text6', 'text7'], ['key6', 'key7']],
      ],
      [
        [$batch, 'beforeFinishedOperation'],
        [$this->jobItem],
      ],
    ];

    $callIndex = 0;
    $batch_builder->expects($this->exactly(3))
      ->method('addOperation')
      ->willReturnCallback(function ($method, $args) use (&$callIndex, $expected_calls) {
        [$expected_method, $expected_args] = $expected_calls[$callIndex];
        $this->assertEquals($expected_method, $method);
        $this->assertEquals($expected_args, $args);
        $callIndex++;
      });

    $this->invokeProtectedMethod($batch, 'addBatchOperations', [
      $batch_builder,
      $this->job,
      $this->jobItem,
      $q,
      $keys_sequence,
    ]);
  }

  /**
   * Tests addBatchOperations() also queues a document translation.
   */
  public function testAddBatchOperationsWithDocuments(): void {
    $q = ['text1'];
    $keys_sequence = ['key1'];
    $document = $this->createMock(FileInterface::class);
    $documents = ['doc_key' => $document];

    $batch = $this->createBatch();
    $batch_builder = $this->createMock(BatchBuilder::class);

    $expected_calls = [
      [
        [$batch, 'translateOperation'],
        [$this->job, ['text1'], $keys_sequence],
      ],
      [
        [$batch, 'translateDocumentOperation'],
        [$this->job, 'doc_key', $document],
      ],
      [
        [$batch, 'beforeFinishedOperation'],
        [$this->jobItem],
      ],
    ];

    $callIndex = 0;
    $batch_builder->expects($this->exactly(3))
      ->method('addOperation')
      ->willReturnCallback(function ($method, $args) use (&$callIndex, $expected_calls) {
        [$expected_method, $expected_args] = $expected_calls[$callIndex];
        $this->assertEquals($expected_method, $method);
        $this->assertEquals($expected_args, $args);
        $callIndex++;
      });

    $this->invokeProtectedMethod($batch, 'addBatchOperations', [
      $batch_builder,
      $this->job,
      $this->jobItem,
      $q,
      $keys_sequence,
      $documents,
    ]);
  }

  /**
   * Tests translateDocumentOperation() translates a file.
   */
  public function testTranslateDocumentOperation(): void {
    $context = [];
    $document = $this->createMock(FileInterface::class);

    $translated_file = $this->createMock(FileInterface::class);
    $translated_file->expects($this->once())->method('set')->with('langcode', 'de')->willReturnSelf();
    $translated_file->expects($this->once())->method('setOwnerId')->with(1)->willReturnSelf();
    $translated_file->expects($this->once())->method('save');
    $translated_file->method('id')->willReturn(99);

    $this->job->method('getTranslator')->willReturn($this->translator);
    $this->job->method('getRemoteSourceLanguage')->willReturn('EN-GB');
    $this->job->method('getRemoteTargetLanguage')->willReturn('DE');
    $this->job->method('getTargetLanguage')->willReturn('de');
    $this->job->method('getOwnerId')->willReturn(1);
    $this->job->method('id')->willReturn(5);

    // Real getTranslationOptions() needs the plugin + default settings.
    $this->translator->method('getPlugin')->willReturn($this->translatorPlugin);
    $this->translatorPlugin->method('getDefaultSettings')->with($this->translator)->willReturn([]);

    $deepl_api = $this->createMock(DeeplTranslatorApi::class);
    $deepl_api->method('fixSourceLanguageMappings')->with('EN-GB')->willReturn('EN');
    $deepl_api->expects($this->once())->method('setTranslator')->with($this->translator);
    $deepl_api->expects($this->once())
      ->method('translateDocument')
      ->with($this->translator, $document, 'EN', 'DE', [])
      ->willReturn($translated_file);

    $file_usage = $this->createMock(FileUsageInterface::class);
    $file_usage->expects($this->once())
      ->method('add')
      ->with($translated_file, 'tmgmt_deepl', 'tmgmt_job', '5');

    $batch = $this->createBatch([
      'deepl_api' => $deepl_api,
      'file_usage' => $file_usage,
    ]);

    $batch->translateDocumentOperation($this->job, 'doc_key', $document, $context);

    $this->assertIsArray($context['results']);
    $this->assertIsArray($context['results']['translation']);
    $this->assertSame(['#file' => 99], $context['results']['translation']['doc_key']);
  }

  /**
   * Tests translateDocumentOperation() when no file is returned.
   */
  public function testTranslateDocumentOperationNoFile(): void {
    $context = [];
    $document = $this->createMock(FileInterface::class);

    $this->job->method('getTranslator')->willReturn($this->translator);
    $this->job->method('getRemoteSourceLanguage')->willReturn('EN-GB');
    $this->job->method('getRemoteTargetLanguage')->willReturn('DE');

    // Real getTranslationOptions() needs the plugin + default settings.
    $this->translator->method('getPlugin')->willReturn($this->translatorPlugin);
    $this->translatorPlugin->method('getDefaultSettings')->with($this->translator)->willReturn([]);

    $deepl_api = $this->createMock(DeeplTranslatorApi::class);
    $deepl_api->method('fixSourceLanguageMappings')->willReturn('EN');
    $deepl_api->method('translateDocument')->willReturn(NULL);

    $file_usage = $this->createMock(FileUsageInterface::class);
    $file_usage->expects($this->never())->method('add');

    $batch = $this->createBatch([
      'deepl_api' => $deepl_api,
      'file_usage' => $file_usage,
    ]);

    $batch->translateDocumentOperation($this->job, 'doc_key', $document, $context);

    $this->assertIsArray($context['results']);
    $this->assertArrayNotHasKey('translation', $context['results']);
  }

  /**
   * Tests translateOperation() advances $i by chunk size on NULL result.
   */
  public function testTranslateOperationNullResultAdvancesIndexByChunkSize(): void {
    $texts = ['text1', 'text2', 'text3'];
    $keys_sequence = ['key1', 'key2', 'key3', 'key4', 'key5'];
    $context = ['results' => ['i' => 0]];

    $this->translator->method('getPlugin')->willReturn($this->translatorPlugin);
    $this->translatorPlugin->method('getDefaultSettings')->willReturn([]);

    $this->job->method('getTranslator')->willReturn($this->translator);
    $this->job->method('getRemoteSourceLanguage')->willReturn('EN-GB');
    $this->job->method('getRemoteTargetLanguage')->willReturn('DE');

    $deepl_api = $this->createMock(DeeplTranslatorApi::class);
    $deepl_api->method('fixSourceLanguageMappings')->willReturn('EN');
    $deepl_api->method('setTranslator');
    $deepl_api->method('translate')->willReturn(NULL);

    $module_handler = $this->createMock(ModuleHandlerInterface::class);

    $batch = $this->createBatch([
      'deepl_api' => $deepl_api,
      'module_handler' => $module_handler,
    ]);

    $batch->translateOperation($this->job, $texts, $keys_sequence, $context);

    $this->assertIsArray($context['results']);
    $this->assertSame(3, $context['results']['i']);
    $this->assertArrayNotHasKey('translation', $context['results']);
  }

  /**
   * Invokes a protected or private method via reflection.
   *
   * Used to test internal computation methods on real instances.
   *
   * @param object $object
   *   The object instance.
   * @param string $method
   *   The method name.
   * @param array $args
   *   Arguments to pass.
   *
   * @return mixed
   *   The method return value.
   */
  protected function invokeProtectedMethod(object $object, string $method, array $args = []): mixed {
    $reflection = new \ReflectionMethod($object, $method);
    return $reflection->invokeArgs($object, $args);
  }

}
