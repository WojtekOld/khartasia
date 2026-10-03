<?php

namespace Drupal\Tests\external_entities\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\Core\Field\FieldDefinitionInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\external_entities\Plugin\ExternalEntities\DataProcessor\Boolean as DPBoolean;

/**
 * The class to test boolean data processor.
 *
 * @group ExternalEntities
 */
#[Group('ExternalEntities')]
class DataProcessorBooleanUnitTest extends UnitTestCase {

  /**
   * Data provider for testBooleanProcessor().
   *
   * Structure:
   * - data
   * - expected_result
   * - expected_origin
   * - config
   * - test name.
   */
  public static function provideTestBooleanProcessor() {
    return [
      // Basic.
      [
        [FALSE, TRUE, 'TRUE', 'FALSE', 'true', 'false', 'True', 'False'],
        [FALSE, TRUE, TRUE, FALSE, TRUE, FALSE, TRUE, FALSE],
        [FALSE, TRUE, 'TRUE', 'FALSE', 'true', 'false', 'True', 'False'],
        [],
        'Basic boolean test',
      ],
      // Numeric.
      [
        ['0', 0, 1, -1, 42, 0., '0.'],
        [FALSE, FALSE, TRUE, TRUE, TRUE, FALSE, TRUE],
        ['0', 0, 1, -1, 42, 0., '0.'],
        [],
        'Numeric boolean test',
      ],
      // Textual.
      [
        ['', 'one', ' ', 'No', 'NONE', 'nothing', 'nope', 'empty', 'Zero', '-', "   \n\t   "],
        [FALSE, TRUE, FALSE, FALSE, FALSE, FALSE, TRUE, FALSE, FALSE, FALSE, FALSE],
        ['', 'one', ' ', 'No', 'NONE', 'nothing', 'nope', 'empty', 'Zero', '-', "   \n\t   "],
        [],
        'Textual boolean test',
      ],
      // Language boolean.
      [
        ['Null', NULL, 'NuL', 'nil', 'undef'],
        [FALSE, FALSE, FALSE, FALSE, FALSE],
        ['Null', NULL, 'NuL', 'nil', 'undef'],
        [],
        'Lanugage boolean test',
      ],
    ];
  }

  /**
   * Tests boolean data processor.
   *
   * @dataProvider provideTestBooleanProcessor
   */
  #[DataProvider('provideTestBooleanProcessor')]
  public function testBooleanProcessor(
    array $data,
    array $expected_result,
    ?array $expected_origin,
    array $config,
    string $test_name,
  ) {

    $string_translation = $this->createStub(TranslationInterface::class);

    $logger = $this->createStub(LoggerInterface::class);

    $field_def = $this->createStub(FieldDefinitionInterface::class);

    $data_processor = new DPBoolean($config, 'boolean', [], $string_translation, $logger);

    $result = $data_processor->processData($data, $field_def, 'value');
    $this->assertEquals($expected_result, $result, $test_name);
    $reversed = $data_processor->reverseDataProcessing($result, $data, $field_def, 'value');
    $this->assertEquals($expected_origin, $reversed, $test_name . ' reversed');
    $inverted_data = array_map(
      function ($val) {
          return !$val;
      },
      $result
    );
    $inv_reversed = $data_processor->reverseDataProcessing($inverted_data, $data, $field_def, 'value');
    $this->assertEquals($inverted_data, $inv_reversed, $test_name . ' inverted');
  }

}
