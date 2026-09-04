<?php

namespace Drupal\Tests\tmgmt_deepl\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the tmgmt_deepl.install update hooks.
 *
 * The update_8003 hook is intentionally not covered here: it rewrites legacy
 * deepl_pro config with `url`/`url_usage` keys that were dropped from the
 * 2.3.x schema, so its resulting save cannot pass strict config schema
 * validation in isolation. It is an intermediate legacy step superseded by
 * the consolidating update_8004 (which is covered below).
 *
 * @covers ::tmgmt_deepl_update_8001
 * @covers ::tmgmt_deepl_update_8002
 * @covers ::tmgmt_deepl_update_8004
 * @covers ::tmgmt_deepl_update_8005
 * @covers ::tmgmt_deepl_update_8006
 * @covers ::tmgmt_deepl_update_8007
 * @group tmgmt_deepl
 */
class TmgmtDeeplUpdateHooksTest extends KernelTestBase {

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
    require_once dirname(__DIR__, 3) . '/tmgmt_deepl.install';
  }

  /**
   * Seeds a tmgmt translator config directly in the active storage.
   *
   * Writing through config.storage bypasses strict schema validation, which
   * lets the legacy (pre-2.3.x) keys exercised by the update hooks be set up
   * without disabling the schema checker for saves the hooks perform.
   *
   * @param string $id
   *   The translator id.
   * @param string $plugin
   *   The plugin id.
   * @param array $settings
   *   The translator settings.
   */
  protected function seedTranslator(string $id, string $plugin, array $settings = []): void {
    $this->container->get('config.storage')->write("tmgmt.translator.$id", [
      'name' => $id,
      'label' => ucfirst($id),
      'plugin' => $plugin,
      'settings' => $settings,
    ]);
  }

  /**
   * Tests update_8001/8002 resave deepl_pro translators without error.
   */
  public function testUpdate8001And8002ResaveProTranslators(): void {
    $this->seedTranslator('pro', 'deepl_pro', ['auth_key_entity' => 'my_key']);

    tmgmt_deepl_update_8001();
    tmgmt_deepl_update_8002();

    // The plugin and valid settings survive the (idempotent) resave.
    $this->assertSame('deepl_pro', $this->config('tmgmt.translator.pro')->get('plugin'));
    /** @var array<string, mixed> $settings */
    $settings = $this->config('tmgmt.translator.pro')->get('settings');
    $this->assertSame('my_key', $settings['auth_key_entity']);
  }

  /**
   * Tests update_8004 migrates legacy plugins to deepl_api.
   */
  public function testUpdate8004MigratesToDeeplApi(): void {
    // Legacy keys (auth_key/url) are seeded via raw storage; the hook strips
    // them, so the resulting saved config is schema-valid.
    $this->seedTranslator('pro', 'deepl_pro', [
      'auth_key' => 'secret-key',
      'url' => 'https://api.deepl.com/v2/translate',
      'auth_key_entity' => '',
    ]);
    $this->seedTranslator('free', 'deepl_free', [
      'auth_key' => '',
      'auth_key_entity' => 'existing_key',
    ]);

    tmgmt_deepl_update_8004();

    // Both legacy plugins are rewritten to deepl_api.
    $this->assertSame('deepl_api', $this->config('tmgmt.translator.pro')->get('plugin'));
    $this->assertSame('deepl_api', $this->config('tmgmt.translator.free')->get('plugin'));

    // Obsolete legacy keys are removed.
    /** @var array<string, mixed> $pro_settings */
    $pro_settings = $this->config('tmgmt.translator.pro')->get('settings');
    $this->assertArrayNotHasKey('auth_key', $pro_settings);
    $this->assertArrayNotHasKey('url', $pro_settings);

    // The translator with a direct auth_key (and no Key entity) is queued for
    // the Key module migration post_update via state.
    $pending = \Drupal::state()->get('tmgmt_deepl.pending_key_migration');
    $this->assertIsArray($pending);
    $this->assertArrayHasKey('pro', $pending);
    // The translator that already uses a Key entity is not queued.
    $this->assertArrayNotHasKey('free', $pending);
  }

  /**
   * Data provider for testSettingFlagUpdates.
   */
  public static function settingFlagProvider(): array {
    return [
      'update_8005 enable_context' => ['tmgmt_deepl_update_8005', 'enable_context', FALSE],
      'update_8006 omit_partner_id' => ['tmgmt_deepl_update_8006', 'omit_partner_id', FALSE],
      'update_8007 tag_handling_version' => ['tmgmt_deepl_update_8007', 'tag_handling_version', 'v1'],
    ];
  }

  /**
   * Tests update_8005/8006/8007 set their default flag on deepl_api.
   *
   * @dataProvider settingFlagProvider
   */
  public function testSettingFlagUpdates(string $function, string $key, mixed $expected): void {
    $this->seedTranslator('api', 'deepl_api', []);
    $this->seedTranslator('other', 'dummy', []);

    self::assertTrue(is_callable($function));
    $function();

    /** @var array<string, mixed> $settings */
    $settings = $this->config('tmgmt.translator.api')->get('settings');
    $this->assertSame($expected, $settings[$key]);
    // The non-DeepL translator is untouched.
    $this->assertSame([], $this->config('tmgmt.translator.other')->get('settings'));
  }

}
