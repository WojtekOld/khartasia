<?php

namespace Drupal\proc\Plugin\Validation\Constraint;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\ContentEntityInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Validates the UpdateJobs constraint.
 */
class UpdateJobsValidator extends ConstraintValidator {
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * UpdateJobsValidator constructor.
   *
   * EntityTypeManagerInterface is injected by the service container.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    if (!$value) {
      return;
    }
    $update_jobs = (array) $value;
    $generation_timestamp_raw = NULL;
    $owner_uid = NULL;

    $entity = $value instanceof ContentEntityInterface ? $value : NULL;
    if ($entity) {
      $meta = $entity->get('meta')->getValue()[0] ?? [];
      $update_jobs = $meta['update_jobs'] ?? [];
      $generation_timestamp_raw = $meta['generation_timestamp'] ?? NULL;
      $owner_uid = $entity->getOwnerId();
    }

    foreach ($update_jobs as $item) {
      // Validate integer > 0.
      if (filter_var($item, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === FALSE) {
        $this->context->addViolation($constraint->messageInvalidId, ['%value' => $item]);
        return;
      }

      try {
        $proc = $this->entityTypeManager->getStorage('proc')->load((int) $item);
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
        // @phpstan-ignore-next-line
        \Drupal::logger('proc')->error('Failed to load proc entity with ID %id: %message', [
          '%id' => $item,
          '%message' => $e->getMessage(),
        ]);
        $proc = NULL;
      }
      if (!$proc) {
        $this->context->addViolation($constraint->messageNotCipher, ['%value' => $item]);
        return;
      }

      if ($proc->getType() !== 'cipher') {
        $this->context->addViolation($constraint->messageNotCipher, ['%value' => $item]);
        return;
      }

      // Normalize timestamps to integer seconds.
      $proc_ts_raw = $proc->get('meta')->getValue()[0]['generation_timestamp'] ?? NULL;
      if ($proc_ts_raw !== NULL && $generation_timestamp_raw !== NULL) {
        $proc_ts = (int) floor((float) $proc_ts_raw);
        $gen_ts = (int) floor((float) $generation_timestamp_raw);
        if ($proc_ts < $gen_ts) {
          $this->context->addViolation($constraint->messageOlder, ['%value' => $item]);
          return;
        }
      }

      $wished = $proc->get('field_wished_recipients_set')->getValue();
      if (empty($wished)) {
        $this->context->addViolation($constraint->messageEmptyWished, ['%value' => $item]);
        return;
      }

      if ($owner_uid !== NULL) {
        $recipients = $proc->get('field_recipients_set')->getValue();
        $owner_found = FALSE;
        foreach ($recipients as $recipient) {
          if ((int) $recipient['target_id'] === (int) $owner_uid) {
            $owner_found = TRUE;
            break;
          }
        }
        if (!$owner_found) {
          $this->context->addViolation($constraint->messageMissingOwner, ['%value' => $item]);
          return;
        }
      }
    }
  }

}
