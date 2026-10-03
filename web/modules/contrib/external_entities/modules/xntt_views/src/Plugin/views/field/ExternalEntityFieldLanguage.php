<?php

namespace Drupal\xntt_views\Plugin\views\field;

use Drupal\Core\Session\AccountInterface;
use Drupal\views\Attribute\ViewsField;

/**
 * Provides a Views field handler for entity language for External Entities.
 *
 * Shows the language of the entity (or of the translation used by the view if
 * a view language is set and the entity has that translation).
 */
#[ViewsField("external_entity_field_language")]
class ExternalEntityFieldLanguage extends ExternalEntityField {

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): bool {
    // No point in displaying the language field on monolingual sites,
    // as only one language value is available.
    return $this->languageManager->isMultilingual() && parent::access($account);
  }

}
