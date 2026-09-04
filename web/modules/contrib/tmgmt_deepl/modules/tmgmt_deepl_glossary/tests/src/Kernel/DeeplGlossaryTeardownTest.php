<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Kernel;

use Drupal\Core\Database\Connection;

/**
 * Tests teardown of the deprecated deepl_glossary entity type.
 *
 * @covers ::tmgmt_deepl_glossary_post_update_remove_single_glossary_entity
 * @group tmgmt_deepl_glossary
 */
class DeeplGlossaryTeardownTest extends DeeplGlossaryKernelTestBase {

  /**
   * The database connection.
   */
  protected Connection $database;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->database = $this->container->get('database');

    // Seed the legacy tables.
    $schema = $this->database->schema();
    $schema->createTable('tmgmt_deepl_glossary', [
      'fields' => ['id' => ['type' => 'serial', 'not null' => TRUE]],
      'primary key' => ['id'],
    ]);
    $schema->createTable('deepl_glossary__entries', [
      'fields' => ['entity_id' => ['type' => 'int', 'not null' => TRUE]],
    ]);

    // Seed stored entity definitions and an obsolete view config. The view is
    // written through the raw storage so it can be created without enabling the
    // views module or disabling strict config schema checking.
    $installed = $this->container->get('keyvalue')->get('entity.definitions.installed');
    $installed->set('deepl_glossary.entity_type', 'placeholder');
    $installed->set('deepl_glossary.field_storage_definitions', 'placeholder');
    $this->container->get('config.storage')
      ->write('views.view.tmgmt_deepl_glossary', ['id' => 'tmgmt_deepl_glossary']);
  }

  /**
   * Tests that teardown removes all deprecated-entity artifacts.
   */
  public function testTeardown(): void {
    require_once dirname(__DIR__, 3) . '/tmgmt_deepl_glossary.post_update.php';
    tmgmt_deepl_glossary_post_update_remove_single_glossary_entity();

    $schema = $this->database->schema();
    $this->assertFalse($schema->tableExists('tmgmt_deepl_glossary'));
    $this->assertFalse($schema->tableExists('deepl_glossary__entries'));

    $installed = $this->container->get('keyvalue')->get('entity.definitions.installed');
    $this->assertNull($installed->get('deepl_glossary.entity_type'));
    $this->assertNull($installed->get('deepl_glossary.field_storage_definitions'));

    $view = $this->container->get('config.factory')->get('views.view.tmgmt_deepl_glossary');
    $this->assertTrue($view->isNew());
  }

}
