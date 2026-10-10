<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_patterns\ComponentPluginManager;
use Drupal\ui_patterns_test\SynthesizingComponentPluginManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Other modules decorating plugin.manager.sdc do not get in our way.
 *
 * @internal
 */
#[CoversClass(ComponentPluginManager::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class ComponentPluginManagerDecoratorsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ui_patterns',
    'ui_patterns_test',
  ];

  private const string INNER_ID = 'ui_patterns_test_sdc_inner:synthetic';

  private const string OUTER_ID = 'ui_patterns_test_sdc_outer:synthetic';

  /**
   * Components added by an inner decorator are annotated like the ones on disk.
   */
  public function testInnerDecorator(): void {
    $this->enableModules(['ui_patterns_test_sdc_inner']);
    self::assertInstanceOf(ComponentPluginManager::class, $this->container->get('plugin.manager.sdc'));
    $this->assertAnnotated(self::INNER_ID);
    $this->assertAnnotated('ui_patterns_test:test-component');
  }

  /**
   * An outer decorator does not break our service, and its components stay out.
   */
  public function testOuterDecorator(): void {
    $this->enableModules(['ui_patterns_test_sdc_inner', 'ui_patterns_test_sdc_outer']);
    $sdc = $this->container->get('plugin.manager.sdc');
    self::assertInstanceOf(SynthesizingComponentPluginManager::class, $sdc);
    self::assertSame(self::OUTER_ID, $sdc->syntheticId());
    $this->assertAnnotated(self::INNER_ID);
    $this->assertAnnotated('ui_patterns_test:test-component');
    self::assertArrayNotHasKey(self::OUTER_ID, $this->manager()->getNegotiatedSortedDefinitions());
    self::assertSame('synthetic', $sdc->find(self::OUTER_ID)->machineName);
    self::assertArrayHasKey(self::INNER_ID, $sdc->getDefinitions());
  }

  /**
   * Our own service, whatever plugin.manager.sdc is.
   */
  private function manager(): ComponentPluginManager {
    return $this->container->get(ComponentPluginManager::class);
  }

  /**
   * Asserts our service lists a component and builds it annotated.
   */
  private function assertAnnotated(string $component_id): void {
    self::assertArrayHasKey($component_id, $this->manager()->getNegotiatedSortedDefinitions());
    $component = $this->manager()->find($component_id);
    foreach ($component->metadata->schema['properties'] as $prop_id => $prop) {
      self::assertArrayHasKey('ui_patterns', $prop, "{$component_id}: {$prop_id}");
    }
    foreach ($component->metadata->slots as $slot_id => $slot) {
      self::assertArrayHasKey('ui_patterns', $slot, "{$component_id}: {$slot_id}");
    }
  }

}
