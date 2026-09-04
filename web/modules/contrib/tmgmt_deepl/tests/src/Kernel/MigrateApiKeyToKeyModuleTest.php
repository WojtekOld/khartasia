<?php

namespace Drupal\Tests\tmgmt_deepl\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the API key migration advisory post_update.
 *
 * @covers ::tmgmt_deepl_post_update_migrate_api_key_to_key_module
 * @group tmgmt_deepl
 */
class MigrateApiKeyToKeyModuleTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'key',
    'options',
    'tmgmt',
    'tmgmt_deepl',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    require_once dirname(__DIR__, 3) . '/tmgmt_deepl.post_update.php';
  }

  /**
   * The translators recorded in state by update_8004 are reported.
   */
  public function testWarnsFromState(): void {
    \Drupal::state()->set('tmgmt_deepl.pending_key_migration', ['deepl_pro' => 'DeepL API Pro']);

    $sandbox = [];
    $message = (string) tmgmt_deepl_post_update_migrate_api_key_to_key_module($sandbox);

    $this->assertStringContainsString('deepl_pro', $message);
    $this->assertStringContainsString('ACTION REQUIRED', $message);
    // State is consumed.
    $this->assertSame([], \Drupal::state()->get('tmgmt_deepl.pending_key_migration', []));
  }

  /**
   * A translator that still carries a direct key in config is detected.
   */
  public function testFallbackDetectsConfigKey(): void {
    $this->container->get('config.storage')->write('tmgmt.translator.legacy', [
      'name' => 'legacy',
      'label' => 'Legacy DeepL',
      'plugin' => 'deepl_api',
      'settings' => ['auth_key' => 'secret', 'auth_key_entity' => ''],
    ]);

    $sandbox = [];
    $message = (string) tmgmt_deepl_post_update_migrate_api_key_to_key_module($sandbox);

    $this->assertStringContainsString('legacy', $message);
  }

  /**
   * No advisory is emitted when no translator needs migration.
   */
  public function testNoActionWhenClean(): void {
    $this->container->get('config.storage')->write('tmgmt.translator.clean', [
      'name' => 'clean',
      'label' => 'Clean DeepL',
      'plugin' => 'deepl_api',
      'settings' => ['auth_key_entity' => 'my_key'],
    ]);

    $sandbox = [];
    $message = (string) tmgmt_deepl_post_update_migrate_api_key_to_key_module($sandbox);

    $this->assertStringContainsString('No DeepL translators required', $message);
  }

}
