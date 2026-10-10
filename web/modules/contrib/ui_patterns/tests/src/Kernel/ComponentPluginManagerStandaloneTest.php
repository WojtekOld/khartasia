<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_patterns\ComponentPluginManager;
use Drupal\ui_patterns\DependencyInjection\Compiler\ComponentPluginManagerPass;
use Drupal\ui_patterns_test\ReplacingComponentPluginManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * We work the same when we do not decorate plugin.manager.sdc.
 *
 * @internal
 */
#[CoversClass(ComponentPluginManagerPass::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class ComponentPluginManagerStandaloneTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ui_patterns',
    'ui_patterns_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    // What a module owning the class of plugin.manager.sdc does.
    $container->getDefinition('plugin.manager.sdc')->setClass(ReplacingComponentPluginManager::class);
    $container->setAlias(ReplacingComponentPluginManager::class, 'plugin.manager.sdc')->setPublic(TRUE);
  }

  /**
   * A module replacing the class of plugin.manager.sdc keeps its own service.
   */
  public function testReplacedClass(): void {
    self::assertSame(ReplacingComponentPluginManager::class, \get_class($this->container->get('plugin.manager.sdc')));
    self::assertSame(ReplacingComponentPluginManager::class, \get_class($this->container->get(ReplacingComponentPluginManager::class)));
    $this->assertStandalone();
  }

  /**
   * Asserts plugin.manager.sdc is left as it is, and our service annotated.
   */
  private function assertStandalone(): void {
    // Its props are typed by `ui-patterns://` only: core validates them here.
    $definition = $this->container->get('plugin.manager.sdc')->getDefinition('ui_patterns_test:test-component');
    self::assertArrayNotHasKey('attributes', $definition['props']['properties']);
    self::assertSame([], \array_filter($definition['props']['properties'], static fn (array $prop): bool => isset($prop['ui_patterns'])));

    $manager = $this->container->get(ComponentPluginManager::class);
    self::assertInstanceOf(ComponentPluginManager::class, $manager);
    self::assertArrayHasKey('ui_patterns_test:test-component', $manager->getNegotiatedSortedDefinitions());
    $props = $manager->find('ui_patterns_test:test-component')->metadata->schema['properties'];
    self::assertSame('attributes', \array_key_first($props));
    self::assertSame('string', $props['string']['ui_patterns']['type_definition']->getPluginId());
  }

}
