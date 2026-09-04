<?php

namespace Drupal\Tests\security_review\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;

/**
 * Contains tests for the help page.
 *
 * @group security_review
 */
class SecurityReviewHelpPageTest extends BrowserTestBase {

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
   * The test user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $user;

  /**
   * The security checks defined by Security Review.
   *
   * @var \Drupal\security_review\SecurityCheckInterface[]
   */
  protected array $checks;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    // Login.
    $this->user = $this->drupalCreateUser(
      [
        'access security review list',
        'access administration pages',
        'administer site configuration',
      ]
    );
    $this->drupalLogin($this->user);

    // Get checks.
    $this->checks = $this->container->get('plugin.manager.security_review.security_check')->getChecks();
  }

  /**
   * Tests that help pages don't throw errors.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testHelpPagesForErrors(): void {
    foreach ($this->checks as $check) {
      $this->drupalGet('/admin/reports/security-review/help/security_review/' . $check->getPluginId());
      $this->assertSession()->statusCodeEquals(200);
    }
  }

}
