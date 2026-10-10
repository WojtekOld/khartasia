<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\entity_test\Entity\EntityTestComputedField;
use Drupal\node\Entity\Node;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\ui_patterns_test\Plugin\UiPatterns\Source\Cacheable;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The access cacheability of sources reaches the render context.
 *
 * Pins the last hop: ComponentElementBuilder::build() runs as a pre_render
 * of the component element, so what it merges into #cache is bubbled by the
 * renderer, denied things included.
 *
 * @internal
 */
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class ComponentCacheabilityTest extends SourcePluginsTestBase {

  use UserCreationTrait;

  private const REFERENCE_CONTEXT_ID = 'entity_reference:node:page:field_related:node:page';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createNodeReferenceField('field_related');
    $this->setUpCurrentUser([], ['access content']);
  }

  /**
   * The cache tag of a referenced node the user cannot see is kept.
   */
  #[DataProvider('provideDeltas')]
  public function testReferenceFormatterDeniedEntity(?int $delta): void {
    [$host, $secret] = $this->createNodeWithSecret();
    $bubbled = $this->renderComponent([
      'source_id' => 'field_formatter:node:page:field_related',
      'source' => ['type' => 'entity_reference_label', 'settings' => []],
    ], $host, $delta);
    self::assertStringNotContainsString('Secret', $this->output);
    self::assertContains('node:' . $secret->id(), $bubbled->getCacheTags());
  }

  /**
   * The cache metadata of a computed field is kept.
   */
  #[DataProvider('provideDeltas')]
  public function testComputedFieldFormatter(?int $delta): void {
    // Enabled here: setUp() would add a field for each of its field types.
    $this->enableModules(['entity_test']);
    $this->installEntitySchema('entity_test_computed_field');
    $this->container->get('state')->set('entity_test_computed_integer_value', 4815);
    $entity = EntityTestComputedField::create();
    $entity->save();
    $bubbled = $this->renderComponent([
      'source_id' => 'field_formatter:entity_test_computed_field:entity_test_computed_field:computed_test_cacheable_integer_field',
      'source' => ['type' => 'number_integer', 'settings' => []],
    ], $entity, $delta);
    self::assertStringContainsString('4815', $this->output);
    self::assertContains('field:computed_test_cacheable_integer_field', $bubbled->getCacheTags());
    self::assertContains('headers:X-computed_test_cacheable_integer_field=1', $bubbled->getCacheContexts());
  }

  /**
   * The attachments a formatter sets on the whole field are kept.
   */
  #[DataProvider('provideDeltas')]
  public function testFormatterAttachments(?int $delta): void {
    $this->enableModules(['views', 'views_test_formatter']);
    $node = Node::create(['type' => 'page', 'title' => 'Host', 'field_integer' => [4815, 1623]]);
    $node->save();
    $bubbled = $this->renderComponent([
      'source_id' => 'field_formatter:node:page:field_integer',
      'source' => ['type' => 'number_unformatted_with_attachment', 'settings' => []],
    ], $node, $delta);
    self::assertStringContainsString('4815', $this->output);
    $attachments = $bubbled->getAttachments();
    self::assertContains('foo/fake_library', $attachments['library'] ?? []);
    self::assertSame($node->id(), $attachments['drupalSettings']['AttachmentIntegerFormatter'][$node->id()] ?? NULL);
  }

  /**
   * All the items, then only the first one.
   */
  public static function provideDeltas(): array {
    return ['all items' => [NULL], 'one item' => [0]];
  }

  /**
   * A denied referenced entity leaves its tag and the permissions context.
   */
  public function testReferencedEntity(): void {
    [$host, $secret] = $this->createNodeWithSecret();
    $bubbled = $this->renderComponent([
      'source_id' => 'entity_reference',
      'source' => [
        'derivable_context' => self::REFERENCE_CONTEXT_ID,
        self::REFERENCE_CONTEXT_ID => [
          'value' => [
            'sources' => [
              ['source_id' => 'field_property:node:title:value', 'source' => []],
            ],
          ],
        ],
      ],
    ], $host);
    self::assertContains('node:' . $secret->id(), $bubbled->getCacheTags());
    self::assertContains('user.permissions', $bubbled->getCacheContexts());
    self::assertStringNotContainsString('Secret', $this->output);
  }

  /**
   * A denied block leaves its access cacheability and no markup.
   */
  public function testDeniedBlock(): void {
    $bubbled = $this->renderComponent([
      'source_id' => 'block',
      'source' => ['plugin_id' => 'ui_patterns_test_denied_block'],
    ]);
    self::assertContains('ui_patterns_test:denied', $bubbled->getCacheTags());
    self::assertContains('user.permissions', $bubbled->getCacheContexts());
    self::assertStringNotContainsString('denied block content', $this->output);
  }

  /**
   * An inaccessible entity link leaves the permissions context.
   */
  public function testInaccessibleLink(): void {
    $node = Node::create(['type' => 'page', 'title' => 'Host']);
    $node->save();
    $bubbled = $this->renderComponent(['source_id' => 'entity_link', 'source' => ['template' => 'edit-form']], $node);
    self::assertContains('user.permissions', $bubbled->getCacheContexts());
    self::assertStringNotContainsString('/edit', $this->output);
  }

  /**
   * What a source adds while computing its value reaches the page.
   *
   * Sources of other modules rely on it, wherever the source is used.
   */
  #[DataProvider('provideCustomSourcePlaces')]
  public function testCustomSource(array $configuration): void {
    $target = Node::create(['type' => 'page', 'title' => 'Target']);
    $target->save();
    $host = Node::create(['type' => 'page', 'title' => 'Host', 'field_related' => [$target->id()]]);
    $host->save();
    $bubbled = $this->renderConfiguration($configuration, $host);
    self::assertStringContainsString(Cacheable::OUTPUT, $this->output);
    self::assertContains(Cacheable::CONTEXT, $bubbled->getCacheContexts());
    self::assertContains(Cacheable::TAG, $bubbled->getCacheTags());
    self::assertSame(Cacheable::MAX_AGE, $bubbled->getCacheMaxAge());
  }

  /**
   * The custom source as a prop, in a slot, nested, and under a derivation.
   */
  public static function provideCustomSourcePlaces(): array {
    $source = ['source_id' => 'cacheable', 'source' => []];
    return [
      'prop' => [['props' => ['string' => $source]]],
      'slot' => [['slots' => ['slot' => ['sources' => [$source]]]]],
      'nested component' => [
        [
          'slots' => [
            'slot' => [
              'sources' => [
                [
                  'source_id' => 'component',
                  'source' => [
                    'component' => [
                      'component_id' => 'ui_patterns_test:test-component',
                      'props' => ['string' => $source],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'referenced entity' => [
        [
          'props' => [
            'string' => [
              'source_id' => 'entity_reference',
              'source' => [
                'derivable_context' => self::REFERENCE_CONTEXT_ID,
                self::REFERENCE_CONTEXT_ID => ['value' => $source],
              ],
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * A render array given to a string prop keeps its cache metadata.
   */
  public function testRenderArrayInStringProp(): void {
    $bubbled = $this->renderConfiguration([
      'props' => ['string' => ['source_id' => 'cacheable', 'source' => ['render_array' => TRUE]]],
    ]);
    self::assertStringContainsString(Cacheable::OUTPUT, $this->output);
    self::assertContains(Cacheable::RENDER_ARRAY_TAG, $bubbled->getCacheTags());
  }

  /**
   * Sources keep the cache metadata of the data they read.
   */
  #[DataProvider('provideSources')]
  public function testSourceCacheMetadata(string $prop_id, array $source, string $expected): void {
    $target = Node::create(['type' => 'page', 'title' => 'Target']);
    $target->save();
    $entity = Node::create([
      'type' => 'page',
      'title' => 'Host',
      'field_text_1' => ['value' => 'Some text', 'format' => 'plain_text'],
      'field_related' => [$target->id()],
    ]);
    $entity->save();
    $bubbled = $this->renderConfiguration(['props' => [$prop_id => $source]], $entity);
    self::assertContains($expected, \array_merge($bubbled->getCacheContexts(), $bubbled->getCacheTags()));
  }

  /**
   * Sources whose data carries cache metadata.
   */
  public static function provideSources(): array {
    return [
      'breadcrumb' => ['links', ['source_id' => 'breadcrumb', 'source' => []], 'url.path.parent'],
      'token in a string prop' => [
        'string',
        ['source_id' => 'token', 'source' => ['value' => '[site:name]']],
        'config:system.site',
      ],
      'processed text' => [
        'string',
        ['source_id' => 'field_property:node:field_text_1:processed', 'source' => []],
        'config:filter.format.plain_text',
      ],
      'menu of a referenced entity' => [
        'links',
        [
          'source_id' => 'entity_reference',
          'source' => [
            'derivable_context' => self::REFERENCE_CONTEXT_ID,
            self::REFERENCE_CONTEXT_ID => ['value' => ['source_id' => 'menu', 'source' => ['menu' => 'main']]],
          ],
        ],
        'config:system.menu.main',
      ],
    ];
  }

  /**
   * The link of an entity keeps the entity's cache tag.
   *
   * Own account: access adds no user tag, only the source does.
   */
  public function testEntityLinkCacheMetadata(): void {
    $user = User::load($this->container->get('current_user')->id());
    $bubbled = $this->renderConfiguration([
      'props' => ['url' => ['source_id' => 'entity_link', 'source' => ['template' => 'canonical']]],
    ], $user);
    self::assertContains('user:' . $user->id(), $bubbled->getCacheTags());
  }

  /**
   * The rendered markup of the last renderComponent() call.
   */
  private string $output = '';

  /**
   * A node that links to an unpublished node.
   *
   * @return \Drupal\node\Entity\Node[]
   *   The node, then the unpublished node.
   */
  private function createNodeWithSecret(): array {
    $secret = Node::create(['type' => 'page', 'title' => 'Secret', 'status' => 0]);
    $secret->save();
    $host = Node::create(['type' => 'page', 'title' => 'Host', 'field_related' => [$secret->id()]]);
    $host->save();
    return [$host, $secret];
  }

  /**
   * Renders the test component with one source in its slot.
   *
   * @return \Drupal\Core\Render\BubbleableMetadata
   *   What the renderer bubbled for the whole element.
   */
  private function renderComponent(array $source, ?ContentEntityInterface $entity = NULL, ?int $delta = NULL): BubbleableMetadata {
    return $this->renderConfiguration(['slots' => ['slot' => ['sources' => [$source]]]], $entity, $delta);
  }

  /**
   * Renders the test component with a whole configuration.
   *
   * @return \Drupal\Core\Render\BubbleableMetadata
   *   What the renderer bubbled for the whole element.
   */
  private function renderConfiguration(array $configuration, ?EntityInterface $entity = NULL, ?int $delta = NULL): BubbleableMetadata {
    $contexts = $entity ? ['entity' => EntityContext::fromEntity($entity)] : [];
    if ($delta !== NULL) {
      $contexts['ui_patterns:field:index'] = new Context(new ContextDefinition('integer'), $delta);
    }
    $element = [
      '#type' => 'component',
      '#component' => 'ui_patterns_test:test-component',
      '#source_contexts' => $contexts,
      '#ui_patterns' => $configuration,
    ];
    $renderer = $this->container->get('renderer');
    $context = new RenderContext();
    $this->output = (string) $renderer->executeInRenderContext($context, static fn () => $renderer->render($element));
    return $context->pop();
  }

}
