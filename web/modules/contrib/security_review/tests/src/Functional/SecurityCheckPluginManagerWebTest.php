<?php

namespace Drupal\Tests\security_review\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;

/**
 * Contains tests related to the SecurityReview class.
 *
 * @group security_review
 */
class SecurityCheckPluginManagerWebTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
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

    $this->user = $this->drupalCreateUser(
      [
        'run security checks',
        'access security review list',
        'access administration pages',
        'administer site configuration',
      ]
    );
    $this->drupalLogin($this->user);

    // Populate $checks.
    $this->checks = $this->container->get('plugin.manager.security_review.security_check')->getChecks();
  }

  /**
   * Tests a full checklist run.
   *
   * Tests whether the checks haven't been run yet, then runs them and checks
   * that their lastRun value is not 0.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testRun(): void {
    foreach ($this->checks as $check) {
      $result = $check->lastResult();
      $this->assertEquals([], $result, $check->getTitle() . ' has not been run yet.');
    }

    $this->drupalGet('/admin/reports/security-review');
    $this->assertSession()->buttonExists('Run checklist');
    $this->submitForm([], 'Run checklist');
    foreach ($this->checks as $check) {
      $result = $check->lastResult();
      $this->assertIsArray($result, $check->getTitle() . ' has been run.');
      if (!empty($result)) {
        $this->assertIsInt($result['result'], $check->getTitle() . ' has been run.');
        $this->assertIsInt($result['time'], $check->getTitle() . ' has been run.');
        $this->assertIsArray($result['findings'], $check->getTitle() . ' has been run.');
        $this->assertIsArray($result['hushed'], $check->getTitle() . ' has been run.');
      }
    }
  }

  /**
   * Skips all checks, then runs the checklist. No checks should be run.
   */
  public function testSkippedRun(): void {
    $security_review_service = $this->container->get('security_review');

    foreach ($this->checks as $check) {
      $name = $check->getPluginId();
      $security_review_service->skip($name);
    }

    $security_review_service->runChecks($this->checks);
    foreach ($this->checks as $check) {
      $this->assertEquals([], $check->lastResult(), $check->getTitle() . ' has not been run.');
    }
  }

}
