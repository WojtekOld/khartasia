<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Source;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\FieldPropertySource;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test FieldPropertySource.
 *
 * @internal
 */
#[CoversClass(FieldPropertySource::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class FieldPropertySourceTest extends SourcePluginsTestBase {

  use UserCreationTrait;

  /**
   * The cache contexts of the last source run by mailOf().
   */
  private array $lastCacheContexts = [];

  /**
   * Test FieldPropertySource Plugin.
   */
  public function testPlugin(): void {
    $this->runSourcePluginTests('field_property_');
  }

  /**
   * A field the user may not view gives no value, and its cacheability is kept.
   *
   * The email of another user is such a field in core.
   */
  public function testFieldAccessIsChecked(): void {
    $viewer = $this->setUpCurrentUser();
    $owner = $this->createUser();
    self::assertNull($this->mailOf($owner), 'Another user cannot read the email.');
    self::assertContains('user', $this->lastCacheContexts);
    $this->setCurrentUser($owner);
    self::assertSame($owner->getEmail(), $this->mailOf($owner), 'The owner reads the email.');
    $this->setCurrentUser($viewer);
  }

  /**
   * The mail property of a user, as the source gives it.
   */
  private function mailOf(UserInterface $user): ?string {
    $source = $this->sourcePluginManager()->getSource('prop', [], [
      'source_id' => 'field_property:user:mail:value',
    ], [
      'entity' => EntityContext::fromEntity($user),
      'bundle' => new Context(new ContextDefinition('any'), 'user'),
      'field_name' => new Context(new ContextDefinition('any'), 'mail'),
    ]);
    self::assertInstanceOf(FieldPropertySource::class, $source);
    $value = $source->getPropValue();
    $this->lastCacheContexts = $source->getCacheContexts();
    return $value;
  }

  /**
   * A <script> stored in an entity field reaches a slot escaped.
   *
   * Only the slot case is tested here: the string → slot conversion goes
   * through SlotPropType::convertFrom() → #plain_text, which Drupal core
   * escapes. The string-prop case belongs to the prop-type layer and is
   * covered by StringPropTypeTest::testScriptTagInStringPropIsStripped.
   */
  public function testXssInFieldValueForSlotIsStripped(): void {
    $this->runSourcePluginTest([
      'skip_schema_check' => TRUE,
      'component' => [
        'component_id' => 'ui_patterns_test:test-component',
        'slots' => [
          'slot' => [
            'sources' => [
              [
                'source_id' => 'field_property:node:field_text_1:value',
                'source' => [
                  'type' => 'string',
                ],
              ],
            ],
          ],
        ],
      ],
      'entity' => [
        'field_text_1' => [
          'value' => 'Hello<script>alert("xss")</script>World',
        ],
      ],
      'contexts' => [
        'field_name' => 'field_text_1',
        'bundle' => 'page',
      ],
      'output' => [
        'slots' => [
          'slot' => [
            [
              'rendered_value' => '<script',
              'assert' => 'assertStringNotContainsString',
            ],
          ],
        ],
      ],
    ]);
  }

  /**
   * A <script> stored in an entity field reaches a string prop escaped.
   *
   * The source returns the raw string, untrusted: the rendered component
   * shows it as literal text, never as a live element.
   */
  public function testXssInFieldValueForStringPropIsEscaped(): void {
    $captured = NULL;
    $this->runSourcePluginTest([
      'skip_schema_check' => TRUE,
      'component' => [
        'component_id' => 'ui_patterns_test:test-component',
        'props' => [
          'string' => [
            'source_id' => 'field_property:node:field_text_1:value',
            'source' => [
              'type' => 'string',
            ],
          ],
        ],
      ],
      'entity' => [
        'field_text_1' => [
          'value' => 'Hello<script>alert("xss")</script>World',
        ],
      ],
      'contexts' => [
        'field_name' => 'field_text_1',
        'bundle' => 'page',
      ],
      'output' => [
        'props' => [
          'string' => [
            'closure' => function ($output) use (&$captured) {
              $this->assertSame('Hello<script>alert("xss")</script>World', $output);
              $captured = $output;
            },
          ],
        ],
      ],
    ]);
    $build = [
      '#type' => 'component',
      '#component' => 'ui_patterns_test:test-component',
      '#props' => ['string' => $captured],
    ];
    $html = (string) \Drupal::service(RendererInterface::class)->renderInIsolation($build);
    self::assertStringNotContainsString('<script>alert', $html);
    self::assertStringContainsString('Hello&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;World', $html);
  }

  /**
   * A `processed` property is trusted and renders its filtered HTML.
   *
   * Trust is carried by type: TextProcessed::getValue() returns
   * FilteredMarkup, which the string prop type keeps as safe HTML.
   */
  public function testProcessedFieldValueForStringPropIsTrusted(): void {
    $captured = NULL;
    $this->runSourcePluginTest([
      'skip_schema_check' => TRUE,
      'component' => [
        'component_id' => 'ui_patterns_test:test-component',
        'props' => [
          'string' => [
            'source_id' => 'field_property:node:field_text_1:processed',
            'source' => [
              'type' => 'string',
            ],
          ],
        ],
      ],
      'entity' => [
        'field_text_1' => [
          'value' => 'Tom & Jerry',
          'format' => 'plain_text',
        ],
      ],
      'contexts' => [
        'field_name' => 'field_text_1',
        'bundle' => 'page',
      ],
      'output' => [
        'props' => [
          'string' => [
            'closure' => function ($output) use (&$captured) {
              $this->assertInstanceOf('\Drupal\Component\Render\MarkupInterface', $output);
              $captured = $output;
            },
          ],
        ],
      ],
    ]);
    $build = [
      '#type' => 'component',
      '#component' => 'ui_patterns_test:test-component',
      '#props' => ['string' => $captured],
    ];
    $html = (string) \Drupal::service(RendererInterface::class)->renderInIsolation($build);
    // Single escape from the plain_text format; a double-escape would
    // show &amp;amp;.
    self::assertStringContainsString('Tom &amp; Jerry', $html);
    self::assertStringNotContainsString('Tom &amp;amp; Jerry', $html);
  }

}
