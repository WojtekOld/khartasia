<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel;

use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\node\Entity\Node;
use Drupal\Tests\user\Traits\UserCreationTrait;
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
   * A denied referenced entity leaves its tag and the permissions context.
   */
  public function testReferencedEntity(): void {
    $secret = Node::create(['type' => 'page', 'title' => 'Secret', 'status' => 0]);
    $secret->save();
    $host = Node::create([
      'type' => 'page',
      'title' => 'Host',
      'field_related' => [$secret->id()],
    ]);
    $host->save();
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
   * The rendered markup of the last renderComponent() call.
   */
  private string $output = '';

  /**
   * Renders the test component with one source in its slot.
   *
   * @return \Drupal\Core\Render\BubbleableMetadata
   *   What the renderer bubbled for the whole element.
   */
  private function renderComponent(array $source, ?Node $entity = NULL): BubbleableMetadata {
    $element = [
      '#type' => 'component',
      '#component' => 'ui_patterns_test:test-component',
      '#source_contexts' => $entity ? ['entity' => EntityContext::fromEntity($entity)] : [],
      '#ui_patterns' => ['slots' => ['slot' => ['sources' => [$source]]]],
    ];
    $renderer = $this->container->get('renderer');
    $context = new RenderContext();
    $this->output = (string) $renderer->executeInRenderContext($context, static fn () => $renderer->render($element));
    return $context->pop();
  }

}
