<?php
// phpcs:ignoreFile

namespace Drupal\Tests\proc_recipients_storage_acme\Unit;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\proc\ProcInterface;
use Drupal\proc_recipients_storage_acme\Plugin\RecipientStorage\AcmeRecipientStorage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests the Acme recipient storage plugin.
 *
 * @coversDefaultClass \Drupal\proc_recipients_storage_acme\Plugin\RecipientStorage\AcmeRecipientStorage
 *
 * @group proc
 * @SuppressWarnings(PHPMD)
 */
class AcmeRecipientStorageTest extends TestCase {

  // Group join timestamps (mirrors AcmeGroupMembership):
  // user 1 => 2022-01-01, user 2 => 2024-01-01, user 3 => 2026-01-01.
  const TS_2022 = 1640995200;
  const TS_2024 = 1704067200;
  const TS_2026 = 1767225600;

  protected AcmeRecipientStorage $plugin;

  protected function setUp(): void {
    $this->plugin = new AcmeRecipientStorage(
      [],
      'proc_recipients_storage_acme',
      ['description' => 'Acme'],
      $this->createMock(LoggerInterface::class)
    );
  }

  /**
   * Builds a mocked cipher proc reporting the given encryption timestamp.
   */
  protected function mockProcWithEncryptionTs($ts): ProcInterface {
    $proc = $this->createMock(ProcInterface::class);
    $proc->method('hasField')->with('meta')->willReturn(TRUE);
    $meta = $ts === NULL ? [] : [['generation_timestamp' => $ts]];
    $list = $this->createMock(FieldItemListInterface::class);
    $list->method('getValue')->willReturn($meta);
    $proc->method('get')->with('meta')->willReturn($list);
    return $proc;
  }

  /**
   * @covers ::getRecipients
   * @covers ::getEncryptionTimestamp
   */
  public function testFiltersOutMembersJoinedAfterEncryption(): void {
    // Encrypted mid-2025: only users 1 and 2 had joined by then.
    $proc = $this->mockProcWithEncryptionTs(self::TS_2024 + 100);
    $this->assertSame(
      [['target_id' => 1], ['target_id' => 2]],
      $this->plugin->getRecipients($proc)
    );
    $this->assertSame([1, 2], $this->plugin->getRecipientIds($proc));
    $this->assertTrue($this->plugin->isRecipient($proc, 1));
    $this->assertFalse($this->plugin->isRecipient($proc, 3));
  }

  /**
   * @covers ::getRecipients
   */
  public function testIncludesAllMembersWhenEncryptedAfterAllJoins(): void {
    $proc = $this->mockProcWithEncryptionTs(self::TS_2026 + 1);
    $this->assertSame(
      [['target_id' => 1], ['target_id' => 2], ['target_id' => 3]],
      $this->plugin->getRecipients($proc)
    );
  }

  /**
   * @covers ::getRecipients
   */
  public function testBoundaryIsInclusive(): void {
    // A member who joined exactly at the encryption instant is a recipient.
    $proc = $this->mockProcWithEncryptionTs(self::TS_2024);
    $this->assertSame([1, 2], $this->plugin->getRecipientIds($proc));
  }

  /**
   * @covers ::getRecipients
   * @covers ::getEncryptionTimestamp
   */
  public function testReturnsFullMembershipWhenNoTimestamp(): void {
    $proc = $this->mockProcWithEncryptionTs(NULL);
    $this->assertSame([1, 2, 3], $this->plugin->getRecipientIds($proc));
  }

  /**
   * @covers ::setRecipients
   */
  public function testSetRecipientsKeepsFieldEmpty(): void {
    $proc = $this->createMock(ProcInterface::class);
    $proc->method('hasField')->with('field_recipients_set')->willReturn(TRUE);
    $proc->expects($this->once())->method('set')->with('field_recipients_set', []);
    $this->plugin->setRecipients($proc, [['target_id' => 9]]);
  }

  /**
   * @covers ::getWishedRecipients
   */
  public function testGetWishedReadsFromDatabaseField(): void {
    $value = [['target_id' => 1], ['target_id' => 2], ['target_id' => 3]];
    $list = $this->createMock(FieldItemListInterface::class);
    $list->method('getValue')->willReturn($value);

    $proc = $this->createMock(ProcInterface::class);
    $proc->method('hasField')->with('field_wished_recipients_set')->willReturn(TRUE);
    $proc->method('get')->with('field_wished_recipients_set')->willReturn($list);

    $this->assertSame($value, $this->plugin->getWishedRecipients($proc));
  }

  /**
   * @covers ::setWishedRecipients
   */
  public function testSetWishedNormalizesAndWritesDatabaseField(): void {
    $proc = $this->createMock(ProcInterface::class);
    $proc->method('hasField')->with('field_wished_recipients_set')->willReturn(TRUE);
    $proc->expects($this->once())
      ->method('set')
      ->with('field_wished_recipients_set', [['target_id' => 3], ['target_id' => 4]]);

    $this->plugin->setWishedRecipients($proc, [3, ['target_id' => 4]]);
  }

}
