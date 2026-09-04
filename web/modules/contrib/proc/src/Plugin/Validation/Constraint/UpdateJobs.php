<?php

namespace Drupal\proc\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Defines the UpdateJobs constraint.
 *
 * @Constraint(
 *   id = "UpdateJobs",
 * )
 */
class UpdateJobs extends Constraint {

  /**
   * Error message when an entry is not a positive integer.
   *
   * @var string
   */
  public string $messageInvalidId = 'All entries in Update Jobs must be integers greater than 0. Invalid value: %value';

  /**
   * Error message when the referenced proc is not a cipher proc entity.
   *
   * @var string
   */
  public string $messageNotCipher = 'Cipher text (ID: %value) is not a cipher proc entity.';

  /**
   * Error message when the cipher text is older than the keyring.
   *
   * @var string
   */
  public string $messageOlder = 'Cipher text (ID: %value) is older than the keyring (not decryptable by the current key).';

  /**
   * Error message when the cipher text has an empty wished recipients set.
   *
   * @var string
   */
  public string $messageEmptyWished = 'Cipher text (ID: %value) has an empty Wished Recipients Set field (can not be re-encrypted).';

  /**
   * Error message when the cipher does not include the keyring owner.
   *
   * @var string
   */
  public string $messageMissingOwner = 'Cipher text (ID: %value) does not include the owner of the keyring among its recipients (can not be re-encrypted) .';

  /**
   * Return the service id of the validator so Drupal will create it via DI.
   *
   * @return string
   *   The service id.
   */
  public function validatedBy(): string {
    return 'proc.update_jobs_validator';
  }

}
