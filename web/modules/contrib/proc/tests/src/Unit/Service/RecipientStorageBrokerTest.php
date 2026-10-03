<?php
// phpcs:ignoreFile

namespace Drupal\Tests\proc\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\proc\ProcInterface;
use Drupal\proc\RecipientStorageInterface;
use Drupal\proc\RecipientStoragePluginManager;
use Drupal\proc\Service\RecipientStorageBroker;
use Drupal\proc\Service\RecipientStorageBrokerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests the recipient storage broker resolution and delegation.
 *
 * @coversDefaultClass \Drupal\proc\Service\RecipientStorageBroker
 *
 * @group proc
 * @SuppressWarnings(PHPMD)
 */
class RecipientStorageBrokerTest extends TestCase {

  protected $pluginManager;
  protected $entityTypeManager;
  protected $logger;
  protected RecipientStorageBroker $broker;

  protected function setUp(): void {
    $this->pluginManager = $this->createMock(RecipientStoragePluginManager::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->broker = new RecipientStorageBroker(
      $this->pluginManager,
      $this->entityTypeManager,
      $this->logger
    );
  }

  /**
   * Builds a mocked cipher proc with the given meta array.
   */
  protected function mockProc(array $meta): ProcInterface {
    $proc = $this->createMock(ProcInterface::class);
    $proc->method('hasField')->with('meta')->willReturn(TRUE);
    $meta_list = $this->createMock(FieldItemListInterface::class);
    $meta_list->method('getValue')->willReturn($meta ? [$meta] : []);
    $proc->method('get')->with('meta')->willReturn($meta_list);
    return $proc;
  }

  /**
   * @covers ::resolveForProc
   */
  public function testResolvesDefaultWhenNoBinding(): void {
    $default = $this->createMock(RecipientStorageInterface::class);
    $this->pluginManager->expects($this->once())
      ->method('createInstance')
      ->with(RecipientStorageBrokerInterface::DEFAULT_PLUGIN_ID)
      ->willReturn($default);

    $proc = $this->mockProc([]);
    $this->assertSame($default, $this->broker->resolveForProc($proc));
  }

  /**
   * @covers ::resolveForProc
   */
  public function testResolvesBoundPlugin(): void {
    $this->pluginManager->method('hasDefinition')
      ->with('my_plugin')->willReturn(TRUE);
    $plugin = $this->createMock(RecipientStorageInterface::class);
    $this->pluginManager->expects($this->once())
      ->method('createInstance')
      ->with('my_plugin')
      ->willReturn($plugin);

    $proc = $this->mockProc([RecipientStorageBrokerInterface::META_KEY => 'my_plugin']);
    $this->assertSame($plugin, $this->broker->resolveForProc($proc));
  }

  /**
   * @covers ::resolveForProc
   */
  public function testFallsBackWhenBoundPluginUnknown(): void {
    $this->pluginManager->method('hasDefinition')
      ->with('ghost')->willReturn(FALSE);
    $default = $this->createMock(RecipientStorageInterface::class);
    $this->pluginManager->expects($this->once())
      ->method('createInstance')
      ->with(RecipientStorageBrokerInterface::DEFAULT_PLUGIN_ID)
      ->willReturn($default);
    $this->logger->expects($this->once())->method('warning');

    $proc = $this->mockProc([RecipientStorageBrokerInterface::META_KEY => 'ghost']);
    $this->assertSame($default, $this->broker->resolveForProc($proc));
  }

  /**
   * @covers ::isRecipient
   * @covers ::getRecipientIds
   */
  public function testDelegatesToResolvedPlugin(): void {
    $plugin = $this->createMock(RecipientStorageInterface::class);
    $plugin->method('getRecipientIds')->willReturn([3, 5]);
    $plugin->method('isRecipient')->willReturnCallback(
      fn (ProcInterface $p, int $uid) => in_array($uid, [3, 5], TRUE)
    );
    $this->pluginManager->method('createInstance')->willReturn($plugin);

    $proc = $this->mockProc([]);
    $this->assertSame([3, 5], $this->broker->getRecipientIds($proc));
    $this->assertTrue($this->broker->isRecipient($proc, 3));
    $this->assertFalse($this->broker->isRecipient($proc, 9));
  }

  /**
   * @covers ::getWishedRecipients
   * @covers ::getWishedRecipientIds
   * @covers ::setWishedRecipients
   */
  public function testDelegatesWishedToResolvedPlugin(): void {
    $plugin = $this->createMock(RecipientStorageInterface::class);
    $plugin->method('getWishedRecipients')->willReturn([['target_id' => 8]]);
    $plugin->method('getWishedRecipientIds')->willReturn([8]);
    $plugin->expects($this->once())
      ->method('setWishedRecipients')
      ->with($this->anything(), [['target_id' => 8]]);
    $this->pluginManager->method('createInstance')->willReturn($plugin);

    $proc = $this->mockProc([]);
    $this->assertSame([['target_id' => 8]], $this->broker->getWishedRecipients($proc));
    $this->assertSame([8], $this->broker->getWishedRecipientIds($proc));
    $this->broker->setWishedRecipients($proc, [['target_id' => 8]]);
  }

  /**
   * @covers ::getRecipientsForProcIds
   */
  public function testBatchGetRecipientsForProcIds(): void {
    $plugin = $this->createMock(RecipientStorageInterface::class);
    $plugin->method('getRecipients')->willReturn([['target_id' => 8]]);
    $this->pluginManager->method('createInstance')->willReturn($plugin);

    $proc = $this->mockProc([]);
    $proc->method('id')->willReturn(42);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->with([42])->willReturn([42 => $proc]);
    $this->entityTypeManager->method('getStorage')->with('proc')->willReturn($storage);

    $result = $this->broker->getRecipientsForProcIds([42]);
    $this->assertArrayHasKey(42, $result);
    $this->assertSame([['target_id' => 8]], $result[42]);
  }

}
