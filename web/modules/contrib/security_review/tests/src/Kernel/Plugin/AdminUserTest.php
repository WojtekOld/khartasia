<?php

namespace Drupal\Tests\security_review\Kernel\Plugin;

use Drupal\security_review\CheckResult;
use Drupal\Tests\security_review\Kernel\SecurityReviewTestBase;
use Drupal\user\Entity\User;

/**
 * Contains tests for the AdminUser plugin.
 *
 * @group security_review
 */
class AdminUserTest extends SecurityReviewTestBase {

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container->get('module_handler')->loadInclude('user', 'install');
    user_install();

    /** @var \Drupal\security_review\SecurityCheckPluginManager $manager */
    $manager = $this->container->get('plugin.manager.security_review.security_check');
    $this->plugin = $manager->createInstance('admin_user');
  }

  /**
   * Tests for user 1 not being blocked.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testAdminUserNotBlocked(): void {
    // Run the check.
    $this->plugin->doRun();
    $result = $this->plugin->getResult();

    $this->assertIsArray($result);
    $this->assertEquals(CheckResult::FAIL, $result['result']);
    $this->assertNotEmpty($result['findings']);

    $findings = $result['findings'];
    $this->assertArrayHasKey('admin', $findings);
    $this->assertFalse($findings['admin']);
  }

  /**
   * Tests a non-admin permission with an untrusted role.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testAdminUserIsBlocked(): void {
    $user1 = User::load(1);
    $user1->set('status', FALSE)->save();

    $this->plugin->doRun();
    $result = $this->plugin->getResult();

    $this->assertIsArray($result);
    $this->assertEquals(CheckResult::SUCCESS, $result['result']);
    $findings = $result['findings'];
    $this->assertTrue($findings['admin']);
  }

}
