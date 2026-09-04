<?php

namespace Drupal\Tests\tmgmt_deepl\Unit\Plugin\tmgmt\Translator;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tmgmt\Data;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt_deepl\DeeplTranslatorApi;
use Drupal\tmgmt_deepl\DeeplTranslatorBatch;
use Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the DeeplTranslator.
 *
 * @covers \Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator
 * @group tmgmt_deepl
 */
class DeeplTranslatorTest extends UnitTestCase {

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
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    $container = new ContainerBuilder();
    \Drupal::setContainer($container);
  }

  /**
   * Data provider for the testCheckAvailable test.
   *
   * @return array
   *   The test data.
   */
  public static function dataProviderTestCheckAvailable(): array {
    return [
      'valid key' => [
        'test_key',
        TRUE,
      ],
      'empty key' => [
        '',
        FALSE,
      ],
      'no key (null)' => [
        NULL,
        FALSE,
      ],
    ];
  }

  /**
   * Test checkAvailable method.
   *
   * @param mixed $key
   *   The auth key entity id.
   * @param bool $expected_result
   *   The expected result can be true or false.
   *
   *   Test the method ::checkAvailable.
   *
   * @dataProvider dataProviderTestCheckAvailable
   */
  public function testCheckAvailable(mixed $key, mixed $expected_result): void {
    // Create a mock translator.
    $translator = $this->createMock(Translator::class);
    $translator->expects($this->once())
      ->method('getSetting')
      ->with('auth_key_entity')
      ->willReturn($key);

    // Mock the class.
    /** @var \Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator&MockObject $class */
    $class = $this->createClassMock();

    if ($expected_result) {
      $result = $class->checkAvailable($translator);
      $this->assertTrue($result->getSuccess());
    }
    else {
      $translator->expects($this->once())
        ->method('label')
        ->willReturn('Test Translator');

      $translator->expects($this->once())
        ->method('toUrl')
        ->willReturn($this->createMock('\Drupal\Core\Url'));

      $result = $class->checkAvailable($translator);
      $this->assertFalse($result->getSuccess());

      // @phpstan-ignore-next-line.
      $this->assertInstanceOf('\Drupal\Core\StringTranslation\TranslatableMarkup', $result->getReason());
      $this->assertEquals('@translator is not available. Make sure it is properly <a href=:configured>configured</a>.', $result->getReason()->getUntranslatedString());
    }
  }

  /**
   * Test requestTranslation method.
   *
   * Tests the method ::requestTranslation.
   */
  public function testRequestTranslation(): void {
    // Create a mock for the job.
    $job = $this->createMock(Job::class);

    // Create mocks two job items.
    $job_item_1 = $this->createMock(JobItemInterface::class);
    $job_item_2 = $this->createMock(JobItemInterface::class);

    // Set up the job items.
    $job->expects($this->once())
      ->method('getItems')
      ->willReturn([$job_item_1, $job_item_2]);

    // Set up expectation for isRejected().
    $job->expects($this->once())
      ->method('isRejected')
      ->willReturn(FALSE);

    $job->expects($this->once())
      ->method('submitted')
      ->with('The translation job has been submitted.');

    // Mock the class.
    /** @var \Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator&MockObject $class */
    $class = $this->createClassMock(['requestJobItemsTranslation']);
    $class->expects($this->once())
      ->method('requestJobItemsTranslation')
      ->with([$job_item_1, $job_item_2]);

    $class->requestTranslation($job);
  }

  /**
   * Tests ::requestTranslation when the job is rejected.
   */
  public function testRequestTranslationRejected(): void {
    $job = $this->createMock(Job::class);
    $job_item = $this->createMock(JobItemInterface::class);

    $job->expects($this->once())
      ->method('getItems')
      ->willReturn([$job_item]);
    $job->expects($this->once())
      ->method('isRejected')
      ->willReturn(TRUE);
    $job->expects($this->never())
      ->method('submitted');

    $class = $this->createClassMock(['requestJobItemsTranslation']);
    $class->expects($this->once())
      ->method('requestJobItemsTranslation')
      ->with([$job_item]);

    $class->requestTranslation($job);
  }

  /**
   * Tests getDefaultRemoteLanguagesMappings method.
   *
   * Tests the method ::getDefaultRemoteLanguagesMappings.
   */
  public function testGetDefaultRemoteLanguagesMappings():void {
    // Define expected mappings.
    $expected_mappings = [
      'ar' => 'AR',
      'bg' => 'BG',
      'cs' => 'CS',
      'da' => 'DA',
      'de' => 'DE',
      'el' => 'EL',
      'en' => 'EN-GB',
      'es' => 'ES',
      'et' => 'ET',
      'fi' => 'FI',
      'fr' => 'FR',
      'hu' => 'HU',
      'id' => 'ID',
      'it' => 'IT',
      'ja' => 'JA',
      'ko' => 'KO',
      'lt' => 'LT',
      'lv' => 'LV',
      'nb' => 'NB',
      'nl' => 'NL',
      'pl' => 'PL',
      'pt-br' => 'PT-BR',
      'pt-pt' => 'PT-PT',
      'ro' => 'RO',
      'ru' => 'RU',
      'sk' => 'SK',
      'sl' => 'SL',
      'sv' => 'SV',
      'tr' => 'TR',
      'uk' => 'UK',
      'zh-hans' => 'ZH',
      'zh-hant' => 'ZH',
    ];

    /** @var \Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator&MockObject $class */
    $class = $this->createClassMock();

    $this->assertEquals($expected_mappings, $class->getDefaultRemoteLanguagesMappings());
    $mappings = $class->getDefaultRemoteLanguagesMappings();

    // Assert that all expected keys are present.
    foreach ($expected_mappings as $key => $value) {
      $this->assertArrayHasKey($key, $mappings);
      $this->assertEquals($value, $mappings[$key]);
    }
  }

  /**
   * Tests getSupportedRemoteLanguages method.
   *
   * Tests the method ::getSupportedRemoteLanguages.
   */
  public function testGetSupportedRemoteLanguages():void {
    // Create a mock translator.
    $translator = $this->createMock(Translator::class);

    // Create a mock DeepL API.
    $tmgmt_deepl_api = $this->createMock(DeeplTranslatorApi::class);

    // Mock some languages.
    $available_languages = [
      'EN' => 'English',
      'DE' => 'German',
      'FR' => 'French',
      'ES' => 'Spanish',
    ];

    // Set up expectation for getTargetLanguages().
    $tmgmt_deepl_api->expects($this->once())
      ->method('getTargetLanguages')
      ->willReturn($available_languages);

    // Mock the class.
    /** @var \Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator&MockObject $class */
    $class = $this->createClassMock();
    $this->setProtectedProperty($class, 'deeplTranslatorApi', $tmgmt_deepl_api);

    // Set the translator for the DeepL API.
    $tmgmt_deepl_api->expects($this->once())
      ->method('setTranslator')
      ->with($translator);

    $result = $class->getSupportedRemoteLanguages($translator);
    $this->assertEquals($available_languages, $result);
  }

  /**
   * Data provider for the testGetSupportedTargetLanguages test.
   *
   * @return array
   *   The test data.
   */
  public static function dataProviderTestGetSupportedTargetLanguages(): array {
    return [
      'Source language equals target language' => [
        'DE',
        [
          'EN' => 'English',
          'FR' => 'French',
          'ES' => 'Spanish',
          'IT' => 'Italian',
        ],
      ],
      'Source language is not available' => [
        'ZH',
        [],
      ],
      'Source language is empty' => [
        '',
        [],
      ],
    ];
  }

  /**
   * Tests getSupportedTargetLanguages method.
   *
   * @param string $source_language
   *   The source language.
   * @param array $expected_result
   *   The expected target languages.
   *
   *   Test the method ::getSupportedTargetLanguages.
   *
   * @dataProvider dataProviderTestGetSupportedTargetLanguages
   */
  public function testGetSupportedTargetLanguages(string $source_language, array $expected_result):void {
    // Create a mock translator.
    $translator = $this->createMock(Translator::class);

    // Mock the class.
    /** @var \Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator&MockObject $class */
    $class = $this->createClassMock(['getSupportedRemoteLanguages']);

    // Mock some languages.
    $available_languages = [
      'EN' => 'English',
      'DE' => 'German',
      'FR' => 'French',
      'ES' => 'Spanish',
      'IT' => 'Italian',
    ];

    // Set up expectation for getSupportedRemoteLanguages().
    $class->expects($this->once())
      ->method('getSupportedRemoteLanguages')
      ->with($translator)
      ->willReturn($available_languages);

    // Source language is available.
    $target_languages = $class->getSupportedTargetLanguages($translator, $source_language);
    $this->assertEquals($expected_result, $target_languages);
  }

  /**
   * Data provider for the testFixSourceLanguageMappings test.
   *
   * @return array
   *   The test data.
   */
  public static function dataProviderTestFixSourceLanguageMappings(): array {
    return [
      'EN-GB' => [
        'EN-GB',
        'EN',
      ],
      'EN-US' => [
        'EN-US',
        'EN',
      ],
      'PT-BR' => [
        'PT-BR',
        'PT',
      ],
      'PT-PT' => [
        'PT-PT',
        'PT',
      ],
      'Unmapped language' => [
        'FR',
        'FR',
      ],
    ];
  }

  /**
   * Tests getSupportedTargetLanguages method.
   *
   * @param string $source_language
   *   The source language.
   * @param string $expected_result
   *   The expected language mapping.
   *
   *   Test the method ::fixSourceLanguageMappings.
   *
   * @dataProvider dataProviderTestFixSourceLanguageMappings
   */
  public function testFixSourceLanguageMappings(string $source_language, string $expected_result):void {
    $api = $this->createMock(DeeplTranslatorApi::class);
    $api->expects($this->once())
      ->method('fixSourceLanguageMappings')
      ->with($source_language)
      ->willReturn($expected_result);

    /** @var \Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator&MockObject $class */
    $class = $this->createClassMock();
    $this->setProtectedProperty($class, 'deeplTranslatorApi', $api);

    $result = $class->fixSourceLanguageMappings($source_language);
    $this->assertEquals($expected_result, $result);
  }

  /**
   * Data provider for the testHasCheckoutSettings test.
   *
   * @return array
   *   The test data.
   */
  public static function dataProviderTestHasCheckoutSettings(): array {
    return [
      'Default - should return false' => [
        '',
        FALSE,
        FALSE,
      ],
      'Set alteration to be true' => [
        TRUE,
        TRUE,
        FALSE,
      ],
      'Set alteration and context to be false' => [
        FALSE,
        FALSE,
        FALSE,
      ],
      'Set alteration and context to be true' => [
        TRUE,
        TRUE,
        TRUE,
      ],
      'Set alteration to be false and context to be true' => [
        FALSE,
        TRUE,
        TRUE,
      ],
    ];
  }

  /**
   * Tests hasCheckoutSettings method.
   *
   * @param mixed $altered_value
   *   The value can be true, false or empty.
   * @param bool $expected_result
   *   Whether the method should return true or false.
   * @param bool $enable_context
   *   Whether the context setting is enabled or not.
   *
   *   Test the method ::hasCheckoutSettings.
   *
   * @dataProvider dataProviderTestHasCheckoutSettings
   */
  public function testHasCheckoutSettings(mixed $altered_value, bool $expected_result, bool $enable_context = FALSE): void {
    // Create a mock for the job.
    $job = $this->createMock(Job::class);
    $translator = $this->createMock(TranslatorInterface::class);

    // Mock the getSetting method of the translator.
    $translator->expects($this->once())
      ->method('getSetting')
      ->with('enable_context')
      ->willReturn($enable_context);
    $job->method('getTranslator')->willReturn($translator);

    // Mock the module handler.
    $module_handler = $this->createMock(ModuleHandlerInterface::class);

    // In case of having empty alteration.
    if ($altered_value === '') {
      $module_handler->expects($this->once())
        ->method('alter')
        ->with('tmgmt_deepl_has_checkout_settings', $this->isFalse(), $job);
    }
    else {
      $module_handler->expects($this->once())
        ->method('alter')
        ->willReturnCallback(function ($hook, &$has_checkout_settings, $jobArg) use ($job, $altered_value) {
          $this->assertEquals('tmgmt_deepl_has_checkout_settings', $hook);
          $this->assertSame($job, $jobArg);
          $has_checkout_settings = $altered_value;
        });
    }

    // Mock the class.
    /** @var \Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator&MockObject $class */
    $class = $this->createClassMock();
    $this->setProtectedProperty($class, 'moduleHandler', $module_handler);
    $result = $class->hasCheckoutSettings($job);
    $this->assertEquals($expected_result, $result);
  }

  /**
   * Data provider for the testGetDefaultSettings test.
   *
   * @return array
   *   The test data.
   */
  public static function dataProviderTestGetDefaultSettings(): array {
    return [
      'All settings provided' => [
        [
          'model_type' => 'latency_optimized',
          'split_sentences' => '1',
          'preserve_formatting' => '0',
          'formality' => 'default',
          'tag_handling' => 'xml',
          'tag_handling_version' => 'v1',
          'outline_detection' => '1',
          'splitting_tags' => 'p,br',
          'non_splitting_tags' => 'div,span',
          'ignore_tags' => 'script,style',
        ],
        [
          'model_type' => 'latency_optimized',
          'split_sentences' => '1',
          'preserve_formatting' => '0',
          'formality' => 'default',
          'tag_handling' => 'xml',
          'tag_handling_version' => 'v1',
          'outline_detection' => '1',
          'splitting_tags' => 'p,br',
          'non_splitting_tags' => 'div,span',
          'ignore_tags' => 'script,style',
        ],
      ],
      'Some settings empty' => [
        [
          'model_type' => 'latency_optimized',
          'split_sentences' => '1',
          'preserve_formatting' => '',
          'formality' => 'default',
          'tag_handling' => '',
          'tag_handling_version' => 'v1',
          'outline_detection' => '1',
          'splitting_tags' => '',
          'non_splitting_tags' => 'div,span',
          'ignore_tags' => '',
        ],
        // Empty-string settings fall back to module defaults:
        // preserve_formatting and tag_handling come from defaultSettings().
        [
          'model_type' => 'latency_optimized',
          'split_sentences' => '1',
          'formality' => 'default',
          'tag_handling_version' => 'v1',
          'outline_detection' => '1',
          'non_splitting_tags' => 'div,span',
          'preserve_formatting' => 0,
          'tag_handling' => 0,
        ],
      ],
      'Settings with null values fall back to defaults' => [
        [
          'model_type' => 'latency_optimized',
          'split_sentences' => '1',
          'preserve_formatting' => NULL,
          'formality' => 'default',
          'tag_handling' => NULL,
          'tag_handling_version' => 'v1',
          'outline_detection' => NULL,
          'splitting_tags' => '',
          'non_splitting_tags' => 'div,span',
          'ignore_tags' => '',
        ],
        [
          'model_type' => 'latency_optimized',
          'split_sentences' => '1',
          'formality' => 'default',
          'tag_handling_version' => 'v1',
          'outline_detection' => 0,
          'non_splitting_tags' => 'div,span',
          'preserve_formatting' => 0,
          'tag_handling' => 0,
        ],
      ],
      'All settings empty' => [
        [
          'model_type' => '',
          'split_sentences' => '',
          'preserve_formatting' => '',
          'formality' => '',
          'tag_handling' => '',
          'tag_handling_version' => '',
          'outline_detection' => '',
          'splitting_tags' => '',
          'non_splitting_tags' => '',
          'ignore_tags' => '',
        ],
        // All empty settings must fall back entirely to module defaults so the
        // DeepL API receives sensible values instead of an empty options array.
        [
          'model_type' => 'latency_optimized',
          'split_sentences' => '1',
          'formality' => 'default',
          'preserve_formatting' => 0,
          'tag_handling' => 0,
          'tag_handling_version' => 'v1',
          'outline_detection' => 0,
        ],
      ],
    ];
  }

  /**
   * Tests getDefaultSettings method.
   *
   * @param array $settings
   *   The provided settings array.
   * @param array $expected_result
   *   The expected settings array.
   *
   *   Test the method ::getDefaultSettings.
   *
   * @dataProvider dataProviderTestGetDefaultSettings
   */
  public function testGetDefaultSettings(array $settings, array $expected_result): void {
    // Create a mock translator.
    $translator = $this->createMock(Translator::class);
    // Set up the getSettings method to return values based on the input.
    $translator->method('getSettings')->willReturn($settings);

    // Mock the class.
    /** @var \Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator&MockObject $class */
    $class = $this->createClassMock();
    $result = $class->getDefaultSettings($translator);
    $this->assertEquals($expected_result, $result);
  }

  /**
   * Data provider for the testRequestJobItemsTranslation test.
   *
   * @return array
   *   The test data.
   */
  public static function dataProviderTestRequestJobItemsTranslation(): array {
    return [
      'no cron execution, not continuous' => [
        FALSE,
        FALSE,
      ],
      'cron execution, not continuous' => [
        TRUE,
        FALSE,
      ],
      'no cron execution, continuous' => [
        FALSE,
        TRUE,
      ],
      'cron execution, continuous' => [
        TRUE,
        TRUE,
      ],
    ];
  }

  /**
   * Test the method ::requestJobItemsTranslation.
   *
   * @param bool $is_cron
   *   Whether the cron is enabled or not.
   * @param bool $is_continuous
   *   Whether the job is continuous or not.
   *
   * @dataProvider dataProviderTestRequestJobItemsTranslation
   */
  public function testRequestJobItemsTranslation(bool $is_cron, bool $is_continuous = FALSE): void {
    // Create a mock job.
    $job = $this->createMock(Job::class);

    // Create a mock job item.
    $job_item = $this->createMock(JobItemInterface::class);

    // Create a mock job item.
    $tmgmt_data = $this->createMock(Data::class);

    // Create a mock queue.
    $queue = $this->createMock(QueueInterface::class);

    // Mick the DeepL translator batch class.
    $batch = $this->createMock(DeeplTranslatorBatch::class);

    // Set up the job item expectations.
    $job_item->expects($this->once())
      ->method('getJob')
      ->willReturn($job);

    // Set up the job expectations.
    $job->expects($this->once())
      ->method('isContinuous')
      ->willReturn($is_continuous);

    // Set up the job expectations.
    if ($is_continuous) {
      $job_item->expects($this->once())
        ->method('active');
    }
    else {
      $job_item->expects($this->never())
        ->method('active');
    }

    $job_item->method('getData')->willReturn([]);

    // Set up the tmgmt data expectations.
    $tmgmt_data->expects($this->once())
      ->method('filterTranslatable')
      ->willReturn([
        'key1' => ['#text' => 'value1', '#escape' => []],
        'key2' => ['#text' => 'value2', '#escape' => []],
      ]);

    // Mock the class.
    /** @var \Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator&MockObject $class */
    $class = $this->createClassMock(['useQueue', 'escapeText']);
    // Set the tmgmt data property.
    $this->setProtectedProperty($class, 'tmgmtData', $tmgmt_data);
    // Set the queue property.
    $this->setProtectedProperty($class, 'queue', $queue);
    // Set the batch property.
    $this->setProtectedProperty($class, 'deeplTranslatorBatch', $batch);

    // Set up useQueue expectations.
    $class->expects($this->once())
      ->method('useQueue')
      ->willReturn($is_cron);

    // Set up escapeText expectations.
    $class->expects($this->atLeastOnce())
      ->method('escapeText')
      ->willReturnCallback(function (array $data_item) {
        assert(is_string($data_item['#text']));

        return 'escaped__' . $data_item['#text'];
      });

    // In case cron is enabled.
    if ($is_cron) {
      // Set up queue expectation for cron.
      $queue->expects($this->once())
        ->method('createItem')
        ->with($this->callback(function ($item) use ($job, $job_item) {
          assert(is_array($item));

          return $item['job'] === $job &&
            $item['job_item'] === $job_item &&
            $item['q'] === ['escaped__value1', 'escaped__value2'] &&
            $item['keys_sequence'] === ['key1', 'key2'];
        }));

      // Ensure buildBatch is not called during cron.
      $batch->expects($this->never())
        ->method('buildBatch');
    }
    else {
      // Set up the batch expectations.
      $batch->expects($this->once())
        ->method('buildBatch')
        ->with(
          $job,
          $job_item,
          $this->equalTo(['escaped__value1', 'escaped__value2']),
          $this->equalTo(['key1', 'key2']),
        );

      // Ensure createItem is not called during non-cron.
      $queue->expects($this->never())
        ->method('createItem');
    }

    // Call method.
    $class->requestJobItemsTranslation([$job_item]);
  }

  /**
   * Tests ::requestJobItemsTranslation with an empty job items array.
   */
  public function testRequestJobItemsTranslationEmptyItems(): void {
    $class = $this->createClassMock(['useQueue', 'escapeText']);
    $class->expects($this->never())->method('useQueue');
    $class->expects($this->never())->method('escapeText');

    // requestJobItemsTranslation returns void; the never() expectations above
    // already verify no work was attempted on an empty items array.
    $class->requestJobItemsTranslation([]);
  }

  /**
   * Tests ::requestJobItemsTranslation with documents in the batch path.
   */
  public function testRequestJobItemsTranslationWithDocuments(): void {
    $job = $this->createMock(Job::class);
    $job_item = $this->createMock(JobItemInterface::class);
    $tmgmt_data = $this->createMock(Data::class);
    $batch = $this->createMock(DeeplTranslatorBatch::class);

    $job_item->expects($this->once())
      ->method('getJob')
      ->willReturn($job);
    $job->expects($this->once())
      ->method('isContinuous')
      ->willReturn(FALSE);
    $job_item->method('getData')->willReturn([]);

    $tmgmt_data->expects($this->once())
      ->method('filterTranslatable')
      ->willReturn([
        'key1' => ['#text' => 'value1', '#escape' => []],
      ]);

    $document = $this->createMock('Drupal\file\FileInterface');
    $tmgmt_data->expects($this->once())
      ->method('getTranslatableFiles')
      ->willReturn(['file_key' => $document]);

    $class = $this->createClassMock(['useQueue', 'escapeText']);
    $this->setProtectedProperty($class, 'tmgmtData', $tmgmt_data);
    $this->setProtectedProperty($class, 'deeplTranslatorBatch', $batch);

    $class->expects($this->once())
      ->method('useQueue')
      ->willReturn(FALSE);
    $class->expects($this->once())
      ->method('escapeText')
      ->willReturn('escaped__value1');

    $batch->expects($this->once())
      ->method('buildBatch')
      ->with(
        $job,
        $job_item,
        $this->equalTo(['escaped__value1']),
        $this->equalTo(['key1']),
        $this->equalTo(['file_key' => $document]),
      );

    $class->requestJobItemsTranslation([$job_item]);
  }

  /**
   * Data provider for the testUseQueue test.
   *
   * @return array
   *   The test data.
   */
  public static function dataProviderTestUseQueue(): array {
    return [
      'Web cron route' => [
        'system.cron',
        TRUE,
      ],
      'Non-cron route' => [
        'some.random.route',
        FALSE,
      ],
      'No current request' => [
        NULL,
        FALSE,
      ],
    ];
  }

  /**
   * Tests the method ::useQueue.
   *
   * Verifies that the web cron route (system.cron) triggers queue mode,
   * while other routes fall back to batch API.
   *
   * @param string|null $route_name
   *   The route name to simulate, or NULL for no request.
   * @param bool $expected_result
   *   Whether useQueue should return TRUE.
   *
   * @dataProvider dataProviderTestUseQueue
   */
  public function testUseQueue(?string $route_name, bool $expected_result): void {
    // Partial mock that overrides isCli() to return FALSE so the route
    // check is the only factor (PHP_SAPI is always 'cli' during tests).
    /** @var \Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator&MockObject $class */
    $class = $this->getMockBuilder(DeeplTranslator::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['isCli'])
      ->getMock();
    $class->method('isCli')->willReturn(FALSE);

    $request = $route_name !== NULL ? new Request([], [], ['_route' => $route_name]) : NULL;

    $request_stack = $this->createMock(RequestStack::class);
    $request_stack->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $this->setProtectedProperty($class, 'requestStack', $request_stack);

    $result = $class->useQueue();
    $this->assertEquals($expected_result, $result);
  }

  /**
   * Tests ::useQueue when running in CLI context.
   */
  public function testUseQueueCli(): void {
    $class = $this->getMockBuilder(DeeplTranslator::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['isCli'])
      ->getMock();
    $class->method('isCli')->willReturn(TRUE);

    $request_stack = $this->createMock(RequestStack::class);
    $request_stack->expects($this->never())->method('getCurrentRequest');
    $this->setProtectedProperty($class, 'requestStack', $request_stack);

    $this->assertTrue($class->useQueue());
  }

  /**
   * Tests the method ::getTranslators.
   */
  public function testGetTranslators(): void {
    // Mock the entity storage.
    $entity_storage = $this->createMock(EntityStorageInterface::class);
    // Mock the entity type manager.
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);

    // Set up expectations for entity type manager.
    $entity_type_manager->expects($this->once())
      ->method('getStorage')
      ->with('tmgmt_translator')
      ->willReturn($entity_storage);

    // Create mock translators.
    $translator_1 = $this->createMock('Drupal\tmgmt\Entity\Translator');
    $translator_2 = $this->createMock('Drupal\tmgmt\Entity\Translator');
    $translator_3 = $this->createMock('Drupal\tmgmt\Entity\Translator');

    // Set up the expectations for the storage.
    $entity_storage->expects($this->once())
      ->method('loadByProperties')
      ->with(['plugin' => DeeplTranslator::DEEPL_TRANSLATORS])
      ->willReturn([
        'deepl_pro' => $translator_1,
        'deepl_free' => $translator_2,
        'deepl_api' => $translator_3,
      ]);

    // Set up the expectations for the translators.
    $translator_1->expects($this->once())
      ->method('label')
      ->willReturn('DeepL API Pro (deprecated)');
    $translator_2->expects($this->once())
      ->method('label')
      ->willReturn('DeepL API Free (deprecated)');
    $translator_3->expects($this->once())
      ->method('label')
      ->willReturn('DeepL API');

    // Replace global entity type manager service.
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    \Drupal::setContainer($container);

    $result = DeeplTranslator::getTranslators();
    $expected = [
      'deepl_pro' => 'DeepL API Pro (deprecated)',
      'deepl_free' => 'DeepL API Free (deprecated)',
      'deepl_api' => 'DeepL API',
    ];
    $this->assertEquals($expected, $result);
  }

  /**
   * Sets a protected property on an object using reflection.
   *
   * @param object $object
   *   The object.
   * @param string $property
   *   The property name.
   * @param mixed $value
   *   The value to set.
   */
  protected function setProtectedProperty(object $object, string $property, mixed $value): void {
    $ref = new \ReflectionProperty($object::class, $property);
    $ref->setValue($object, $value);
  }

  /**
   * Gets a protected property from an object using reflection.
   *
   * @param object $object
   *   The object.
   * @param string $property
   *   The property name.
   *
   * @return mixed
   *   The property value.
   */
  protected function getProtectedProperty(object $object, string $property): mixed {
    $ref = new \ReflectionProperty($object::class, $property);
    return $ref->getValue($object);
  }

  /**
   * Creates and returns a test class mock.
   *
   * @param list<non-empty-string> $only_methods
   *   An array of names for methods to be configurable.
   *
   * @return \Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked class.
   */
  protected function createClassMock(array $only_methods = []): DeeplTranslator|MockObject {
    return $this->getMockBuilder(DeeplTranslator::class)
      ->disableOriginalConstructor()
      ->onlyMethods($only_methods)
      ->getMock();
  }

}
