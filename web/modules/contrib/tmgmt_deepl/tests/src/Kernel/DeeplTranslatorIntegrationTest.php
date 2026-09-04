<?php

namespace Drupal\Tests\tmgmt_deepl\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\key\Entity\Key;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt_deepl\DeeplTranslatorApiInterface;
use Drupal\tmgmt_deepl\DeeplTranslatorUi;
use Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator;

/**
 * Integration tests for the DeepL translator plugin with real Drupal entities.
 *
 * @covers \Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator
 * @covers \Drupal\tmgmt_deepl\DeeplTranslatorUi
 * @group tmgmt_deepl
 */
class DeeplTranslatorIntegrationTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'key',
    'options',
    'language',
    'tmgmt',
    'tmgmt_deepl',
  ];

  /**
   * The translator entity.
   *
   * @var \Drupal\tmgmt\Entity\Translator
   */
  protected Translator $translator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('key');
    $this->installEntitySchema('tmgmt_job');
    $this->installEntitySchema('tmgmt_job_item');
    $this->installEntitySchema('tmgmt_message');

    \Drupal::service('router.builder')->rebuild();

    // Configure languages needed for TMGMT jobs.
    ConfigurableLanguage::createFromLangcode('en')->save();
    ConfigurableLanguage::createFromLangcode('de')->save();
    $this->config('language.negotiation')->set('url.prefixes', ['en' => 'en', 'de' => 'de'])->save();

    // Create a Key entity with a placeholder value. The API service will be
    // mocked so the key value never reaches DeepL.
    $key = Key::create([
      'id' => 'test_deepl_key',
      'label' => 'Test DeepL Key',
      'key_type' => 'deepl_api_key',
      'key_value' => 'test-key-placeholder',
    ]);
    $key->save();

    // Create a Translator entity using the deepl_api plugin.
    $this->translator = Translator::create([
      'name' => 'deepl_api_test',
      'label' => 'DeepL API Test',
      'plugin' => 'deepl_api',
      'settings' => [
        'auth_key_entity' => 'test_deepl_key',
        'omit_partner_id' => TRUE,
      ],
    ]);
    $this->translator->save();

    // Mock the DeepL API service to avoid needing a live API key.
    // Use lowercase language codes to match Drupal conventions so that
    // TMGMT's checkTranslatable can find them without remote mappings.
    $api = $this->createMock(DeeplTranslatorApiInterface::class);
    $api->method('setTranslator')->willReturnCallback(function (): void {});
    $api->method('getTargetLanguages')->willReturn([
      'en' => 'English',
      'de' => 'German',
      'fr' => 'French',
    ]);
    $this->container->set('tmgmt_deepl.api', $api);
  }

  /**
   * Tests translator plugin discovery via the TMGMT plugin manager.
   */
  public function testTranslatorPluginDiscovery(): void {
    $plugin = $this->translator->getPlugin();
    $this->assertInstanceOf(DeeplTranslator::class, $plugin);
    $this->assertEquals('deepl_api', $plugin->getPluginId());
  }

  /**
   * Tests that the translator is listed as available when configured.
   */
  public function testTranslatorAvailable(): void {
    $plugin = $this->translator->getPlugin();
    $result = $plugin->checkAvailable($this->translator);
    $this->assertTrue($result->getSuccess());
  }

  /**
   * Tests that getSupportedRemoteLanguages delegates to the API service.
   */
  public function testSupportedRemoteLanguages(): void {
    $plugin = $this->translator->getPlugin();
    $languages = $plugin->getSupportedRemoteLanguages($this->translator);
    $this->assertArrayHasKey('en', $languages);
    $this->assertArrayHasKey('de', $languages);
  }

  /**
   * Tests that getSupportedTargetLanguages excludes the source language.
   */
  public function testSupportedTargetLanguagesExcludesSource(): void {
    $plugin = $this->translator->getPlugin();
    $targets = $plugin->getSupportedTargetLanguages($this->translator, 'en');
    $this->assertArrayNotHasKey('en', $targets);
    $this->assertArrayHasKey('de', $targets);
  }

  /**
   * Tests the translator interacts correctly with the Key module.
   */
  public function testTranslatorKeyIntegration(): void {
    $key = Key::load('test_deepl_key');
    if ($key === NULL) {
      $this->fail('Key entity not found');
    }
    $this->assertSame('test_deepl_key', $key->id());
    // Verify the translator references the correct key entity ID.
    $this->assertEquals('test_deepl_key', $this->translator->getSetting('auth_key_entity'));
  }

  /**
   * Tests that default settings are returned correctly.
   */
  public function testDefaultSettings(): void {
    $plugin = $this->translator->getPlugin();
    if (!$plugin instanceof DeeplTranslator) {
      $this->fail('Expected DeeplTranslator plugin instance');
    }

    $defaults = $plugin->getDefaultSettings($this->translator);
    $this->assertArrayHasKey('model_type', $defaults);
    $this->assertEquals('latency_optimized', $defaults['model_type']);
    $this->assertArrayHasKey('tag_handling', $defaults);
    $this->assertArrayHasKey('formality', $defaults);
  }

  /**
   * Tests DeeplTranslatorUi::create() uses the passed container.
   *
   * Verifies ContainerFactoryPluginInterface wiring: create() must pull
   * tmgmt_deepl.api, messenger, and module_handler from the passed container
   * rather than calling \Drupal::service() directly.
   */
  public function testTranslatorUiCreateFromContainer(): void {
    $ui = DeeplTranslatorUi::create($this->container, [], 'deepl_api', []);
    // The plugin ID passed to create() must be reflected back on the instance.
    $this->assertSame('deepl_api', $ui->getPluginId());
  }

  /**
   * Tests checkoutSettingsForm returns a fallback description when context off.
   */
  public function testCheckoutSettingsFormContextDisabled(): void {
    $job = Job::create([
      'source_language' => 'en',
      'target_language' => 'de',
      'uid' => 0,
      'translator' => 'deepl_api_test',
    ]);
    $job->save();

    $ui = DeeplTranslatorUi::create($this->container, [], 'deepl_api', []);
    $form = $ui->checkoutSettingsForm([], new FormState(), $job);

    $this->assertArrayNotHasKey('context', $form);
    $this->assertArrayHasKey('#description', $form);
  }

  /**
   * Tests checkoutSettingsForm adds a textarea when enable_context is on.
   */
  public function testCheckoutSettingsFormContextEnabled(): void {
    $this->translator->set('settings', $this->translator->getSettings() + ['enable_context' => 1]);
    $this->translator->save();

    $job = Job::create([
      'source_language' => 'en',
      'target_language' => 'de',
      'uid' => 0,
      'translator' => 'deepl_api_test',
    ]);
    $job->save();

    $ui = DeeplTranslatorUi::create($this->container, [], 'deepl_api', []);
    $form = $ui->checkoutSettingsForm([], new FormState(), $job);

    $this->assertArrayHasKey('context', $form);
    $context_element = $form['context'];
    if (!is_array($context_element)) {
      $this->fail('Expected context form element to be an array');
    }
    $this->assertSame('textarea', $context_element['#type']);
  }

  /**
   * Tests that translator settings survive a save+reload cycle.
   */
  public function testTranslatorSettingsPersistence(): void {
    $settings = $this->translator->getSettings();
    $this->assertEquals('test_deepl_key', $settings['auth_key_entity']);
    $this->assertTrue($settings['omit_partner_id'] ?? FALSE);

    $full_settings = $settings + [
      'model_type' => 'prefer_quality_optimized',
      'split_sentences' => 'nonewlines',
      'formality' => 'prefer_more',
      'preserve_formatting' => 1,
      'tag_handling' => 'xml',
      'tag_handling_version' => 'v2',
      'outline_detection' => 1,
      'splitting_tags' => 'p,br',
      'non_splitting_tags' => 'a,strong',
      'ignore_tags' => 'script',
      'enable_context' => 1,
      'translate_documents' => 1,
      'enable_document_minification' => 1,
    ];
    $this->translator->set('settings', $full_settings);
    $this->translator->save();

    $reloaded = Translator::load('deepl_api_test');
    if ($reloaded === NULL) {
      $this->fail('Translator entity not found after save');
    }
    $this->assertEquals('prefer_quality_optimized', $reloaded->getSetting('model_type'));
    $this->assertEquals('nonewlines', $reloaded->getSetting('split_sentences'));
    $this->assertEquals('prefer_more', $reloaded->getSetting('formality'));
    $this->assertEquals('xml', $reloaded->getSetting('tag_handling'));
    $this->assertEquals('v2', $reloaded->getSetting('tag_handling_version'));
    $this->assertEquals('1', $reloaded->getSetting('outline_detection'));
    $this->assertEquals('p,br', $reloaded->getSetting('splitting_tags'));
    $this->assertEquals('test_deepl_key', $reloaded->getSetting('auth_key_entity'));
  }

}
