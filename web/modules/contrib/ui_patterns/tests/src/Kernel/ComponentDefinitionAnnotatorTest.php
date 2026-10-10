<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_patterns\ComponentDefinitionAnnotator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the ComponentDefinitionAnnotator service.
 *
 * @internal
 */
#[CoversClass(ComponentDefinitionAnnotator::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class ComponentDefinitionAnnotatorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ui_patterns',
  ];

  /**
   * An undeclared required prop is not created: core needs a type.
   */
  public function testUndeclaredRequiredProp(): void {
    $definition = $this->annotate([
      'required' => ['missing', 'label'],
      'properties' => ['label' => ['type' => 'string']],
    ]);
    self::assertSame(['attributes', 'label', 'variant'], \array_keys($definition['props']['properties']));
    self::assertTrue($definition['props']['properties']['label']['ui_patterns']['required']);
  }

  /**
   * Annotating twice gives the same definition.
   *
   * ComponentValidator annotates definitions our plugin manager annotates too.
   */
  public function testIdempotence(): void {
    $once = $this->annotate(['properties' => ['url' => ['$ref' => 'ui-patterns://url']]]);
    self::assertSame('url', $once['props']['properties']['url']['ui_patterns']['type_definition']->getPluginId());
    self::assertEquals($once, $this->container->get(ComponentDefinitionAnnotator::class)->annotateDefinition($once));
  }

  /**
   * Annotates a definition made of the given props.
   */
  private function annotate(array $props): array {
    return $this->container->get(ComponentDefinitionAnnotator::class)->annotateDefinition([
      'id' => 'ui_patterns_test:annotated',
      'variants' => ['primary' => ['title' => 'Primary']],
      'slots' => ['content' => []],
      'props' => ['type' => 'object'] + $props,
    ]);
  }

}
