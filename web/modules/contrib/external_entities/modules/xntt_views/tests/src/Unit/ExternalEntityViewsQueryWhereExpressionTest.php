<?php

declare(strict_types=1);

namespace Drupal\Tests\xntt_views\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\xntt_views\Plugin\views\query\ExternalEntityViewsQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests placeholder() and addWhereExpression() of ExternalEntityViewsQuery.
 *
 * Core views argument handlers (contextual filters such as "Raw value from
 * URL") call $query->placeholder() and $query->addWhereExpression() on every
 * views query plugin, assuming the SQL implementation. These are regression
 * tests for https://www.drupal.org/project/external_entities/issues/3590257
 * where these calls caused a fatal "Call to undefined method" error.
 */
#[CoversClass(ExternalEntityViewsQuery::class)]
#[Group('xntt_views')]
#[Group('external_entities')]
class ExternalEntityViewsQueryWhereExpressionTest extends UnitTestCase {

  /**
   * The query plugin under test.
   *
   * @var \Drupal\xntt_views\Plugin\views\query\ExternalEntityViewsQuery
   */
  protected $queryPlugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->buildQueryPlugin(
      $this->createStub(LoggerInterface::class),
      $this->createStub(MessengerInterface::class),
    );
  }

  /**
   * Builds the query plugin and container with the given logger and messenger.
   */
  protected function buildQueryPlugin(LoggerInterface $logger, MessengerInterface $messenger): void {
    $this->queryPlugin = new ExternalEntityViewsQuery(
      [],
      'xntt_query',
      ['title' => 'External Entities Query'],
      $this->createStub(EntityTypeManagerInterface::class),
      $logger,
    );

    // The unsupported-expression fallback notifies the user through the
    // messenger and string translation services of the global container.
    $container = new ContainerBuilder();
    $container->set('messenger', $messenger);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Returns the WHERE structure of the query plugin.
   *
   * @return array
   *   The internal "where" array of the views query plugin.
   */
  protected function getWhere(): array {
    $ref = new \ReflectionProperty($this->queryPlugin, 'where');
    return $ref->getValue($this->queryPlugin);
  }

  /**
   * Tests that placeholder() exists and generates unique placeholder names.
   *
   * @legacy-covers ::placeholder
   */
  public function testPlaceholderGeneratesUniqueNames(): void {
    $this->assertSame(':views', $this->queryPlugin->placeholder());
    $this->assertSame(':views1', $this->queryPlugin->placeholder());
    $this->assertSame(':views2', $this->queryPlugin->placeholder());
    // A different base restarts unsuffixed, like the core SQL plugin.
    $this->assertSame(':base', $this->queryPlugin->placeholder('base'));
    $this->assertSame(':base1', $this->queryPlugin->placeholder('base'));
  }

  /**
   * Provides expressions emitted by core handlers and expected conditions.
   *
   * The expressions reproduce what core argument handlers build: the table
   * alias is empty for external entity queries (ensureTable() returns ''),
   * hence the leading dot.
   *
   * @return array
   *   Test scenarios.
   */
  public static function provideSupportedExpressions(): array {
    return [
      // StringArgument::query() with a single value.
      'string equals' => [
        '.title = :views',
        [':views' => 'foo'],
        ['field' => 'title', 'value' => 'foo', 'operator' => '='],
      ],
      // NumericArgument::query() with a single value.
      'numeric equals' => [
        '.field_count = :views',
        [':views' => 42],
        ['field' => 'field_count', 'value' => 42, 'operator' => '='],
      ],
      // NumericArgument::query() with multiple values ("1+2+3").
      'numeric in' => [
        '.id IN(:views[])',
        [':views[]' => [1, 2, 3]],
        ['field' => 'id', 'value' => [1, 2, 3], 'operator' => 'IN'],
      ],
      // StringArgument::query() with multiple excluded values.
      'string not in' => [
        '.title NOT IN(:views[])',
        [':views[]' => ['a', 'b']],
        ['field' => 'title', 'value' => ['a', 'b'], 'operator' => 'NOT IN'],
      ],
      // "<>" must be normalized to the entity query "!=" operator.
      'not equals normalization' => [
        '.title <> :views',
        [':views' => 'foo'],
        ['field' => 'title', 'value' => 'foo', 'operator' => '!='],
      ],
      'not equals' => [
        '.title != :views',
        [':views' => 'foo'],
        ['field' => 'title', 'value' => 'foo', 'operator' => '!='],
      ],
      // A qualified field name keeps only the field part.
      'qualified field name' => [
        'xntt_table.title = :views',
        [':views' => 'foo'],
        ['field' => 'xntt_table.title', 'value' => 'foo', 'operator' => '='],
      ],
      'greater than' => [
        '.weight > :views',
        [':views' => 5],
        ['field' => 'weight', 'value' => 5, 'operator' => '>'],
      ],
    ];
  }

  /**
   * Tests that supported SQL snippets are converted to field conditions.
   *
   * @param string $snippet
   *   The SQL snippet as emitted by a core views handler.
   * @param array $args
   *   The placeholder arguments.
   * @param array $expected_condition
   *   The expected condition triplet.
   *
   * @legacy-covers ::addWhereExpression
   */
  #[DataProvider('provideSupportedExpressions')]
  public function testAddWhereExpressionSupported(string $snippet, array $args, array $expected_condition): void {
    $this->queryPlugin->addWhereExpression(0, $snippet, $args);
    $where = $this->getWhere();
    $this->assertArrayHasKey(0, $where, 'A default condition group is created.');
    $this->assertCount(1, $where[0]['conditions']);
    $this->assertEquals($expected_condition, $where[0]['conditions'][0]);
  }

  /**
   * Tests the "OR field IS NULL" suffix used by excluding arguments.
   *
   * Core StringArgument and NumericArgument append " OR <field> IS NULL"
   * when their "Exclude" option is enabled.
   *
   * @legacy-covers ::addWhereExpression
   */
  public function testAddWhereExpressionWithNullCheck(): void {
    $this->queryPlugin->addWhereExpression(
      0,
      '.title != :views OR .title IS NULL',
      [':views' => 'foo']
    );
    $where = $this->getWhere();
    // The condition pair must live in a dedicated OR group.
    $this->assertCount(1, $where);
    $group = reset($where);
    $this->assertSame('OR', $group['type']);
    $this->assertEquals(
      [
        ['field' => 'title', 'value' => 'foo', 'operator' => '!='],
        ['field' => 'title', 'value' => NULL, 'operator' => 'IS NULL'],
      ],
      $group['conditions']
    );
  }

  /**
   * Tests that unsupported expressions are skipped with a warning, not fatal.
   *
   * @legacy-covers ::addWhereExpression
   */
  public function testAddWhereExpressionUnsupported(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('warning');
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())
      ->method('addWarning');
    $this->buildQueryPlugin($logger, $messenger);
    $this->queryPlugin->addWhereExpression(
      0,
      "CONCAT_WS(' ', .title, ' ', .name) LIKE :views",
      [':views' => '%foo%']
    );
    $this->assertSame([], $this->getWhere(), 'No condition is added for an unsupported expression.');
  }

  /**
   * Tests that addWhere() converts "formula" conditions instead of mangling.
   *
   * Formula-based core handlers (e.g. the date argument) call
   * addWhere($group, $sql_snippet, $placeholders, 'formula'). Without
   * dedicated handling the whole snippet would be used as a field name and
   * corrupt the entity query.
   *
   * @legacy-covers ::addWhere
   */
  public function testAddWhereFormulaOperatorIsConverted(): void {
    $this->queryPlugin->addWhere(
      0,
      '.field_mtime = :views',
      [':views' => 1764763200],
      'formula'
    );
    $where = $this->getWhere();
    $this->assertEquals(
      ['field' => 'field_mtime', 'value' => 1764763200, 'operator' => '='],
      $where[0]['conditions'][0]
    );
  }

  /**
   * Tests the exact call sequence of a "Raw value from URL" string argument.
   *
   * This reproduces the fatal error scenario from issue #3590257:
   * HandlerBase::placeholder() calls $query->placeholder() and
   * StringArgument::query() then calls $query->addWhereExpression().
   *
   * @legacy-covers ::placeholder
   * @legacy-covers ::addWhereExpression
   */
  public function testContextualFilterCallSequence(): void {
    // HandlerBase::placeholder().
    $placeholder = $this->queryPlugin->placeholder('xntt_table_title');
    // StringArgument::query() single-value branch, with an empty table alias
    // as returned by ExternalEntityViewsQuery::ensureTable().
    $this->queryPlugin->addWhereExpression(
      0,
      ".title = $placeholder",
      [$placeholder => 'page-title']
    );
    $where = $this->getWhere();
    $this->assertEquals(
      ['field' => 'title', 'value' => 'page-title', 'operator' => '='],
      $where[0]['conditions'][0]
    );
  }

}
