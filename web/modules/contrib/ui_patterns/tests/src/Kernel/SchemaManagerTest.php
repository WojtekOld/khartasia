<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_patterns\PropTypePluginManager;
use Drupal\ui_patterns\SchemaManager\ReferencesResolver;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test SchemaManager parts.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class SchemaManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ui_patterns',
  ];

  /**
   * Test the StreamWrapper service.
   */
  public function testStreamWrapper(): void {
    $correctUri = 'ui-patterns://number';
    $wrongUri = 'ui-patterns://wrongProptype';

    $correctPropType = \file_get_contents($correctUri);
    $wrongPropType = \file_get_contents($wrongUri);

    self::assertEquals('{"type":["number","integer"]}', $correctPropType);
    self::assertEquals('[]', $wrongPropType);
  }

  /**
   * Test the ReferencesResolver service.
   */
  public function testResolve(): void {
    $resolver = \Drupal::service(ReferencesResolver::class);

    foreach (self::provideResolveData() as $case) {
      [$schema, $expected] = $case;
      $result = $resolver->resolve($schema);
      // Skipping everything under patternProperties keys
      // to avoid having to deal with std classes in the yaml file.
      $this->cleanupSchema($result);
      self::assertEqualsCanonicalizing($expected, $result);
    }
  }

  /**
   * A resolved schema is named "id", whoever resolved its "$ref".
   */
  public function testResolvedSchemaId(): void {
    $resolver = \Drupal::service(ReferencesResolver::class);
    $prop_types = \Drupal::service(PropTypePluginManager::class);

    $resolved = $resolver->resolve(['$ref' => 'ui-patterns://enum_list', 'items' => ['enum' => ['a']]]);
    self::assertSame('ui-patterns://enum-list', $resolved['id']);
    self::assertArrayNotHasKey('$id', $resolved);
    self::assertSame('enum_list', $prop_types->guessFromSchema($resolved)->getPluginId());

    // Resolved by another module: "$id", no "$ref".
    $inlined = ['type' => 'object', 'properties' => ['pack_id' => ['enum' => ['x']]], '$id' => 'ui-patterns://links'];
    self::assertSame('links', $prop_types->guessFromSchema($resolver->resolve($inlined))->getPluginId());
  }

  /**
   * Provide data for testResolve.
   */
  public static function provideResolveData(): \Generator {
    $file_contents = \file_get_contents(__DIR__ . '/../../fixtures/ReferencesResolverData.yml');
    $sources = $file_contents ? Yaml::decode($file_contents) : [];

    foreach ($sources as $source) {
      yield [$source['schema'], $source['expected']];
    }
  }

  /**
   * Remove all "patternProperties" keys from the given schema.
   */
  private function cleanupSchema(array &$schema): void {
    foreach ($schema as $key => &$value) {
      if ($key === 'patternProperties') {
        unset($schema[$key]);
      }
      elseif (\is_array($value)) {
        $this->cleanupSchema($value);
      }
    }
  }

}
