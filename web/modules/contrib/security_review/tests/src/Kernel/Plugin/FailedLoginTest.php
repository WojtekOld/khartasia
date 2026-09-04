<?php

namespace Drupal\Tests\security_review\Kernel\Plugin;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\security_review\CheckResult;
use Drupal\Tests\security_review\Kernel\SecurityReviewTestBase;
use Drupal\user\Entity\User;

/**
 * Contains tests for the FailedLogin plugin.
 *
 * @group security_review
 */
class FailedLoginTest extends SecurityReviewTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dblog'];

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('user', ['users_data']);
    $this->installSchema('dblog', ['watchdog']);

    /** @var \Drupal\security_review\SecurityCheckPluginManager $manager */
    $manager = $this->container->get('plugin.manager.security_review.security_check');
    $this->plugin = $manager->createInstance('failed_logins');
  }

  /**
   * Tests behavior when dblog is uninstalled.
   *
   * @throws \Exception
   */
  public function testFailedLoginWhenDblogUninstalled(): void {
    $this->container->get('module_installer')->uninstall(['dblog']);
    $this->plugin->doRun();
    $results = $this->plugin->getResult();

    $this->assertNotEmpty($results);
    $this->assertEquals(CheckResult::INFO, $results['result']);
  }

  /**
   * Tests the plugin when it should pass.
   */
  public function testFailedLoginSuccess() {
    $this->plugin->doRun();
    $results = $this->plugin->getResult();

    $this->assertIsArray($results);
    $this->assertEquals(CheckResult::SUCCESS, $results['result']);
    $this->assertEmpty($results['findings']);
  }

  /**
   * Tests the plugin when it should fail.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function testFailedLoginFailed(): void {
    $user = User::create([
      'name' => 'test_user',
      'mail' => 'test@example.com',
      'status' => 1,
    ]);
    $user->save();

    $variables = serialize(['%user' => $user->getAccountName()]);
    $timestamp = \Drupal::time()->getCurrentTime();

    $connection = $this->container->get('database');
    for ($i = 0; $i < 11; $i++) {
      $connection->insert('watchdog')
        ->fields([
          'uid' => 0,
          'type' => 'user',
          'message' => 'Login attempt failed for %user.',
          'variables' => $variables,
          'severity' => RfcLogLevel::NOTICE,
          'hostname' => '127.0.0.1',
          'location' => 'security_review_failed_logins_check',
          'timestamp' => $timestamp,
        ])->execute();
    }

    // Run the check.
    $this->plugin->doRun();
    $result = $this->plugin->getResult();

    $this->assertIsArray($result);
    $this->assertEquals(CheckResult::FAIL, $result['result']);
    $this->assertNotEmpty($result['findings']);

    $findings = $result['findings'];
    $this->assertNotEmpty($findings);
    $this->assertEquals('127.0.0.1:test_user', $findings[0]);
  }

}
