<?php

namespace Drupal\Tests\security_review\Kernel\Plugin;

use Drupal\security_review\CheckResult;
use Drupal\Tests\security_review\Kernel\SecurityReviewTestBase;

/**
 * Contains tests for the ViewsAccess plugin.
 *
 * @group security_review
 */
class ViewsAccessTest extends SecurityReviewTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'views',
    'security_review_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('user', ['users_data']);
    $this->installConfig('security_review_test');

    /** @var \Drupal\security_review\SecurityCheckPluginManager $manager */
    $manager = $this->container->get('plugin.manager.security_review.security_check');
    $this->plugin = $manager->createInstance('views_access');
  }

  /**
   * Tests the plugin returns INFO when views is uninstalled.
   *
   * @throws \Exception
   */
  public function testViewAccessWithoutViews(): void {
    $this->container->get('module_installer')->uninstall(['views']);

    $this->plugin->run();
    $result = $this->plugin->getResult();

    $this->assertEquals(CheckResult::INFO, $result['result']);
  }

  /**
   * Tests the plugin succeeds correctly.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  public function testViewsAccessSuccess(): void {
    // First, we delete the page_1 display, since we know it fails.
    $view_storage = $this->container->get('entity_type.manager')->getStorage('view');
    /** @var \Drupal\views\ViewEntityInterface $view */
    $view = $view_storage->load('security_review_test');
    if ($view) {
      $display_id = 'page_1';
      $displays = $view->get('display');
      if (isset($displays[$display_id])) {
        unset($displays[$display_id]);
        $view->set('display', $displays);
        $view->save();
      }
    }

    // Second, ignore the default display.
    $this->config('security_review.settings')
      ->set('views_access.ignore_default', TRUE)
      ->save();

    // Run the plugin.
    $sandbox = [];
    $finished = 0;
    // Run until the plugin is fully finished.
    while ($finished < 1) {
      $finished = $this->plugin->run(FALSE, $sandbox);
    }

    $this->assertEquals(1.0, $finished);

    $this->assertEmpty($sandbox['findings']);
  }

  /**
   * Tests the plugin fails correctly.
   */
  public function testViewsAccessFailure(): void {
    // Run the plugin.
    $sandbox = [];
    $finished = 0;
    // Run until the plugin is fully finished.
    while ($finished < 1) {
      $finished = $this->plugin->run(FALSE, $sandbox);
    }

    $this->assertEquals(1.0, $finished);

    $this->assertNotEmpty($sandbox['findings']);
    $this->assertArrayHasKey('security_review_test', $sandbox['findings']);
    $this->assertEquals('default', $sandbox['findings']['security_review_test'][0]);
    $this->assertEquals('page_1', $sandbox['findings']['security_review_test'][1]);
  }

  /**
   * Tests the plugin setting ignore_default.
   */
  public function testViewsAccessIgnoreDefault(): void {
    $this->config('security_review.settings')
      ->set('views_access.ignore_default', TRUE)
      ->save();

    // Run the plugin.
    $sandbox = [];
    $finished = 0;
    // Run until the plugin is fully finished.
    while ($finished < 1) {
      $finished = $this->plugin->run(FALSE, $sandbox);
    }

    $this->assertEquals(1.0, $finished);

    $this->assertNotEmpty($sandbox['findings']);
    $this->assertArrayHasKey('security_review_test', $sandbox['findings']);
    $this->assertNotEquals('default', $sandbox['findings']['security_review_test'][0]);
    $this->assertEquals('page_1', $sandbox['findings']['security_review_test'][0]);
  }

  /**
   * Tests the plugin setting hushed_views.
   */
  public function testViewsAccessHushedViews(): void {
    // Test hushing the entire view.
    $this->config('security_review.settings')
      ->set('views_access.hushed_views', ['security_review_test'])
      ->save();

    // Run the plugin.
    $sandbox = [];
    $finished = 0;
    // Run until the plugin is fully finished.
    while ($finished < 1) {
      $finished = $this->plugin->run(FALSE, $sandbox);
    }

    $this->assertEquals(1.0, $finished);

    $this->assertEmpty($sandbox['findings']);
    $this->assertNotEmpty($sandbox['hushed']);
    $this->assertArrayHasKey('security_review_test', $sandbox['hushed']);
    $this->assertEquals('security_review_test', $sandbox['hushed']['security_review_test']);

    // Test hushing individual display.
    $this->config('security_review.settings')
      ->set('views_access.hushed_views', ['security_review_test:page_1'])
      ->save();

    // Run the plugin.
    $sandbox = [];
    $finished = 0;
    // Run until the plugin is fully finished.
    while ($finished < 1) {
      $finished = $this->plugin->run(FALSE, $sandbox);
    }

    $this->assertEquals(1.0, $finished);

    $this->assertNotEmpty($sandbox['findings']);
    $this->assertArrayHasKey('security_review_test', $sandbox['findings']);
    $this->assertEquals('default', $sandbox['findings']['security_review_test'][0]);

    $this->assertNotEmpty($sandbox['hushed']);
    $this->assertArrayHasKey('security_review_test', $sandbox['hushed']);
    $this->assertEquals('page_1', $sandbox['hushed']['security_review_test'][0]);
  }

}
