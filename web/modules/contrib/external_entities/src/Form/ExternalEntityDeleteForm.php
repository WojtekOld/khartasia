<?php

namespace Drupal\external_entities\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\external_entities\Entity\ExternalEntityInterface;

/**
 * Provides a generic base class for a external entity deletion form.
 */
class ExternalEntityDeleteForm extends ContentEntityDeleteForm {

  /**
   * Delete method that only deletes the annotation.
   *
   * @var string
   */
  public const DELETE_METHOD_ANNOTATION = 'annotation';

  /**
   * Delete method that only deletes the external entity.
   *
   * @var string
   */
  public const DELETE_METHOD_ENTITY = 'external_entity';

  /**
   * Delete method that deletes the annotation and the entity.
   *
   * @var string
   */
  public const DELETE_METHOD_ALL = 'all';

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $entity = $this->getEntity();
    if (!$entity instanceof ExternalEntityInterface) {
      return $form;
    }

    $annotation = $entity->getAnnotation();
    $has_annotation = FALSE;
    $can_delete_annotation = FALSE;
    if ($annotation) {
      $has_annotation = TRUE;
      if ($annotation->access('delete')) {
        $can_delete_annotation = TRUE;
      }
    }

    if ($has_annotation && $can_delete_annotation) {
      $form['delete_method'] = [
        '#type' => 'radios',
        '#title' => $this->t('What do you want to delete?'),
        '#options' => [
          static::DELETE_METHOD_ANNOTATION => $this->t('The annotation'),
          static::DELETE_METHOD_ENTITY => $this->t('The external entity'),
          static::DELETE_METHOD_ALL => $this->t('Both the annotation and the external entity'),
        ],
        '#required' => TRUE,
        '#default_value' => static::DELETE_METHOD_ENTITY,
        '#weight' => -10,
      ];
    }
    else {
      $form['delete_method'] = [
        '#type' => 'hidden',
        '#value' => static::DELETE_METHOD_ENTITY,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();
    $message = $this->getExternalEntityDeletionMessage($form, $form_state);
    $delete_method = $form_state->getValue('delete_method', static::DELETE_METHOD_ENTITY);

    if ($delete_method === static::DELETE_METHOD_ANNOTATION || $delete_method === static::DELETE_METHOD_ALL) {
      // @todo For translatable annotations, we may want to only delete the
      // corresponding translation.
      $entity->getAnnotation()->delete();
      // If only the annotation is deleted, redirect to the external entity, if
      // not, the redirection will be overridden after.
      $form_state->setRedirectUrl($entity->toUrl('canonical'));
    }

    if ($delete_method === static::DELETE_METHOD_ENTITY || $delete_method === static::DELETE_METHOD_ALL) {
      // Make sure that deleting a translation does not delete the whole entity.
      if (!$entity->isDefaultTranslation()) {
        $langcode = $entity->language()->getId();
        $untranslated_entity = $entity->getUntranslated();
        $untranslated_entity->removeTranslation($langcode);
        // Only remove translation if they use different storage settings.
        if ($entity->getExternalEntityType()->isDataAggregatorOverridden($langcode)) {
          $entity->delete();
        }
        else {
          $this->messenger()->addWarning(
            $this->t(
              'External entity translation %label (%langcode) can not be removed as it uses the same external data source as its corresponding untranslated entity.',
              [
                '%label' => $entity->label() ?? '',
                '%langcode' => $langcode,
              ]
            )
          );
        }
        $form_state->setRedirectUrl($untranslated_entity->toUrl('canonical'));
      }
      else {
        $entity->delete();
        $form_state->setRedirectUrl($this->getRedirectUrl());
      }
    }

    $this->messenger()->addStatus($message);
    $this->logExternalEntityDeletionMessage($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $entity = $this->getEntity();
    if ($entity instanceof ExternalEntityInterface) {
      $annotation = $entity->getAnnotation();
      $can_delete_annotation = FALSE;
      if ($annotation && $annotation->access('delete')) {
        $can_delete_annotation = TRUE;
      }

      if ($can_delete_annotation) {
        return $this->t(
          'Are you sure you want to delete the @entity-type %label and/or its annotation?',
          [
            '@entity-type' => $this->getEntity()->getEntityType()->getSingularLabel(),
            '%label' => $this->getEntity()->label() ?? '',
          ]
        );
      }
    }

    return parent::getQuestion();
  }

  /**
   * Gets the message to display to the user after deleting the entity.
   *
   * @param array $form
   *   The current form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return string
   *   The translated string of the deletion message.
   */
  protected function getExternalEntityDeletionMessage(
    array &$form,
    FormStateInterface $form_state,
  ) {
    $entity = $this->getEntity();
    $delete_method = $form_state->getValue('delete_method', static::DELETE_METHOD_ENTITY);
    if ($entity instanceof ExternalEntityInterface) {
      if ($delete_method === static::DELETE_METHOD_ANNOTATION) {
        return $this->t('The annotation for @entity-type %label has been deleted.', [
          '@entity-type' => $entity->getEntityType()->getSingularLabel(),
          '%label' => $entity->label() ?? '',
        ]);
      }
      elseif ($delete_method === static::DELETE_METHOD_ALL) {
        return $this->t('The @entity-type %label (including annotation) has been deleted.', [
          '@entity-type' => $entity->getEntityType()->getSingularLabel(),
          '%label' => $entity->label() ?? '',
        ]);
      }
    }
    return parent::getDeletionMessage();
  }

  /**
   * Logs a message about the deleted entity.
   *
   * @param array $form
   *   The current form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function logExternalEntityDeletionMessage(
    array &$form,
    FormStateInterface $form_state,
  ) {
    $entity = $this->getEntity();
    $delete_method = $form_state->getValue('delete_method', static::DELETE_METHOD_ENTITY);
    if ($entity instanceof ExternalEntityInterface) {
      if ($delete_method === static::DELETE_METHOD_ANNOTATION) {
        $this->logger($entity->getEntityType()->getProvider())->info(
          'The annotation for @entity-type %label has been deleted.',
          [
            '@entity-type' => $entity->getEntityType()->getSingularLabel(),
            '%label' => $entity->label() ?? '',
          ]
        );
      }
      elseif ($delete_method === static::DELETE_METHOD_ALL) {
        $this->logger($entity->getEntityType()->getProvider())->info(
          'The @entity-type %label (including annotation) has been deleted.',
          [
            '@entity-type' => $entity->getEntityType()->getSingularLabel(),
            '%label' => $entity->label() ?? '',
          ]
        );
      }
      else {
        parent::logDeletionMessage();
      }
    }
    else {
      parent::logDeletionMessage();
    }
  }

}
