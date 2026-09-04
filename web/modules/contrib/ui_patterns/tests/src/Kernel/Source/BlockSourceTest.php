<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Source;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\BlockSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test BlockSource.
 *
 * @internal
 */
#[CoversClass(BlockSource::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class BlockSourceTest extends SourcePluginsTestBase {

  use UserCreationTrait;

  /**
   * Test BlockSource Plugin.
   */
  public function testPlugin(): void {
    $this->runSourcePluginTests('block_');
    $this->runSourcePluginTests('block_', __DIR__ . '/../../../fixtures/block_tests.yml');
  }

  /**
   * A block the user may not see is not built, and its cacheability is kept.
   */
  public function testDeniedBlockIsNotBuilt(): void {
    $this->setUpCurrentUser();
    $source = $this->sourcePluginManager()->getSource('slot', [], [
      'source_id' => 'block',
      'source' => ['plugin_id' => 'ui_patterns_test_denied_block'],
    ]);
    self::assertInstanceOf(BlockSource::class, $source);
    self::assertSame([], $source->getPropValue());
    self::assertContains('ui_patterns_test:denied', $source->getCacheTags());
    self::assertContains('user.permissions', $source->getCacheContexts());
  }

  /**
   * The settings of a choice hold the block ID and nothing else.
   *
   * The block own defaults are merged when the plugin is instantiated, so
   * copying them here would only freeze them in the stored configuration.
   */
  public function testGetChoiceSettings(): void {
    $settings = $this->blockSource()->getChoiceSettings('ui_patterns_test_block');

    self::assertSame(['plugin_id' => 'ui_patterns_test_block'], $settings);
  }

  /**
   * A choice renders from its own settings, with no visit to the block form.
   */
  public function testGetChoiceSettingsBuildTheSource(): void {
    $this->setUpCurrentUser();
    $settings = $this->blockSource()->getChoiceSettings('ui_patterns_test_block');

    $build = $this->blockSource($settings)->getPropValue();

    self::assertSame('no message set', $build['#children']);
  }

  /**
   * The settings of a choice are read back as that choice, derivatives too.
   */
  public function testGetChoiceIsReadBack(): void {
    $source = $this->blockSource();

    foreach (['ui_patterns_test_block', 'system_menu_block:main'] as $choice_id) {
      $settings = $source->getChoiceSettings($choice_id);

      self::assertSame($choice_id, $source->getChoice($settings), $choice_id);
      self::assertInstanceOf(BlockPluginInterface::class, $this->blockSource($settings)->getBlock($choice_id), $choice_id);
    }
  }

  /**
   * A block whose required context has no value is not built.
   *
   * A stored component outlives the context it was placed in, and the block
   * list cannot filter what is already stored.
   */
  public function testBlockWithMissingContextIsNotBuilt(): void {
    $this->setUpCurrentUser();
    $source = $this->blockSource(['plugin_id' => 'ui_patterns_test_context_block']);

    self::assertSame([], $source->getPropValue());
  }

  /**
   * Get a block source, optionally with settings.
   *
   * @param array $settings
   *   The source settings.
   *
   * @return \Drupal\ui_patterns\Plugin\UiPatterns\Source\BlockSource
   *   The source plugin.
   */
  private function blockSource(array $settings = []): BlockSource {
    $source = $this->sourcePluginManager()->getSource('slot', [], [
      'source_id' => 'block',
      'source' => $settings,
    ]);
    self::assertInstanceOf(BlockSource::class, $source);

    return $source;
  }

}
