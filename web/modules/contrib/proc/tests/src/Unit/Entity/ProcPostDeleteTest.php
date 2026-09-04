<?php

namespace Drupal\Tests\proc\Unit\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\proc\Entity\Proc;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests Proc::postDelete().
 *
 * @coversDefaultClass \Drupal\proc\Entity\Proc
 *
 * @group proc
 */
final class ProcPostDeleteTest extends TestCase {

  /**
   * Mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * Mocked file usage service.
   *
   * @var \Drupal\file\FileUsage\FileUsageInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  private FileUsageInterface $fileUsage;

  /**
   * Mocked file storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  private EntityStorageInterface $fileStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->fileUsage = $this->createMock(FileUsageInterface::class);
    $this->fileStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager->method('getStorage')
      ->with('file')
      ->willReturn($this->fileStorage);

    $cache_tags_invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $logger_factory = $this->createMock('Drupal\Core\Logger\LoggerChannelFactoryInterface');
    $logger_channel = $this->createMock('Drupal\Core\Logger\LoggerChannelInterface');
    $logger_factory->method('get')->with('proc')->willReturn($logger_channel);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $this->entityTypeManager);
    $container->set('file.usage', $this->fileUsage);
    $container->set('cache_tags.invalidator', $cache_tags_invalidator);
    $container->set('logger.factory', $logger_factory);
    \Drupal::setContainer($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::setContainer(new ContainerBuilder());
    parent::tearDown();
  }

  /**
   * Ensures cipher procs clear usage rows and delete their managed file.
   *
   * @covers ::postDelete
   */
  public function testPostDeleteRemovesUsageAndDeletesCipherFile(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('getListCacheTags')->willReturn([]);

    $entity = $this->createMock(Proc::class);
    $entity->method('getEntityType')->willReturn($entity_type);
    $entity->method('getCacheTagsToInvalidate')->willReturn([]);
    $entity->method('id')->willReturn(123);
    $entity->method('get')->willReturnMap([
      ['type', (object) ['value' => 'cipher']],
      [
        'armored',
        new class {

          /**
           * Simulates the getValue() method to return a file ID for the test.
           *
           * @return array
           *   An array containing the file ID.
           */
          public function getValue(): array {

            return [['cipher_fid' => '99']];
          }

        },
      ],
    ]);

    $file = $this->createMock(FileInterface::class);

    $entity_storage = $this->createMock(EntityStorageInterface::class);
    $entity_storage->method('getEntityType')->willReturn($entity_type);

    $this->fileStorage->expects($this->once())
      ->method('load')
      ->with(99)
      ->willReturn($file);

    $this->fileUsage->expects($this->once())
      ->method('listUsage')
      ->with($file)
      ->willReturn([
        'proc' => [
          'file' => [123 => 2, 999 => 1],
          'other' => [111 => 1],
        ],
        'another_module' => [
          'file' => [123 => 3],
        ],
      ]);

    $this->fileUsage->expects($this->once())
      ->method('delete')
      ->with($file, 'proc', 'file', 123, 2);

    $file->expects($this->once())
      ->method('delete');

    Proc::postDelete($entity_storage, [$entity]);
  }

  /**
   * Ensures non-cipher procs are ignored.
   *
   * @covers ::postDelete
   */
  public function testPostDeleteSkipsNonCipherEntities(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('getListCacheTags')->willReturn([]);

    $entity = $this->createMock(Proc::class);
    $entity->method('getEntityType')->willReturn($entity_type);
    $entity->method('getCacheTagsToInvalidate')->willReturn([]);
    $entity->method('get')->willReturnMap([
      ['type', (object) ['value' => 'keyring']],
    ]);

    $entity_storage = $this->createMock(EntityStorageInterface::class);
    $entity_storage->method('getEntityType')->willReturn($entity_type);

    $this->fileStorage->expects($this->never())
      ->method('load');

    $this->fileUsage->expects($this->never())
      ->method('listUsage');

    $this->fileUsage->expects($this->never())
      ->method('delete');

    Proc::postDelete($entity_storage, [$entity]);
  }

}
