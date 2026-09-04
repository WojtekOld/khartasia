<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\PropType\StringPropType;
use Drupal\ui_patterns\PropTypePluginManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test PropTypePluginManager.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class PropTypePluginManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ui_patterns',
    'ui_patterns_test',
  ];

  /**
   * Test callback.
   */
  public function testGuessFromSchema(): void {
    $prop_type_plugin_manager = \Drupal::service(PropTypePluginManager::class);
    $plugin_type = $prop_type_plugin_manager->guessFromSchema(['type' => 'string']);
    self::assertInstanceOf(StringPropType::class, $plugin_type);
  }

}
