<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\DerivableContext;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\ui_patterns\DerivableContextPluginBase;
use Drupal\ui_patterns\DerivableContextPluginManager;
use Drupal\ui_patterns\SourcePluginManager;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Referenced entities are exposed only when the user can view them.
 *
 * Mirrors what core does for entity reference formatters: the referenced
 * entity is translated for the display language, then filtered by 'view'
 * access. The cacheability side is pinned by ComponentCacheabilityTest.
 *
 * @internal
 */
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class EntityReferencedDerivableContextTest extends SourcePluginsTestBase {

  use UserCreationTrait;

  private const DERIVABLE_CONTEXT_ID = 'entity_reference:node:page:field_related:node:page';

  private const FIELD_NAME = 'field_related';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['language'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['language']);
    ConfigurableLanguage::createFromLangcode('fr')->save();
    $this->createNodeReferenceField(self::FIELD_NAME, translatable: FALSE);
    $this->container->get(SourcePluginManager::class)->clearCachedDefinitions();
    $this->container->get(DerivableContextPluginManager::class)->clearCachedDefinitions();
  }

  /**
   * An unpublished referenced entity is hidden from users who cannot view it.
   */
  public function testInaccessibleReferencedEntitiesAreSkipped(): void {
    $a = $this->createPage('A', TRUE);
    $b = $this->createPage('B', FALSE);
    $c = $this->createPage('C', TRUE);
    $host = $this->createPage('Host', TRUE, [$a, $b, $c]);

    $this->setUpCurrentUser([], ['access content']);
    self::assertNotSame('1', (string) $this->container->get('current_user')->id(), 'Not the superuser.');
    self::assertFalse($b->access('view'), 'The current user cannot view the unpublished node.');

    self::assertSame([$a->id(), $c->id()], $this->derivedEntityIds($host), 'Only viewable referenced entities are exposed.');
    self::assertCount(3, $host->get(self::FIELD_NAME), 'Filtering does not touch the field data.');

    $this->setCurrentUser(User::load(1));
    self::assertSame([$a->id(), $b->id(), $c->id()], $this->derivedEntityIds($host), 'The superuser sees every referenced entity.');
  }

  /**
   * The field index context is a field delta: a denied delta exposes nothing.
   */
  public function testFieldIndexIsTheFieldDelta(): void {
    $a = $this->createPage('A', TRUE);
    $b = $this->createPage('B', FALSE);
    $c = $this->createPage('C', TRUE);
    $host = $this->createPage('Host', TRUE, [$a, $b, $c]);
    $this->setUpCurrentUser([], ['access content']);

    self::assertSame([], $this->derivedEntityIds($host, ['ui_patterns:field:index' => $this->context(1)]), 'A denied delta exposes nothing.');
    self::assertSame([$c->id()], $this->derivedEntityIds($host, ['ui_patterns:field:index' => $this->context(2)]), 'The delta after a denied one still resolves to its own entity.');
  }

  /**
   * Access is checked on the translation that will be displayed.
   */
  public function testAccessIsCheckedOnTheDisplayedTranslation(): void {
    $referenced = $this->createPage('Referenced EN', TRUE);
    $referenced->addTranslation('fr', ['title' => 'Referenced FR', 'status' => 0])->save();
    $host = $this->createPage('Host EN', TRUE, [$referenced]);
    $host->addTranslation('fr', ['title' => 'Host FR'])->save();
    $this->setUpCurrentUser([], ['access content']);

    self::assertSame(['en'], $this->derivedLangcodes($host), 'The English display gets the published English translation.');
    self::assertSame([], $this->derivedLangcodes($host, ['ui_patterns:lang_code' => $this->context('fr')]), 'The French display gets nothing while the French translation is unpublished.');
    self::assertSame([], $this->derivedLangcodes($host->getTranslation('fr')), 'The host translation language is used when no display language is given.');

    $referenced->getTranslation('fr')->setPublished()->save();
    // What a new request would see: the host loaded again, and no access
    // result kept from before publishing.
    $entity_type_manager = $this->container->get('entity_type.manager');
    $entity_type_manager->getAccessControlHandler('node')->resetCache();
    $host = $entity_type_manager->getStorage('node')->loadUnchanged($host->id());
    self::assertSame(['fr'], $this->derivedLangcodes($host->getTranslation('fr')), 'The French display gets the French translation once published.');
  }

  /**
   * Creates a page, optionally referencing nodes.
   *
   * @param string $title
   *   The title.
   * @param bool $published
   *   Whether the page is published.
   * @param \Drupal\node\NodeInterface[] $references
   *   Nodes to reference, in field delta order.
   */
  private function createPage(string $title, bool $published, array $references = []): NodeInterface {
    $node = Node::create([
      'type' => 'page',
      'title' => $title,
      'status' => $published,
      self::FIELD_NAME => \array_map(static fn (NodeInterface $node) => ['target_id' => $node->id()], $references),
    ]);
    $node->save();
    return $node;
  }

  /**
   * Wraps a value in a context.
   */
  private function context(mixed $value): Context {
    return new Context(new ContextDefinition('any'), $value);
  }

  /**
   * The referenced entities derivable context for a host entity.
   */
  private function derivableContext(EntityInterface $host, array $extra_contexts = []): DerivableContextPluginBase {
    $contexts = ['entity' => EntityContext::fromEntity($host)] + $extra_contexts;
    $plugin = $this->container->get(DerivableContextPluginManager::class)->createInstance(self::DERIVABLE_CONTEXT_ID, DerivableContextPluginBase::buildConfiguration($contexts));
    self::assertInstanceOf(DerivableContextPluginBase::class, $plugin);
    return $plugin;
  }

  /**
   * The entities exposed by the derived contexts, in order.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The entities.
   */
  private function derivedEntities(EntityInterface $host, array $extra_contexts = []): array {
    $entities = [];
    foreach ($this->derivableContext($host, $extra_contexts)->getDerivedContexts() as $contexts) {
      $entities[] = $contexts['entity']->getContextValue();
    }
    return $entities;
  }

  /**
   * The ids of the entities exposed by the derived contexts, in order.
   */
  private function derivedEntityIds(EntityInterface $host, array $extra_contexts = []): array {
    return \array_map(static fn (EntityInterface $entity) => $entity->id(), $this->derivedEntities($host, $extra_contexts));
  }

  /**
   * The languages of the entities exposed by the derived contexts, in order.
   */
  private function derivedLangcodes(EntityInterface $host, array $extra_contexts = []): array {
    return \array_map(static fn (EntityInterface $entity) => $entity->language()->getId(), $this->derivedEntities($host, $extra_contexts));
  }

}
