<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_views\Functional;

use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Tests\ui_patterns\Functional\UiPatternsFunctionalTestBase;
use Drupal\ui_patterns_views_test\Hook\UiPatternsViewsTestHooks;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * A view page rendered with a component through the display extender.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_views')]
#[RunTestsInSeparateProcesses]
final class ComponentDisplayExtenderPageTest extends UiPatternsFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'ui_patterns',
    'ui_patterns_views',
    'ui_patterns_views_test',
    'views',
    'views_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createTestContentContentType();
    $config = $this->loadConfigFixture(__DIR__ . '/../../fixtures/config/views.view.test.display.yml');
    // Its own view: the test module renders "test" through a preprocess hook.
    $config['id'] = 'test_extender';
    $config['display']['page_1']['display_options']['path'] = 'test-extender';
    $config['display']['feed_1']['display_options']['path'] = 'test-extender.xml';
    $slots = [];
    foreach (UiPatternsViewsTestHooks::SLOT_SOURCES as $slot => $source_id) {
      $slots[$slot] = ['sources' => [['source_id' => $source_id]]];
    }
    // On the default display: page_1 inherits it, the attachments override it.
    $config['display']['default']['display_options']['ui_patterns'] = [
      'component_id' => 'ui_patterns_views_test:test-view',
      'props' => ['title' => ['source_id' => 'view_title']],
      'slots' => $slots,
    ];
    foreach (['attachment_1', 'attachment_2'] as $display_id) {
      $config['display'][$display_id]['display_options']['defaults']['ui_patterns'] = FALSE;
      $config['display'][$display_id]['display_options']['ui_patterns'] = [
        'component_id' => NULL,
        'props' => [],
        'slots' => [],
      ];
    }
    $this->importConfigFixture('views.view.test_extender', $config);
    \Drupal::service(RouteBuilderInterface::class)->rebuild();
    foreach (['Node A', 'Node B', 'Node C'] as $title) {
      $this->drupalCreateNode(['type' => 'page', 'title' => $title, 'status' => 1]);
    }
  }

  /**
   * The page is the component, and the pager and the exposed form still work.
   */
  public function testViewPageRenderedByTheComponent(): void {
    $assert_session = $this->assertSession();

    $this->drupalGet('test-extender');
    $assert_session->elementExists('css', '.test-view.view.view-id-test_extender.view-display-id-page_1[class*="js-view-dom-id-"]');
    $assert_session->elementTextContains('css', '.test-view__title', 'Test view title');
    $assert_session->elementTextContains('css', '.test-view__header', 'Test header text');
    $assert_session->elementsCount('css', '.test-view__rows .views-row', 2);
    $assert_session->elementExists('css', '.test-view__pager .pager__item--next a');
    $assert_session->elementExists('css', '.test-view__exposed form input[name="title"]');
    $assert_session->elementTextContains('css', '.test-view__more .more-link', 'Test more text');
    $assert_session->elementTextContains('css', '.test-view__footer', 'Test footer text');
    $assert_session->elementsCount('css', '.test-view__attachment-before .test-attachment-before .views-row', 1);
    $assert_session->elementAttributeContains('css', '.test-view__feed-icons a.feed-icon', 'href', 'test-extender.xml');
    // Nothing prints twice.
    $assert_session->pageTextMatchesCount(1, '/Test header text/');
    $assert_session->elementsCount('css', 'nav.pager', 1);

    $this->drupalGet('test-extender', ['query' => ['page' => 1]]);
    $assert_session->elementsCount('css', '.test-view__rows .views-row', 1);
    $assert_session->elementTextContains('css', '.test-view__rows', 'Node C');

    $this->drupalGet('test-extender', ['query' => ['title' => 'Node A']]);
    $assert_session->elementsCount('css', '.test-view__rows .views-row', 1);
    $assert_session->fieldValueEquals('title', 'Node A');
  }

  /**
   * The Views UI offers the component and the display sources.
   */
  public function testViewsUiOffersTheComponentForm(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer views']));
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/structure/views/view/test_extender/edit/page_1');
    $assert_session->pageTextContains('ui_patterns_views_test:test-view');

    $this->drupalGet('admin/structure/views/nojs/display/test_extender/page_1/ui_patterns');
    // Inherited from the default display, with the usual override choice.
    $assert_session->fieldValueEquals('override[dropdown]', 'default');
    $assert_session->fieldValueEquals('ui_patterns[component_id]', 'ui_patterns_views_test:test-view');
    // The display sources are offered, the style and row ones are not.
    $assert_session->responseContains('[View] Header');
    $assert_session->responseNotContains('[View row] Field');
  }

}
