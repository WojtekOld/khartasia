<?php

declare(strict_types=1);

namespace Drupal\Tests\term_glossary\Traits;

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Provides shared setup and helpers for term_glossary tests.
 *
 * @method void installEntitySchema(string $entity_type_id)
 * @method void installConfig(array|string $modules)
 */
trait GlossaryTestTrait {

  /**
   * Installs entity schemas, config and creates the glossary vocabulary.
   *
   * Installs taxonomy_term and user entity schemas, the module
   * config for term_glossary, taxonomy, filter and text, and
   * creates a 'glossary' vocabulary. Kernel test classes using
   * this trait should call this method from setUp().
   */
  protected function installGlossaryTestDependencies(): void {
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('user');
    $this->installConfig(['term_glossary', 'taxonomy', 'filter', 'text']);

    Vocabulary::create([
      'vid' => 'glossary',
      'name' => 'Glossary',
    ])->save();
  }

  /**
   * Creates a glossary term.
   *
   * @param string $name
   *   The term name.
   * @param array $values
   *   Additional values merged with defaults.
   *
   * @return \Drupal\taxonomy\Entity\Term
   *   The created term.
   */
  protected function createGlossaryTerm(string $name, array $values = []): Term {
    $term = Term::create($values + [
      'vid' => 'glossary',
      'name' => $name,
      'langcode' => 'en',
      'status' => 1,
    ]);
    $term->save();
    return $term;
  }

}
