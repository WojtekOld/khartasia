<?php

namespace Drupal\Tests\security_review\Kernel\Plugin;

use Drupal\security_review\CheckResult;
use Drupal\Tests\security_review\Kernel\SecurityReviewTestBase;

/**
 * Contains tests for the ErrorReporting plugin.
 *
 * @group security_review
 */
class ErrorReportingTest extends SecurityReviewTestBase {

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    /** @var \Drupal\security_review\SecurityCheckPluginManager $manager */
    $manager = $this->container->get('plugin.manager.security_review.security_check');
    $this->plugin = $manager->createInstance('error_reporting');
  }

  /**
   * Tests the various settings for error reporting.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testErrorReportingLevels(): void {
    $this->config('system.logging')->set('error_level', 'verbose')->save();

    // Run the check.
    $this->plugin->doRun();
    $result = $this->plugin->getResult();

    $this->assertIsArray($result);
    $this->assertEquals(CheckResult::INFO, $result['result']);

    $this->config('system.logging')->set('error_level', 'hide')->save();

    // Run the check.
    $this->plugin->doRun();
    $result = $this->plugin->getResult();

    $this->assertIsArray($result);
    $this->assertEquals(CheckResult::SUCCESS, $result['result']);

    $this->config('system.logging')->set('error_level', 'all')->save();

    // Run the check.
    $this->plugin->doRun();
    $result = $this->plugin->getResult();

    $this->assertIsArray($result);
    $this->assertEquals(CheckResult::FAIL, $result['result']);

    $this->config('system.logging')->set('error_level', 'some')->save();

    // Run the check.
    $this->plugin->doRun();
    $result = $this->plugin->getResult();

    $this->assertIsArray($result);
    $this->assertEquals(CheckResult::FAIL, $result['result']);
  }

}
