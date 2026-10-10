<?php

declare(strict_types=1);

namespace Drupal\Tests\term_glossary\Functional;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\filter\Entity\FilterFormat;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\term_glossary\Traits\GlossaryTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests end-to-end glossary term rendering on node pages.
 *
 * @group term_glossary
 */
#[Group('term_glossary')]
#[RunTestsInSeparateProcesses]
class TermGlossaryRenderTest extends BrowserTestBase {

  use GlossaryTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'taxonomy',
    'text',
    'field',
    'filter',
    'term_glossary',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create content type with a body field.
    $this->drupalCreateContentType(['type' => 'article']);

    Vocabulary::create([
      'vid' => 'glossary',
      'name' => 'Glossary',
    ])->save();

    // Configure the glossary module.
    $this->config('term_glossary.glossaryconfig')
      ->set('vocab', 'glossary')
      ->set('integration_type', 'link')
      ->set('single_match', FALSE)
      ->set('full_word', FALSE)
      ->set('case_sensitive', FALSE)
      ->save();

    // Enable glossary on the body field display.
    $display = EntityViewDisplay::load(
      'node.article.default',
    );
    if (!$display) {
      $display = EntityViewDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => 'article',
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }
    $body_component = $display->getComponent('body');
    if ($body_component) {
      $body_component['third_party_settings']['term_glossary'] = [
        'glossary' => TRUE,
        'glossary_vocabulary' => [],
      ];
      $display->setComponent('body', $body_component);
      $display->save();
    }
  }

  /**
   * Tests that a glossary term in node body is highlighted.
   *
   * Creates a 'Drupal' glossary term and a node whose body
   * contains the word. Visits the node page and verifies the
   * response contains 'glos-term' (the CSS class added by
   * the glossary link/span replacement).
   */
  public function testGlossaryTermHighlighted(): void {
    $this->createGlossaryTerm('Drupal');

    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Test Article',
      'body' => [
        'value' => '<p>Welcome to Drupal CMS.</p>',
        'format' => 'plain_text',
      ],
    ]);

    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('glos-term');
  }

  /**
   * Tests that terms are not highlighted when glossary is disabled.
   *
   * Disables the glossary third-party setting on the body
   * field display. Creates the same term and node, visits
   * the page, and verifies 'glos-term' is absent from the
   * response — the preprocess hook skips disabled fields.
   */
  public function testGlossaryTermNotHighlightedWhenDisabled(): void {
    $this->createGlossaryTerm('Drupal');

    // Disable glossary on the body field display.
    $display = EntityViewDisplay::load(
      'node.article.default',
    );
    $body_component = $display->getComponent('body');
    $body_component['third_party_settings']['term_glossary'] = [
      'glossary' => FALSE,
      'glossary_vocabulary' => [],
    ];
    $display->setComponent('body', $body_component);
    $display->save();

    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Test Article Disabled',
      'body' => [
        'value' => '<p>Welcome to Drupal CMS.</p>',
        'format' => 'plain_text',
      ],
    ]);

    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseNotContains('glos-term');
  }

  /**
   * Tests that glossary processing keeps format-allowed field markup.
   *
   * Regression test for #3606615: the preprocess hook replaced the field
   * content with a plain-string '#markup' element, which the renderer then ran
   * through Xss::filterAdmin(), stripping markup the field's text format
   * legitimately allowed (style attributes from the Font CKEditor plugin and
   * inline SVG from UI icons). With a glossary term present so the replacement
   * path actually runs, a body carrying a styled span and an inline SVG must
   * keep both after the term is wrapped, not just the matched term.
   */
  public function testGlossaryPreservesFieldMarkup(): void {
    // A text format that passes HTML through unfiltered, standing in for a
    // permissive editor format that allows style attributes and inline SVG.
    FilterFormat::create([
      'format' => 'rich_html',
      'name' => 'Rich HTML',
      'weight' => 0,
      'filters' => [],
    ])->save();

    $this->createGlossaryTerm('Drupal');

    $body = '<p><span style="color:#e74c3c;">Red text</span> about Drupal, '
      . 'with an icon <svg xmlns="http://www.w3.org/2000/svg" '
      . 'viewBox="0 0 16 16" width="16"><path d="M8 0l2 6z"></path></svg>.</p>';
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Rich Article',
      'body' => [
        'value' => $body,
        'format' => 'rich_html',
      ],
    ]);

    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->statusCodeEquals(200);
    // The term was matched and wrapped, so the replacement path ran.
    $this->assertSession()->responseContains('glos-term');
    // The style attribute and the inline SVG survive the glossary processing.
    $this->assertSession()->responseContains('color:#e74c3c');
    $this->assertSession()->responseContains('<svg');
    $this->assertSession()->responseContains('</svg>');
  }

}
