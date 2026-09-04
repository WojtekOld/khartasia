<?php

namespace Drupal\Tests\security_review\Functional;

use Drupal\Tests\BrowserTestBase;
use Drush\TestTraits\DrushTestTrait;

/**
 * Tests the Drush command for the Security Review module.
 *
 * @group security_review
 */
class SecurityReviewDrushCommandTest extends BrowserTestBase {

  use DrushTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'security_review',
  ];

  /**
   * Tests the drush command secrev.
   *
   * @throws \Exception
   */
  public function testDrushCommandBasic(): void {
    $this->drush('secrev', [], [], NULL, NULL, 1);

    $output = preg_replace('/\s+/', ' ', $this->getOutput());
    $output = preg_replace('/\b(success|failed|info)\b/', '', $output);
    $output = preg_replace('/\s+/', ' ', $output);

    $this->assertStringContainsString('Untrusted roles do not have administrative or trusted Drupal permissions.', $output);
    $this->assertStringContainsString('Module field is not enabled.', $output);
    $this->assertStringContainsString('The administrative account is enabled - dangerous!', $output);
    $this->assertStringContainsString('Dangerous tags were not found in any submitted content (fields).', $output);
    $this->assertStringContainsString('Errors are managed in the "verbose" way from local settings overrides.', $output);
    $this->assertStringContainsString('PHP files in the Drupal files directory cannot be executed.', $output);
    $this->assertStringContainsString('Failed login attempts - Dblog module not installed.', $output);
    $this->assertStringContainsString('Some files and directories in your install are writable by the server.', $output);
    $this->assertStringContainsString('Some specified headers are missing.', $output);
    $this->assertStringContainsString('No users, with matching username and password, found.', $output);
    $this->assertStringContainsString('Private files is enabled but the specified directory is not secure outside the web server root.', $output);
    $this->assertStringContainsString('Query errors - Dblog module not installed.', $output);
    $this->assertStringContainsString('No sensitive temporary files were found.', $output);
    $this->assertStringContainsString('Module filter is not enabled.', $output);
    $this->assertStringContainsString('Trusted hosts are not set.', $output);
    $this->assertStringContainsString('Vendor directory is outside webroot.', $output);
    $this->assertStringContainsString('Module views is not enabled.', $output);
  }

  /**
   * Tests the drush command secrev with --skip.
   */
  public function testDrushCommandSkipOption(): void {
    $this->drush('secrev', [], ['skip' => 'trusted_hosts,views_access'], NULL, NULL, 1);

    $output = preg_replace('/\s+/', ' ', $this->getOutput());
    $output = preg_replace('/\b(success|failed|info)\b/', '', $output);
    $output = preg_replace('/\s+/', ' ', $output);

    $this->assertStringContainsString('Untrusted roles do not have administrative or trusted Drupal permissions.', $output);
    // Not testing all the strings.
    $this->assertStringNotContainsString('Trusted hosts are not set.', $output);
    $this->assertStringNotContainsString('Module views is not enabled.', $output);
  }

}
