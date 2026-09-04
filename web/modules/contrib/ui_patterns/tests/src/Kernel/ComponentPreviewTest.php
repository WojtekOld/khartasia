<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\node\Entity\Node;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\ui_patterns_test\Plugin\UiPatterns\Source\PreviewAware;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The 'ui_patterns:in_preview' context reaches every preview aware source.
 *
 * @internal
 */
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class ComponentPreviewTest extends SourcePluginsTestBase {

  use UserCreationTrait;

  private const PREVIEW_CONTEXT = 'ui_patterns:in_preview';

  private const REFERENCE_CONTEXT_ID = 'entity_reference:node:page:field_related:node:page';

  private const SOURCE = ['source_id' => 'preview_aware', 'source' => []];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createNodeReferenceField('field_related');
    $this->setUpCurrentUser([], ['access content']);
  }

  /**
   * Without the context, a source is built for a real display.
   */
  public function testNoContext(): void {
    $output = $this->renderComponent(['props' => ['string' => self::SOURCE]], []);
    self::assertStringContainsString(PreviewAware::REAL_OUTPUT, $output);
    self::assertStringNotContainsString(PreviewAware::PREVIEW_OUTPUT, $output);
  }

  /**
   * A TRUE context puts the source in preview.
   */
  public function testPreviewContext(): void {
    $output = $this->renderComponent(['props' => ['string' => self::SOURCE]], $this->previewContexts(TRUE));
    self::assertStringContainsString(PreviewAware::PREVIEW_OUTPUT, $output);
  }

  /**
   * A FALSE context is a real display, not a preview.
   */
  public function testFalseContext(): void {
    $output = $this->renderComponent(['props' => ['string' => self::SOURCE]], $this->previewContexts(FALSE));
    self::assertStringContainsString(PreviewAware::REAL_OUTPUT, $output);
  }

  /**
   * A context with no value is a real display and keeps the prop.
   */
  public function testContextWithoutValue(): void {
    $contexts = [self::PREVIEW_CONTEXT => new Context(new ContextDefinition('boolean'))];
    $output = $this->renderComponent(['props' => ['string' => self::SOURCE]], $contexts);
    self::assertStringContainsString(PreviewAware::REAL_OUTPUT, $output);
  }

  /**
   * A component nested through a component source is in preview too.
   */
  public function testNestedComponent(): void {
    $nested = [
      'source_id' => 'component',
      'source' => [
        'component' => [
          'component_id' => 'ui_patterns_test:test-component',
          'props' => ['string' => self::SOURCE],
        ],
      ],
    ];
    $output = $this->renderComponent(['slots' => ['slot' => ['sources' => [$nested]]]], $this->previewContexts(TRUE));
    self::assertStringContainsString(PreviewAware::PREVIEW_OUTPUT, $output);
  }

  /**
   * A source nested under a derivable context is in preview too.
   */
  public function testNestedUnderDerivableContext(): void {
    $referenced = Node::create(['type' => 'page', 'title' => 'Referenced']);
    $referenced->save();
    $host = Node::create(['type' => 'page', 'title' => 'Host', 'field_related' => [$referenced->id()]]);
    $host->save();
    $nested = [
      'source_id' => 'entity_reference',
      'source' => [
        'derivable_context' => self::REFERENCE_CONTEXT_ID,
        self::REFERENCE_CONTEXT_ID => ['value' => ['sources' => [self::SOURCE]]],
      ],
    ];
    $contexts = $this->previewContexts(TRUE) + ['entity' => EntityContext::fromEntity($host)];
    $output = $this->renderComponent(['slots' => ['slot' => ['sources' => [$nested]]]], $contexts);
    self::assertStringContainsString(PreviewAware::PREVIEW_OUTPUT, $output);
  }

  /**
   * Source contexts holding the preview flag.
   */
  private function previewContexts(bool $in_preview): array {
    return [self::PREVIEW_CONTEXT => new Context(new ContextDefinition('boolean'), $in_preview)];
  }

  /**
   * Renders the test component and returns its markup.
   */
  private function renderComponent(array $configuration, array $contexts): string {
    $element = [
      '#type' => 'component',
      '#component' => 'ui_patterns_test:test-component',
      '#source_contexts' => $contexts,
      '#ui_patterns' => $configuration,
    ];
    return (string) $this->container->get('renderer')->renderRoot($element);
  }

}
