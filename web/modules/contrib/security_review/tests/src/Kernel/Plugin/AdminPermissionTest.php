<?php

namespace Drupal\Tests\security_review\Kernel\Plugin;

use Drupal\security_review\CheckResult;
use Drupal\Tests\security_review\Kernel\SecurityReviewTestBase;
use Drupal\user\Entity\Role;

/**
 * Contains tests for the AdminPermissions plugin.
 *
 * @group security_review
 */
class AdminPermissionTest extends SecurityReviewTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node'];

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');

    Role::create(['id' => 'tester', 'label' => 'Testing Role'])->save();
    $this->config('security_review.settings')
      ->set('untrusted_roles', ['tester'])
      ->save();

    /** @var \Drupal\security_review\SecurityCheckPluginManager $manager */
    $manager = $this->container->get('plugin.manager.security_review.security_check');
    $this->plugin = $manager->createInstance('admin_permissions');
  }

  /**
   * Tests an admin permission with an untrusted role.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testAdminPermissionWithUntrustedUser(): void {
    $role = Role::load('tester');
    $role->grantPermission('administer nodes');
    $role->save();

    // Run the check.
    $this->plugin->doRun();
    $result = $this->plugin->getResult();

    $this->assertIsArray($result);
    $this->assertEquals(CheckResult::FAIL, $result['result']);
    $this->assertNotEmpty($result['findings']);

    $findings = $result['findings'];
    $this->assertArrayHasKey('tester', $findings);
    $this->assertContains('administer nodes', $findings['tester']);
  }

  /**
   * Tests a non-admin permission with an untrusted role.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testNonAdminPermissionWithUntrustedUser(): void {
    $role = Role::load('tester');
    $role->grantPermission('access content');
    $role->save();

    $this->plugin->doRun(FALSE);
    $result = $this->plugin->getResult();

    $this->assertIsArray($result);
    $this->assertEquals(CheckResult::SUCCESS, $result['result']);
    $this->assertEmpty($result['findings']);
  }

}
