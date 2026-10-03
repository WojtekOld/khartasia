<?php

namespace Drupal\Tests\external_entities\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests cleanup of derived entity types when a provider module is uninstalled.
 *
 * Reproduces the scenario where a separate module (like xnttwiki) provides an
 * external_entity_type config. When that module is uninstalled, the config is
 * deleted via dependency cascade. The derived entity type definitions must be
 * properly cleaned up from the key-value store.
 *
 * @group ExternalEntities
 *
 * @see https://www.drupal.org/project/external_entities/issues/3575860
 */
#[Group('ExternalEntities')]
#[RunTestsInSeparateProcesses]
class ExternalEntityTypeModuleUninstallTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'external_entities',
    'external_entities_test',
    'external_entities_provider_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $profile = 'minimal';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that uninstalling a provider module cleans up derived entity types.
   */
  public function testProviderModuleUninstallCleansUp(): void {
    $entity_type_manager = $this->container->get('entity_type.manager');
    $kv = $this->container->get('keyvalue')->get('entity.definitions.installed');

    // Verify the derived entity type exists after module install.
    $entity_type_manager->clearCachedDefinitions();
    $this->assertTrue(
      $entity_type_manager->hasDefinition('provider_test_entity'),
      'Derived entity type should exist after provider module install.'
    );

    // Verify the last installed definition exists in key-value store.
    $this->assertNotNull(
      $kv->get('provider_test_entity.entity_type'),
      'Last installed entity type definition should exist.'
    );

    // Uninstall the provider module (not external_entities itself).
    // This triggers cascading config deletion of
    // external_entity_type.provider_test_entity because of the enforced
    // dependency.
    /** @var \Drupal\Core\Extension\ModuleInstallerInterface $module_installer */
    $module_installer = $this->container->get('module_installer');
    $module_installer->uninstall(['external_entities_provider_test']);

    // Refresh the container reference after module uninstall since the
    // container is rebuilt.
    $this->container = \Drupal::getContainer();
    $entity_type_manager = $this->container->get('entity_type.manager');
    $kv = $this->container->get('keyvalue')->get('entity.definitions.installed');

    // Verify the derived entity type no longer exists.
    $entity_type_manager->clearCachedDefinitions();
    $this->assertFalse(
      $entity_type_manager->hasDefinition('provider_test_entity'),
      'Derived entity type should not exist after provider module uninstall.'
    );

    // Verify the key-value store has been cleaned up (this is the actual bug).
    $this->assertNull(
      $kv->get('provider_test_entity.entity_type'),
      'Last installed entity type definition should be removed.'
    );
    $this->assertNull(
      $kv->get('provider_test_entity.field_storage_definitions'),
      'Last installed field storage definitions should be removed.'
    );
  }

}
