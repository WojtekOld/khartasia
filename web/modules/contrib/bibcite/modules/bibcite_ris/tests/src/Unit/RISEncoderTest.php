<?php

declare(strict_types=1);

namespace Drupal\Tests\bibcite_ris\Unit;

use Drupal\bibcite_ris\Encoder\RISEncoder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\bibcite_ris\Encoder\RISEncoder
 * @group bibcite_ris
 */
final class RISEncoderTest extends UnitTestCase {

  /**
   * The encoder under test.
   */
  protected RISEncoder $encoder;

  /**
   * The field mapping used by the encoder.
   *
   * @var array<string, string>
   */
  protected array $fields;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->encoder = new RISEncoder();

    // A subset of the default field mapping from
    // bibcite_entity.mapping.ris.yml, sufficient for the tests below.
    $this->fields = [
      'TY' => 'type',
      'A1' => '',
      'A2' => '',
      'AB' => 'bibcite_abst_e',
      'AU' => 'author',
      'BT' => 'bibcite_secondary_title',
      'C1' => 'bibcite_custom1',
      'CP' => '',
      'CT' => '',
      'ED' => '',
      'IS' => 'bibcite_issue',
      'J1' => '',
      'J2' => 'bibcite_alternate_title',
      'JA' => '',
      'JF' => '',
      'JO' => '',
      'KW' => 'keywords',
      'N2' => 'bibcite_abst_e',
      'PY' => 'bibcite_year',
      'SP' => 'bibcite_pages',
      'ST' => 'bibcite_short_title',
      'T1' => '',
      'T2' => 'bibcite_secondary_title',
      'TI' => 'title',
      'U1' => '',
      'U2' => '',
      'U3' => '',
      'U4' => '',
      'U5' => '',
      'Y1' => '',
    ];

    // Set up the Drupal container with a mock config factory.
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->with('fields')
      ->willReturn($this->fields);

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')
      ->with('bibcite_entity.mapping.ris')
      ->willReturn($config);

    $container = new ContainerBuilder();
    $container->set('config.factory', $config_factory);
    \Drupal::setContainer($container);
  }

  /**
   * Helper: build a minimal RIS string from tagged lines.
   *
   * @param array<array{string, string}> $lines
   *   An array of [key, value] pairs. Do not use $key => $value since that does
   *   not allow for repeated keys.
   *
   * @return string
   *   A valid RIS record string.
   */
  protected function buildRis(array $lines): string {
    return "TY - CONF\n" . implode('', array_map(
      fn(array $entry): string => "{$entry[0]}  - {$entry[1]}\n",
      $lines,
    )) . "ER  - \n";
  }

  /**
   * Tests alias handling during decode().
   *
   * @param array<array{string, string}> $lines
   *   RIS tagged lines as [key, value] pairs.
   * @param array<string, string|string[]> $expected
   *   Keys and values expected in the decoded record.
   * @param string[] $absent
   *   Keys that should NOT be in the decoded record.
   *
   * @covers ::decode
   * @dataProvider aliasProvider
   */
  public function testHandleAliases(array $lines, array $expected, array $absent): void {
    $ris = $this->buildRis($lines);
    $record = $this->encoder->decode($ris, 'ris')[0];

    foreach ($expected as $key => $value) {
      self::assertArrayHasKey($key, $record);
      if (is_array($value)) {
        self::assertEqualsCanonicalizing($value, (array) $record[$key]);
      }
      else {
        self::assertSame($value, $record[$key]);
      }
    }
    foreach ($absent as $key) {
      self::assertArrayNotHasKey($key, $record);
    }
  }

  /**
   * Data provider for testHandleAliases().
   */
  public static function aliasProvider(): array {
    $default = [
      ['T1', 'New key agreement protocols in braid group cryptography'],
      ['JO', "Cryptographers' Track at the RSA Conference"],
      ['SP', '13'],
      ['EP', '27'],
      ['PB', 'Springer'],
    ];
    return [
      'AB and N2 resolve to N2 (last mapped alias)' => [
        'lines' => [['AB', 'Abstract from AB'], ['N2', 'Abstract from N2'], ...$default],
        'expected' => ['N2' => 'Abstract from N2'],
        'absent' => ['AB'],
      ],
      'AB alone moves to target key N2' => [
        'lines' => [['AB', 'Only abstract'], ...$default],
        'expected' => ['N2' => 'Only abstract'],
        'absent' => ['AB'],
      ],
      'AU and A1 author values are merged' => [
        'lines' => [['AU', 'Iris Anshel'], ['A1', 'Michael Anshel'], ['AU', 'Benji Fisher'], ['A1', 'Dorian Goldfeld'], ...$default],
        'expected' => ['AU' => ['Iris Anshel', 'Michael Anshel', 'Benji Fisher', 'Dorian Goldfeld']],
        'absent' => ['A1'],
      ],
      'duplicate authors across AU and A1 are deduplicated' => [
        'lines' => [['AU', 'Same Author'], ['A1', 'Same Author'], ...$default],
        'expected' => ['AU' => 'Same Author'],
        'absent' => ['A1'],
      ],
      'ED and A2 editor values are merged' => [
        'lines' => [['ED', 'Editor One'], ['A2', 'Editor Two'], ['ED', 'Editor Three'], ...$default],
        'expected' => ['ED' => ['Editor One', 'Editor Two', 'Editor Three']],
        'absent' => ['A2'],
      ],
      'duplicate editors across ED and A2 are deduplicated' => [
        'lines' => [['ED', 'Same Editor'], ['A2', 'Same Editor'], ...$default],
        'expected' => ['ED' => 'Same Editor'],
        'absent' => ['A2'],
      ],
      'TI and BT resolve to TI (last mapped alias for title)' => [
        'lines' => [['BT', 'Title from BT'], ['TI', 'Title from TI'], ...$default],
        'expected' => ['TI' => 'Title from TI'],
        'absent' => ['BT'],
      ],
      'Y1 and PY resolve to PY' => [
        'lines' => [['Y1', '2020'], ['PY', '2001'], ...$default],
        'expected' => ['PY' => '2001'],
        'absent' => ['Y1'],
      ],
      'no alias conflict passes through cleanly' => [
        'lines' => [['TI', 'My Title'], ['PY', '2023'], ['AU', 'Some Author'], ...$default],
        'expected' => ['TI' => 'My Title', 'PY' => '2023', 'AU' => 'Some Author'],
        'absent' => [],
      ],
    ];
  }

}
