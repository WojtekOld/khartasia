<?php

namespace Drupal\Tests\external_entities\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\Core\Field\FieldDefinitionInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\external_entities\Plugin\ExternalEntities\DataProcessor\Numeric;

/**
 * The class to test numeric data processor.
 *
 * @group ExternalEntities
 */
#[Group('ExternalEntities')]
class DataProcessorNumericUnitTest extends UnitTestCase {

  /**
   * Data provider for testNumericProcessor().
   *
   * Structure:
   * - data
   * - expected_result
   * - expected_origin
   * - alter
   * - expected_alter
   * - config
   * - test name.
   */
  public static function provideTestNumericProcessor() {
    return [
      // Basic numeric.
      [
        [1234, 501.33, -1e-42],
        [1234, 501.33, -1e-42],
        [1234, 501.33, -1e-42],
        [98, NULL, 54],
        [98, NULL, 54],
        ['position' => 'first'],
        'Basic numeric test',
      ],
      // Numeric position last.
      [
        [1234, 501.33, -1e-42],
        [1234, 501.33, -1e-42],
        [1234, 501.33, -1e-42],
        [98, 501.33, 54],
        [98, 501.33, 54],
        ['position' => 'last'],
        'Numeric position last test',
      ],
      // Position first.
      [
        ['72e+4', '42bla806bla13bla51'],
        [720000, 42],
        ['720000', '42bla806bla13bla51'],
        [36e+2, 54, .42],
        [3600, '54bla806bla13bla51', 0.42],
        ['position' => 'first'],
        'Position first',
      ],
      // Position last.
      [
        ['72e+4', '42bla806bla13bla-5e+3'],
        [720000, -5000],
        ['720000', '42bla806bla13bla-5000'],
        [720000, 421],
        [720000, '42bla806bla13bla421'],
        ['position' => 'last'],
        'Position last',
      ],
    ];
  }

  /**
   * Tests numeric data processor.
   *
   * @dataProvider provideTestNumericProcessor
   */
  #[DataProvider('provideTestNumericProcessor')]
  public function testNumericProcessor(
    array $data,
    array $expected_result,
    ?array $expected_origin,
    array $alter,
    ?array $expected_alter,
    array $config,
    string $test_name,
  ) {

    $string_translation = $this->createStub(TranslationInterface::class);

    $logger = $this->createStub(LoggerInterface::class);

    $field_def = $this->createStub(FieldDefinitionInterface::class);

    $data_processor = new Numeric($config, 'numeric', [], $string_translation, $logger);

    $result = $data_processor->processData($data, $field_def, 'value');
    $this->assertEquals($expected_result, $result, $test_name);
    $reversed = $data_processor->reverseDataProcessing($result, $data, $field_def, 'value');
    $this->assertEquals($expected_origin, $reversed, $test_name . ' reversed');

    $rev_altered = $data_processor->reverseDataProcessing($alter, $data, $field_def, 'value');
    $this->assertEquals($expected_alter, $rev_altered, $test_name . ' reversed altered');
  }

}
