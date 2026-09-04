<?php

namespace Drupal\Tests\tmgmt_deepl\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests removal of obsolete DeepL translator settings dropped from the schema.
 *
 * @covers ::tmgmt_deepl_post_update_remove_obsolete_translator_settings
 * @group tmgmt_deepl
 */
class RemoveObsoleteTranslatorSettingsTest extends KernelTestBase {

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
  }

  /**
   * Tests that obsolete settings are removed and valid ones are preserved.
   */
  public function testRemovesObsoleteSettings(): void {
    // Seed config via the raw storage so the schema-invalid legacy keys can be
    // introduced without disabling strict config schema checking.
    $storage = $this->container->get('config.storage');
    $storage->write('tmgmt.translator.deepl_pro', [
      'name' => 'deepl_pro',
      'label' => 'DeepL API Pro',
      'plugin' => 'deepl_api',
      'settings' => [
        'auth_key_entity' => 'my_key',
        'auth_key' => '',
        'url' => 'https://api.deepl.com/v2/translate',
        'url_usage' => 'https://api.deepl.com/v2/usage',
        'test_url' => 'https://example.com/test',
        'test_url_usage' => 'https://example.com/test-usage',
        'tmgmt_deepl_glossary' => ['enabled' => TRUE],
        'enable_context' => FALSE,
      ],
    ]);
    // A non-DeepL translator that must be left untouched.
    $storage->write('tmgmt.translator.other', [
      'name' => 'other',
      'label' => 'Other',
      'plugin' => 'dummy',
      'settings' => [],
    ]);

    require_once dirname(__DIR__, 3) . '/tmgmt_deepl.post_update.php';
    tmgmt_deepl_post_update_remove_obsolete_translator_settings();

    /** @var array<string, mixed> $settings */
    $settings = $this->config('tmgmt.translator.deepl_pro')->get('settings');
    foreach (['auth_key', 'url', 'url_usage', 'test_url', 'test_url_usage', 'tmgmt_deepl_glossary'] as $obsolete_key) {
      $this->assertArrayNotHasKey($obsolete_key, $settings);
    }
    // Valid settings are preserved.
    $this->assertSame('my_key', $settings['auth_key_entity']);
    $this->assertArrayHasKey('enable_context', $settings);

    // The non-DeepL translator is untouched.
    $this->assertSame([], $this->config('tmgmt.translator.other')->get('settings'));
  }

}
