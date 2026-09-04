<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_skins\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ui_skins\Definition\ThemeDefinition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests ThemeDefinition.
 */
#[Group('ui_skins')]
class ThemeDefinitionTest extends UnitTestCase {

  /**
   * Test getters.
   *
   * @param string $getter
   *   The getter callback.
   * @param string $name
   *   The name of the plugin attributes.
   * @param mixed $value
   *   The attribute's value.
   */
  #[DataProvider('definitionGettersProvider')]
  public function testGetters(string $getter, string $name, $value): void {
    $definition = new ThemeDefinition([$name => $value]);
    // @phpstan-ignore-next-line
    $this->assertEquals(\call_user_func([$definition, $getter]), $value);
  }

  /**
   * Provider.
   *
   * @return array
   *   Data.
   */
  public static function definitionGettersProvider(): array {
    return [
      ['id', 'id', 'plugin_id'],
      ['getLabel', 'label', 'Plugin label'],
      ['getDescription', 'description', 'Plugin description.'],
      ['getLibrary', 'library', 'css'],
      ['getKey', 'key', 'key'],
      ['getProvider', 'provider', 'my_module'],
      ['getTarget', 'target', 'html'],
      ['getValue', 'value', 'value-example'],
      ['isEnabled', 'enabled', FALSE],
      ['isEnabled', 'enabled', TRUE],
    ];
  }

  /**
   * Test getComputedTarget.
   *
   * @param string $value
   *   The value.
   * @param string $expected
   *   The expected result.
   */
  #[DataProvider('definitionComputedTargetProvider')]
  public function testGetComputedTarget(string $value, string $expected): void {
    $definition = new ThemeDefinition([
      'target' => $value,
    ]);
    $this->assertEquals($expected, $definition->getComputedTarget());
  }

  /**
   * Provider.
   *
   * @return array
   *   Data.
   */
  public static function definitionComputedTargetProvider(): array {
    return [
      ['html', 'html_attributes'],
      ['anything_else', 'attributes'],
    ];
  }

}
