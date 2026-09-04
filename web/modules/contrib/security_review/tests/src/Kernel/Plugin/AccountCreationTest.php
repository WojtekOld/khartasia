<?php

namespace Drupal\Tests\security_review\Kernel\Plugin;

use Drupal\security_review\CheckResult;
use Drupal\Tests\security_review\Kernel\SecurityReviewTestBase;

/**
 * Contains tests for the AccountCreation plugin.
 *
 * @group security_review
 */
class AccountCreationTest extends SecurityReviewTestBase {

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    /** @var \Drupal\security_review\SecurityCheckPluginManager $manager */
    $manager = $this->container->get('plugin.manager.security_review.security_check');
    $this->plugin = $manager->createInstance('account_creation');
  }

  /**
   * Tests the various settings for user creation.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testUserCreationLevels(): void {
    $this->config('user.settings')->set('register', 'admin_only')->save();
    // Run the check.
    $this->plugin->doRun();
    $result = $this->plugin->getResult();

    $this->assertIsArray($result);
    $this->assertEquals(CheckResult::SUCCESS, $result['result']);

    $this->config('user.settings')->set('register', 'visitors')->save();
    // Run the check.
    $this->plugin->doRun();
    $result = $this->plugin->getResult();

    $this->assertIsArray($result);
    $this->assertEquals(CheckResult::INFO, $result['result']);

    $this->config('user.settings')->set('register', 'visitors_admin_approval')->save();
    // Run the check.
    $this->plugin->doRun();
    $result = $this->plugin->getResult();

    $this->assertIsArray($result);
    $this->assertEquals(CheckResult::INFO, $result['result']);

  }

}
