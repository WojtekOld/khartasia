<?php

namespace Drupal\term_glossary_per_node\Service;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterInterface;
use Drupal\node\NodeInterface;
use Drupal\term_glossary\Service\TermGlossaryManager;
use Drupal\term_glossary_per_node\TermGlossaryUtils;

/**
 * The TermGlossaryPerNode manager service will replace the TermGlossaryManager.
 */
class TermGlossaryPerNodeManager extends TermGlossaryManager {

  /**
   * Get vocabulary from preprocess variables array.
   */
  public function getVocabulariesFromFieldPreprocessVariables(&$variables) {
    $entity = $variables['element']['#object'];
    $view_mode = $variables['element']['#view_mode'];
    $field_name = $variables['element']['#field_name'];
    $display_vocabularies = $this->getVocabulariesFromEntity($entity, $field_name, $view_mode);
    if ($display_vocabularies !== FALSE) {
      $display_vocabularies = empty($display_vocabularies)
          ? explode(',', $this->config->get('vocab'))
          : $display_vocabularies;
      // Special handling of paragraph entities.
      $entity = $this->findRootEntity($entity);
      // @phpstan-ignore-next-line See: https://github.com/mglaman/phpstan-drupal/issues/927
      if ($entity instanceof NodeInterface) {
        $node_type = $this->entityTypeManager->getStorage('node_type')->load($entity->bundle());
        $term_glossary_active = $node_type->getThirdPartySetting(
          'term_glossary_per_node', 'active', 0);
        if ($term_glossary_active) {
          $term_glossary_override = $node_type->getThirdPartySetting(
            'term_glossary_per_node', 'override', 0);
          if ($term_glossary_override) {
            if (TermGlossaryUtils::isNodeGlossaryActive($entity)) {
              if ($entity->hasField('term_glossary_vocabulary')) {
                $vocabulary_field = $entity->get('term_glossary_vocabulary');
                if (!$vocabulary_field->isEmpty()) {
                  $display_vocabularies = explode(',', $vocabulary_field->value);
                }
              }
              return $display_vocabularies;
            }
          }
          else {
            return $display_vocabularies;
          }
        }
      }
      else {
        return $display_vocabularies;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function getFieldFormatterThirdPartySettingsElements(
    FormatterInterface $plugin,
    FieldDefinitionInterface $field_definition,
  ) {
    if ($field_definition->getTargetEntityTypeId() === 'node') {
      $node_type = $this->entityTypeManager->getStorage('node_type')->load($field_definition->getTargetBundle());
      $term_glossary_active = $node_type->getThirdPartySetting(
        'term_glossary_per_node', 'active', 0);
      if ($term_glossary_active) {
        return parent::getFieldFormatterThirdPartySettingsElements($plugin, $field_definition);
      }
    }
    else {
      return parent::getFieldFormatterThirdPartySettingsElements($plugin, $field_definition);
    }
    return [];
  }

}
