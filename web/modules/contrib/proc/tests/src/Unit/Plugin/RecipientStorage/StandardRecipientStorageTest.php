<?php
// phpcs:ignoreFile

namespace Drupal\Tests\proc\Unit\Plugin\RecipientStorage;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\proc\Plugin\RecipientStorage\StandardRecipientStorage;
use Drupal\proc\ProcInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests the standard (database-backed) recipient storage plugin.
 *
 * @coversDefaultClass \Drupal\proc\Plugin\RecipientStorage\StandardRecipientStorage
 *
 * @group proc
 * @SuppressWarnings(PHPMD)
 */
class StandardRecipientStorageTest extends TestCase {

  protected StandardRecipientStorage $plugin;

  protected function setUp(): void {
    $this->plugin = new StandardRecipientStorage(
      [],
      'proc_standard_recipient_storage',
      ['description' => 'Standard recipient storage (database)']
    );
  }

  /**
   * @covers ::getRecipients
   * @covers ::getRecipientIds
   * @covers ::isRecipient
   */
  public function testReadsFromField(): void {
    $value = [['target_id' => 5], ['target_id' => 7]];
    $list = $this->createMock(FieldItemListInterface::class);
    $list->method('getValue')->willReturn($value);

    $proc = $this->createMock(ProcInterface::class);
    $proc->method('hasField')->with('field_recipients_set')->willReturn(TRUE);
    $proc->method('get')->with('field_recipients_set')->willReturn($list);

    $this->assertSame($value, $this->plugin->getRecipients($proc));
    $this->assertSame([5, 7], $this->plugin->getRecipientIds($proc));
    $this->assertTrue($this->plugin->isRecipient($proc, 5));
    $this->assertFalse($this->plugin->isRecipient($proc, 9));
  }

  /**
   * @covers ::getRecipients
   */
  public function testReturnsEmptyWhenFieldMissing(): void {
    $proc = $this->createMock(ProcInterface::class);
    $proc->method('hasField')->with('field_recipients_set')->willReturn(FALSE);
    $this->assertSame([], $this->plugin->getRecipients($proc));
  }

  /**
   * @covers ::setRecipients
   */
  public function testSetNormalizesAndWritesField(): void {
    $proc = $this->createMock(ProcInterface::class);
    $proc->method('hasField')->with('field_recipients_set')->willReturn(TRUE);
    // Both a list of IDs and a list of target_id items normalize identically.
    $proc->expects($this->once())
      ->method('set')
      ->with('field_recipients_set', [['target_id' => 3], ['target_id' => 4]]);

    $this->plugin->setRecipients($proc, [3, ['target_id' => 4]]);
  }

  /**
   * @covers ::getWishedRecipients
   * @covers ::getWishedRecipientIds
   */
  public function testReadsWishedFromField(): void {
    $value = [['target_id' => 5], ['target_id' => 7]];
    $list = $this->createMock(FieldItemListInterface::class);
    $list->method('getValue')->willReturn($value);

    $proc = $this->createMock(ProcInterface::class);
    $proc->method('hasField')->with('field_wished_recipients_set')->willReturn(TRUE);
    $proc->method('get')->with('field_wished_recipients_set')->willReturn($list);

    $this->assertSame($value, $this->plugin->getWishedRecipients($proc));
    $this->assertSame([5, 7], $this->plugin->getWishedRecipientIds($proc));
  }

  /**
   * @covers ::getWishedRecipients
   */
  public function testReturnsEmptyWishedWhenFieldMissing(): void {
    $proc = $this->createMock(ProcInterface::class);
    $proc->method('hasField')->with('field_wished_recipients_set')->willReturn(FALSE);
    $this->assertSame([], $this->plugin->getWishedRecipients($proc));
  }

  /**
   * @covers ::setWishedRecipients
   */
  public function testSetWishedNormalizesAndWritesField(): void {
    $proc = $this->createMock(ProcInterface::class);
    $proc->method('hasField')->with('field_wished_recipients_set')->willReturn(TRUE);
    // Both a list of IDs and a list of target_id items normalize identically.
    $proc->expects($this->once())
      ->method('set')
      ->with('field_wished_recipients_set', [['target_id' => 3], ['target_id' => 4]]);

    $this->plugin->setWishedRecipients($proc, [3, ['target_id' => 4]]);
  }

}
