<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_field_formatters\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * A hidden referenced entity shows up once published, without a cache clear.
 *
 * Same scenario as core's Layout Builder entity reference cache tags test:
 * the tag of the denied entity has to reach the page cache.
 *
 * @internal
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_field_formatters')]
#[RunTestsInSeparateProcesses]
final class ReferencedEntityCacheTagsTest extends BrowserTestBase {

  use ContentTypeCreationTrait;
  use EntityReferenceFieldCreationTrait;
  use NodeCreationTrait;

  private const REFERENCE_CONTEXT_ID = 'entity_reference:node:page:field_related:node:page';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'ui_patterns', 'ui_patterns_test', 'ui_patterns_field_formatters'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->config('system.performance')->set('cache.page.max_age', 3600)->save();
    $this->createContentType(['type' => 'page', 'name' => 'Page']);
    $this->createEntityReferenceField('node', 'page', 'field_related', 'Related', 'node', 'default', ['target_bundles' => ['page' => 'page']], -1);
    $this->container->get('entity_display.repository')
      ->getViewDisplay('node', 'page', 'full')
      ->setComponent('field_related', [
        'type' => 'ui_patterns_component',
        'settings' => [
          'ui_patterns' => [
            'component_id' => 'ui_patterns_test:test-component',
            'slots' => [
              'slot' => [
                'sources' => [
                  [
                    'source_id' => 'entity_reference',
                    'source' => [
                      'derivable_context' => self::REFERENCE_CONTEXT_ID,
                      self::REFERENCE_CONTEXT_ID => [
                        'value' => [
                          'sources' => [
                            ['source_id' => 'field_property:node:title:value', 'source' => []],
                          ],
                        ],
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ])
      ->save();
  }

  /**
   * The referenced node appears when published, from a warm page cache.
   */
  public function testReferencedEntityIsShownOncePublished(): void {
    $referenced = $this->createNode(['type' => 'page', 'title' => 'The referenced node title', 'status' => 0]);
    $referencing = $this->createNode([
      'type' => 'page',
      'title' => 'The referencing node title',
      'field_related' => [$referenced->id()],
    ]);
    $url = $referencing->toUrl();

    $this->verifyPageCache($url, 'MISS');
    $this->verifyPageCache($url, 'HIT', $referenced->getCacheTags());
    $this->assertSession()->pageTextNotContains('The referenced node title');

    $referenced->setPublished()->save();

    $this->verifyPageCache($url, 'MISS');
    $this->assertSession()->pageTextContains('The referenced node title');
    $this->verifyPageCache($url, 'HIT', $referenced->getCacheTags());
  }

  /**
   * Loads a page and checks the page cache header, and optionally its tags.
   */
  private function verifyPageCache(Url $url, string $hit_or_miss, array $tags = []): void {
    $this->drupalGet($url);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', $hit_or_miss);
    if ($tags) {
      $cache_tags = \explode(' ', (string) $this->getSession()->getResponseHeader('X-Drupal-Cache-Tags'));
      self::assertEmpty(\array_diff($tags, $cache_tags), 'The page cache tags contain the expected tags.');
    }
  }

}
