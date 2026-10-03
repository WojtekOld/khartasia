<?php

namespace Drupal\Tests\external_entities\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\Core\Field\FieldDefinitionInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\external_entities\Plugin\ExternalEntities\DataProcessor\Version;

/**
 * The class to test version data processor.
 *
 * @group ExternalEntities
 */
#[Group('ExternalEntities')]
class DataProcessorVersionUnitTest extends UnitTestCase {

  /**
   * Data provider for testVersionProcessor().
   *
   * Structure:
   * - data
   * - expected_result
   * - config
   * - test name.
   */
  public static function provideTestVersionProcessor() {
    return [
      // Major.
      [
        ['5', '2.0', 'v3.9', '3.1.2', '6b', '7.8beta', '1.0.x'],
        [5, 2, 3, 3, 6, 7, 1],
        [
          'elements' => ['major'],
        ],
        'Major test',
      ],
      // Minor.
      [
        ['5', '2.0', 'v3.9', '3.1.2', '6b', '7.8beta', '1.0.x'],
        ['', '0', '9', '1', '', '8', '0'],
        [
          'elements' => ['minor'],
        ],
        'Minor test',
      ],
      // Build.
      [
        ['5', '2.0', 'v3.9', '3.1.2', '6b', '7.8beta', '1.0.x'],
        ['', '', '', '2', '', '', 'x'],
        [
          'elements' => ['build'],
        ],
        'Build test',
      ],
      // Stability.
      [
        ['5', '2.0', 'v3.9', '3.1.2', '6b', '7.8beta', '1.0.x', '7.0.1 alpha', '4.8RC1', '9-dev'],
        ['', '', '', '', 'b', 'beta', '', 'alpha', 'RC1', 'dev'],
        [
          'elements' => ['stability'],
        ],
        'Stability test',
      ],
      // Major-minor.
      [
        ['5', '2.0', 'v3.9', '3.1.2', '6b', '7.8beta', '1.0.x'],
        ['5', '2.0', '3.9', '3.1', '6', '7.8', '1.0'],
        [
          'elements' => ['major', 'minor'],
        ],
        'Major-minor test',
      ],
    ];
  }

  /**
   * Tests version data processor.
   *
   * @dataProvider provideTestVersionProcessor
   */
  #[DataProvider('provideTestVersionProcessor')]
  public function testVersionProcessor(
    array $data,
    array $expected_result,
    array $config,
    string $test_name,
  ) {

    $string_translation = $this->createStub(TranslationInterface::class);

    $logger = $this->createStub(LoggerInterface::class);

    $field_def = $this->createStub(FieldDefinitionInterface::class);

    $data_processor = new Version($config, 'version', [], $string_translation, $logger);

    $result = $data_processor->processData($data, $field_def, 'value');
    $this->assertEquals($expected_result, $result, $test_name);
  }

}
