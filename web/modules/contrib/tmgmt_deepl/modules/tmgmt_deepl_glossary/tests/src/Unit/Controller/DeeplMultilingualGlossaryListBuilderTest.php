<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Unit\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tmgmt_deepl_glossary\Controller\DeeplMultilingualGlossaryListBuilder;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossary;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests the DeeplMultilingualGlossaryListBuilder.
 *
 * @covers \Drupal\tmgmt_deepl_glossary\Controller\DeeplMultilingualGlossaryListBuilder
 * @group tmgmt_deepl_glossary
 */
class DeeplMultilingualGlossaryListBuilderTest extends UnitTestCase {

  /**
   * The mocked list builder.
   *
   * @var \Drupal\tmgmt_deepl_glossary\Controller\DeeplMultilingualGlossaryListBuilder&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject&DeeplMultilingualGlossaryListBuilder $listBuilder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_storage = $this->createMock(EntityStorageInterface::class);

    $this->listBuilder = $this->getMockBuilder(DeeplMultilingualGlossaryListBuilder::class)
      ->setConstructorArgs([$entity_type, $entity_storage])
      ->onlyMethods(['t'])
      ->getMock();

    $this->listBuilder->method('t')
      ->willReturnArgument(0);
  }

  /**
   * Tests the method ::buildHeader.
   */
  public function testBuildHeader(): void {
    $expected_header = [
      'name' => 'Name',
      'glossary_id' => 'Glossary Id',
      'operations' => 'Operations',
    ];

    $header = $this->listBuilder->buildHeader();

    $this->assertEquals($expected_header, $header);
  }

  /**
   * Tests the method ::buildRow.
   */
  public function testBuildRow(): void {
    // Mock the module handler.
    $module_handler = $this->createMock('Drupal\Core\Extension\ModuleHandlerInterface');
    $module_handler->method('invokeAll')->willReturn([]);

    // Mock the string translation.
    $string_translation = $this->createMock('Drupal\Core\StringTranslation\TranslationInterface');
    $string_translation->method('translate')->willReturnArgument(0);

    // Mock the container and set it.
    $container = $this->createMock('Symfony\Component\DependencyInjection\ContainerInterface');
    $container->method('get')
      ->willReturnMap([
        ['module_handler', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $module_handler],
        ['string_translation', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $string_translation],
      ]);
    \Drupal::setContainer($container);

    $glossary_entity = $this->createMock(DeeplMultilingualGlossary::class);
    $glossary_entity->method('label')->willReturn('Test Glossary');
    $glossary_entity->method('getGlossaryId')->willReturn('glossary123');
    $glossary_entity->method('access')->willReturn(AccessResult::allowed());

    $expected_row = [
      'name' => 'Test Glossary',
      'glossary_id' => 'glossary123',
      'operations' => [
        'data' => [
          '#type' => 'operations',
          '#links' => [],
          '#attached' => [
            'library' => ['core/drupal.dialog.ajax'],
          ],
          '#cache' => [
            'contexts' => [],
            'tags' => [],
            'max-age' => -1,
          ],
        ],
      ],
    ];
    if (version_compare(\Drupal::VERSION, '11.3-dev', '<')) {
      unset($expected_row['operations']['data']['#cache']);
    }

    $row = $this->listBuilder->buildRow($glossary_entity);

    $this->assertEquals($expected_row, $row);

    // Unset the container after the test.
    \Drupal::unsetContainer();
  }

}
