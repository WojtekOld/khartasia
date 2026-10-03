<?php

declare(strict_types=1);

namespace Drupal\Tests\external_entities_pathauto\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\external_entities_pathauto\Hook\ExternalEntitiesPathautoHooks;
use Drupal\external_entities_pathauto\Plugin\pathauto\AliasType\ExternalEntityAliasTypeBase;
use Drupal\pathauto\AliasTypeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the external entity pathauto alias type.
 *
 * @group external_entities
 */
#[Group('external_entities')]
#[RunTestsInSeparateProcesses]
class ExternalEntityAliasTypeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'token',
    'path',
    'path_alias',
    'pathauto',
    'external_entities',
    'external_entities_pathauto',
  ];

  /**
   * The alias type class must stay instantiable.
   *
   * Pathauto instantiates this class directly: the alter hook assigns it as
   * the alias type plugin 'class', so an abstract class would fatal at
   * runtime through the alias type manager.
   */
  public function testAliasTypeIsInstantiable(): void {
    $instance = ExternalEntityAliasTypeBase::create($this->container, [], 'canonical_entities:test', []);
    $this->assertInstanceOf(AliasTypeInterface::class, $instance);
  }

  /**
   * The alter hook only rewires external entity provided alias types.
   */
  public function testAlterAssignsClassForExternalEntitiesOnly(): void {
    $types = [
      'canonical_entities:some_xntt' => ['provider' => 'external_entities'],
      'canonical_entities:node' => ['provider' => 'node'],
    ];
    ExternalEntitiesPathautoHooks::pathautoAliasTypesAlter($types);
    $this->assertSame(ExternalEntityAliasTypeBase::class, $types['canonical_entities:some_xntt']['class']);
    $this->assertArrayNotHasKey('class', $types['canonical_entities:node']);
  }

}
