<?php

declare(strict_types=1);

namespace Drupal\Tests\xntt_views\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\xntt_views\Plugin\views\argument\ExternalEntityDate;
use Drupal\xntt_views\Plugin\views\query\ExternalEntityViewsQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the xntt_date views argument handler.
 *
 * Part of the regression coverage for issue #3590257: date contextual
 * filters must produce a timestamp range condition instead of an SQL
 * formula that cannot be mapped to an external entity query.
 */
#[CoversClass(ExternalEntityDate::class)]
#[Group('xntt_views')]
#[Group('external_entities')]
class ExternalEntityDateArgumentTest extends UnitTestCase {

  /**
   * The argument handler under test.
   *
   * @var \Drupal\xntt_views\Plugin\views\argument\ExternalEntityDate
   */
  protected $handler;

  /**
   * The mocked views query plugin.
   *
   * @var \Drupal\xntt_views\Plugin\views\query\ExternalEntityViewsQuery|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $queryPlugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->handler = new ExternalEntityDate(
      [],
      'xntt_date',
      ['id' => 'xntt_date'],
      $this->createStub(RouteMatchInterface::class),
      $this->createStub(DateFormatterInterface::class),
      $this->createStub(TimeInterface::class),
    );
    $this->queryPlugin = $this->createMock(ExternalEntityViewsQuery::class);
    $this->handler->query = $this->queryPlugin;
    $this->handler->realField = 'field_mtime';
  }

  /**
   * Tests that a day argument becomes a timestamp range condition.
   *
   * @legacy-covers ::query
   */
  public function testDayArgumentBecomesTimestampRange(): void {
    // Unit tests run with the UTC timezone.
    $start = (new \DateTime('2026-06-03', new \DateTimeZone(date_default_timezone_get())))->getTimestamp();
    $end = (new \DateTime('2026-06-04', new \DateTimeZone(date_default_timezone_get())))->getTimestamp();

    $calls = [];
    $this->queryPlugin->expects($this->exactly(2))
      ->method('addWhere')
      ->willReturnCallback(function (...$args) use (&$calls) {
        $calls[] = $args;
      });

    $this->handler->argument = '2026-06-03';
    $this->handler->query();

    $this->assertSame([0, 'field_mtime', $start, '>='], $calls[0]);
    $this->assertSame([0, 'field_mtime', $end, '<'], $calls[1]);
  }

  /**
   * Tests that an invalid date argument fails closed (no result).
   *
   * @legacy-covers ::query
   */
  public function testInvalidArgumentFailsClosed(): void {
    $this->queryPlugin->expects($this->once())
      ->method('addWhere')
      ->with(0, 'field_mtime', PHP_INT_MAX, '>');

    $this->handler->argument = 'definitely-not-a-date';
    $this->handler->query();
  }

}
