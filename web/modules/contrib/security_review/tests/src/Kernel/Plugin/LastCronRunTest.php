<?php

namespace Drupal\Tests\security_review\Kernel\Plugin;

use Drupal\Tests\security_review\Kernel\SecurityReviewTestBase;
use Drupal\security_review\CheckResult;

/**
 * Contains tests for LastCronRun plugin.
 *
 * @group security_review
 */
class LastCronRunTest extends SecurityReviewTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    /** @var \Drupal\security_review\SecurityCheckPluginManager $manager */
    $manager = $this->container->get('plugin.manager.security_review.security_check');
    $this->plugin = $manager->createInstance('last_cron_run');
  }

  /**
   * Tests plugin success if cron ran yesterday.
   */
  public function testCronLastSuccess(): void {
    $this->container->get('state')->set('system.cron_last', time() - 86400);
    // Run the plugin.
    $this->plugin->doRun();
    $results = $this->plugin->getResult();
    $this->assertIsArray($results);
    $this->assertEquals(CheckResult::SUCCESS, $results['result']);
  }

  /**
   * Tests plugin fail if cron ran four days ago.
   */
  public function testCronLastFail(): void {
    $this->container->get('state')->set('system.cron_last', time() - 4 * 86400);
    // Run the plugin.
    $this->plugin->doRun();
    $results = $this->plugin->getResult();
    $this->assertIsArray($results);
    $this->assertEquals(CheckResult::FAIL, $results['result']);
  }

}
