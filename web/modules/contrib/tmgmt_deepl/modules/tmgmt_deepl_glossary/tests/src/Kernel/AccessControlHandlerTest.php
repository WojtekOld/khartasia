<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Kernel;

use Drupal\Core\Entity\EntityAccessControlHandlerInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossary;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Tests the AccessControlHandler.
 *
 * @covers \Drupal\tmgmt_deepl_glossary\AccessControlHandler
 * @group tmgmt_deepl_glossary
 */
class AccessControlHandlerTest extends DeeplGlossaryKernelTestBase {

  /**
   * The access control handler.
   *
   * @var \Drupal\Core\Entity\EntityAccessControlHandlerInterface
   */
  protected EntityAccessControlHandlerInterface $accessControlHandler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->accessControlHandler = $this->container->get('entity_type.manager')->getAccessControlHandler('deepl_ml_glossary');
  }

  /**
   * Data provider for testAccess.
   */
  public static function dataProviderTestAccess(): array {
    return [
      ['view', ['access deepl_glossary overview'], TRUE],
      ['view', [], FALSE],
      ['update', ['edit deepl_glossary entities'], TRUE],
      ['update', ['edit deepl_glossary glossary entries'], TRUE],
      ['update',
        [
          'edit deepl_glossary entities',
          'edit deepl_glossary glossary entries',
        ],
        TRUE,
      ],
      ['update', [], FALSE],
      ['delete', ['delete deepl_glossary entities'], TRUE],
      ['delete', [], FALSE],
    ];
  }

  /**
   * Tests the method ::checkAccess.
   *
   * @dataProvider dataProviderTestAccess
   */
  public function testAccess(string $operation, array $permissions, bool $expected_result): void {
    // Create user account with given permissions.
    $account = $this->createUserWithPermissions($permissions);

    // Create a DeeplGlossary entity.
    $glossary = DeeplMultilingualGlossary::create([
      'name' => $this->randomMachineName(),
      'tmgmt_translator' => 'dummy_translator',
    ]);
    $glossary->save();

    $access = $this->accessControlHandler->access($glossary, $operation, $account, TRUE);

    $this->assertEquals($expected_result, $access->isAllowed());
  }

  /**
   * Data provider for testAccess.
   */
  public static function dataProviderTestCheckCreateAccess(): array {
    return [
      [['add deepl_glossary entities'], TRUE],
      [[], FALSE],
    ];
  }

  /**
   * Tests the method ::checkCreateAccess.
   *
   * @dataProvider dataProviderTestCheckCreateAccess
   */
  public function testCheckCreateAccess(array $permissions, bool $expected_result): void {
    // Create user account with given permissions.
    $account = $this->createUserWithPermissions($permissions);

    // Call createAccess and check the result.
    $access = $this->accessControlHandler->createAccess('deepl_ml_glossary', $account, [], TRUE);
    $this->assertEquals($expected_result, $access->isAllowed());
  }

  /**
   * Creates a user with a random ID and given permissions.
   *
   * @param array $permissions
   *   An array of permission strings to assign to the user.
   *
   * @return \Drupal\user\UserInterface
   *   The created user entity.
   */
  protected function createUserWithPermissions(array $permissions): UserInterface {
    $role = Role::create([
      'id' => $this->randomMachineName(),
      'label' => $this->randomString(),
    ]);
    $role->set('permissions', $permissions);
    $role->save();

    $account = User::create([
      'name' => $this->randomMachineName(),
      'uid' => rand(2, 100),
    ]);

    $account->addRole((string) $role->id());
    $account->save();

    return $account;
  }

}
