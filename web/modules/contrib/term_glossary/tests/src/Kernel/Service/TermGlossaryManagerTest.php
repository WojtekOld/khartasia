<?php

declare(strict_types=1);

namespace Drupal\Tests\term_glossary\Kernel\Service;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\term_glossary\Service\TermGlossaryManager;
use Drupal\term_glossary\Service\TermGlossaryManagerInterface;
use Drupal\Tests\term_glossary\Traits\GlossaryTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the TermGlossaryManager service.
 *
 * @group term_glossary
 */
#[CoversClass(TermGlossaryManager::class)]
#[Group('term_glossary')]
#[RunTestsInSeparateProcesses]
class TermGlossaryManagerTest extends KernelTestBase {

  use GlossaryTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'taxonomy',
    'text',
    'field',
    'filter',
    'term_glossary',
  ];

  /**
   * The glossary manager service.
   *
   * @var \Drupal\term_glossary\Service\TermGlossaryManagerInterface
   */
  protected TermGlossaryManagerInterface $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installGlossaryTestDependencies();

    // Configure the module to use the link handler.
    $config = $this->config('term_glossary.glossaryconfig');
    $config->set('vocab', 'glossary');
    $config->set('integration_type', 'link');
    $config->set('single_match', FALSE);
    $config->set('single_match_per_content', FALSE);
    $config->set('full_word', FALSE);
    $config->set('case_sensitive', FALSE);
    $config->set('per_term_options', FALSE);
    $config->set('term_synonyms', FALSE);
    $config->set('exclude_self_reference', FALSE);
    $config->set('ignore_tags', '');
    $config->set('boundary_exceptions', '');
    $config->save();

    $this->manager = $this->container->get('term_glossary.manager');
  }

  /**
   * Tests empty input returns FALSE.
   *
   * The replaceFieldValue() method short-circuits on empty
   * string before any DOM parsing or term lookup.
   */
  public function testReplaceFieldValueEmpty(): void {
    $replacement = $this->manager->replaceFieldValue('', 'glossary');
    $this->assertFalse($replacement);
  }

  /**
   * Tests invalid UTF-8 returns FALSE.
   *
   * Validates UTF-8 via Unicode::validateUtf8() and returns
   * FALSE for malformed input, preventing DOM parsing errors.
   */
  public function testReplaceFieldValueInvalidUtf8(): void {
    // Create a string with invalid UTF-8 bytes.
    $invalid = "Hello \x80\x81 world";
    $replacement = $this->manager->replaceFieldValue(
      $invalid, 'glossary',
    );
    $this->assertFalse($replacement);
  }

  /**
   * Tests basic term matching produces a link.
   *
   * Creates a 'Drupal' term and passes HTML containing the
   * word. Asserts the result array has count=1, the 'glos-term'
   * CSS class, the term text, a link to the term path, and
   * the vocabulary cache tag.
   */
  public function testReplaceFieldValueBasicMatch(): void {
    $term = $this->createGlossaryTerm('Drupal');

    $input = '<p>Welcome to Drupal world.</p>';
    $replacement = $this->manager->replaceFieldValue(
      $input, 'glossary',
    );

    $this->assertIsArray($replacement);
    $this->assertEquals(1, $replacement['count']);
    $this->assertStringContainsString(
      'glos-term', $replacement['html'],
    );
    $this->assertStringContainsString(
      'Drupal', $replacement['html'],
    );
    $this->assertStringContainsString(
      '/taxonomy/term/' . $term->id(),
      $replacement['html'],
    );
    $this->assertContains(
      'taxonomy_term_list:glossary',
      $replacement['cache_tags'],
    );
  }

  /**
   * Tests input without terms returns result with no count key.
   *
   * When a term exists but the input text does not contain it,
   * the result is still an array (with cache tags) but has no
   * 'count' key, indicating zero replacements.
   */
  public function testReplaceFieldValueNoMatch(): void {
    $this->createGlossaryTerm('Drupal');

    $input = '<p>No matching words here.</p>';
    $replacement = $this->manager->replaceFieldValue(
      $input, 'glossary',
    );

    $this->assertIsArray($replacement);
    $this->assertArrayNotHasKey('count', $replacement);
    $this->assertContains(
      'taxonomy_term_list:glossary',
      $replacement['cache_tags'],
    );
  }

  /**
   * Tests multiple terms are replaced.
   *
   * Creates 'Drupal' and 'Module' terms, passes HTML containing
   * both words. Asserts count=2 and both terms appear in the
   * output HTML.
   */
  public function testReplaceFieldValueMultipleTerms(): void {
    $this->createGlossaryTerm('Drupal');
    $this->createGlossaryTerm('Module');

    $input = '<p>Drupal has a Module system.</p>';
    $replacement = $this->manager->replaceFieldValue(
      $input, 'glossary',
    );

    $this->assertIsArray($replacement);
    $this->assertEquals(2, $replacement['count']);
    $this->assertStringContainsString(
      'Drupal', $replacement['html'],
    );
    $this->assertStringContainsString(
      'Module', $replacement['html'],
    );
  }

  /**
   * Tests case-sensitive matching.
   *
   * Enables the case_sensitive config option. Verifies that
   * exact case 'Drupal' matches (count=1) while lowercase
   * 'drupal' does not match (no count key).
   */
  public function testReplaceFieldValueCaseSensitive(): void {
    $this->createGlossaryTerm('Drupal');

    $this->config('term_glossary.glossaryconfig')
      ->set('case_sensitive', TRUE)
      ->save();

    // Exact case matches.
    $replacement = $this->manager->replaceFieldValue(
      '<p>Drupal is great.</p>', 'glossary',
    );
    $this->assertIsArray($replacement);
    $this->assertEquals(1, $replacement['count']);

    // Wrong case does not match.
    $replacement = $this->manager->replaceFieldValue(
      '<p>drupal is great.</p>', 'glossary',
    );
    $this->assertIsArray($replacement);
    $this->assertArrayNotHasKey('count', $replacement);
  }

  /**
   * Tests case-insensitive matching (default).
   *
   * With case_sensitive=FALSE (the default), verifies that
   * lowercase 'drupal' still matches the 'Drupal' term
   * (count=1).
   */
  public function testReplaceFieldValueCaseInsensitive(): void {
    $this->createGlossaryTerm('Drupal');

    $replacement = $this->manager->replaceFieldValue(
      '<p>drupal is great.</p>', 'glossary',
    );
    $this->assertIsArray($replacement);
    $this->assertEquals(1, $replacement['count']);
  }

  /**
   * Tests that a synonym matches when the term name is absent.
   *
   * Enables term_synonyms with a multi-value synonyms field. The
   * input contains only a synonym (in a different case), so the
   * term must still be considered and linked via the synonym.
   */
  public function testReplaceFieldValueSynonymWithoutTermName(): void {
    $this->enableSynonymsField();

    $term = $this->createGlossaryTerm('Content Management System', [
      'field_synonyms' => ['CMS'],
    ]);

    $replacement = $this->manager->replaceFieldValue(
      '<p>Pick a <em>cms</em> that scales.</p>', 'glossary',
    );
    $this->assertIsArray($replacement);
    $this->assertEquals(1, $replacement['count']);
    $this->assertStringContainsString(
      '/taxonomy/term/' . $term->id(),
      $replacement['html'],
    );
  }

  /**
   * Tests single match with match_all_synonyms across text nodes.
   *
   * The name and the synonym are each linked once, in the first
   * paragraph. In the second paragraph the name is already seen,
   * so it must not be linked again even though it is present.
   */
  public function testReplaceFieldValueSingleMatchAllSynonyms(): void {
    $this->enableSynonymsField();
    $this->config('term_glossary.glossaryconfig')
      ->set('single_match', TRUE)
      ->set('match_all_synonyms', TRUE)
      ->save();

    $this->createGlossaryTerm('Content Management System', [
      'field_synonyms' => ['CMS'],
    ]);

    $replacement = $this->manager->replaceFieldValue(
      '<p>A CMS is a Content Management System.</p><p>Content Management System again.</p>',
      'glossary',
    );
    $this->assertIsArray($replacement);
    $this->assertEquals(2, $replacement['count']);
    $this->assertStringContainsString(
      '<p>Content Management System again.</p>',
      $replacement['html'],
    );
  }

  /**
   * Tests letters that only case folding treats as equal.
   *
   * The case-insensitive regex matches the micro sign "µ" (U+00B5)
   * with the Greek "μ" (U+03BC), and the final sigma "ς" with "Σ",
   * but mb_strtolower() maps neither pair to the same letter. The
   * absent-term pre-check must not skip these terms.
   */
  public function testReplaceFieldValueCaseFoldedLetters(): void {
    // cspell:ignore λόγος ΛΌΓΟΣΤΗΣ
    $micrograms = $this->createGlossaryTerm('µg');
    $logos = $this->createGlossaryTerm('λόγος');

    $replacement = $this->manager->replaceFieldValue(
      '<p>Take 5 μg daily.</p><p>ΛΌΓΟΣΤΗΣ</p>', 'glossary',
    );
    $this->assertIsArray($replacement);
    $this->assertEquals(2, $replacement['count']);
    $this->assertStringContainsString(
      '/taxonomy/term/' . $micrograms->id(),
      $replacement['html'],
    );
    $this->assertStringContainsString(
      '/taxonomy/term/' . $logos->id(),
      $replacement['html'],
    );
  }

  /**
   * Tests matching against a large vocabulary.
   *
   * Creates many terms of which only two appear in the input,
   * in different cases and in separate text nodes. Exactly those
   * two must be linked and the surrounding markup preserved.
   */
  public function testReplaceFieldValueLargeVocabulary(): void {
    for ($i = 0; $i < 50; $i++) {
      $this->createGlossaryTerm('Unused term ' . $i);
    }
    $alpha = $this->createGlossaryTerm('Alpha');
    $beta = $this->createGlossaryTerm('Beta release');

    $replacement = $this->manager->replaceFieldValue(
      '<p>ALPHA first.</p><ul><li>Then the beta Release.</li></ul>',
      'glossary',
    );
    $this->assertIsArray($replacement);
    $this->assertEquals(2, $replacement['count']);
    $this->assertStringContainsString(
      '/taxonomy/term/' . $alpha->id(),
      $replacement['html'],
    );
    $this->assertStringContainsString(
      '/taxonomy/term/' . $beta->id(),
      $replacement['html'],
    );
    $this->assertStringContainsString('<ul><li>', $replacement['html']);
  }

  /**
   * Tests full-word matching.
   *
   * Enables full_word config. Verifies that 'cat' as a
   * standalone word matches (count=1) but 'cat' inside
   * 'category' does not match (word boundary enforcement).
   */
  public function testReplaceFieldValueFullWord(): void {
    $this->createGlossaryTerm('cat');

    $this->config('term_glossary.glossaryconfig')
      ->set('full_word', TRUE)
      ->save();

    // Full word "cat" matches.
    $replacement = $this->manager->replaceFieldValue(
      '<p>The cat sat on the mat.</p>', 'glossary',
    );
    $this->assertIsArray($replacement);
    $this->assertEquals(1, $replacement['count']);

    // "cat" inside "category" does not match.
    $replacement = $this->manager->replaceFieldValue(
      '<p>This is a category page.</p>', 'glossary',
    );
    $this->assertIsArray($replacement);
    $this->assertArrayNotHasKey('count', $replacement);
  }

  /**
   * Tests boundary exceptions with full-word matching.
   *
   * Enables full_word and sets '-' as a boundary exception.
   * Standalone 'step' still matches (count=1), but 'step'
   * inside 'step-by-step' does not because the hyphen is
   * not treated as a word boundary.
   */
  public function testReplaceFieldValueFullWordBoundaryExceptions(): void {
    $this->createGlossaryTerm('step');

    $this->config('term_glossary.glossaryconfig')
      ->set('full_word', TRUE)
      ->set('boundary_exceptions', '-')
      ->save();

    // "step" alone still matches.
    $replacement = $this->manager->replaceFieldValue(
      '<p>Take a step forward.</p>', 'glossary',
    );
    $this->assertIsArray($replacement);
    $this->assertEquals(1, $replacement['count']);

    // "step" inside "step-by-step" does not match
    // because hyphen is a boundary exception.
    $replacement = $this->manager->replaceFieldValue(
      '<p>Follow the step-by-step guide.</p>',
      'glossary',
    );
    $this->assertIsArray($replacement);
    $this->assertArrayNotHasKey('count', $replacement);
  }

  /**
   * Tests single match per field.
   *
   * Enables single_match config. Passes HTML with 'Drupal'
   * appearing twice. Asserts count=1 — only the first
   * occurrence is replaced within the same field value.
   */
  public function testReplaceFieldValueSingleMatchPerField(): void {
    $this->createGlossaryTerm('Drupal');

    $this->config('term_glossary.glossaryconfig')
      ->set('single_match', TRUE)
      ->save();

    $input = '<p>Drupal and Drupal again.</p>';
    $replacement = $this->manager->replaceFieldValue(
      $input, 'glossary',
    );

    $this->assertIsArray($replacement);
    $this->assertEquals(1, $replacement['count']);
  }

  /**
   * Tests single match per content across fields.
   *
   * Enables single_match_per_content config and uses a root
   * entity. The first replaceFieldValue() call matches
   * 'Drupal' (count=1). The second call with the same root
   * entity finds no new matches (no count key) because the
   * term was already seen for that entity.
   */
  public function testReplaceFieldValueSingleMatchPerContent(): void {
    $this->createGlossaryTerm('Drupal');

    $this->config('term_glossary.glossaryconfig')
      ->set('single_match_per_content', TRUE)
      ->save();

    // Create a mock root entity.
    $root_entity = $this->createGlossaryTerm('Root');

    // First field — should match.
    $first_replacement = $this->manager->replaceFieldValue(
      '<p>Drupal is great.</p>',
      'glossary',
      $root_entity,
    );
    $this->assertIsArray($first_replacement);
    $this->assertEquals(1, $first_replacement['count']);

    // Second field (same root entity) — already seen.
    $second_replacement = $this->manager->replaceFieldValue(
      '<p>Drupal is powerful.</p>',
      'glossary',
      $root_entity,
    );
    $this->assertIsArray($second_replacement);
    $this->assertArrayNotHasKey('count', $second_replacement);
  }

  /**
   * Tests exclude self-reference for taxonomy terms.
   *
   * Enables exclude_self_reference and passes the term itself
   * as root_entity. The term is removed from the lookup list
   * so it does not link to itself (no count key in result).
   */
  public function testReplaceFieldValueExcludeSelfReference(): void {
    $term = $this->createGlossaryTerm('Drupal');

    $this->config('term_glossary.glossaryconfig')
      ->set('exclude_self_reference', TRUE)
      ->save();

    // When root_entity is the term itself, skip it.
    $replacement = $this->manager->replaceFieldValue(
      '<p>Drupal is a CMS.</p>',
      'glossary',
      $term,
    );
    $this->assertIsArray($replacement);
    $this->assertArrayNotHasKey('count', $replacement);
  }

  /**
   * Tests that text inside configured ignore_tags is skipped.
   *
   * Sets ignore_tags to 'code,pre'. 'Drupal' inside a
   * <code> element is excluded from the XPath query via
   * the ancestor::code condition, so no match occurs.
   */
  public function testReplaceFieldValueIgnoreTags(): void {
    $this->createGlossaryTerm('Drupal');

    $this->config('term_glossary.glossaryconfig')
      ->set('ignore_tags', 'code,pre')
      ->save();

    $input = '<p>Learn <code>Drupal</code> today.</p>';
    $replacement = $this->manager->replaceFieldValue(
      $input, 'glossary',
    );

    $this->assertIsArray($replacement);
    $this->assertArrayNotHasKey('count', $replacement);
  }

  /**
   * Tests that text inside anchor tags is not replaced.
   *
   * The XPath query always excludes ancestor::a nodes.
   * 'Drupal' inside an <a> tag is skipped so no replacement
   * occurs (no count key in result).
   */
  public function testReplaceFieldValueInsideLinks(): void {
    $this->createGlossaryTerm('Drupal');

    $input = '<p><a href="/foo">Drupal</a> is great.</p>';
    $replacement = $this->manager->replaceFieldValue(
      $input, 'glossary',
    );

    // "Drupal" inside the <a> is skipped; the one
    // outside is not present in this input.
    $this->assertIsArray($replacement);
    $this->assertArrayNotHasKey('count', $replacement);
  }

  /**
   * Tests that glossary-exclude class elements are skipped.
   *
   * The XPath query excludes nodes whose ancestor has the
   * 'glossary-exclude' CSS class. 'Drupal' inside such a
   * <span> is not replaced (no count key).
   */
  public function testReplaceFieldValueGlossaryExcludeClass(): void {
    $this->createGlossaryTerm('Drupal');

    $input = '<p><span class="glossary-exclude">Drupal</span></p>';
    $replacement = $this->manager->replaceFieldValue(
      $input, 'glossary',
    );

    $this->assertIsArray($replacement);
    $this->assertArrayNotHasKey('count', $replacement);
  }

  /**
   * Tests that getHandler returns a handler instance.
   *
   * Verifies the manager resolves a non-null handler
   * implementing TermGlossaryHandlerInterface based on
   * the configured integration_type ('link').
   */
  public function testGetHandler(): void {
    $handler = $this->manager->getHandler();
    $this->assertNotNull($handler);
    $this->assertInstanceOf(
      '\Drupal\term_glossary\TermGlossaryHandlerInterface',
      $handler,
    );
  }

  /**
   * Tests getConfig returns the module's config object.
   *
   * Verifies the ImmutableConfig returned by getConfig()
   * contains the values set during setUp (vocab='glossary',
   * integration_type='link').
   */
  public function testGetConfig(): void {
    $config = $this->manager->getConfig();
    $this->assertNotNull($config);
    $this->assertEquals('glossary', $config->get('vocab'));
    $this->assertEquals('link', $config->get('integration_type'));
  }

  /**
   * Tests term caching behavior.
   *
   * Calls replaceFieldValue() twice with the same term. Both
   * calls succeed with count=1, verifying that the internal
   * term cache does not break subsequent lookups.
   */
  public function testGetTermsCaching(): void {
    $this->createGlossaryTerm('CacheTest');

    // First call — populates cache.
    $first_replacement = $this->manager->replaceFieldValue(
      '<p>CacheTest term.</p>', 'glossary',
    );
    $this->assertIsArray($first_replacement);
    $this->assertEquals(1, $first_replacement['count']);

    // Second call should work from cache.
    $second_replacement = $this->manager->replaceFieldValue(
      '<p>CacheTest again.</p>', 'glossary',
    );
    $this->assertIsArray($second_replacement);
    $this->assertEquals(1, $second_replacement['count']);
  }

  /**
   * Adds a multi-value synonyms field to glossary terms and enables it.
   */
  protected function enableSynonymsField(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_synonyms',
      'entity_type' => 'taxonomy_term',
      'type' => 'string',
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_synonyms',
      'entity_type' => 'taxonomy_term',
      'bundle' => 'glossary',
    ])->save();
    $this->config('term_glossary.glossaryconfig')
      ->set('term_synonyms', TRUE)
      ->set('synonyms_field', 'field_synonyms')
      ->save();
  }

}
