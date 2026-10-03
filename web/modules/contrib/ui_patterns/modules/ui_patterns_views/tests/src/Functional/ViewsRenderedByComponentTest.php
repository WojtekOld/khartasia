<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_views\Functional;

use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Tests\ui_patterns\Functional\UiPatternsFunctionalTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * A view page fully assembled by a component, over HTTP.
 *
 * The test module renders the page display of the test view through the
 * views:display sources, so the pager links and the exposed form must keep
 * working as on the plain view page.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_views')]
#[RunTestsInSeparateProcesses]
final class ViewsRenderedByComponentTest extends UiPatternsFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'ui_patterns',
    'ui_patterns_views',
    'ui_patterns_views_test',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createTestContentContentType();
    $this->importConfigFixture('views.view.test', $this->loadConfigFixture(__DIR__ . '/../../fixtures/config/views.view.test.display.yml'));
    \Drupal::service(RouteBuilderInterface::class)->rebuild();
    foreach (['Node A', 'Node B', 'Node C'] as $title) {
      $this->drupalCreateNode(['type' => 'page', 'title' => $title, 'status' => 1]);
    }
  }

  /**
   * Every part renders once, the pager pages and the exposed form filters.
   */
  public function testViewPageAssembledBySources(): void {
    $assert_session = $this->assertSession();

    $this->drupalGet('test');
    $assert_session->elementTextContains('css', '.test-view__title', 'Test view title');
    $assert_session->elementTextContains('css', '.test-view__header', 'Test header text');
    $assert_session->elementTextContains('css', '.test-view__footer', 'Test footer text');
    $assert_session->pageTextNotContains('Test empty text');
    $assert_session->elementsCount('css', '.test-view__rows .views-row', 2);
    $assert_session->elementTextContains('css', '.test-view__rows', 'Node A');
    $assert_session->elementTextContains('css', '.test-view__rows', 'Node B');
    $assert_session->elementExists('css', '.test-view__pager .pager__item--next a');
    $assert_session->elementExists('css', '.test-view__exposed form#views-exposed-form-test-page-1 input[name="title"]');
    $assert_session->elementTextContains('css', '.test-view__more .more-link', 'Test more text');
    $assert_session->elementsCount('css', '.test-view__attachment-before .test-attachment-before .views-row', 1);
    $assert_session->elementsCount('css', '.test-view__attachment-after .test-attachment-after .views-row', 1);
    $assert_session->elementAttributeContains('css', '.test-view__feed-icons a.feed-icon', 'href', 'test.xml');
    // Nothing prints twice.
    $assert_session->pageTextMatchesCount(1, '/Test header text/');
    $assert_session->elementsCount('css', 'nav.pager', 1);

    $this->drupalGet('test', ['query' => ['page' => 1]]);
    $assert_session->elementsCount('css', '.test-view__rows .views-row', 1);
    $assert_session->elementTextContains('css', '.test-view__rows', 'Node C');

    $this->drupalGet('test', ['query' => ['title' => 'Node A']]);
    $assert_session->elementsCount('css', '.test-view__rows .views-row', 1);
    $assert_session->elementTextContains('css', '.test-view__rows', 'Node A');
    $assert_session->fieldValueEquals('title', 'Node A');

    // The attached feed display still answers on its own.
    $this->drupalGet('test.xml');
    $assert_session->statusCodeEquals(200);
    $assert_session->responseContains('<rss');
  }

}
