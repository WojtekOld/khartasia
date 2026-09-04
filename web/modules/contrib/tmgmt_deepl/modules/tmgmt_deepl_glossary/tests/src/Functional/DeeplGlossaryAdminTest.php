<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossary;
use Drupal\user\Entity\User;

/**
 * Tests the DeepL glossary admin UI.
 *
 * @covers \Drupal\tmgmt_deepl_glossary\Controller\DeeplMultilingualGlossaryListBuilder
 * @group tmgmt_deepl_glossary
 */
class DeeplGlossaryAdminTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['tmgmt', 'tmgmt_deepl', 'tmgmt_deepl_glossary', 'key', 'file', 'node'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  // phpcs:ignore DrupalPractice.Objects.StrictSchemaDisabled.StrictConfigSchema
  protected $strictConfigSchema = FALSE;

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
   * Tests access to the glossary collection page.
   */
  public function testCollectionAccess(): void {
    // Anonymous users have no access.
    $this->drupalGet('/admin/tmgmt/deepl_glossaries');
    $this->assertSession()->statusCodeEquals(403);

    // Privileged users see the collection.
    $admin = $this->drupalCreateUser(['access deepl_glossary overview']);
    $this->assertInstanceOf(User::class, $admin);
    $this->drupalLogin($admin);
    $this->drupalGet('/admin/tmgmt/deepl_glossaries');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests the glossary list shows created glossaries.
   */
  public function testCollectionListsGlossaries(): void {
    DeeplMultilingualGlossary::create([
      'label' => 'My functional test glossary',
      'tmgmt_translator' => 'deepl_api_test',
      'glossary_id' => 'deepl-uuid-functional',
    ])->save();

    $admin = $this->drupalCreateUser(['access deepl_glossary overview']);
    $this->assertInstanceOf(User::class, $admin);
    $this->drupalLogin($admin);
    $this->drupalGet('/admin/tmgmt/deepl_glossaries');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('My functional test glossary');
  }

  /**
   * Tests the add-glossary form renders.
   */
  public function testAddFormRenders(): void {
    $admin = $this->drupalCreateUser(['add deepl_glossary entities']);
    $this->assertInstanceOf(User::class, $admin);
    $this->drupalLogin($admin);
    $this->drupalGet('/admin/tmgmt/deepl_glossaries/add');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('label[0][value]');
    $this->assertSession()->fieldExists('tmgmt_translator');
    $this->assertSession()->buttonExists('Save and add entries');
  }

}
