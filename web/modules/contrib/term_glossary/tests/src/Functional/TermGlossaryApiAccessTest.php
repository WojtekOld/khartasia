<?php

declare(strict_types=1);

namespace Drupal\Tests\term_glossary\Functional;

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\term_glossary\Traits\GlossaryTestTrait;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests what the glossary JSON endpoints hand to a visitor.
 *
 * @group term_glossary
 */
#[Group('term_glossary')]
#[RunTestsInSeparateProcesses]
class TermGlossaryApiAccessTest extends BrowserTestBase {

  use GlossaryTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
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
   * A published term of the glossary vocabulary.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $publishedTerm;

  /**
   * An unpublished term of the glossary vocabulary.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $unpublishedTerm;

  /**
   * A published term of a vocabulary the glossary does not use.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $otherVocabularyTerm;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    Vocabulary::create(['vid' => 'glossary', 'name' => 'Glossary'])->save();
    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();

    $this->config('term_glossary.glossaryconfig')
      ->set('vocab', 'glossary')
      ->set('integration_type', 'link')
      ->set('view_mode', '')
      ->set('json_term_cache', 3600)
      ->save();

    $this->publishedTerm = $this->createGlossaryTerm('Sunshine', [
      'description' => 'A published description.',
    ]);
    $this->unpublishedTerm = $this->createGlossaryTerm('Sunset', [
      'status' => 0,
      'description' => 'A draft description.',
    ]);
    $this->otherVocabularyTerm = Term::create([
      'vid' => 'tags',
      'name' => 'Sandstorm',
      'langcode' => 'en',
      'status' => 1,
      'description' => 'Not a glossary description.',
    ]);
    $this->otherVocabularyTerm->save();
    // The endpoints only require the 'access content' permission.
    user_role_grant_permissions(
      RoleInterface::ANONYMOUS_ID, ['access content'],
    );
  }

  /**
   * Tests what an anonymous visitor gets from the term endpoint.
   */
  public function testTermEndpointForAnonymousVisitors(): void {
    $assert_session = $this->assertSession();

    // A published glossary term is served, with the cache headers the
    // module's JSON cache duration asks for.
    $this->drupalGet($this->termPath($this->publishedTerm->id()));
    $assert_session->statusCodeEquals(200);
    $assert_session->responseContains('Sunshine');
    $cache_control = $this->getSession()->getResponseHeader('Cache-Control');
    $this->assertStringContainsString('public', $cache_control);
    $this->assertStringContainsString('s-maxage=3600', $cache_control);

    // An unpublished term is not readable, and neither is its
    // description.
    $this->drupalGet($this->termPath($this->unpublishedTerm->id()));
    $assert_session->statusCodeEquals(200);
    $assert_session->responseNotContains('Sunset');
    $assert_session->responseNotContains('A draft description.');

    // A term of a vocabulary the glossary was never pointed at is not
    // glossary content.
    $this->drupalGet($this->termPath($this->otherVocabularyTerm->id()));
    $assert_session->statusCodeEquals(200);
    $assert_session->responseNotContains('Sandstorm');
    $assert_session->responseNotContains('Not a glossary description.');
  }

  /**
   * Tests the search endpoints hide what the visitor may not view.
   */
  public function testSearchEndpointsForAnonymousVisitors(): void {
    $assert_session = $this->assertSession();

    $this->drupalGet('glossary-search-letter/S');
    $assert_session->statusCodeEquals(200);
    $assert_session->responseContains('Sunshine');
    $assert_session->responseNotContains('Sunset');
    // The other vocabulary was already out of the query.
    $assert_session->responseNotContains('Sandstorm');

    // Only the unpublished term matches, so the answer is the 'no
    // results' message, which quotes the search string back.
    $this->drupalGet('glossary-search-term', ['query' => ['t' => 'Sunset']]);
    $assert_session->statusCodeEquals(200);
    $assert_session->responseContains('No results found for search term');
    $assert_session->responseNotContains('A draft description.');
    $assert_session->responseNotContains('"name"');
  }

  /**
   * Tests an editor does not read an unpublished term either.
   *
   * A user allowed to administer taxonomy may view an unpublished
   * term, but the glossary lists published terms only, the way
   * the terms highlighted in content do.
   */
  public function testTermEndpointForPrivilegedUsers(): void {
    $assert_session = $this->assertSession();
    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'administer taxonomy',
    ]));

    $this->drupalGet($this->termPath($this->unpublishedTerm->id()));
    $assert_session->statusCodeEquals(200);
    $assert_session->responseNotContains('Sunset');
    $assert_session->responseNotContains('A draft description.');
    // Their answer is specific to them, so it is not offered to a
    // shared cache.
    $cache_control = $this->getSession()->getResponseHeader('Cache-Control');
    $this->assertStringNotContainsString('public', $cache_control);
    $this->assertStringNotContainsString('s-maxage', $cache_control);
    $this->assertStringContainsString('private', $cache_control);

    $this->drupalGet('glossary-search-letter/S');
    $assert_session->statusCodeEquals(200);
    $assert_session->responseContains('Sunshine');
    $assert_session->responseNotContains('Sunset');
  }

  /**
   * Builds the path of the term endpoint.
   *
   * @param string|int $tid
   *   The term id to request.
   *
   * @return string
   *   The endpoint path.
   */
  protected function termPath($tid): string {
    return 'glossary-get-term-by-id/' . $tid;
  }

}
