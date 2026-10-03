<?php

namespace Drupal\external_entities;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\content_translation\ContentTranslationHandler;

/**
 * Defines the translation handler for external entities.
 */
class ExternalEntityTranslationHandler extends ContentTranslationHandler {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function entityFormAlter(array &$form, FormStateInterface $form_state, EntityInterface $entity) {
    $metadata = $this->manager->getTranslationMetadata($entity);
    $untranslated_entity = $entity->getUntranslated();
    $entity_langcode = $untranslated_entity->language()->getId();
    $source_langcode = $metadata->getSource();
    // Fallback to default if no source specified.
    if (($source_langcode == LanguageInterface::LANGCODE_NOT_SPECIFIED)
      || ($source_langcode == LanguageInterface::LANGCODE_DEFAULT)
      || ($source_langcode == LanguageInterface::LANGCODE_SITE_DEFAULT)
    ) {
      $source_langcode = $entity_langcode;
    }
    $metadata->setSource($source_langcode);

    // Avoid empty translated titles.
    if (empty($entity->label())) {
      $entity->set('title', $untranslated_entity->label() ?? '');
    }

    parent::entityFormAlter($form, $form_state, $entity);

    if (isset($form['content_translation'])) {
      // We do not need to show these values on the forms: they inherit the
      // basic property values.
      $form['content_translation']['status']['#access'] = FALSE;
      $form['content_translation']['name']['#access'] = FALSE;
      $form['content_translation']['created']['#access'] = FALSE;
    }

    $form_object = $form_state->getFormObject();
    $form_langcode = $form_object->getFormLangcode($form_state);
    $translations = $entity->getTranslationLanguages();
    $status_translatable = NULL;
    // Change the submit button labels if there was a status field they affect
    // in which case their publishing / unpublishing may or may not apply
    // to all translations.
    if (!$entity->isNew() && (!isset($translations[$form_langcode]) || count($translations) > 1)) {
      foreach ($entity->getFieldDefinitions() as $property_name => $definition) {
        if ($property_name == 'status') {
          $status_translatable = $definition->isTranslatable();
        }
      }
      if (isset($status_translatable)) {
        if (isset($form['actions']['submit'])) {
          $form['actions']['submit']['#value'] .= ' ' . ($status_translatable ? $this->t('(this translation)') : $this->t('(all translations)'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function entityFormEntityBuild($entity_type, EntityInterface $entity, array $form, FormStateInterface $form_state) {
    if ($form_state->hasValue('content_translation')) {
      $translation = &$form_state->getValue('content_translation');
      $translation['status'] = $entity->isPublished();
      $account = $entity->uid->entity;
      $translation['uid'] = $account ? $account->id() : 0;
      $translation['created'] = $this->dateFormatter->format(time(), 'custom', 'Y-m-d H:i:s O');
    }
    parent::entityFormEntityBuild($entity_type, $entity, $form, $form_state);
  }

}
