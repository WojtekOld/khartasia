<?php

declare(strict_types=1);

namespace Drupal\Tests\term_glossary\Kernel\Controller;

use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\term_glossary\Controller\TermGlossaryController;
use Drupal\Tests\term_glossary\Traits\GlossaryTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the TermGlossaryController JSON API endpoints.
 *
 * @group term_glossary
 */
#[CoversClass(TermGlossaryController::class)]
#[Group('term_glossary')]
#[RunTestsInSeparateProcesses]
class TermGlossaryControllerTest extends KernelTestBase {

  use GlossaryTestTrait;
  use UserCreationTrait;

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
   * The controller under test.
   *
   * @var \Drupal\term_glossary\Controller\TermGlossaryController
   */
  protected TermGlossaryController $controller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installGlossaryTestDependencies();
    // The endpoints are reachable by anonymous visitors, who need the
    // 'access content' permission to view a published term.
    $this->installConfig(['user']);
    $this->grantPermissions(
      Role::load(RoleInterface::ANONYMOUS_ID),
      ['access content'],
    );

    $this->config('term_glossary.glossaryconfig')
      ->set('vocab', 'glossary')
      ->set('integration_type', 'link')
      ->set('view_mode', '')
      ->set('json_term_cache', 3600)
      ->save();

    // Create test terms.
    $this->createGlossaryTerm('Apple');
    $this->createGlossaryTerm('Avocado');
    $this->createGlossaryTerm('Banana');

    $this->controller = TermGlossaryController::create(
      $this->container,
    );
  }

  /**
   * Tests apiSearchPerLetter returns matching terms.
   *
   * Calls the endpoint with letter 'A' and verifies the JSON
   * response contains Apple and Avocado (STARTS_WITH 'A')
   * but not Banana.
   */
  public function testApiSearchPerLetterValid(): void {
    $response = $this->controller->apiSearchPerLetter('A');

    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertIsArray($data);
    // Should contain Apple and Avocado.
    $names = array_column($data, 'name');
    $this->assertContains('Apple', $names);
    $this->assertContains('Avocado', $names);
    $this->assertNotContains('Banana', $names);
  }

  /**
   * Tests apiSearchPerLetter with invalid input.
   *
   * Passes '123' which fails the /^[a-zA-Z]$/ regex check.
   * The response is still HTTP 200 but contains a 'message'
   * key instead of term data.
   */
  public function testApiSearchPerLetterInvalid(): void {
    $response = $this->controller->apiSearchPerLetter('123');

    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertArrayHasKey('message', $data);
  }

  /**
   * Tests apiGetTermById returns term data.
   *
   * Creates a 'Cherry' term, requests it by ID, and verifies
   * the JSON response contains the correct tid, name, and an
   * 'html' key with rendered output.
   */
  public function testApiGetTermById(): void {
    $term = $this->createGlossaryTerm('Cherry');

    $response = $this->controller->apiGetTermById(
      $term->id(),
    );

    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertEquals($term->id(), $data['tid']);
    $this->assertEquals('Cherry', $data['name']);
    $this->assertArrayHasKey('html', $data);
  }

  /**
   * Tests apiGetTermById with invalid tid.
   *
   * Passes a non-existent tid (99999). The response is HTTP
   * 200 (JS client does not check for errors) but contains
   * a 'message' key indicating no results.
   */
  public function testApiGetTermByIdNotFound(): void {
    $response = $this->controller->apiGetTermById(99999);

    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertArrayHasKey('message', $data);
  }

  /**
   * Tests apiGetTermById does not expose an unpublished term.
   *
   * An anonymous visitor may not view an unpublished term, so
   * the endpoint must answer with the 'no results' message
   * instead of the term data.
   */
  public function testApiGetTermByIdSkipsUnpublishedTerms(): void {
    $term = $this->createGlossaryTerm('Cherimoya', ['status' => 0]);

    $response = $this->controller->apiGetTermById($term->id());

    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertArrayHasKey('message', $data);
    $this->assertArrayNotHasKey('name', $data);
    $this->assertStringNotContainsString(
      'Cherimoya', (string) $response->getContent(),
    );
  }

  /**
   * Tests apiGetTermById only serves the configured vocabularies.
   *
   * A term living in a vocabulary the glossary was never pointed
   * at is not glossary content and must not be readable through
   * the endpoint.
   */
  public function testApiGetTermByIdSkipsTermsOutsideTheGlossary(): void {
    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    $term = Term::create([
      'vid' => 'tags',
      'name' => 'Clementine',
      'langcode' => 'en',
      'status' => 1,
    ]);
    $term->save();

    $response = $this->controller->apiGetTermById($term->id());

    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertArrayHasKey('message', $data);
    $this->assertArrayNotHasKey('name', $data);
    $this->assertStringNotContainsString(
      'Clementine', (string) $response->getContent(),
    );
  }

  /**
   * Tests apiGetTermById hides an unpublished term from an editor.
   *
   * A user allowed to administer taxonomy may view an unpublished
   * term, but the glossary lists published terms only, so the
   * endpoint must not serve it to them either.
   */
  public function testApiGetTermByIdHidesUnpublishedTermsFromPrivilegedUsers(): void {
    $term = $this->createGlossaryTerm('Cranberry', ['status' => 0]);
    $this->setUpCurrentUser([], ['access content', 'administer taxonomy']);

    $response = $this->controller->apiGetTermById($term->id());

    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertArrayHasKey('message', $data);
    $this->assertArrayNotHasKey('name', $data);
    $this->assertStringNotContainsString(
      'Cranberry', (string) $response->getContent(),
    );
  }

  /**
   * Tests the letter search hides an unpublished term from an editor.
   */
  public function testApiSearchPerLetterHidesUnpublishedTermsFromPrivilegedUsers(): void {
    $this->createGlossaryTerm('Apricot', ['status' => 0]);
    $this->setUpCurrentUser([], ['access content', 'administer taxonomy']);

    $response = $this->controller->apiSearchPerLetter('A');

    $data = json_decode($response->getContent(), TRUE);
    $names = array_column($data, 'name');
    $this->assertContains('Apple', $names);
    $this->assertNotContains('Apricot', $names);
    $this->assertStringNotContainsString(
      'Apricot', (string) $response->getContent(),
    );
  }

  /**
   * Tests apiGetTermById keeps its shared cache for anonymous visitors.
   *
   * The configured JSON cache duration is what makes the endpoint
   * cheap on a public site, so it must survive the access checks.
   */
  public function testApiGetTermByIdStaysSharedCacheableForAnonymousVisitors(): void {
    $term = $this->createGlossaryTerm('Cherry');

    $response = $this->controller->apiGetTermById($term->id());

    $this->assertTrue($response->headers->hasCacheControlDirective('public'));
    $this->assertEquals(
      3600, $response->headers->getCacheControlDirective('s-maxage'),
    );
    $this->assertEquals(
      3600, $response->headers->getCacheControlDirective('max-age'),
    );
  }

  /**
   * Tests apiGetTermById offers no shared cache to a logged-in user.
   *
   * The payload depends on what the visitor may view, so the
   * response must not be offered to a reverse proxy or a CDN.
   */
  public function testApiGetTermByIdIsNotSharedCacheableForAuthenticatedUsers(): void {
    $term = $this->createGlossaryTerm('Chestnut');
    $this->setUpCurrentUser([], ['access content']);

    $response = $this->controller->apiGetTermById($term->id());

    $data = json_decode($response->getContent(), TRUE);
    $this->assertEquals('Chestnut', $data['name']);
    $this->assertFalse($response->headers->hasCacheControlDirective('public'));
    $this->assertNull($response->headers->getCacheControlDirective('s-maxage'));
  }

  /**
   * Tests apiGetTermById sends no cache headers when caching is off.
   */
  public function testApiGetTermByIdWithCachingDisabled(): void {
    $this->config('term_glossary.glossaryconfig')
      ->set('json_term_cache', 0)
      ->save();
    $term = $this->createGlossaryTerm('Cherry');

    $response = $this->controller->apiGetTermById($term->id());

    $data = json_decode($response->getContent(), TRUE);
    $this->assertEquals('Cherry', $data['name']);
    $this->assertFalse($response->headers->hasCacheControlDirective('public'));
    $this->assertNull($response->headers->getCacheControlDirective('s-maxage'));
  }

  /**
   * Tests apiSearchPerLetter does not expose unpublished terms.
   */
  public function testApiSearchPerLetterSkipsUnpublishedTerms(): void {
    $this->createGlossaryTerm('Apricot', ['status' => 0]);

    $response = $this->controller->apiSearchPerLetter('A');

    $data = json_decode($response->getContent(), TRUE);
    $names = array_column($data, 'name');
    $this->assertContains('Apple', $names);
    $this->assertNotContains('Apricot', $names);
    $this->assertStringNotContainsString(
      'Apricot', (string) $response->getContent(),
    );
  }

  /**
   * Tests apiSearchPerLetter reports no results when access is denied.
   *
   * Only the unpublished term starts with the letter, so the
   * response must be the 'no results' message and not an empty
   * result list.
   */
  public function testApiSearchPerLetterReportsNoResultsWhenAllMatchesAreDenied(): void {
    $this->createGlossaryTerm('Zucchini', ['status' => 0]);

    $response = $this->controller->apiSearchPerLetter('Z');

    $data = json_decode($response->getContent(), TRUE);
    $this->assertArrayHasKey('message', $data);
    $this->assertStringNotContainsString(
      'Zucchini', (string) $response->getContent(),
    );
  }

  /**
   * Tests apiSearchPerTerm does not expose unpublished terms.
   */
  public function testApiSearchPerTermSkipsUnpublishedTerms(): void {
    $this->createGlossaryTerm('Canary', ['status' => 0]);
    $this->pushSearchRequest('ana');

    $response = $this->controller->apiSearchPerTerm();

    $data = json_decode($response->getContent(), TRUE);
    $names = array_column($data, 'name');
    $this->assertContains('Banana', $names);
    $this->assertNotContains('Canary', $names);
    $this->assertStringNotContainsString(
      'Canary', (string) $response->getContent(),
    );
  }

  /**
   * Tests apiSearchPerTerm reports no results when access is denied.
   *
   * Only the unpublished term matches the query, so the
   * response must be the 'no results' message and not an empty
   * result list.
   */
  public function testApiSearchPerTermReportsNoResultsWhenAllMatchesAreDenied(): void {
    $this->createGlossaryTerm('Kumquat', ['status' => 0]);
    $this->pushSearchRequest('Kumquat');

    $response = $this->controller->apiSearchPerTerm();

    $data = json_decode($response->getContent(), TRUE);
    $this->assertArrayHasKey('message', $data);
    $this->assertStringNotContainsString(
      '"name"', (string) $response->getContent(),
    );
  }

  /**
   * Pushes a search request with the given query onto the stack.
   *
   * @param string $query
   *   The search term query parameter.
   */
  protected function pushSearchRequest(string $query): void {
    $request = Request::create(
      '/glossary-search-term', 'GET', ['t' => $query],
    );
    $request->setSession(
      new Session(new MockArraySessionStorage()),
    );
    $this->container->get('request_stack')->push($request);
  }

  /**
   * Tests apiSearchPerTerm with CONTAINS match.
   *
   * Pushes a request with query parameter t='ana' onto the
   * request stack. The controller performs a CONTAINS query
   * and returns 'Banana' (which contains 'ana') in the JSON
   * response.
   */
  public function testApiSearchPerTerm(): void {
    $this->pushSearchRequest('ana');

    $response = $this->controller->apiSearchPerTerm();

    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    // "Banana" contains "ana".
    $names = array_column($data, 'name');
    $this->assertContains('Banana', $names);
  }

  /**
   * Tests apiSearchPerTerm with no results.
   *
   * Pushes a request with t='zzzznotfound'. Neither the
   * CONTAINS nor the LIKE fallback query returns results,
   * so the response contains a 'message' key.
   */
  public function testApiSearchPerTermNoResults(): void {
    $this->pushSearchRequest('zzzznotfound');

    $response = $this->controller->apiSearchPerTerm();

    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertArrayHasKey('message', $data);
  }

}
