<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_views\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
use Drupal\ui_patterns\SourcePluginBase;
use Drupal\ui_patterns\SourcePluginManager;
use Drupal\views\Entity\View;
use Drupal\views_ui\ViewUI;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the contexts the views source plugins are driven by.
 *
 * The contexts are built by hand: this is the consumer side of the contract,
 * the same shape other modules inject. The real producers are covered end to
 * end by ViewsFieldsRenderTest and the views Playwright spec, and
 * testStylePluginProvidesTheContexts() checks the hand-built shape against
 * the real one.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_views')]
#[RunTestsInSeparateProcesses]
final class ViewsSourceContextsTest extends KernelTestBase {

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
  ];

  /**
   * The source plugin manager.
   */
  protected SourcePluginManager $sourcePluginManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['views']);
    $this->setUpCurrentUser();
    $config = Yaml::decode((string) \file_get_contents(__DIR__ . '/../../fixtures/config/views.view.test.yml'));
    unset($config['uuid']);
    $config['display']['default']['display_options']['title'] = 'saved view title';
    $config['display']['page_1']['display_options']['defaults']['style'] = FALSE;
    $config['display']['page_1']['display_options']['style'] = [
      'type' => 'ui_patterns',
      'options' => ['uses_fields' => FALSE, 'ui_patterns' => ['ui_patterns' => []]],
    ];
    View::create($config)->save();
    $this->sourcePluginManager = \Drupal::service(SourcePluginManager::class);
  }

  /**
   * The style plugin produces the contexts the other tests build by hand.
   *
   * Without this pin, a refactor changing producer and consumer together
   * would keep the other tests green while their hand-built contexts drift
   * from reality. The context ids are an API read by other modules, so a
   * change has to land here on purpose.
   */
  public function testStylePluginProvidesTheContexts(): void {
    $executable = View::load('test')->getExecutable();
    $executable->setDisplay('page_1');
    $style = $executable->getStyle();
    $contexts = (new \ReflectionMethod($style, 'getComponentSourceContexts'))->invoke($style);
    $keys = \array_keys($contexts);
    \sort($keys);
    self::assertSame([
      'context_requirements',
      'ui_patterns_views:plugin:display_handler',
      'ui_patterns_views:plugin:options',
      'ui_patterns_views:view',
      'ui_patterns_views:view_entity',
    ], $keys);
    self::assertContains('views:style', $contexts['context_requirements']->getContextValue());
    self::assertSame('test', $contexts['ui_patterns_views:view_entity']->getContextValue()->id());
  }

  /**
   * A source with a views requirement is offered only under that requirement.
   */
  public function testDefinitionsFilteredByViewsRequirements(): void {
    $cases = [
      [NULL, 'slot', [], ['view_rows', 'view_field']],
      ['views:style', 'slot', ['view_rows'], ['view_field']],
      ['views:row', 'slot', ['view_field'], ['view_rows']],
      ['views:style', 'string', ['view_title'], []],
      ['views:row', 'string', ['view_title'], []],
    ];
    foreach ($cases as [$requirement, $prop_type_id, $present, $absent]) {
      $definition_ids = \array_keys($this->sourcePluginManager->getDefinitionsForPropType($prop_type_id, $this->viewContexts($requirement)));
      $label = $requirement ?? 'no requirement';
      self::assertSame($present, \array_values(\array_intersect($present, $definition_ids)), \sprintf('Missing for %s', $label));
      self::assertSame([], \array_values(\array_intersect($absent, $definition_ids)), \sprintf('Wrongly offered for %s', $label));
    }
  }

  /**
   * A view being edited in the views UI wins over its saved configuration.
   */
  public function testUnsavedViewFromTempstore(): void {
    self::assertSame('saved view title', $this->getViewTitle());
    $unsaved = clone View::load('test');
    $display = $unsaved->get('display');
    $display['default']['display_options']['title'] = 'unsaved view title';
    $unsaved->set('display', $display);
    \Drupal::service('tempstore.shared')->get('views')->set('test', new ViewUI($unsaved));
    self::assertSame('unsaved view title', $this->getViewTitle());
  }

  /**
   * Renders the view_title source against the saved view entity.
   */
  private function getViewTitle(): mixed {
    $configuration = SourcePluginBase::buildConfiguration('string', [], [], $this->viewContexts());
    $source = $this->sourcePluginManager->createInstance('view_title', $configuration);
    self::assertInstanceOf(SourcePluginBase::class, $source);
    return $source->getPropValue();
  }

  /**
   * Returns the contexts a views style or row plugin provides.
   */
  private function viewContexts(?string $requirement = NULL): array {
    $contexts = [
      'ui_patterns_views:view_entity' => EntityContext::fromEntity(View::load('test')),
    ];
    return $requirement ? RequirementsContext::addToContext([$requirement], $contexts) : $contexts;
  }

}
