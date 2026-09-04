<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Source;

use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\MenuSource;
use Drupal\ui_patterns\SourcePluginBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test MenuSource.
 *
 * @internal
 */
#[CoversClass(MenuSource::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class MenuSourceTest extends SourcePluginsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
    'menu_link_content',
    'menu_ui',
  ];

  /**
   * A first top-level link, with one child.
   */
  protected MenuLinkContent $parentOne;

  /**
   * The child of the first top-level link.
   */
  protected MenuLinkContent $childOne;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('user');
    $link = MenuLinkContent::create([
      'menu_name' => 'main',
      'link' => [['uri' => 'internal:/example-path']],
      'weight' => 5,
    ]);
    $link->save();
    $this->parentOne = MenuLinkContent::create([
      'menu_name' => 'main',
      'title' => 'Parent one',
      'link' => [['uri' => 'internal:/parent-one']],
      'weight' => 10,
    ]);
    $this->parentOne->save();
    $this->childOne = MenuLinkContent::create([
      'menu_name' => 'main',
      'title' => 'Child one',
      'link' => [['uri' => 'internal:/child-one']],
      'parent' => 'menu_link_content:' . $this->parentOne->uuid(),
    ]);
    $this->childOne->save();
    $parent_two = MenuLinkContent::create([
      'menu_name' => 'main',
      'title' => 'Parent two',
      'link' => [['uri' => 'internal:/parent-two']],
      'weight' => 20,
    ]);
    $parent_two->save();
    MenuLinkContent::create([
      'menu_name' => 'main',
      'title' => 'Child two',
      'link' => [['uri' => 'internal:/child-two']],
      'parent' => 'menu_link_content:' . $parent_two->uuid(),
    ])->save();
  }

  /**
   * Test MenuSource Plugin.
   */
  public function testPlugin(): void {
    $testData = self::loadTestDataFixture(__DIR__ . '/../../../fixtures/menu_tests.yml');
    $testSet = $testData->getTestSet('menu_1');
    $testSet['output'] = [
      'props' => [
        'links' => [
          'closure' => function ($output_of_source) {
            $this->assertNotNull($output_of_source);
            $this->assertTrue(\is_array($output_of_source));
            $this->assertTrue(\count($output_of_source) > 0);
            $this->assertTrue(\is_array($output_of_source[0]));
            $this->assertEquals($output_of_source[0]['url'], '/example-path');
            // No active trail by default.
            foreach ($output_of_source as $link) {
              $this->assertArrayNotHasKey('in_active_trail', $link);
            }
          },
        ],
      ],
    ];
    $this->runSourcePluginTest($testSet);
  }

  /**
   * Menu links on the route:<button> and route:<nolink> special routes.
   */
  public function testSpecialRouteMenuLinks(): void {
    MenuLinkContent::create([
      'menu_name' => 'main',
      'title' => 'Open menu',
      'link' => [['uri' => 'route:<button>']],
      'weight' => 6,
    ])->save();
    MenuLinkContent::create([
      'menu_name' => 'main',
      'title' => 'Section heading',
      'link' => [['uri' => 'route:<nolink>']],
      'weight' => 7,
    ])->save();

    $testData = self::loadTestDataFixture(__DIR__ . '/../../../fixtures/menu_tests.yml');
    $testSet = $testData->getTestSet('menu_1');
    $testSet['output'] = [
      'props' => [
        'links' => [
          'closure' => function ($output_of_source) {
            $this->assertIsArray($output_of_source);
            $by_title = [];
            foreach ($output_of_source as $item) {
              $this->assertIsArray($item);
              $by_title[(string) $item['title']] = $item;
            }
            $this->assertArrayHasKey('Open menu', $by_title);
            $this->assertArrayNotHasKey('url', $by_title['Open menu']);
            $this->assertTrue($by_title['Open menu']['is_button'] ?? FALSE);
            $this->assertArrayHasKey('Section heading', $by_title);
            $this->assertArrayNotHasKey('url', $by_title['Section heading']);
            $this->assertArrayNotHasKey('is_button', $by_title['Section heading']);
          },
        ],
      ],
    ];
    $this->runSourcePluginTest($testSet);
  }

  /**
   * A menu link title with a phishing anchor stays an untrusted string.
   *
   * A MenuLinkContent title (a plain-text field, no text format) holds a
   * phishing <a>. The source returns it as a plain string — untrusted, so
   * Twig autoescapes it at render (see MenuSourceRenderTest for the
   * rendered assertion).
   */
  public function testPhishingTitleIsUntrusted(): void {
    MenuLinkContent::create([
      'menu_name' => 'main',
      'title' => '<a href="https://phishing.example/">Sign in</a>',
      'link' => [['uri' => 'internal:/user/login']],
      'weight' => 1,
    ])->save();

    $testData = self::loadTestDataFixture(__DIR__ . '/../../../fixtures/menu_tests.yml');
    $testSet = $testData->getTestSet('menu_1');
    $testSet['output'] = [
      'props' => [
        'links' => [
          'closure' => function ($output_of_source) {
            $this->assertIsArray($output_of_source);
            $phishing_item = NULL;
            foreach ($output_of_source as $item) {
              if (!\is_array($item) || !isset($item['url'])) {
                continue;
              }
              if ($item['url'] === '/user/login') {
                $phishing_item = $item;
                break;
              }
            }
            $this->assertNotNull($phishing_item, 'Phishing menu link was not produced by the source.');
            // assertSame on the raw string also proves the title is not
            // wrapped in Markup, so it stays untrusted for Twig.
            $this->assertSame('<a href="https://phishing.example/">Sign in</a>', $phishing_item['title']);
          },
        ],
      ],
    ];
    $this->runSourcePluginTest($testSet);
  }

  /**
   * Marking the active trail sets in_active_trail without reshaping the tree.
   */
  public function testSetActiveTrail(): void {
    $this->setActiveTrail([$this->childOne, $this->parentOne]);
    $testData = self::loadTestDataFixture(__DIR__ . '/../../../fixtures/menu_tests.yml');
    $testSet = $testData->getTestSet('menu_set_active_trail');
    $testSet['output'] = [
      'props' => [
        'links' => [
          'closure' => function ($links) {
            $parent_one = self::findByUrl($links, '/parent-one');
            $this->assertNotNull($parent_one);
            $this->assertTrue($parent_one['in_active_trail']);
            $child_one = self::findByUrl($parent_one['below'], '/child-one');
            $this->assertNotNull($child_one);
            $this->assertTrue($child_one['in_active_trail']);
            // No in_active_trail property outside the trail.
            $this->assertArrayNotHasKey('in_active_trail', self::findByUrl($links, '/example-path'));
            $parent_two = self::findByUrl($links, '/parent-two');
            $this->assertArrayNotHasKey('in_active_trail', $parent_two);
            // The tree is not reshaped: other subtrees are still loaded.
            $child_two = self::findByUrl($parent_two['below'], '/child-two');
            $this->assertNotNull($child_two);
            $this->assertArrayNotHasKey('in_active_trail', $child_two);
          },
        ],
      ],
    ];
    $this->runSourcePluginTest($testSet);
  }

  /**
   * Collapsing to the active trail loads only trail or expanded subtrees.
   */
  public function testCollapsed(): void {
    $this->setActiveTrail([$this->childOne, $this->parentOne]);
    $testData = self::loadTestDataFixture(__DIR__ . '/../../../fixtures/menu_tests.yml');
    $testSet = $testData->getTestSet('menu_collapsed');
    $testSet['output'] = [
      'props' => [
        'links' => [
          'closure' => function ($links) {
            $parent_one = self::findByUrl($links, '/parent-one');
            $this->assertNotNull($parent_one);
            $this->assertTrue($parent_one['in_active_trail']);
            $this->assertNotNull(self::findByUrl($parent_one['below'], '/child-one'));
            // The subtree of a link outside the active trail is not loaded.
            $parent_two = self::findByUrl($links, '/parent-two');
            $this->assertNotNull($parent_two);
            $this->assertSame([], $parent_two['below'] ?? []);
          },
        ],
      ],
    ];
    $this->runSourcePluginTest($testSet);
  }

  /**
   * With a start level greater than 1, only the active subtree is shown.
   */
  public function testCollapsedLevelTwo(): void {
    $this->setActiveTrail([$this->childOne, $this->parentOne]);
    $testData = self::loadTestDataFixture(__DIR__ . '/../../../fixtures/menu_tests.yml');
    $testSet = $testData->getTestSet('menu_collapsed_level_2');
    $testSet['output'] = [
      'props' => [
        'links' => [
          'closure' => function ($links) {
            $this->assertCount(1, $links);
            $this->assertEquals('/child-one', $links[0]['url']);
            $this->assertTrue($links[0]['in_active_trail']);
          },
        ],
      ],
    ];
    $this->runSourcePluginTest($testSet);

    // With a trail shorter than the start level, nothing is shown.
    $this->setActiveTrail([]);
    $testSet['output']['props']['links']['closure'] = function ($links) {
      $this->assertSame([], $links);
    };
    $this->runSourcePluginTest($testSet);
  }

  /**
   * The active trail cache context is added only when the trail is used.
   */
  public function testAlterComponentCacheMetadata(): void {
    $element = $this->createMenuSource(['menu' => 'main'])->alterComponent([]);
    self::assertContains('config:system.menu.main', $element['#cache']['tags']);
    self::assertNotContains('route.menu_active_trails:main', $element['#cache']['contexts'] ?? []);

    $trail_settings = [
      ['menu' => 'main', 'set_active_trail' => TRUE],
      ['menu' => 'main', 'expand_all_items' => FALSE],
    ];
    foreach ($trail_settings as $settings) {
      $element = $this->createMenuSource($settings)->alterComponent([]);
      self::assertContains('config:system.menu.main', $element['#cache']['tags']);
      self::assertContains('route.menu_active_trails:main', $element['#cache']['contexts']);
    }

    // Without a selected menu, the element is left untouched.
    self::assertSame([], $this->createMenuSource([])->alterComponent([]));
  }

  /**
   * Creates a menu source plugin instance with the given settings.
   */
  protected function createMenuSource(array $settings): SourcePluginBase {
    $component = $this->componentManager()->getDefinition('ui_patterns_test:test-component');
    $configuration = SourcePluginBase::buildConfiguration(
      'links',
      $component['props']['properties']['links'],
      ['source_id' => 'menu', 'source' => $settings],
      []
    );
    $source = $this->sourcePluginManager()->createInstance('menu', $configuration);
    self::assertInstanceOf(SourcePluginBase::class, $source);
    return $source;
  }

  /**
   * Swaps the active trail service with a fixed trail.
   *
   * @param \Drupal\menu_link_content\Entity\MenuLinkContent[] $links
   *   The links of the trail, child first.
   */
  protected function setActiveTrail(array $links): void {
    $trail = [];
    foreach ($links as $link) {
      $id = 'menu_link_content:' . $link->uuid();
      $trail[$id] = $id;
    }
    $trail[''] = '';
    // Rebuild menu.link_tree so it picks up the swapped service.
    $this->container->set('menu.link_tree', NULL);
    $this->container->set('menu.active_trail', new class($trail) implements MenuActiveTrailInterface {

      public function __construct(protected array $trail) {}

      /**
       * {@inheritdoc}
       */
      public function getActiveTrailIds($menu_name) {
        return $this->trail;
      }

      /**
       * {@inheritdoc}
       */
      public function getActiveLink($menu_name = NULL) {
        return NULL;
      }

    });
  }

  /**
   * Finds a normalized link by URL.
   */
  protected static function findByUrl(array $links, string $url): ?array {
    foreach ($links as $link) {
      if (($link['url'] ?? NULL) === $url) {
        return $link;
      }
    }
    return NULL;
  }

}
