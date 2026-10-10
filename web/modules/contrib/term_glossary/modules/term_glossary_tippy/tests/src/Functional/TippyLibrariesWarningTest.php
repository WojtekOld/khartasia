<?php

declare(strict_types=1);

namespace Drupal\Tests\term_glossary_tippy\Functional;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\term_glossary_tippy\TippyLibraries;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the warnings displayed when the Tippy.js libraries are missing.
 *
 * The popper.js and tippy.js libraries are external dependencies that
 * cannot be shipped with the module, so the tests covering the missing
 * libraries case are skipped on environments where the libraries are
 * installed in the web root, and the test covering the installed case
 * is skipped everywhere else.
 *
 * @group term_glossary
 */
#[Group('term_glossary')]
#[RunTestsInSeparateProcesses]
class TippyLibrariesWarningTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'taxonomy',
    'text',
    'field',
    'filter',
    'term_glossary',
    'term_glossary_tippy',
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

    $admin_user = $this->drupalCreateUser([
      'access administration pages',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);
  }

  /**
   * Tests the status report entry when the libraries are missing.
   *
   * Visits the status report page on an installation where the Tippy.js
   * libraries are not available and verifies that the requirements
   * entry is displayed with its title, its missing libraries message
   * and the paths of the missing files, so administrators are pointed
   * to the download instructions.
   */
  public function testStatusReportMissingLibraries(): void {
    if (TippyLibraries::getMissingFiles() === []) {
      $this->markTestSkipped('The Tippy.js libraries are installed in this environment.');
    }

    $this->drupalGet('/admin/reports/status');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Taxonomy Term Glossary Tippy.js');
    $this->assertSession()->pageTextContains('Required external libraries are missing.');
    foreach (TippyLibraries::getMissingFiles() as $file) {
      $this->assertSession()->pageTextContains($file);
    }
  }

  /**
   * Tests the status report entry when the libraries are installed.
   *
   * Visits the status report page on an installation where the Tippy.js
   * libraries are available in the web root and verifies that the
   * requirements entry reports them as correctly installed.
   */
  public function testStatusReportInstalledLibraries(): void {
    if (TippyLibraries::getMissingFiles() !== []) {
      $this->markTestSkipped('The Tippy.js libraries are not installed in this environment.');
    }

    $this->drupalGet('/admin/reports/status');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Taxonomy Term Glossary Tippy.js');
    $this->assertSession()->pageTextContains('The required external libraries are correctly installed.');
  }

  /**
   * Tests the glossary configuration form warning for missing libraries.
   *
   * Selects the Tippy.js handler as the active integration type, visits
   * the glossary configuration form on an installation where the
   * libraries are not available and verifies that the warning message
   * and the missing file paths are displayed above the handler
   * settings, so the problem is visible where the handler is chosen.
   */
  public function testConfigFormMissingLibrariesWarning(): void {
    if (TippyLibraries::getMissingFiles() === []) {
      $this->markTestSkipped('The Tippy.js libraries are installed in this environment.');
    }

    $this->config('term_glossary.glossaryconfig')
      ->set('integration_type', 'term_glossary_tippy')
      ->save();

    $this->drupalGet('/admin/config/glossary');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('The Tippy.js tooltips integration requires external JavaScript libraries that could not be found');
    foreach (TippyLibraries::getMissingFiles() as $file) {
      $this->assertSession()->pageTextContains($file);
    }
  }

  /**
   * Tests the glossary configuration form when the libraries are installed.
   *
   * Selects the Tippy.js handler as the active integration type, visits
   * the glossary configuration form on an installation where the
   * libraries are available and verifies that the handler settings are
   * displayed without any missing libraries warning.
   */
  public function testConfigFormNoWarning(): void {
    if (TippyLibraries::getMissingFiles() !== []) {
      $this->markTestSkipped('The Tippy.js libraries are not installed in this environment.');
    }

    $this->config('term_glossary.glossaryconfig')
      ->set('integration_type', 'term_glossary_tippy')
      ->save();

    $this->drupalGet('/admin/config/glossary');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Generates tooltips for glossary terms using the Tippy.js Javascript library.');
    $this->assertSession()->pageTextNotContains('The Tippy.js tooltips integration requires external JavaScript libraries that could not be found');
  }

}
