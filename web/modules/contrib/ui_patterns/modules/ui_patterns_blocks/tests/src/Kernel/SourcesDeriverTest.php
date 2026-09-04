<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_blocks\Kernel;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\ui_patterns\ComponentPluginManager;
use Drupal\ui_patterns_blocks\Plugin\Block\ComponentBlock;
use Drupal\ui_patterns_blocks\Plugin\Block\EntityComponentBlock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests UI patterns block plugin deriver.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_blocks')]
#[RunTestsInSeparateProcesses]
final class SourcesDeriverTest extends SourcePluginsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ui_patterns',
    'ui_patterns_test',
    'ui_patterns_blocks',
  ];

  /**
   * The block plugin manager.
   */
  protected BlockManagerInterface $blockManager;

  /**
   * The component plugin manager.
   */
  protected ComponentPluginManager $componentManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->blockManager = $this->container->get(BlockManagerInterface::class);
    $this->componentManager = $this->container->get(ComponentPluginManager::class);
  }

  /**
   * Tests creating fields of all types on a content type.
   */
  public function testDerivedPluginPerComponent() {
    $components = $this->componentManager->getNegotiatedSortedDefinitions();

    foreach ($components as $component) {
      $id = (string) $component['id'];
      $block_plugin_id = \sprintf('ui_patterns:%s', $id);
      $block = $this->blockManager->createInstance($block_plugin_id);
      self::assertNotNull($block, "Block for component {$component['id']} is missing");
      self::assertInstanceOf(
        ComponentBlock::class,
        $block,
        \get_class($block) . ' ' . $component['id'] . ' ' . \print_r($this->blockManager->getDefinitions(), TRUE)
      );
      $block_plugin_id = \sprintf('ui_patterns_entity:%s', $id);
      $block = $this->blockManager->createInstance($block_plugin_id);
      self::assertNotNull($block, "Block with entity context for component {$component['id']} is missing");
      self::assertInstanceOf(EntityComponentBlock::class, $block);
      $plugin_definition = $block->getPluginDefinition() ?? [];

      if (!\is_array($plugin_definition)) {
        $plugin_definition = [];
      }
      $context_definitions = $plugin_definition['context_definitions'] ?? [];
      self::assertArrayHasKey('entity', $context_definitions);
    }
  }

}
