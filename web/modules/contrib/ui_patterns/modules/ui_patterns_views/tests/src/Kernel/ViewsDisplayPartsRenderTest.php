<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_views\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
use Drupal\ui_patterns\SourcePluginBase;
use Drupal\ui_patterns\SourcePluginManager;
use Drupal\ui_patterns_views_test\Hook\UiPatternsViewsTestHooks;
use Drupal\views\Entity\View;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Drupal\views_ui\ViewUI;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Renders a whole view from its parts through a component.
 *
 * The views:display sources give a component every part of
 * views-view.html.twig.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_views')]
#[RunTestsInSeparateProcesses]
final class ViewsDisplayPartsRenderTest extends KernelTestBase {

  use UserCreationTrait;

  private const COMPONENT = 'ui_patterns_views_test:test-view';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'views',
    'views_ui',
    'ui_patterns',
    'ui_patterns_views',
    'ui_patterns_views_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system', 'views']);
    $this->setUpCurrentUser();
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $config = Yaml::decode((string) \file_get_contents(__DIR__ . '/../../fixtures/config/views.view.test.display.yml'));
    unset($config['uuid']);
    View::create($config)->save();
    // The more link, the exposed form action and the feed icon URL are built
    // from the view routes inside execute().
    \Drupal::service(RouteBuilderInterface::class)->rebuild();
  }

  /**
   * How to render a view with a component: a render array and sources.
   *
   * No 'views:display' requirement: it only filters the form's source list.
   */
  public function testRenderViewFromRenderArray(): void {
    $this->createNodes(['Node A', 'Node B', 'Node C']);
    $view = $this->executedView();
    $build = [
      '#type' => 'component',
      '#component' => self::COMPONENT,
      '#ui_patterns' => [
        'props' => [
          'title' => ['source_id' => 'view_title'],
        ],
        'slots' => [
          'header' => ['sources' => [['source_id' => 'view_header']]],
          'exposed' => ['sources' => [['source_id' => 'view_exposed']]],
          'attachment_before' => ['sources' => [['source_id' => 'view_attachment_before']]],
          'rows' => ['sources' => [['source_id' => 'view_rows']]],
          'empty' => ['sources' => [['source_id' => 'view_empty']]],
          'pager' => ['sources' => [['source_id' => 'view_pager']]],
          'attachment_after' => ['sources' => [['source_id' => 'view_attachment_after']]],
          'more' => ['sources' => [['source_id' => 'view_more']]],
          'footer' => ['sources' => [['source_id' => 'view_footer']]],
          'feed_icons' => ['sources' => [['source_id' => 'view_feed_icons']]],
        ],
      ],
      '#source_contexts' => [
        'ui_patterns_views:view' => new Context(new ContextDefinition('any'), $view),
      ],
    ];
    $this->renderBuild($build);
    $this->assertEveryPartRendered();
    self::assertSame(1, $this->executions('page_1'), 'The sources reuse the executed view.');
  }

  /**
   * How to render a view with a component: the view entity and a display ID.
   *
   * The sources execute the view themselves, once for all of them.
   */
  public function testRenderViewFromViewEntity(): void {
    $this->createNodes(['Node A', 'Node B', 'Node C']);
    $this->renderBuild($this->componentBuild([
      'ui_patterns_views:view_entity' => EntityContext::fromEntity(View::load('test')),
      'ui_patterns_views:display' => new Context(new ContextDefinition('string'), 'page_1'),
    ]));
    $this->assertEveryPartRendered();
    self::assertSame(1, $this->executions('page_1'), 'Ten sources, one execution.');
  }

  /**
   * A view open in the Views UI: its unsaved copy renders, and runs once.
   */
  public function testRenderViewFromViewEntityOpenInViewsUi(): void {
    $this->createNodes(['Node A', 'Node B', 'Node C']);
    $unsaved = clone View::load('test');
    $display = $unsaved->get('display');
    $display['default']['display_options']['title'] = 'Unsaved view title';
    $unsaved->set('display', $display);
    $this->container->get('tempstore.shared')->get('views')->set('test', new ViewUI($unsaved));

    $this->renderBuild($this->componentBuild([
      'ui_patterns_views:view_entity' => EntityContext::fromEntity(View::load('test')),
      'ui_patterns_views:display' => new Context(new ContextDefinition('string'), 'page_1'),
    ]));
    self::assertSame('Unsaved view title', \trim(\strip_tags($this->part('title'))));
    self::assertCount(2, $this->cssSelect('.test-view__rows .views-row'));
    self::assertSame(1, $this->executions('page_1'), 'Ten sources, one unsaved copy, one execution.');
  }

  /**
   * Without result, only the parts core keeps stay: no rows, no pager.
   */
  public function testEmptyResultShowsTheEmptyAreaOnly(): void {
    $this->renderBuild($this->componentBuild($this->executableContexts()));
    self::assertStringContainsString('Test empty text', $this->part('empty'));
    self::assertCount(0, $this->cssSelect('.test-view__rows .views-row'));
    self::assertCount(0, $this->cssSelect('.test-view__pager nav'));
    // The header area hides without result, the footer area does not.
    self::assertStringNotContainsString('Test header text', $this->part('header'));
    self::assertStringContainsString('Test footer text', $this->part('footer'));
  }

  /**
   * The rows come out as the style plugin of the display renders them.
   */
  public function testStyledRowsFollowTheStylePlugin(): void {
    $this->createNodes(['Node A', 'Node B', 'Node C']);
    $view = View::load('test');
    $display = $view->get('display');
    $display['default']['display_options']['style'] = [
      'type' => 'html_list',
      'options' => [
        'type' => 'ul',
        'class' => '',
        'wrapper_class' => 'item-list',
        'row_class' => '',
        'default_row_class' => TRUE,
      ],
    ];
    $view->set('display', $display);
    $view->save();
    $this->renderBuild($this->componentBuild($this->executableContexts()));
    self::assertCount(1, $this->cssSelect('.test-view__rows ul'));
    self::assertCount(2, $this->cssSelect('.test-view__rows ul > li'));
  }

  /**
   * The rows depend on the context.
   *
   * Given as they are in a style plugin, rendered by the style plugin for a
   * display.
   */
  public function testViewRowsFollowTheContext(): void {
    $this->createNodes(['Node A', 'Node B', 'Node C']);
    $renderer = $this->container->get(RendererInterface::class);
    $raw_rows = [['#markup' => 'raw-a'], ['#markup' => 'raw-b']];
    $style = RequirementsContext::addToContext(['views:style'], $this->executableContexts() + [
      'ui_patterns_views:rows' => new Context(new ContextDefinition('any'), $raw_rows),
    ]);
    $rows = $this->source('view_rows', $style)->getPropValue();
    self::assertIsArray($rows);
    $this->setRawContent((string) $renderer->renderInIsolation($rows));
    self::assertStringContainsString('raw-a', $this->getRawContent());
    self::assertCount(0, $this->cssSelect('.views-row'));

    $rows = $this->source('view_rows', $this->executableContexts())->getPropValue();
    self::assertIsArray($rows);
    $this->setRawContent((string) $renderer->renderInIsolation($rows));
    self::assertStringNotContainsString('raw-a', $this->getRawContent());
    self::assertCount(2, $this->cssSelect('.views-row'));
  }

  /**
   * The display cacheability core applies in render() reaches the source.
   */
  public function testDisplayCacheabilityReachesTheSource(): void {
    $source = $this->source('view_header', $this->executableContexts());
    $source->getPropValue();
    self::assertContains('config:views.view.test', $source->getCacheTags());
    self::assertContains('node_list', $source->getCacheTags());
  }

  /**
   * The display ID selects the display, and a running view wins over both.
   */
  public function testViewContextsPrecedence(): void {
    $entity_contexts = [
      'ui_patterns_views:view_entity' => EntityContext::fromEntity(View::load('test')),
      'ui_patterns_views:display' => new Context(new ContextDefinition('string'), 'attachment_2'),
    ];
    self::assertSame('Attachment after title', $this->source('view_title', $entity_contexts)->getPropValue());
    self::assertSame('Test view title', $this->source('view_title', $entity_contexts + $this->executableContexts())->getPropValue());
    self::assertSame('', $this->source('view_title', [])->getPropValue());
  }

  /**
   * Asserts every part of the fixture view sits in its slot.
   */
  private function assertEveryPartRendered(): void {
    self::assertSame('Test view title', \trim(\strip_tags($this->part('title'))));
    self::assertStringContainsString('Test header text', $this->part('header'));
    self::assertStringContainsString('Test footer text', $this->part('footer'));
    self::assertStringNotContainsString('Test empty text', $this->part('empty'));
    self::assertCount(2, $this->cssSelect('.test-view__rows .views-row'));
    self::assertStringContainsString('Node A', $this->part('rows'));
    self::assertStringContainsString('Node B', $this->part('rows'));
    self::assertStringNotContainsString('Node C', $this->part('rows'));
    self::assertCount(1, $this->cssSelect('.test-view__pager nav.pager'));
    self::assertCount(1, $this->cssSelect('.test-view__pager .pager__item--next'));
    self::assertCount(1, $this->cssSelect('.test-view__exposed form#views-exposed-form-test-page-1'));
    self::assertCount(1, $this->cssSelect('.test-view__exposed input[name="title"]'));
    self::assertStringContainsString('Test more text', $this->part('more'));
    self::assertCount(1, $this->cssSelect('.test-view__more .more-link a'));
    self::assertCount(1, $this->cssSelect('.test-view__attachment-before .test-attachment-before .views-row'));
    self::assertStringContainsString('Node A', $this->part('attachment-before'));
    self::assertCount(1, $this->cssSelect('.test-view__attachment-after .test-attachment-after .views-row'));
    $icons = $this->cssSelect('.test-view__feed-icons a.feed-icon');
    self::assertCount(1, $icons);
    self::assertStringContainsString('test.xml', (string) $icons[0]['href']);
  }

  /**
   * The page display of the fixture view, executed the way a request does.
   */
  private function executedView(): ViewExecutable {
    $view = Views::getView('test');
    $view->setDisplay('page_1');
    $view->preExecute();
    $view->execute();
    return $view;
  }

  /**
   * The contexts a producer holding a running view hands over.
   */
  private function executableContexts(): array {
    return [
      'ui_patterns_views:view' => new Context(new ContextDefinition('any'), $this->executedView()),
    ];
  }

  /**
   * The test-view component with one views:display source per slot.
   */
  private function componentBuild(array $source_contexts): array {
    return [
      '#type' => 'component',
      '#component' => self::COMPONENT,
      '#ui_patterns' => [
        'props' => ['title' => ['source_id' => 'view_title']],
        'slots' => \array_map(
          static fn (string $source_id): array => ['sources' => [['source_id' => $source_id]]],
          UiPatternsViewsTestHooks::SLOT_SOURCES,
        ),
      ],
      '#source_contexts' => $source_contexts,
    ];
  }

  /**
   * Builds a slot source as a component would.
   */
  private function source(string $source_id, array $contexts): SourcePluginBase {
    $configuration = SourcePluginBase::buildConfiguration('slot', [], [], $contexts);
    $source = $this->container->get(SourcePluginManager::class)->createInstance($source_id, $configuration);
    self::assertInstanceOf(SourcePluginBase::class, $source);
    return $source;
  }

  /**
   * Renders a build and keeps its markup for the assertions.
   */
  private function renderBuild(array $build): void {
    $this->setRawContent((string) $this->container->get(RendererInterface::class)->renderInIsolation($build));
  }

  /**
   * The markup of one slot wrapper of the component.
   */
  private function part(string $part): string {
    $elements = $this->cssSelect('.test-view__' . $part);
    self::assertCount(1, $elements, $part);
    return (string) $elements[0]->asXML();
  }

  /**
   * How many times a display of the fixture view ran.
   */
  private function executions(string $display_id): int {
    return $this->container->get('state')->get(UiPatternsViewsTestHooks::EXECUTIONS, [])['test:' . $display_id] ?? 0;
  }

  /**
   * Creates published page nodes.
   */
  private function createNodes(array $titles): void {
    foreach ($titles as $title) {
      Node::create(['type' => 'page', 'title' => $title, 'status' => 1])->save();
    }
  }

}
