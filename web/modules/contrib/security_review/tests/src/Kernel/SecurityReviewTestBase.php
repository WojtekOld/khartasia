<?php

namespace Drupal\Tests\security_review\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\UserInterface;

/**
 * Base class for testing Security review functionality.
 */
abstract class SecurityReviewTestBase extends KernelTestBase {

  /**
   * An admin user with administrative permissions for security review.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * The permissions required for an admin user to run security review.
   *
   * @var array
   *   A list of permissions.
   */
  protected array $permissions = ['access security review list', 'run security checks'];

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['security_review', 'system', 'user'];

  /**
   * The plugin being tests.
   */
  protected object $plugin;

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig('security_review');
  }

}
