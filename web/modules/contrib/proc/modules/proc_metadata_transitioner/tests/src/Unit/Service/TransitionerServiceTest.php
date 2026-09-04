<?php

namespace Drupal\Tests\proc_metadata_transitioner\Unit\Service;

use Drupal\proc_metadata_transitioner\Services\TransitionerService;
use Drupal\proc\ByteSizeFormatter;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\proc_metadata_transitioner\Services\TransitionerService
 *
 * @group proc_metadata_transitioner
 */
class TransitionerServiceTest extends TestCase {

  /**
   * @covers ::getFieldTables
   */
  public function testGetFieldTablesEmpty() {
    $entityTypeManager = $this->createMock('\Drupal\Core\Entity\EntityTypeManagerInterface');
    $database = $this->createMock('\Drupal\Core\Database\Connection');
    $logger = $this->createMock('\Drupal\Core\Logger\LoggerChannelFactoryInterface');
    $tempstore = $this->createMock('\Drupal\Core\TempStore\PrivateTempStoreFactory');
    $configFactory = $this->createMock('\Drupal\Core\Config\ConfigFactoryInterface');
    $byteSizeFormatter = $this->createMock(ByteSizeFormatter::class);
    $dateFormatter = $this->createMock('\Drupal\Core\Datetime\DateFormatterInterface');

    $service = new TransitionerService(
      $entityTypeManager,
      $database,
      $logger,
      $tempstore,
      $configFactory,
      $byteSizeFormatter,
      $dateFormatter
    );

    // Prepare config factory to return the host entity machine name.
    $config = $this->createMock('\Drupal\Core\Config\ImmutableConfig');
    $config->method('get')->with('host_content_machine_name')->willReturn('node');
    $configFactory->method('get')->with('proc_metadata_transitioner.settings')->willReturn($config);

    // Mock database schema: tableExists returns false.
    $schema = $this->createMock('\Drupal\Core\Database\Schema');
    $schema->method('tableExists')->willReturn(FALSE);
    $database->method('schema')->willReturn($schema);

    // Call protected method getFieldTables via reflection.
    $ref = new \ReflectionClass(TransitionerService::class);
    $method = $ref->getMethod('getFieldTables');
    $method->setAccessible(TRUE);

    $result = $method->invokeArgs($service, [['field1']]);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

}
