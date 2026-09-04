<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_ckeditor5\Kernel;

use Drupal\Core\Render\RenderContext;
use Drupal\filter\FilterPluginCollection;
use Drupal\filter\FilterProcessResult;
use Drupal\node\Entity\Node;
use Drupal\ui_patterns_ckeditor5\Plugin\Filter\ComponentEmbed;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The component_embed filter renders the tags the editor saves.
 *
 * Pins what a page shows for the saved text: the component with its
 * configuration, nothing for a tag that can not render, and the entity
 * context the sources get from the entity being rendered.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_ckeditor5')]
#[RunTestsInSeparateProcesses]
final class ComponentEmbedFilterTest extends CKEditor5KernelTestBase {

  /**
   * The component renders with its configuration, around the other text.
   */
  public function testRendersComponent(): void {
    $tag = $this->componentTag(self::COMPONENT, [
      'props' => ['string' => $this->propSource('textfield', ['value' => 'Hello'])],
    ]);
    $result = $this->process('<p>before</p>' . $tag . '<p>after</p>');
    $html = $result->getProcessedText();
    self::assertStringContainsString('<p>before</p><div class="drupal-component">', $html);
    self::assertStringContainsString('</div><p>after</p>', $html);
    self::assertStringContainsString('ui-patterns-test-component', $html);
    self::assertMatchesRegularExpression('#ui-patterns-props-string">\s*Hello\s*<#', $html);
    self::assertStringNotContainsString('<drupal-component', $html);
    self::assertContains('core/components.ui_patterns_test--test-component', $result->getAttachments()['library'], 'The component library reaches the filter result.');
  }

  /**
   * A tag that can not render leaves nothing, the rest of the text stays.
   */
  public function testUnknownAndMalformedTags(): void {
    $unknown = '<drupal-component data-component-id="ui_patterns_test:missing"></drupal-component>';
    // A prop configuration must be an array: the element builder throws.
    $malformed = $this->componentTag(self::COMPONENT, ['props' => ['string' => 'not a source']]);
    $html = $this->process('<p>a</p>' . $unknown . '<p>b</p>' . $malformed . '<p>c</p>')->getProcessedText();
    self::assertSame('<p>a</p><p>b</p><p>c</p>', \trim($html));
  }

  /**
   * Settings that are not JSON are ignored, the component still renders.
   */
  public function testInvalidSettings(): void {
    $html = $this->process('<drupal-component data-component-id="' . self::COMPONENT . '" data-component-settings="{oops"></drupal-component>')->getProcessedText();
    self::assertStringContainsString('ui-patterns-test-component', $html);
  }

  /**
   * Text without the tag goes through untouched.
   */
  public function testNoTag(): void {
    $text = '<p>Nothing to <em>embed</em></p>';
    self::assertSame($text, $this->process($text)->getProcessedText());
  }

  /**
   * On a rendered entity, the sources get that entity as context.
   *
   * The token source needs the entity context; a title token in a body
   * proves the entity reached the filter through the display build.
   */
  public function testEntityContext(): void {
    \user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, ['access content']);
    $this->createPageType();
    $tag = $this->componentTag(self::COMPONENT, [
      'props' => ['string' => $this->propSource('token', ['value' => '[node:title]'])],
    ]);
    $node = Node::create([
      'type' => 'page',
      'title' => 'The host title',
      'body' => ['value' => '<p>Body</p>' . $tag, 'format' => self::FORMAT],
    ]);
    $node->save();
    $build = $this->container->get('entity_type.manager')->getViewBuilder('node')->view($node, 'full');
    $html = (string) $this->container->get('renderer')->renderRoot($build);
    self::assertMatchesRegularExpression('#ui-patterns-props-string">\s*The host title\s*<#', $html);
    self::assertSame([], $this->container->get('ui_patterns_ckeditor5.host_entity')->getContexts(), 'The host is released after the render.');
  }

  /**
   * A component outside the allowed list leaves nothing, the others render.
   *
   * A component replacing an allowed one is allowed too.
   */
  public function testAllowedComponents(): void {
    $this->setAllowedComponents(['ui_patterns_test:test-form-component']);
    $filter = $this->filter();
    self::assertFalse($filter->isComponentAllowed(self::COMPONENT));
    self::assertTrue($filter->isComponentAllowed('ui_patterns_test:test-form-component'));
    self::assertTrue($filter->isComponentAllowed('ui_patterns_test:test-form-component-replaced'));
    self::assertFalse($filter->isComponentAllowed('ui_patterns_test:missing'));

    $html = $this->process('<p>a</p>' . $this->componentTag(self::COMPONENT) . $this->componentTag('ui_patterns_test:test-form-component'))->getProcessedText();
    self::assertStringNotContainsString('ui-patterns-test-component', $html);
    self::assertStringContainsString('<p>a</p><div class="drupal-component">', $html);

    $this->setAllowedComponents([]);
    self::assertTrue($this->filter()->isComponentAllowed(self::COMPONENT));
    self::assertStringContainsString('ui-patterns-test-component', $this->process($this->componentTag(self::COMPONENT))->getProcessedText());
  }

  /**
   * A nested component outside the allowed list renders nothing.
   */
  public function testNestedComponents(): void {
    $this->setAllowedComponents([self::COMPONENT]);
    $nested = static fn (string $component_id): array => [
      'slots' => [
        'slot' => [
          'sources' => [
            [
              'source_id' => 'component',
              'source' => [
                'component' => [
                  'component_id' => $component_id,
                  'props' => ['string' => ['source_id' => 'textfield', 'source' => ['value' => 'Nested']]],
                ],
              ],
            ],
          ],
        ],
      ],
    ];
    self::assertStringNotContainsString('Nested', $this->process($this->componentTag(self::COMPONENT, $nested('ui_patterns_test:test-form-component')))->getProcessedText());
    self::assertStringContainsString('Nested', $this->process($this->componentTag(self::COMPONENT, $nested(self::COMPONENT)))->getProcessedText());
  }

  /**
   * Saves the allowed components of the test format.
   */
  private function setAllowedComponents(array $component_ids): void {
    $format = $this->container->get('entity_type.manager')->getStorage('filter_format')->load(self::FORMAT);
    $format->setFilterConfig('component_embed', [
      'status' => TRUE,
      'weight' => 100,
      'settings' => ['allowed_components' => $component_ids],
    ]);
    $format->save();
  }

  /**
   * The component_embed filter of the test format.
   */
  private function filter(): ComponentEmbed {
    $filters = $this->container->get('entity_type.manager')->getStorage('filter_format')->load(self::FORMAT)->filters();
    self::assertInstanceOf(FilterPluginCollection::class, $filters);
    $filter = $filters->get('component_embed');
    self::assertInstanceOf(ComponentEmbed::class, $filter);
    return $filter;
  }

  /**
   * Runs the text through the component_embed filter of the test format.
   */
  private function process(string $text): FilterProcessResult {
    $filter = $this->filter();
    return $this->container->get('renderer')->executeInRenderContext(new RenderContext(), static fn () => $filter->process($text, 'en'));
  }

}
