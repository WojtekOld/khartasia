<?php

namespace Drupal\Tests\external_entities\Unit\PropertyMapper;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\external_entities\Plugin\ExternalEntities\PropertyMapper\PatternPropertyMapper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Unit tests for PatternPropertyMapper.
 *
 * @group external_entities
 * @group ExternalEntities
 * @group PropertyMapper
 */
#[Group('external_entities')]
#[Group('ExternalEntities')]
#[Group('PropertyMapper')]
class PatternPropertyMapperTest extends UnitTestCase {

  /**
   * Helper to create a mapper instance with a given configuration.
   */
  private function createMapper(array $configuration): PatternPropertyMapper {
    $container = $this->createStub(ContainerInterface::class);

    $string_translation = $this->createStub(TranslationInterface::class);

    $logger_factory = $this->createStub(LoggerChannelFactoryInterface::class);
    $logger_channel = $this->createStub(LoggerChannel::class);
    $logger_factory->method('get')->willReturn($logger_channel);

    $entity_field_manager = $this->createStub(EntityFieldManagerInterface::class);
    $data_processor_manager = $this->createStub(PluginManagerInterface::class);

    $container->method('get')->willReturnMap([
      [
        'string_translation',
        ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
        $string_translation,
      ],
      [
        'logger.factory',
        ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
        $logger_factory,
      ],
      [
        'entity_field.manager',
        ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
        $entity_field_manager,
      ],
      [
        'plugin.manager.external_entities.data_processor',
        ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
        $data_processor_manager,
      ],
    ]);

    $plugin_definition = [
      'id' => 'pattern',
      'label' => 'Pattern property mapper',
      'description' => 'Test plugin',
    ];

    return PatternPropertyMapper::create($container, $configuration, 'pattern', $plugin_definition);
  }

  /**
   * Test that complex pattern extraction and related flags work as expected.
   */
  public function testExtractComplexPatternAndRelatedFlags() {
    $config = [
      'mapping' => 'prefix${nested.source.field1}middle${some_other_field}${yetanother}text${last.field}suffix',
      'reversible' => TRUE,
      'reverse_pattern' => NULL,
      'array_handling' => PatternPropertyMapper::ARRAY_HANDLING_FIRST,
    ];
    $mapper = $this->createMapper($config);

    $raw = [
      'nested' => ['source' => ['field1' => 42]],
      'some_other_field' => 'bla',
      'yetanother' => 806,
      'last' => ['field' => 'xyz'],
    ];

    $values = $mapper->extractPropertyValuesFromRawData($raw);
    $this->assertEquals(['prefix42middlebla806textxyzsuffix'], $values, 'Interpolation produces expected string.');

    $this->assertTrue($mapper->isProcessed(), 'Complex pattern should be considered processed.');
    $this->assertNull($mapper->getMappedSourceFieldName(), 'Complex pattern should not return a single source field name.');
    $this->assertTrue($mapper->couldReversePropertyMapping(), 'Reversible flag set to TRUE should make couldReversePropertyMapping() return true.');
  }

  /**
   * Test that reverse mapping populates raw data as expected.
   */
  public function testReverseMappingPopulatesRawData() {
    $config = [
      'mapping' => 'prefix${nested.source.field1}middle${some_other_field}*${yetanother}text${last.field}suffix',
      'reversible' => TRUE,
      'reverse_pattern' => NULL,
      'array_handling' => PatternPropertyMapper::ARRAY_HANDLING_FIRST,
    ];
    $mapper = $this->createMapper($config);

    $final = 'prefix42middlebla*806textxyzsuffix';
    $raw_data = [];
    $context = [];

    $mapper->addPropertyValuesToRawData([$final], $raw_data, $context);

    $expected = [
      'nested' => ['source' => ['field1' => '42']],
      'some_other_field' => 'bla',
      'yetanother' => '806',
      'last' => ['field' => 'xyz'],
    ];
    $this->assertEquals($expected, $raw_data, 'Reverse mapping should populate nested raw data values (as strings).');
  }

  /**
   * Test that a single placeholder pattern works as expected.
   */
  public function testSinglePlaceholderMappingAndReverse() {
    $config = [
      'mapping' => '${a.b}',
      'reversible' => TRUE,
      'reverse_pattern' => NULL,
      'array_handling' => PatternPropertyMapper::ARRAY_HANDLING_FIRST,
    ];
    $mapper = $this->createMapper($config);

    $raw = ['a' => ['b' => 'hello']];
    $values = $mapper->extractPropertyValuesFromRawData($raw);
    $this->assertEquals(['hello'], $values, 'Single placeholder should extract raw value.');

    $this->assertFalse($mapper->isProcessed(), 'Single placeholder is a direct mapping thus not processed.');
    $this->assertEquals('a.b', $mapper->getMappedSourceFieldName(), 'Single placeholder must return dotted source field name.');
    $this->assertTrue($mapper->couldReversePropertyMapping(), 'Reversible flag true.');

    $raw_data = [];
    $context = [];
    $mapper->addPropertyValuesToRawData(['hello'], $raw_data, $context);
    $this->assertEquals($raw, $raw_data, 'Reverse of single placeholder should set same nested value.');
  }

  /**
   * Test that non-reversible mapping behaves as expected.
   *
   * Test that when reversible is FALSE, addPropertyValuesToRawData does not
   * modify the raw data.
   */
  public function testNonReversibleDoesNotModifyRaw() {
    $config = [
      'mapping' => 'pre${a.b}post',
      'reversible' => FALSE,
      'reverse_pattern' => NULL,
      'array_handling' => PatternPropertyMapper::ARRAY_HANDLING_FIRST,
    ];
    $mapper = $this->createMapper($config);

    $raw_data = [];
    $context = [];
    $mapper->addPropertyValuesToRawData(['preXpost'], $raw_data, $context);
    $this->assertEmpty($raw_data, 'When reversible is FALSE, addPropertyValuesToRawData should leave raw data unchanged.');
  }

}
