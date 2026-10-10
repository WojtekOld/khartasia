<?php

declare(strict_types=1);

namespace Drupal\Tests\term_glossary\Functional;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\term_glossary\Form\TermGlossaryConfigForm;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the TermGlossary configuration form.
 *
 * @group term_glossary
 */
#[CoversClass(TermGlossaryConfigForm::class)]
#[Group('term_glossary')]
#[RunTestsInSeparateProcesses]
class TermGlossaryConfigFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'taxonomy',
    'text',
    'field',
    'filter',
    'term_glossary',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    Vocabulary::create([
      'vid' => 'glossary',
      'name' => 'Glossary',
    ])->save();
  }

  /**
   * Tests that the config form saves settings correctly.
   *
   * Logs in as an admin, loads the glossary config page, and
   * submits the form with specific values (vocab, link type,
   * single_match, full_word, case_sensitive, ignore_tags).
   * Verifies the success message appears and all values are
   * persisted in the config object.
   */
  public function testConfigFormSubmission(): void {
    $admin_user = $this->drupalCreateUser([
      'access administration pages',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);

    $this->drupalGet('/admin/config/glossary');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains(
      'Select the vocabularies to use for glossary',
    );

    $edit = [
      'vocab[]' => ['glossary'],
      'integration_type' => 'link',
      'single_match' => TRUE,
      'full_word' => TRUE,
      'case_sensitive' => TRUE,
      'ignore_tags' => 'code,pre',
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->pageTextContains(
      'The configuration options have been saved.',
    );

    // Verify config was saved.
    $config = $this->config('term_glossary.glossaryconfig');
    $this->assertEquals('glossary', $config->get('vocab'));
    $this->assertEquals('link', $config->get('integration_type'));
    $this->assertTrue((bool) $config->get('single_match'));
    $this->assertTrue((bool) $config->get('full_word'));
    $this->assertTrue((bool) $config->get('case_sensitive'));
    $this->assertEquals('code,pre', $config->get('ignore_tags'));
  }

  /**
   * Tests that anonymous user gets access denied.
   *
   * Visits the glossary config page without logging in.
   * Verifies the response is HTTP 403, confirming the route
   * permission check ('administer site configuration').
   */
  public function testConfigFormAccessDenied(): void {
    $this->drupalGet('/admin/config/glossary');
    $this->assertSession()->statusCodeEquals(403);
  }

}
