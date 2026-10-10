<?php

declare(strict_types=1);

namespace Drupal\Tests\term_glossary\Unit\Plugin\TermGlossaryHandler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\term_glossary\Plugin\TermGlossaryHandler\TermGlossaryCustomJSHandler;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the TermGlossaryCustomJSHandler plugin.
 *
 * @group term_glossary
 */
#[CoversClass(TermGlossaryCustomJSHandler::class)]
#[Group('term_glossary')]
class TermGlossaryCustomJSHandlerTest extends UnitTestCase {

  /**
   * The handler under test.
   *
   * @var \Drupal\term_glossary\Plugin\TermGlossaryHandler\TermGlossaryCustomJSHandler
   */
  protected TermGlossaryCustomJSHandler $handler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $this->handler = new TermGlossaryCustomJSHandler(
      [],
      'custom_js',
      ['id' => 'custom_js', 'title' => 'Custom JS'],
      $config_factory,
    );
  }

  /**
   * Tests buildMatchTag produces correct render array.
   *
   * Calls buildMatchTag() with tid=42 and name='Test Term'.
   * Verifies the render array is an html_tag span with the
   * 'glos-term' class, data-gterm attribute set to the tid,
   * role='button', tabindex=0, and the match text as #value.
   */
  public function testBuildMatchTag(): void {
    $match_tag = [];
    $term_data = [
      'tid' => 42,
      'name' => 'Test Term',
      'lang' => 'en',
    ];

    $this->handler->buildMatchTag(
      $match_tag, 'Test Term', $term_data,
    );

    $this->assertEquals('html_tag', $match_tag['#type']);
    $this->assertEquals('span', $match_tag['#tag']);
    $this->assertContains(
      'glos-term', $match_tag['#attributes']['class'],
    );
    $this->assertEquals(
      42, $match_tag['#attributes']['data-gterm'],
    );
    $this->assertEquals(
      'button', $match_tag['#attributes']['role'],
    );
    $this->assertEquals(
      0, $match_tag['#attributes']['tabindex'],
    );
    $this->assertEquals('Test Term', $match_tag['#value']);
  }

  /**
   * Tests buildMatchTag HTML-escapes the match value.
   *
   * Passes a <script> tag as both term name and match text.
   * Verifies the #value does not contain raw '<script>' but
   * contains the escaped '&lt;script&gt;' instead, preventing
   * XSS.
   */
  public function testBuildMatchTagEscapesHtml(): void {
    $match_tag = [];
    $term_data = [
      'tid' => 7,
      'name' => '<script>alert("xss")</script>',
      'lang' => 'en',
    ];

    $this->handler->buildMatchTag(
      $match_tag,
      '<script>alert("xss")</script>',
      $term_data,
    );

    $this->assertStringNotContainsString(
      '<script>', $match_tag['#value'],
    );
    $this->assertStringContainsString(
      '&lt;script&gt;', $match_tag['#value'],
    );
  }

}
