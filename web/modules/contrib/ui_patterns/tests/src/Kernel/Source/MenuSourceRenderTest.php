<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Source;

use Drupal\Core\Render\RendererInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\MenuSource;
use Drupal\ui_patterns\SourcePluginBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Full-pipeline render test for MenuSource feeding a links prop.
 *
 * MenuSource pre-normalizes its items, then the render pipeline
 * normalizes again: titles must come out escaped exactly once (#3610847)
 * and dangerous markup must never reach the DOM live.
 *
 * @internal
 */
#[CoversClass(MenuSource::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class MenuSourceRenderTest extends SourcePluginsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['link', 'menu_link_content', 'menu_ui'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('menu_link_content');
    MenuLinkContent::create([
      'menu_name' => 'main',
      'title' => 'Tom & Jerry',
      'link' => [['uri' => 'internal:/example-path']],
      'weight' => 1,
    ])->save();
    MenuLinkContent::create([
      'menu_name' => 'main',
      'title' => '<script>alert(1)</script>',
      'link' => [['uri' => 'internal:/example-path']],
      'weight' => 2,
    ])->save();
  }

  /**
   * Menu titles render escaped exactly once, with no live element.
   */
  public function testMenuTitleIsEscapedOnce(): void {
    $build = [
      '#type' => 'component',
      '#component' => 'ui_patterns_test:test-component',
      '#props' => [
        'links' => $this->menuLinks(),
      ],
    ];
    $html = (string) \Drupal::service(RendererInterface::class)->renderInIsolation($build);

    self::assertStringContainsString('Tom &amp; Jerry', $html);
    self::assertStringNotContainsString('Tom &amp;amp; Jerry', $html, 'Menu title is double-escaped.');
    self::assertStringNotContainsString('<script>alert(1)</script>', $html);
    self::assertStringContainsString('&lt;script&gt;', $html);
  }

  /**
   * Resolves the links prop value from the menu source, as a builder would.
   */
  private function menuLinks(): array {
    $component = $this->componentManager()->getDefinition('ui_patterns_test:test-component');
    $configuration = SourcePluginBase::buildConfiguration(
      'links',
      $component['props']['properties']['links'],
      ['source_id' => 'menu', 'source' => ['menu' => 'main', 'level' => '1', 'depth' => '0']],
      []
    );
    $plugin = $this->sourcePluginManager()->createInstance('menu', $configuration);
    self::assertInstanceOf(SourcePluginBase::class, $plugin);
    return $plugin->getValue($plugin->getPropDefinition()['ui_patterns']['type_definition']);
  }

}
