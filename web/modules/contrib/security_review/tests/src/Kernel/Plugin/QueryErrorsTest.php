<?php

namespace Drupal\Tests\security_review\Kernel\Plugin;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\security_review\CheckResult;
use Drupal\Tests\security_review\Kernel\SecurityReviewTestBase;

/**
 * Contains tests for the QueryErrors plugin.
 *
 * @group security_review
 */
class QueryErrorsTest extends SecurityReviewTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dblog'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('user', ['users_data']);
    $this->installSchema('dblog', ['watchdog']);

    /** @var \Drupal\security_review\SecurityCheckPluginManager $manager */
    $manager = $this->container->get('plugin.manager.security_review.security_check');
    $this->plugin = $manager->createInstance('query_errors');
  }

  /**
   * Tests for INFO when dblog not installed.
   *
   * @throws \Exception
   */
  public function testWhenDblogUninstalled(): void {
    $this->container->get('module_installer')->uninstall(['dblog']);

    $this->plugin->doRun();
    $result = $this->plugin->getResult();

    $this->assertEquals(CheckResult::INFO, $result['result']);
  }

  /**
   * Tests the plugin succeeds correctly.
   *
   * @throws \Exception
   */
  public function testQueryErrorsSuccess(): void {
    $connection = $this->container->get('database');

    // Insert 9 SQL-related PHP errors from same IP.
    // Shouldn't trigger a failure.
    for ($i = 0; $i < 9; $i++) {
      $connection->insert('watchdog')
        ->fields([
          'uid' => 0,
          'type' => 'php',
          'timestamp' => $this->container->get('datetime.time')->getRequestTime(),
          'severity' => RfcLogLevel::ERROR,
          'message' => 'SQL SELECT failed due to...',
          'variables' => 'N;',
          'hostname' => '127.0.0.1',
          'location' => 'security_review_failed_logins_check',
          'link' => '',
        ])
        ->execute();
    }

    $this->plugin->doRun();
    $result = $this->plugin->getResult();

    $this->assertEquals(CheckResult::SUCCESS, $result['result']);
  }

  /**
   * Tests the plugin fails correctly.
   *
   * @throws \Exception
   */
  public function testFailWithAbundantQueryErrors(): void {
    $connection = $this->container->get('database');

    // Insert 11 SQL-related PHP errors from same IP.
    for ($i = 0; $i < 11; $i++) {
      $connection->insert('watchdog')
        ->fields([
          'uid' => 0,
          'type' => 'php',
          'timestamp' => $this->container->get('datetime.time')->getRequestTime(),
          'severity' => RfcLogLevel::ERROR,
          'message' => 'SQL SELECT failed due to...',
          'variables' => 'N;',
          'hostname' => '127.0.0.1',
          'location' => 'security_review_failed_logins_check',
          'link' => '',
        ])
        ->execute();
    }

    $this->plugin->doRun();
    $result = $this->plugin->getResult();

    $this->assertEquals(CheckResult::FAIL, $result['result']);
    $this->assertNotEmpty($result['findings']);
    $this->assertEquals('127.0.0.1', $result['findings'][0]);
  }

}
