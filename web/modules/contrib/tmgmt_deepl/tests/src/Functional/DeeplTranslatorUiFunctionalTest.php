<?php

namespace Drupal\Tests\tmgmt_deepl\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\tmgmt\Entity\Translator;
use Drupal\user\Entity\User;

/**
 * Tests the DeepL translator configuration UI.
 *
 * @covers \Drupal\tmgmt_deepl\DeeplTranslatorUi
 * @group tmgmt_deepl
 */
class DeeplTranslatorUiFunctionalTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['tmgmt', 'tmgmt_deepl', 'key', 'file'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    Translator::create([
      'name' => 'deepl_api_test',
      'label' => 'DeepL API Test',
      'plugin' => 'deepl_api',
      'settings' => ['auth_key_entity' => ''],
    ])->save();
  }

  /**
   * Tests access and rendering of the translator configuration form.
   */
  public function testTranslatorConfigurationForm(): void {
    // Anonymous users have no access.
    $this->drupalGet('/admin/tmgmt/translators/manage/deepl_api_test');
    $this->assertSession()->statusCodeEquals(403);

    // Admin users can configure the translator.
    $admin = $this->drupalCreateUser(['administer tmgmt']);
    $this->assertInstanceOf(User::class, $admin);
    $this->drupalLogin($admin);
    $this->drupalGet('/admin/tmgmt/translators/manage/deepl_api_test');
    $this->assertSession()->statusCodeEquals(200);

    // The DeepL plugin settings are present.
    $this->assertSession()->fieldExists('settings[auth_key_entity]');
    $this->assertSession()->fieldExists('settings[model_type]');
    $this->assertSession()->fieldExists('settings[formality]');
    $this->assertSession()->fieldExists('settings[split_sentences]');
  }

  /**
   * Tests the formality options are rendered correctly.
   *
   * Note: saving the form is intentionally not tested because auth_key_entity
   * is required and a live API key is unavailable in the test environment.
   */
  public function testFormRendersFormalityOptions(): void {
    $admin = $this->drupalCreateUser(['administer tmgmt']);
    $this->assertInstanceOf(User::class, $admin);
    $this->drupalLogin($admin);
    $this->drupalGet('/admin/tmgmt/translators/manage/deepl_api_test');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->optionExists('settings[formality]', 'prefer_more');
    $this->assertSession()->optionExists('settings[formality]', 'prefer_less');
  }

}
