<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Kernel;

use Drupal\user\Entity\Role;

/**
 * Tests the tmgmt_deepl_glossary.install update hooks.
 *
 * Only update_8001 is exercised here. The later hooks (8002, 8005, 8006, 8007)
 * orchestrate the upgrade itself — clearing all caches, installing new entity
 * definitions and merging shipped view config — and are only meaningful inside
 * a functional UpdatePathTestBase run, not in isolation.
 *
 * @covers ::tmgmt_deepl_glossary_update_8001
 * @group tmgmt_deepl_glossary
 */
class TmgmtDeeplGlossaryUpdateHooksTest extends DeeplGlossaryKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 3) . '/tmgmt_deepl_glossary.install';
  }

  /**
   * Tests update_8001 grants the overview permission to admin roles only.
   */
  public function testUpdate8001GrantsOverviewPermission(): void {
    $admin = Role::create(['id' => 'glossary_admin', 'label' => 'Glossary admin']);
    $admin->grantPermission('administer deepl_glossary entities');
    $admin->save();

    $other = Role::create(['id' => 'plain', 'label' => 'Plain']);
    $other->save();

    tmgmt_deepl_glossary_update_8001();

    // The admin role gains the new overview permission.
    $admin = Role::load('glossary_admin');
    $this->assertInstanceOf(Role::class, $admin);
    $this->assertTrue($admin->hasPermission('access deepl_glossary overview'));
    // A role without the administer permission is left untouched.
    $other = Role::load('plain');
    $this->assertInstanceOf(Role::class, $other);
    $this->assertFalse($other->hasPermission('access deepl_glossary overview'));
  }

}
