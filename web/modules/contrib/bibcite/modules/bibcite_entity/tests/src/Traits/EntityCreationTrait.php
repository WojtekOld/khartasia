<?php

namespace Drupal\Tests\bibcite_entity\Traits;

use Drupal\bibcite_entity\Entity\Keyword;
use Drupal\bibcite_entity\Entity\Reference;
use Drupal\bibcite_entity\Entity\Contributor;

/**
 * Provides common helper methods to create entities for bibcite_entity module.
 */
trait EntityCreationTrait {

  /**
   * Creates and returns a reference entity.
   *
   * @param array $data
   *   The data to use to create the entity.
   *
   * @return \Drupal\bibcite_entity\Entity\ReferenceInterface
   *   The new reference entity object.
   */
  public function createReference(array $data = []) {
    $data += [
      'title' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
      'type' => 'journal_article',
      'bibcite_year' => mt_rand(1900, date('Y')),
      'bibcite_issue' => mt_rand(1, 1000),
      'bibcite_volume' => mt_rand(1, 5),
      'bibcite_pages' => mt_rand(1, 10) . '-' . mt_rand(10, 20),
      'bibcite_publisher' => 'Journal',
    ];
    // Setting the author data if not provided in the data.
    if (empty($data['author'])) {
      $data['author'] = $this->createContributors(2);
    }
    // Setting the keyword data if not provided in the reference_data.
    if (empty($data['keyword'])) {
      $data['keyword'] = $this->createKeywords(2);
    }

    $reference = Reference::create($data);
    $reference->save();

    return $reference;
  }

  /**
   * Creates and returns contributor entities.
   *
   * @param int $count
   *   The number of contributors to create.
   *
   * @return \Drupal\bibcite_entity\Entity\ContributorInterface[]
   *   The new contributor entity objects.
   */
  public function createContributors(int $count = 2) {
    $contributors = [];
    for ($i = 0; $i < $count; $i++) {
      $entity = Contributor::create([
        'first_name' => $this->randomMachineName(4) . ' ' . $i,
        'last_name' => $this->randomMachineName(5) . ' ' . $i,
      ]);
      $entity->save();
      $contributors[] = $entity;
    }
    return $contributors;
  }

  /**
   * Creates and returns keyword entities.
   *
   * @param int $count
   *   The number of keywords to create.
   *
   * @return \Drupal\bibcite_entity\Entity\KeywordInterface[]
   *   The new keyword entity objects.
   */
  public function createKeywords(int $count = 2) {
    $keywords = [];
    for ($i = 0; $i < $count; $i++) {
      $entity = Keyword::create([
        'name' => $this->randomMachineName(8) . ' ' . $this->randomMachineName(4) . '_' . $i,
      ]);
      $entity->save();
      $keywords[] = $entity;
    }
    return $keywords;
  }

}
