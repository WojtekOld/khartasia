<?php

namespace Drupal\tmgmt_deepl_glossary\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\tmgmt\TranslatorInterface;

/**
 * Provides an interface defining a deepl_ml_glossary entity.
 */
interface DeeplMultilingualGlossaryInterface extends ContentEntityInterface {

  /**
   * Gets the glossary id.
   *
   * @return string|null
   *   Glossary id of the deepl_glossary.
   */
  public function getGlossaryId(): ?string;

  /**
   * Get the translator of a glossary.
   *
   * @return \Drupal\tmgmt\TranslatorInterface|null
   *   The translator entity object.
   */
  public function getTranslator(): ?TranslatorInterface;

}
