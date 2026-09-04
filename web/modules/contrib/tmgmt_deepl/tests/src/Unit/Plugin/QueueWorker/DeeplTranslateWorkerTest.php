<?php

namespace Drupal\Tests\tmgmt_deepl\Unit\Plugin\QueueWorker;

use Drupal\Tests\UnitTestCase;
use Drupal\tmgmt_deepl\DeeplTranslatorBatchInterface;
use Drupal\tmgmt_deepl\Plugin\QueueWorker\DeeplTranslateWorker;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests the DeeplTranslateWorker.
 *
 * @covers \Drupal\tmgmt_deepl\Plugin\QueueWorker\DeeplTranslateWorker
 * @group tmgmt_deepl
 */
class DeeplTranslateWorkerTest extends UnitTestCase {

  /**
   * The mocked container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected ContainerInterface|MockObject $container;

  /**
   * The mocked batch wrapper.
   *
   * @var \Drupal\tmgmt_deepl\DeeplTranslatorBatchInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected DeeplTranslatorBatchInterface|MockObject $batch;

  /**
   * The DeeplTranslateWorker instance to test.
   *
   * @var \Drupal\tmgmt_deepl\Plugin\QueueWorker\DeeplTranslateWorker
   */
  protected DeeplTranslateWorker $worker;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->batch = $this->createMock(DeeplTranslatorBatchInterface::class);

    $this->worker = new DeeplTranslateWorker(
      [],
      'deepl_translate_worker',
      ['provider' => 'tmgmt_deepl'],
      $this->batch
    );
  }

  /**
   * Tests the method ::processItem.
   */
  public function testProcessItem(): void {
    // Mock the necessary objects and data.
    $job = $this->createMock('Drupal\tmgmt\Entity\Job');
    $job_item = $this->createMock('Drupal\tmgmt\Entity\JobItem');
    $job_item
      ->method('id')
      ->willReturn(1);

    $data = [
      'job' => $job,
      'job_item' => $job_item,
      'q' => ['Test text'],
      'translation' => 'Translated text',
      'keys_sequence' => ['key1', 'key2'],
    ];

    $this->batch->expects($this->once())
      ->method('translateOperation')
      ->with($job, ['Test text'], ['key1', 'key2'], $this->anything());

    $this->batch->expects($this->once())
      ->method('finishedOperation')
      ->with(TRUE, $this->arrayHasKey('job_item'), []);

    // Test successful processing.
    $this->worker->processItem($data);
  }

  /**
   * Tests ::processItem with documents in the data.
   */
  public function testProcessItemWithDocuments(): void {
    $job = $this->createMock('Drupal\tmgmt\Entity\Job');
    $job_item = $this->createMock('Drupal\tmgmt\Entity\JobItem');
    $document = $this->createMock('Drupal\file\FileInterface');

    $this->batch->expects($this->once())
      ->method('translateOperation')
      ->with($job, ['Hello'], ['key1'], $this->anything());

    $this->batch->expects($this->once())
      ->method('translateDocumentOperation')
      ->with($job, 'file_key', $document, $this->anything());

    $this->batch->expects($this->once())
      ->method('finishedOperation')
      ->with(TRUE, $this->arrayHasKey('job_item'), []);

    $this->worker->processItem([
      'job' => $job,
      'job_item' => $job_item,
      'q' => ['Hello'],
      'keys_sequence' => ['key1'],
      'documents' => ['file_key' => $document],
    ]);
  }

  /**
   * Tests ::processItem with invalid data (silent no-op).
   */
  public function testProcessItemInvalidData(): void {
    $this->batch->expects($this->never())->method('translateOperation');
    $this->batch->expects($this->never())->method('finishedOperation');
    $this->worker->processItem([]);
  }

  /**
   * Tests ::processItem with missing required keys (silent no-op).
   */
  public function testProcessItemMissingKeys(): void {
    $job = $this->createMock('Drupal\tmgmt\Entity\Job');
    $job_item = $this->createMock('Drupal\tmgmt\Entity\JobItem');

    $this->batch->expects($this->never())->method('translateOperation');
    $this->batch->expects($this->never())->method('finishedOperation');

    // Missing 'q' key.
    $this->worker->processItem([
      'job' => $job,
      'job_item' => $job_item,
      'keys_sequence' => ['key1'],
    ]);
  }

  /**
   * Tests that the real constructor wires up the injected services.
   */
  public function testConstructor(): void {
    $instance = new DeeplTranslateWorker(
      [],
      'deepl_translate_worker',
      ['provider' => 'tmgmt_deepl'],
      $this->createMock(DeeplTranslatorBatchInterface::class),
    );
    $this->assertSame('deepl_translate_worker', $instance->getPluginId());
  }

  /**
   * Tests create() wires services from the passed container, not globals.
   */
  public function testCreateInjectsServicesFromPassedContainer(): void {
    $batch = $this->createMock(DeeplTranslatorBatchInterface::class);

    $global_container = new ContainerBuilder();
    \Drupal::setContainer($global_container);

    $create_container = new ContainerBuilder();
    $create_container->set('tmgmt_deepl.batch', $batch);

    $instance = DeeplTranslateWorker::create($create_container, [], 'deepl_translate_worker', []);

    $this->assertSame('deepl_translate_worker', $instance->getPluginId());
  }

}
