<?php

declare(strict_types=1);

namespace Drupal\Tests\term_glossary\Kernel\Controller;

use Drupal\KernelTests\KernelTestBase;
use Drupal\term_glossary\Controller\TermGlossaryController;
use Drupal\term_glossary_hook_test\Hook\TermGlossaryHookTestHooks;
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
 * Tests what hook_term_glossary_alter_results() is handed.
 *
 * @group term_glossary
 */
#[CoversClass(TermGlossaryController::class)]
#[Group('term_glossary')]
#[RunTestsInSeparateProcesses]
class TermGlossaryAlterResultsHookTest extends KernelTestBase {

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
    'term_glossary_hook_test',
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
    $this->installConfig(['user']);
    $this->grantPermissions(
      Role::load(RoleInterface::ANONYMOUS_ID),
      ['access content'],
    );

    $this->config('term_glossary.glossaryconfig')
      ->set('vocab', 'glossary')
      ->set('integration_type', 'link')
      ->set('view_mode', '')
      ->save();

    $this->createGlossaryTerm('Banana');

    $this->controller = TermGlossaryController::create(
      $this->container,
    );
  }

  /**
   * Tests the per-term endpoint hands the hook the search term.
   */
  public function testSearchTermIsHandedToTheHook(): void {
    $request = Request::create(
      '/glossary-search-term', 'GET', ['t' => 'ana'],
    );
    $request->setSession(
      new Session(new MockArraySessionStorage()),
    );
    $this->container->get('request_stack')->push($request);

    $this->controller->apiSearchPerTerm();

    $this->assertSame('ana', $this->recordedSearchTerm());
  }

  /**
   * Tests the per-letter endpoint hands the hook the letter.
   */
  public function testLetterIsHandedToTheHook(): void {
    $this->controller->apiSearchPerLetter('B');

    $this->assertSame('B', $this->recordedSearchTerm());
  }

  /**
   * Reads back what the test module recorded.
   *
   * @return mixed
   *   The third argument the hook was invoked with.
   */
  protected function recordedSearchTerm(): mixed {
    return $this->container->get('state')
      ->get(TermGlossaryHookTestHooks::SEARCH_TERM_STATE);
  }

}
