<?php

namespace Drupal\Tests\external_entities\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\Core\Field\FieldDefinitionInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\external_entities\Plugin\ExternalEntities\DataProcessor\ValueMapping;

/**
 * The class to test value mapping data processor.
 *
 * @group ExternalEntities
 */
#[Group('ExternalEntities')]
class DataProcessorValueMappingUnitTest extends UnitTestCase {

  /**
   * Data provider for testValueMappingProcessor().
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
  public static function provideTestValueMappingProcessor() {
    return [
      // Basic mapping.
      [
        ['toto', 'titi', 'toto', 'tata', 'tutu', 'tete', 'tototyty', 'blitata'],
        [42, 16, 42, 806, 'test', NULL, '123', NULL],
        ['toto', 'titi', 'toto', 'tata', 'tutu', NULL, 'tototyty', NULL],
        [806, 'test', 'none'],
        ['tata', 'tutu', NULL],
        [
          'mapping' => [
            'toto' => 42,
            'titi' => 16,
            'tata' => 806,
            'tutu' => 'test',
            'tototyty' => '123',
          ],
        ],
        'Basic mapping test',
      ],
      // Keep unmapped entries.
      [
        ['toto', 'titi', 'toto', 'tata', 'tutu', 'tete', 'tototyty', 'blitata'],
        [42, 16, 42, 806, 'test', 'tete', '123', 'blitata'],
        ['toto', 'titi', 'toto', 'tata', 'tutu', 'tete', 'tototyty', 'blitata'],
        [806, 'test', 'none'],
        ['tata', 'tutu', 'none'],
        [
          'mapping' => [
            'toto' => 42,
            'titi' => 16,
            'tata' => 806,
            'tutu' => 'test',
            'tototyty' => '123',
          ],
          'keep_unmapped' => TRUE,
        ],
        'Keep unmapped test',
      ],
    ];
  }

  /**
   * Tests value mapping data processor.
   *
   * @dataProvider provideTestValueMappingProcessor
   */
  #[DataProvider('provideTestValueMappingProcessor')]
  public function testValueMappingProcessor(
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

    $data_processor = new ValueMapping($config, 'valuemapping', [], $string_translation, $logger);

    $result = $data_processor->processData($data, $field_def, 'value');
    $this->assertEquals($expected_result, $result, $test_name);
    $reversed = $data_processor->reverseDataProcessing($result, $data, $field_def, 'value');
    $this->assertEquals($expected_origin, $reversed, $test_name . ' reversed');

    $rev_altered = $data_processor->reverseDataProcessing($alter, $data, $field_def, 'value');
    $this->assertEquals($expected_alter, $rev_altered, $test_name . ' reversed altered');
  }

}
