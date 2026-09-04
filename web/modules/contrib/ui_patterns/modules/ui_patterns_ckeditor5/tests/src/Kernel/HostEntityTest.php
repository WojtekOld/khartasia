<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_ckeditor5\Kernel;

use Drupal\node\Entity\Node;
use Drupal\ui_patterns_ckeditor5\HostEntity;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The host entity posted by the editor becomes the entity context, or not.
 *
 * Pins the trust boundary of the request values: only an entity the
 * current user may view, or may create, reaches the sources.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_ckeditor5')]
#[RunTestsInSeparateProcesses]
final class HostEntityTest extends CKEditor5KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createPageType();
  }

  /**
   * A viewable saved entity is the context; a hidden one is not.
   */
  public function testSavedEntity(): void {
    \user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, ['access content']);
    $published = Node::create(['type' => 'page', 'title' => 'Published']);
    $published->save();
    $unpublished = Node::create(['type' => 'page', 'title' => 'Unpublished', 'status' => 0]);
    $unpublished->save();

    $contexts = $this->hostEntity()->getContextsFromValues([
      'entity_type' => 'node',
      'entity_id' => $published->id(),
    ]);
    self::assertSame(['entity', 'bundle'], \array_keys($contexts));
    self::assertSame($published->id(), $contexts['entity']->getContextValue()->id());
    self::assertSame('page', $contexts['bundle']->getContextValue());
    self::assertSame([], $this->hostEntity()->getContextsFromValues([
      'entity_type' => 'node',
      'entity_id' => $unpublished->id(),
    ]));
  }

  /**
   * An unsaved host is a sample entity of its bundle, given create access.
   */
  public function testUnsavedEntity(): void {
    // Node create access also needs 'access content'.
    \user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, ['access content', 'create page content']);
    $contexts = $this->hostEntity()->getContextsFromValues([
      'entity_type' => 'node',
      'entity_id' => '',
      'entity_bundle' => 'page',
    ]);
    self::assertSame(['entity', 'bundle'], \array_keys($contexts));
    $entity = $contexts['entity']->getContextValue();
    self::assertTrue($entity->isNew());
    self::assertSame('page', $entity->bundle());
    self::assertSame('page', $contexts['bundle']->getContextValue());
    $without_bundle = ['entity_type' => 'node', 'entity_id' => ''];
    self::assertSame([], $this->hostEntity()->getContextsFromValues($without_bundle));
  }

  /**
   * Without create access there is no unsaved host.
   */
  public function testUnsavedEntityWithoutAccess(): void {
    self::assertSame([], $this->hostEntity()->getContextsFromValues([
      'entity_type' => 'node',
      'entity_id' => '',
      'entity_bundle' => 'page',
    ]));
  }

  /**
   * Values that describe nothing give no context.
   */
  public function testUnknownValues(): void {
    self::assertSame([], $this->hostEntity()->getContextsFromValues([]));
    self::assertSame([], $this->hostEntity()->getContextsFromValues(['entity_type' => 'nope', 'entity_id' => 1]));
    self::assertSame([], $this->hostEntity()->getContextsFromValues(['entity_type' => 'node', 'entity_id' => 999]));
  }

  /**
   * The rendered host is a stack: an inner render restores the outer one.
   */
  public function testStack(): void {
    $outer = Node::create(['type' => 'page', 'title' => 'Outer']);
    $inner = Node::create(['type' => 'page', 'title' => 'Inner']);
    $host = $this->hostEntity();
    self::assertSame([], $host->getContexts());
    $host->push($outer);
    $host->push($inner);
    self::assertSame(['entity', 'bundle'], \array_keys($host->getContexts()));
    self::assertSame('Inner', $host->getContexts()['entity']->getContextValue()->label());
    $host->pop();
    self::assertSame('Outer', $host->getContexts()['entity']->getContextValue()->label());
    $host->push(NULL);
    self::assertSame([], $host->getContexts(), 'A text without entity hides the outer host.');
    $host->pop();
    $host->pop();
    self::assertSame([], $host->getContexts());
  }

  /**
   * The service.
   */
  private function hostEntity(): HostEntity {
    return $this->container->get('ui_patterns_ckeditor5.host_entity');
  }

}
