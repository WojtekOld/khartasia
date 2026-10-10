<?php

declare(strict_types=1);

namespace Drupal\Tests\term_glossary_per_node\Unit;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\term_glossary_per_node\TermGlossaryUtils;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the TermGlossaryUtils utility class.
 *
 * @group term_glossary
 */
#[CoversClass(TermGlossaryUtils::class)]
#[Group('term_glossary')]
class TermGlossaryUtilsTest extends UnitTestCase {

  /**
   * Creates a mock node with the glossary active field.
   *
   * @param mixed $value
   *   The term_glossary_active field value.
   *
   * @return \Drupal\node\NodeInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked node.
   */
  protected function createNodeWithGlossaryActive(mixed $value): NodeInterface {
    $field_list = new \stdClass();
    $field_list->value = $value;

    $node = $this->createMock(NodeInterface::class);
    $node->method('hasField')
      ->with('term_glossary_active')
      ->willReturn(TRUE);
    $node->method('get')
      ->with('term_glossary_active')
      ->willReturn($field_list);

    return $node;
  }

  /**
   * Tests isNodeGlossaryActive returns TRUE when field is set.
   *
   * Mocks a node with the term_glossary_active field present
   * and value=1. Verifies isNodeGlossaryActive() returns TRUE
   * (the value is cast to bool).
   */
  public function testIsNodeGlossaryActiveTrue(): void {
    $node = $this->createNodeWithGlossaryActive(1);
    $this->assertTrue(
      TermGlossaryUtils::isNodeGlossaryActive($node),
    );
  }

  /**
   * Tests isNodeGlossaryActive returns FALSE when field is 0.
   *
   * Mocks a node with the term_glossary_active field present
   * but value=0. Verifies isNodeGlossaryActive() returns
   * FALSE (0 cast to bool is FALSE).
   */
  public function testIsNodeGlossaryActiveFalse(): void {
    $node = $this->createNodeWithGlossaryActive(0);
    $this->assertFalse(
      TermGlossaryUtils::isNodeGlossaryActive($node),
    );
  }

  /**
   * Tests isNodeGlossaryActive returns FALSE with no field.
   *
   * Mocks a node where hasField('term_glossary_active')
   * returns FALSE (field not installed). Verifies the method
   * returns FALSE without attempting to read the field value.
   */
  public function testIsNodeGlossaryActiveNoField(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('hasField')
      ->with('term_glossary_active')
      ->willReturn(FALSE);

    $this->assertFalse(
      TermGlossaryUtils::isNodeGlossaryActive($node),
    );
  }

  /**
   * Tests hasEditGlossaryPermission with dedicated permission.
   *
   * Mocks a user with 'edit term glossary node display' but
   * without 'administer nodes'. Verifies the method returns
   * TRUE — the dedicated permission alone is sufficient.
   */
  public function testHasEditGlossaryPermissionGranted(): void {
    $user = $this->createMock(AccountProxyInterface::class);
    $user->method('hasPermission')
      ->willReturnMap([
        ['edit term glossary node display', TRUE],
        ['administer nodes', FALSE],
      ]);

    $this->assertTrue(
      TermGlossaryUtils::hasEditGlossaryPermission($user),
    );
  }

  /**
   * Tests hasEditGlossaryPermission with admin permission.
   *
   * Mocks a user without 'edit term glossary node display'
   * but with 'administer nodes'. Verifies the method returns
   * TRUE — the admin permission is an alternative grant.
   */
  public function testHasEditGlossaryPermissionAdmin(): void {
    $user = $this->createMock(AccountProxyInterface::class);
    $user->method('hasPermission')
      ->willReturnMap([
        ['edit term glossary node display', FALSE],
        ['administer nodes', TRUE],
      ]);

    $this->assertTrue(
      TermGlossaryUtils::hasEditGlossaryPermission($user),
    );
  }

  /**
   * Tests hasEditGlossaryPermission without any permission.
   *
   * Mocks a user with neither 'edit term glossary node
   * display' nor 'administer nodes'. Verifies the method
   * returns FALSE.
   */
  public function testHasEditGlossaryPermissionDenied(): void {
    $user = $this->createMock(AccountProxyInterface::class);
    $user->method('hasPermission')
      ->willReturn(FALSE);

    $this->assertFalse(
      TermGlossaryUtils::hasEditGlossaryPermission($user),
    );
  }

}
