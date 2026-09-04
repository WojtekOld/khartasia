<?php

namespace Drupal\Tests\security_review\Kernel\Plugin;

use Drupal\Tests\security_review\Kernel\SecurityReviewTestBase;
use Drupal\filter\Entity\FilterFormat;
use Drupal\security_review\CheckResult;
use Drupal\user\Entity\Role;

/**
 * Contains tests for InputFormats plugin.
 *
 * @group security_review
 */
class InputFormatsTest extends SecurityReviewTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    Role::create(['id' => 'tester', 'label' => 'Testing Role'])->save();

    FilterFormat::create([
      'format' => 'basic_html',
      'name' => 'Basic HTML',
      'filters' => [
        'filter_html' => [
          'status' => TRUE,
          'settings' => [
            'allowed_html' => '<a href hreflang>',
            'filter_html_help' => TRUE,
            'filter_html_nofollow' => TRUE,
          ],
        ],
      ],
      'roles' => ['tester'],
      'weight' => 1,
      'editor' => 'ckeditor',
    ])->save();

    FilterFormat::create([
      'format' => 'full_html',
      'name' => 'Full HTML',
      'filters' => [
        'filter_html_escape' => [
          'status' => FALSE,
          'settings' => [],
        ],
        'filter_html' => [
          'status' => TRUE,
          'settings' => [
            // Body is an unsafe tag.
            'allowed_html' => '<body>',
            'filter_html_help' => TRUE,
            'filter_html_nofollow' => TRUE,
          ],
        ],
      ],
      'roles' => ['tester'],
      'weight' => 2,
      'editor' => 'ckeditor',
    ])->save();

    /** @var \Drupal\security_review\SecurityCheckPluginManager $manager */
    $manager = $this->container->get('plugin.manager.security_review.security_check');
    $this->plugin = $manager->createInstance('input_formats');
  }

  /**
   * Tests plugin fail if untrusted user can use unsafe tags.
   */
  public function testInputFiltersUntrustedRole(): void {
    $this->config('security_review.settings')
      ->set('untrusted_roles', ['tester'])
      ->save();

    // Run the plugin.
    $this->plugin->doRun();
    $results = $this->plugin->getResult();
    $this->assertIsArray($results);
    $this->assertEquals(CheckResult::FAIL, $results['result']);
    // Only full_html, not basic_html is in the findings.
    $this->assertEquals(['full_html'], array_keys($results['findings']['tags']));
  }

  /**
   * Tests plugin success if untrusted user cannot access unsafe tags.
   */
  public function testInputFiltersTrustedRolesOnly(): void {
    // Run the plugin.
    $this->plugin->doRun();
    $results = $this->plugin->getResult();
    $this->assertIsArray($results);
    $this->assertEquals(CheckResult::SUCCESS, $results['result']);
  }

}
