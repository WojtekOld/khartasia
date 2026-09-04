<?php

namespace Drupal\tmgmt_deepl_glossary\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list controller for deepl_ml_glossary entity.
 *
 * @ingroup tmgmt_deepl_glossary
 */
class DeeplMultilingualGlossaryListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   *
   * Building the header and content lines for the contact list.
   *
   * Calling the parent::buildHeader() adds a column for the possible actions
   * and inserts the 'edit' and 'delete' links as defined for the entity type.
   */
  public function buildHeader(): array {
    $header = [];
    $header['name'] = $this->t('Name');
    $header['glossary_id'] = $this->t('Glossary Id');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $row = [];
    /** @var \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossary $entity */
    $row['name'] = $entity->label();
    $row['glossary_id'] = $entity->getGlossaryId();
    return $row + parent::buildRow($entity);
  }

}
