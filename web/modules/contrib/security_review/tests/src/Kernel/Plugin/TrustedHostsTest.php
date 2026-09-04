<?php

namespace Drupal\Tests\security_review\Kernel\Plugin;

use Drupal\Core\Site\Settings;
use Drupal\security_review\CheckResult;
use Drupal\Tests\security_review\Kernel\SecurityReviewTestBase;

/**
 * Contains tests for the TrustedHosts plugin.
 *
 * @group security_review
 */
class TrustedHostsTest extends SecurityReviewTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    /** @var \Drupal\security_review\SecurityCheckPluginManager $manager */
    $manager = $this->container->get('plugin.manager.security_review.security_check');
    $this->plugin = $manager->createInstance('trusted_hosts');
  }

  /**
   * Tests the plugin succeeds correctly.
   */
  public function testTrustedHostsSuccess(): void {
    new Settings(['trusted_host_patterns' => ['^/my-site/.*$']]);

    $this->plugin->doRun();
    $results = $this->plugin->getResult();

    $this->assertEquals(CheckResult::SUCCESS, $results['result']);
    $this->assertEmpty($results['findings']);
  }

  /**
   * Tests the plugin fails correctly.
   */
  public function testTrustedHostsFailure(): void {
    // Purposefully not setting "new Settings" to trigger failure.
    $this->plugin->doRun();
    $results = $this->plugin->getResult();

    $this->assertEquals(CheckResult::FAIL, $results['result']);
    $this->assertNotEmpty($results['findings']);
  }

}
