<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_views\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
use Drupal\ui_patterns_views\Hook\UiPatternsViewsHooks;
use Drupal\ui_patterns_views\Plugin\views\display_extender\ComponentDisplayExtender;
use Drupal\ui_patterns_views_test\Hook\UiPatternsViewsTestHooks;
use Drupal\views\Entity\View;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * A display configured with a component renders through it.
 *
 * The component replaces the views_view element and carries the view
 * classes. Set on the default display, it reaches the displays that do not
 * override it.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_views')]
#[RunTestsInSeparateProcesses]
final class ComponentDisplayExtenderTest extends KernelTestBase {

  use UserCreationTrait;

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
    // A kernel test installs config without running hook_install().
    $this->config('views.settings')->set('display_extenders', ['ui_patterns'])->save();
    $config = Yaml::decode((string) \file_get_contents(__DIR__ . '/../../fixtures/config/views.view.test.display.yml'));
    unset($config['uuid']);
    // On the default display: page_1 inherits it, the attachments override it.
    $config['display']['default']['display_options']['ui_patterns'] = self::componentConfiguration();
    foreach (['attachment_1', 'attachment_2'] as $display_id) {
      $config['display'][$display_id]['display_options']['defaults']['ui_patterns'] = FALSE;
      $config['display'][$display_id]['display_options']['ui_patterns'] = [
        'component_id' => NULL,
        'props' => [],
        'slots' => [],
      ];
    }
    View::create($config)->save();
    \Drupal::service(RouteBuilderInterface::class)->rebuild();
    foreach (['Node A', 'Node B', 'Node C'] as $title) {
      Node::create(['type' => 'page', 'title' => $title, 'status' => 1])->save();
    }
  }

  /**
   * The component is the output of the display, wrapper classes included.
   */
  public function testDisplayRendersThroughTheComponent(): void {
    $view = $this->executedView('page_1');
    $output = $view->render();
    self::assertIsArray($output);
    self::assertSame('component', $output['#type'] ?? NULL);
    $this->setRawContent((string) $this->container->get(RendererInterface::class)->renderInIsolation($output));

    self::assertCount(1, $this->cssSelect('.test-view.view.view-test.view-id-test.view-display-id-page_1[class*="js-view-dom-id-"]'));
    self::assertStringContainsString('Test view title', $this->part('title'));
    self::assertStringContainsString('Test header text', $this->part('header'));
    self::assertCount(2, $this->cssSelect('.test-view__rows .views-row'));
    self::assertCount(1, $this->cssSelect('.test-view__pager .pager__item--next'));
    self::assertCount(1, $this->cssSelect('.test-view__exposed input[name="title"]'));
    self::assertStringContainsString('Test more text', $this->part('more'));
    self::assertStringContainsString('Test footer text', $this->part('footer'));
    self::assertCount(1, $this->cssSelect('.test-view__attachment-before .test-attachment-before .views-row'));
    self::assertCount(1, $this->cssSelect('.test-view__feed-icons a.feed-icon'));
  }

  /**
   * A display overriding the section without a component renders as usual.
   */
  public function testOverrideWithoutComponentRendersAsUsual(): void {
    $view = View::load('test');
    $displays = $view->get('display');
    $displays['page_1']['display_options']['defaults']['ui_patterns'] = FALSE;
    $displays['page_1']['display_options']['ui_patterns'] = ['component_id' => NULL, 'props' => [], 'slots' => []];
    $view->set('display', $displays);
    $view->save();

    $output = $this->executedView('page_1')->render();
    self::assertIsArray($output);
    self::assertContains('views_view', (array) ($output['#theme'] ?? []));
    // The default display keeps its component.
    self::assertSame('component', $this->executedView('default')->render()['#type'] ?? NULL);
  }

  /**
   * The section defaults like the style: the Views UI offers the override.
   */
  public function testTheComponentIsDefaultableSection(): void {
    $view = Views::getView('test');
    $view->setDisplay('page_1');
    self::assertSame(['ui_patterns'], $view->getDisplay()->defaultableSections('ui_patterns'));
    self::assertTrue($view->getDisplay()->isDefaulted('ui_patterns'));
  }

  /**
   * A feed has a response of its own, a component cannot stand for it.
   */
  public function testDisplaysWithoutOutputAreLeftAlone(): void {
    self::assertTrue($this->extender('page_1')->isApplicable());
    self::assertFalse($this->extender('feed_1')->isApplicable());
    self::assertNull($this->extender('feed_1')->buildRenderable());
  }

  /**
   * The options form is the component form in the display context.
   */
  public function testOptionsFormCarriesTheDisplayContext(): void {
    $extender = $this->extender('page_1');
    $form_state = new FormState();
    $form_state->set('section', 'ui_patterns');
    $form = ['#title' => 'The '];
    $extender->buildOptionsForm($form, $form_state);

    self::assertSame('component_form', $form['ui_patterns']['#type'] ?? NULL);
    self::assertFalse($form['ui_patterns']['#component_required']);
    self::assertSame('ui_patterns_views_test:test-view', $form['ui_patterns']['#default_value']['component_id']);
    $contexts = $form['ui_patterns']['#source_contexts'];
    self::assertInstanceOf(RequirementsContext::class, $contexts['context_requirements']);
    self::assertTrue($contexts['context_requirements']->hasValue('views:display'));
    self::assertSame('page_1', $contexts['ui_patterns_views:display']->getContextValue());
    self::assertSame('test', $contexts['ui_patterns_views:view_entity']->getContextValue()->id());

    // Another section leaves the form alone, and saving stores the value.
    $other = [];
    $form_state->set('section', 'title');
    $extender->buildOptionsForm($other, $form_state);
    self::assertSame([], $other);
    $form_state->set('section', 'ui_patterns');
    $form_state->setValue('ui_patterns', [
      'component_id' => 'ui_patterns_views_test:test-view',
      'props' => [],
      'slots' => [],
    ]);
    $extender->submitOptionsForm($form, $form_state);
    self::assertSame([], $extender->getComponentSettings()['ui_patterns']['slots']);
    $categories = [];
    $options = [];
    $extender->optionsSummary($categories, $options);
    self::assertSame('ui_patterns_views_test:test-view', $options['ui_patterns']['value']);
  }

  /**
   * A Views UI dialog holding a component form takes the whole window.
   */
  public function testViewsUiDialogWithComponentFormIsLarge(): void {
    $dialog = static fn (string $class, string $data): array => [
      'command' => 'openDialog',
      'selector' => '#drupal-modal',
      'data' => $data,
      'dialogOptions' => ['classes' => ['ui-dialog' => $class], 'width' => '75%'],
    ];
    $commands = [
      ['command' => 'insert', 'data' => '<select name="ui_patterns[component_id]">'],
      $dialog('views-ui-dialog js-views-ui-dialog', '<select name="ui_patterns[component_id]">'),
      $dialog('views-ui-dialog js-views-ui-dialog', '<select name="style[type]">'),
      $dialog('other-dialog', '<select name="settings[ui_patterns][component_id]">'),
    ];
    $this->container->get(UiPatternsViewsHooks::class)->ajaxRenderAlter($commands);
    self::assertArrayNotHasKey('dialogOptions', $commands[0]);
    self::assertSame('95%', $commands[1]['dialogOptions']['width']);
    self::assertSame('95%', $commands[1]['dialogOptions']['height']);
    self::assertSame('75%', $commands[2]['dialogOptions']['width']);
    self::assertSame('75%', $commands[3]['dialogOptions']['width']);
  }

  /**
   * The component configuration of the fixture display.
   */
  private static function componentConfiguration(): array {
    $slots = [];
    foreach (UiPatternsViewsTestHooks::SLOT_SOURCES as $slot => $source_id) {
      $slots[$slot] = ['sources' => [['source_id' => $source_id]]];
    }
    return [
      'component_id' => 'ui_patterns_views_test:test-view',
      'props' => ['title' => ['source_id' => 'view_title']],
      'slots' => $slots,
    ];
  }

  /**
   * A display of the fixture view, executed the way a request does.
   */
  private function executedView(string $display_id): ViewExecutable {
    $view = Views::getView('test');
    $view->setDisplay($display_id);
    $view->preExecute();
    $view->execute();
    return $view;
  }

  /**
   * The extender plugin of a display.
   */
  private function extender(string $display_id): ComponentDisplayExtender {
    $view = Views::getView('test');
    $view->setDisplay($display_id);
    $extender = $view->getDisplay()->getExtenders()['ui_patterns'] ?? NULL;
    self::assertInstanceOf(ComponentDisplayExtender::class, $extender);
    return $extender;
  }

  /**
   * The markup of one slot wrapper of the component.
   */
  private function part(string $part): string {
    $elements = $this->cssSelect('.test-view__' . $part);
    self::assertCount(1, $elements, $part);
    return (string) $elements[0]->asXML();
  }

}
