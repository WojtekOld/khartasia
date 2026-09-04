<?php

namespace Drupal\Tests\tmgmt_deepl\Unit\Hook;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt_deepl\Hook\TmgmtDeeplHooks;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the TmgmtDeeplHooks class.
 *
 * @covers \Drupal\tmgmt_deepl\Hook\TmgmtDeeplHooks
 * @group tmgmt_deepl
 */
class TmgmtDeeplHooksTest extends UnitTestCase {

  /**
   * The file repository mock.
   *
   * @var \Drupal\file\FileRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected FileRepositoryInterface&MockObject $fileRepository;

  /**
   * The file usage mock.
   *
   * @var \Drupal\file\FileUsage\FileUsageInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected FileUsageInterface&MockObject $fileUsage;

  /**
   * The database connection mock.
   *
   * @var \Drupal\Core\Database\Connection&\PHPUnit\Framework\MockObject\MockObject
   */
  protected Connection&MockObject $database;

  /**
   * The tmgmt_job entity storage mock.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected EntityStorageInterface&MockObject $jobStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->fileRepository = $this->createMock(FileRepositoryInterface::class);
    $this->fileUsage = $this->createMock(FileUsageInterface::class);
    $this->database = $this->createMock(Connection::class);

    // Entity type manager provides storages for tmgmt_job and file entities.
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $this->jobStorage = $this->createMock(EntityStorageInterface::class);
    $entity_type_manager->method('getStorage')->willReturnMap([
      ['tmgmt_job', $this->jobStorage],
      ['file', $this->createMock(EntityStorageInterface::class)],
    ]);
    $entity_type_repository = $this->createMock(EntityTypeRepositoryInterface::class);
    $entity_type_repository->method('getEntityTypeFromClass')->willReturn('tmgmt_job');
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    $container->set('entity_type.repository', $entity_type_repository);
    \Drupal::setContainer($container);
  }

  /**
   * Tests ::create.
   */
  public function testCreate(): void {
    $container = new ContainerBuilder();
    $container->set('file.repository', $this->fileRepository);
    $container->set('file.usage', $this->fileUsage);
    $container->set('database', $this->database);
    $container->set('entity_type.manager', $this->createMock(EntityTypeManagerInterface::class));
    $hooks = TmgmtDeeplHooks::create($container);
    /* @phpstan-ignore-next-line */
    $this->assertInstanceOf(TmgmtDeeplHooks::class, $hooks);
  }

  /**
   * Creates the hook class under test.
   *
   * @return \Drupal\tmgmt_deepl\Hook\TmgmtDeeplHooks
   *   The hooks object.
   */
  protected function createHooks(): TmgmtDeeplHooks {
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    return new TmgmtDeeplHooks(
      $this->fileRepository,
      $this->fileUsage,
      $this->database,
      $entity_type_manager,
    );
  }

  /**
   * Tests ::fileDownload with an unknown file URI.
   */
  public function testFileDownloadUnknownFile(): void {
    $this->fileRepository->method('loadByUri')->with('private://unknown.csv')->willReturn(NULL);
    $this->assertNull($this->createHooks()->fileDownload('private://unknown.csv'));
  }

  /**
   * Tests ::fileDownload with a file not used by tmgmt_deepl.
   */
  public function testFileDownloadNoTmgmtDeeplUsage(): void {
    $file = $this->createMock(FileInterface::class);
    $this->fileRepository->method('loadByUri')->willReturn($file);
    $this->fileUsage->method('listUsage')->with($file)->willReturn([
      'some_other_module' => ['node' => [1 => 1]],
    ]);
    $this->assertNull($this->createHooks()->fileDownload('private://document.docx'));
  }

  /**
   * Tests ::fileDownload denies access when no job grants view access.
   */
  public function testFileDownloadNoJobAccess(): void {
    $file = $this->createMock(FileInterface::class);
    $this->fileRepository->method('loadByUri')->willReturn($file);
    $this->fileUsage->method('listUsage')->with($file)->willReturn([
      'tmgmt_deepl' => ['tmgmt_job' => [3 => 1]],
    ]);
    $job = $this->createMock(Job::class);
    $job->method('access')->with('view')->willReturn(FALSE);
    $this->jobStorage->expects($this->once())
      ->method('loadMultiple')
      ->with([3])
      ->willReturn([3 => $job]);
    $this->assertNull($this->createHooks()->fileDownload('private://document.docx'));
  }

  /**
   * Tests ::fileDownload when a job grants access.
   */
  public function testFileDownloadGrantedAccess(): void {
    $file = $this->createMock(FileInterface::class);
    $this->fileRepository->method('loadByUri')->willReturn($file);
    $this->fileUsage->method('listUsage')->with($file)->willReturn([
      'tmgmt_deepl' => ['tmgmt_job' => [5 => 1]],
    ]);
    $job = $this->createMock(Job::class);
    $job->method('access')->with('view')->willReturn(TRUE);
    $this->jobStorage->expects($this->once())
      ->method('loadMultiple')
      ->with([5])
      ->willReturn([5 => $job]);
    $file->method('getFilename')->willReturn('document.docx');
    $file->method('getSize')->willReturn('1024');
    $hooks = $this->createHooks();
    $result = $hooks->fileDownload('private://document.docx');
    $this->assertNotNull($result);
    /** @var array<string, string> $result */
    $this->assertSame('application/octet-stream', $result['Content-Type']);
    $this->assertStringContainsString('document.docx', $result['Content-Disposition']);
    $this->assertSame('1024', $result['Content-Length']);
  }

}
