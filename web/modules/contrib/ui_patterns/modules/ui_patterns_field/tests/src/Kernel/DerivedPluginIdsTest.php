<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_field\Kernel;

use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\ui_patterns\SourcePluginManager;
use Drupal\ui_patterns_field\Plugin\UiPatterns\Source\UIPatternsSourceFieldPropertySource;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Pins the ui_patterns_source derivative IDs.
 *
 * Companion of \Drupal\Tests\ui_patterns\Kernel\DerivedPluginIdsTest for the
 * deriver this module adds. These IDs are stored in config and in content
 * (SourceValueItem), so they must never change.
 *
 * @internal
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_field')]
#[RunTestsInSeparateProcesses]
final class DerivedPluginIdsTest extends SourcePluginsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ui_patterns_field',
  ];

  /**
   * One ID per ui_patterns_source field storage: two segments, no bundle.
   */
  public function testDerivedIdsAndMetadata(): void {
    $source_manager = $this->container->get(SourcePluginManager::class);
    $source_manager->clearCachedDefinitions();
    $definitions = $source_manager->getDefinitions();
    $ids = \array_filter(\array_keys($definitions), static fn (string $id): bool => \str_starts_with($id, 'ui_patterns_source:'));
    \sort($ids);
    self::assertSame([
      'ui_patterns_source:node:field_ui_patterns_source',
      'ui_patterns_source:node:field_ui_patterns_source_1',
    ], $ids);

    $definition = $definitions['ui_patterns_source:node:field_ui_patterns_source_1'];
    self::assertSame(UIPatternsSourceFieldPropertySource::class, $definition['class']);
    // The deriver filters on the field type stored in metadata.
    self::assertSame('ui_patterns_source', $definition['metadata']['field']['type']);
    self::assertSame('field_ui_patterns_source_1', $definition['metadata']['field_name']);
  }

}
