<?php

namespace Drupal\Tests\external_entities\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\Core\Field\FieldDefinitionInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\external_entities\Plugin\ExternalEntities\DataProcessor\Hash as DPHash;

/**
 * The class to test hash data processor.
 *
 * @group ExternalEntities
 */
#[Group('ExternalEntities')]
class DataProcessorHashUnitTest extends UnitTestCase {

  /**
   * Data provider for testHashProcessor().
   *
   * Structure:
   * - data
   * - expected_result
   * - expected_origin
   * - config
   * - test name.
   */
  public static function provideTestHashProcessor() {
    return [
      // Numeric.
      [
        [1234, 501.33],
        ['81dc9bdb52d04dc20036dbd8313ed055', '6359396dddf124c5c7e695b2135c7fb2'],
        NULL,
        ['algo' => 'md5'],
        'Numeric hash test',
      ],
      // Textual.
      [
        ['blablabla'],
        ['1a36591bceec49c832079e270d7e8b73'],
        NULL,
        ['algo' => 'md5'],
        'Textual hash test',
      ],
      // sha256 algo.
      [
        ['blablabla'],
        ['492f3f38d6b5d3ca859514e250e25ba65935bcdd9f4f40c124b773fe536fee7d'],
        NULL,
        ['algo' => 'sha256'],
        'sha256 algo',
      ],
    ];
  }

  /**
   * Tests hash data processor.
   *
   * @dataProvider provideTestHashProcessor
   */
  #[DataProvider('provideTestHashProcessor')]
  public function testHashProcessor(
    array $data,
    array $expected_result,
    ?array $expected_origin,
    array $config,
    string $test_name,
  ) {

    $string_translation = $this->createStub(TranslationInterface::class);

    $logger = $this->createStub(LoggerInterface::class);

    $field_def = $this->createStub(FieldDefinitionInterface::class);

    $data_processor = new DPHash($config, 'hash', [], $string_translation, $logger);

    $result = $data_processor->processData($data, $field_def, 'value');
    $this->assertEquals($expected_result, $result, $test_name);
    $reversed = $data_processor->reverseDataProcessing($result, $data, $field_def, 'value');
    $this->assertEquals($expected_origin, $reversed, $test_name . ' reversed');
  }

}
