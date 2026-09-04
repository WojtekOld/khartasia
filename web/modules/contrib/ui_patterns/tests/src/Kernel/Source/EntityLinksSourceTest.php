<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Source;

use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\node\NodeInterface;
use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\EntityLinksSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test EntityLinksSource.
 *
 * @internal
 */
#[CoversClass(EntityLinksSource::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class EntityLinksSourceTest extends SourcePluginsTestBase {

  use UserCreationTrait;

  /**
   * The cache contexts of the last source run by linkFor().
   */
  private array $lastCacheContexts = [];

  /**
   * Test EntityLinksSource Plugin.
   */
  public function testPlugin(): void {
    // The fixtures expect edit links, which only an editor may get.
    $this->setUpCurrentUser([], ['access content', 'bypass node access']);
    $this->runSourcePluginTests('entity_links_');
  }

  /**
   * A link the user cannot open is empty, and its cacheability is kept.
   */
  public function testInaccessibleLinkIsEmpty(): void {
    $this->setUpCurrentUser([], ['access content']);
    $node = $this->createTestContentNode();
    self::assertSame('', $this->linkFor($node, 'edit-form'));
    self::assertContains('user.permissions', $this->lastCacheContexts);
    self::assertMatchesRegularExpression('/\/node\/\d+$/', $this->linkFor($node, 'canonical'));
  }

  /**
   * The URL of an entity link template, as the source gives it.
   */
  private function linkFor(NodeInterface $node, string $template): string {
    $source = $this->sourcePluginManager()->getSource('prop', [], [
      'source_id' => 'entity_link',
      'source' => ['template' => $template],
    ], ['entity' => EntityContext::fromEntity($node)]);
    self::assertInstanceOf(EntityLinksSource::class, $source);
    $url = $source->getPropValue();
    self::assertIsString($url);
    $this->lastCacheContexts = $source->getCacheContexts();
    return $url;
  }

}
