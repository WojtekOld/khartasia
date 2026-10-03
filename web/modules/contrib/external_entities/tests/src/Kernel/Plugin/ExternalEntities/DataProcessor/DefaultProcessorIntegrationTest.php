<?php

namespace Drupal\Tests\external_entities\Kernel\Plugin\ExternalEntities\DataProcessor;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\KernelTests\KernelTestBase;
use Drupal\external_entities\Plugin\ExternalEntities\DataProcessor\DefaultProcessor;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * @coversDefaultClass \Drupal\external_entities\Plugin\ExternalEntities\DataProcessor\DefaultProcessor
 *
 * @group external_entities
 * @group external_entities_data_processor
 * @group ExternalEntities
 */
#[Group('external_entities')]
#[Group('external_entities_data_processor')]
#[Group('ExternalEntities')]
#[RunTestsInSeparateProcesses]
class DefaultProcessorIntegrationTest extends KernelTestBase {

  /**
   * The processor.
   *
   * @var \Drupal\external_entities\Plugin\ExternalEntities\DataProcessor\DefaultProcessor
   */
  protected $processor;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'external_entities',
    'datetime',
    'datetime_range',
    'link',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // $this->installEntitySchema('entity_test');
    $this->installConfig(['field']);

    $this->processor = DefaultProcessor::create(
      \Drupal::getContainer(),
      [],
      'default',
      []
    );
  }

  /**
   * Data provider for testDefaultProcessor().
   *
   * Structure:
   * - data
   * - expected_result
   * - expected_origin
   * - config
   * - field plugin name
   * - field property name
   * - field options
   * - test name.
   */
  public static function provideTestDefaultProcessor() {
    return [
      // String, auto, no default.
      [
        ['bla', 'bli', 'blo'],
        ['bla', 'bli', 'blo'],
        ['bla', 'bli', 'blo'],
        [
          'datatype' => '',
          'default' => NULL,
        ],
        'string',
        'value',
        [],
        'String test 1',
      ],
      // String, force string, no default.
      [
        ['bla', 42, NULL],
        ['bla', '42', NULL],
        ['bla', '42', NULL],
        [
          'datatype' => 'string',
          'default' => NULL,
        ],
        'string',
        'value',
        [],
        'String test 2',
      ],
      // String, force string, default.
      [
        ['bla', NULL, 'blo', NULL],
        ['bla', 'toto', 'blo', 'toto'],
        ['bla', 'toto', 'blo', 'toto'],
        [
          'datatype' => 'string',
          'default' => 'toto',
        ],
        'string',
        'value',
        [],
        'String test 3',
      ],
      // String, force string, default.
      [
        [],
        ['toto'],
        ['toto'],
        [
          'datatype' => 'string',
          'default' => 'toto',
        ],
        'string',
        'value',
        [],
        'String test 4',
      ],
      // Integer, auto, default.
      [
        ['42', NULL, 806],
        [42, 33, 806],
        [42, 33, 806],
        [
          'datatype' => '',
          'default' => '33',
        ],
        'integer',
        'value',
        [],
        'Integer test 1',
      ],
      // Boolean, auto, default.
      [
        // Note: the string 'FALSE' should be evaluated as TRUE (ie. non empty
        // string).
        ['', 0, FALSE, NULL, 'FALSE', 1, TRUE],
        [FALSE, FALSE, FALSE, TRUE, TRUE, TRUE, TRUE],
        [FALSE, FALSE, FALSE, TRUE, TRUE, TRUE, TRUE],
        [
          'datatype' => '',
          'default' => 1,
        ],
        'boolean',
        'value',
        [],
        'Boolean test 1',
      ],
      // Float, auto, default.
      [
        [806, 3.14, 4e2, NULL],
        [806., 3.14, 400., -1.],
        [806., 3.14, 400., -1.],
        [
          'datatype' => '',
          'default' => -1,
        ],
        'float',
        'value',
        [],
        'Float test 1',
      ],
      // Decimal, auto, default.
      [
        [501, 3.1418, 4e2, NULL],
        ['501', '3.1418', '400', '-3'],
        ['501', '3.1418', '400', '-3'],
        [
          'datatype' => '',
          'default' => -3.0,
        ],
        'decimal',
        'value',
        [],
        'Decimal test 1',
      ],
      // E-mail, auto, default.
      [
        ['bla', 42, 'bli@blo.com', NULL],
        ['bla', '42', 'bli@blo.com', 'Toto@Titi.tutu'],
        ['bla', '42', 'bli@blo.com', 'Toto@Titi.tutu'],
        [
          'datatype' => '',
          'default' => 'Toto@Titi.tutu',
        ],
        'email',
        'value',
        [],
        'Email test 1',
      ],
      // Link, auto, default.
      [
        ['bla', 42, 'https://blo.com', NULL],
        ['bla', '42', 'https://blo.com', 'https://bli.com'],
        ['bla', '42', 'https://blo.com', 'https://bli.com'],
        [
          'datatype' => '',
          'default' => 'https://bli.com',
        ],
        'link',
        'uri',
        [],
        'Link test 1',
      ],
      // Timestamp, auto, default.
      [
        ['bla', 42, '1789/07/14 17:14:00', 1756046896, NULL],
        [0, 42, -5694943852, 1756046896, 1756045962],
        [0, 42, -5694943852, 1756046896, 1756045962],
        [
          'datatype' => '',
          // Default converted to timestamp 1756045962.
          'default' => 'Sun Aug 24 2025 16:32:42 GMT+0200',
        ],
        'timestamp',
        'value',
        [],
        'Timestamp test 1',
      ],
      // Date-time ISO8601, auto, default.
      [
        [
          'bla',
          42,
          '1789/07/14 17:14:00',
          1756046896,
          '2025-08-25T14:30:00',
          NULL,
        ],
        [
          '',
          '1970-01-01T00:00:42',
          '1789-07-14T07:09:08',
          '2025-08-24T14:48:16',
          '2025-08-25T04:30:00',
          '2025-08-24T14:32:42',
        ],
        [
          '',
          '1970-01-01T00:00:42',
          '1789-07-14T07:09:08',
          '2025-08-24T14:48:16',
          '2025-08-25T04:30:00',
          '2025-08-24T14:32:42',
        ],
        [
          'datatype' => '',
          // Default converted to timestamp 1756045962.
          'default' => 'Sun Aug 24 2025 16:32:42 GMT+0200',
        ],
        'datetime',
        'value',
        [],
        'Date-time test 1',
      ],
    ];
  }

  /**
   * Tests default data processor.
   *
   * @dataProvider provideTestDefaultProcessor
   */
  #[DataProvider('provideTestDefaultProcessor')]
  public function testDefaultProcessor(
    array $data,
    array $expected_result,
    ?array $expected_origin,
    array $config,
    string $field_plugin,
    string $field_property,
    array $field_options,
    string $test_name,
  ) {
    $field_definition = BaseFieldDefinition::create($field_plugin)
      ->setName('test_field');
    if (!empty($field_options['settings'])) {
      $field_definition->setSettings($field_options['settings']);
    }

    $this->processor->setConfiguration($config);
    $result = $this->processor->processData($data, $field_definition, $field_property);
    $this->assertSame($expected_result, $result, $test_name);
    $reversed = $this->processor->reverseDataProcessing($result, $data, $field_definition, $field_property);
    $this->assertSame($expected_origin, $reversed, $test_name . ' reversed');
  }

}
