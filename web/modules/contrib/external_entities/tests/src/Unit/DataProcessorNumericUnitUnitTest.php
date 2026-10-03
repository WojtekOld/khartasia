<?php

namespace Drupal\Tests\external_entities\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\Core\Field\FieldDefinitionInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\external_entities\Plugin\ExternalEntities\DataProcessor\NumericUnit;

/**
 * The class to test numeric unit data processor.
 *
 * @group ExternalEntities
 */
#[Group('ExternalEntities')]
class DataProcessorNumericUnitUnitTest extends UnitTestCase {

  /**
   * Data provider for testNumericUnitProcessor().
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
  public static function provideTestNumericUnitProcessor() {
    return [
      // Basic values.
      [
        [1234, 501.33, -1e-42, '806m', '32km', '20cm'],
        [1234, 501.33, -1e-42, 806, 32000, 0.2],
        ['1234m', '501.33m', '-1.0E-42m', '806m', '32000m', '0.2m'],
        [33],
        ['33m'],
        [
          'metric' => 'm',
          'prefix' => '',
        ],
        'Basic values test',
      ],
      // Unit prefix change.
      [
        [1234, 501.33, -1e-42, '806m', '32km', '20cm'],
        [1234000.0, 501330.0, -1.0000000000000001E-39, 806000.0, 32000000.0, 200.0],
        ['1234000mm', '501330mm', '-1.0E-39mm', '806000mm', '32000000mm', '200mm'],
        [33, 42.0E-1],
        ['33mm', '4.2mm'],
        [
          'metric' => 'm',
          'prefix' => 'm',
        ],
        'Unit prefix change test',
      ],
    ];
  }

  /**
   * Tests numeric unit data processor.
   *
   * @dataProvider provideTestNumericUnitProcessor
   */
  #[DataProvider('provideTestNumericUnitProcessor')]
  public function testNumericUnitProcessor(
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

    $data_processor = new NumericUnit($config, 'numericunit', [], $string_translation, $logger);

    $result = $data_processor->processData($data, $field_def, 'value');
    $this->assertEquals($expected_result, $result, $test_name);
    $reversed = $data_processor->reverseDataProcessing($result, $data, $field_def, 'value');
    $this->assertEquals($expected_origin, $reversed, $test_name . ' reversed');

    $rev_altered = $data_processor->reverseDataProcessing($alter, $data, $field_def, 'value');
    $this->assertEquals($expected_alter, $rev_altered, $test_name . ' reversed altered');
  }

}
