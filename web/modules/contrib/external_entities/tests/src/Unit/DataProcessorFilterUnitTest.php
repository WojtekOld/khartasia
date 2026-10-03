<?php

namespace Drupal\Tests\external_entities\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\Core\Field\FieldDefinitionInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\external_entities\Plugin\ExternalEntities\DataProcessor\Filter;

/**
 * The class to test value mapping data processor.
 *
 * @group ExternalEntities
 */
#[Group('ExternalEntities')]
class DataProcessorFilterUnitTest extends UnitTestCase {

  /**
   * Data provider for testFilterProcessor().
   *
   * Structure:
   * - data
   * - expected_result
   * - config
   * - test name.
   */
  public static function provideTestFilterProcessor() {
    return [
      // NULL filtering.
      [
        ['toto', NULL, 'titi', '', 'toto', 'tata', NULL, 'tutu', 'tete', '0', NULL, 'tototyty', 0, 'blitata', NULL],
        ['toto', 'titi', '', 'toto', 'tata', 'tutu', 'tete', '0', 'tototyty', 0, 'blitata'],
        [
          'filtered' => [],
          'filter_empty' => 'null',
        ],
        'Null filtering test',
      ],
      // Value filtering.
      [
        ['toto', NULL, 'titi', '', 'toto', 'tata', NULL, 'tutu', 'tete', '0', NULL, 'tototyty', 0, 'blitata', NULL],
        [NULL, 'titi', '', 'tata', NULL, 'tutu', '0', NULL, 'tototyty', 0, 'blitata', NULL],
        [
          'filtered' => [
            'tete' => 'tete',
            'toto' => 'toto',
          ],
          'filter_empty' => '',
        ],
        'Value filtering test',
      ],
      // Null and value filtering.
      [
        ['toto', NULL, 'titi', '', 'toto', 'tata', NULL, 'tutu', 'tete', '0', NULL, 'tototyty', 0, 'blitata', NULL],
        ['titi', '', 'tata', 'tutu', '0', 'tototyty', 0, 'blitata'],
        [
          'filtered' => [
            'tete' => 'tete',
            'toto' => 'toto',
          ],
          'filter_empty' => 'null',
        ],
        'Null and value filtering test',
      ],
      // Empty and value filtering.
      [
        ['toto', NULL, 'titi', '', 'toto', 'tata', NULL, 'tutu', 'tete', '0', NULL, 'tototyty', 0, 'blitata', NULL],
        ['titi', 'tata', 'tutu', 'tototyty', 'blitata'],
        [
          'filtered' => [
            'tete' => 'tete',
            'toto' => 'toto',
          ],
          'filter_empty' => 'empty',
        ],
        'Empty and value filtering test',
      ],
      // Empty and value filtering, keeping 0s.
      [
        ['toto', NULL, 'titi', '', 'toto', 'tata', NULL, 'tutu', 'tete', '0', NULL, 'tototyty', 0, 'blitata', NULL],
        ['titi', 'tata', 'tutu', '0', 'tototyty', 0, 'blitata'],
        [
          'filtered' => [
            'tete' => 'tete',
            'toto' => 'toto',
          ],
          'filter_empty' => 'zerook',
        ],
        'Empty and value filtering keeping 0s test',
      ],
      // Keep only matching values (mode explicit).
      [
        ['a', 'ab', 'abc', 'bc', 'b', 'ab'],
        ['ab', 'bc', 'ab'],
        [
          'filtered' => [
            'ab' => 'ab',
            'bc' => 'bc',
          ],
          'filter_mode' => 'keep_matching',
          'filter_empty' => '',
        ],
        'Keep matching test',
      ],
      // Keep values starting with.
      [
        ['foo', 'foobar', 'barfoo', 'foo', 'bar'],
        ['foo', 'foobar', 'foo'],
        [
          'filtered' => [
            'foo' => 'foo',
          ],
          'filter_mode' => 'keep_start',
          'filter_empty' => '',
        ],
        'Keep start test',
      ],
      // Keep values ending with.
      [
        ['apple', 'pineapple', 'maple', 'peach', 'hippie'],
        ['apple', 'pineapple'],
        [
          'filtered' => [
            'apple' => 'apple',
          ],
          'filter_mode' => 'keep_end',
          'filter_empty' => '',
        ],
        'Keep end test',
      ],
      // Remove values starting with.
      [
        ['alpha', 'beta', 'alphabet', 'gamma', 'alp'],
        ['beta', 'gamma'],
        [
          'filtered' => [
            'alp' => 'alp',
          ],
          'filter_mode' => 'remove_start',
          'filter_empty' => '',
        ],
        'Remove start test',
      ],
      // Remove values ending with.
      [
        ['end', 'friend', 'send', 'ending', 'bend'],
        ['ending'],
        [
          'filtered' => [
            'end' => 'end',
          ],
          'filter_mode' => 'remove_end',
          'filter_empty' => '',
        ],
        'Remove end test',
      ],
    ];
  }

  /**
   * Tests value mapping data processor.
   *
   * @dataProvider provideTestFilterProcessor
   */
  #[DataProvider('provideTestFilterProcessor')]
  public function testFilterProcessor(
    array $data,
    array $expected_result,
    array $config,
    string $test_name,
  ) {

    $string_translation = $this->createStub(TranslationInterface::class);

    $logger = $this->createStub(LoggerInterface::class);

    $field_def = $this->createStub(FieldDefinitionInterface::class);

    $data_processor = new Filter($config, 'filter', [], $string_translation, $logger);

    $result = $data_processor->processData($data, $field_def, 'value');
    $this->assertEquals($expected_result, $result, $test_name);
  }

}
