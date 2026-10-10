<?php

declare(strict_types=1);

namespace Drupal\term_glossary_hook_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\State\StateInterface;

/**
 * Records what the term_glossary alter hooks are handed.
 */
class TermGlossaryHookTestHooks {

  /**
   * The state key the recorded search term is stored under.
   */
  const SEARCH_TERM_STATE = 'term_glossary_hook_test.search_term';

  /**
   * Constructs a TermGlossaryHookTestHooks object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service the recorded argument is stored in.
   */
  public function __construct(
    protected StateInterface $state,
  ) {}

  /**
   * Implements hook_term_glossary_alter_results().
   *
   * @param mixed $results
   *   The results the endpoint built.
   * @param mixed $terms
   *   The terms the glossary serves.
   * @param mixed $search_term
   *   The search term or letter, recorded so a test can read it.
   */
  #[Hook('term_glossary_alter_results')]
  public function alterResults(&$results, $terms, $search_term): void {
    $this->state->set(self::SEARCH_TERM_STATE, $search_term);
  }

}
