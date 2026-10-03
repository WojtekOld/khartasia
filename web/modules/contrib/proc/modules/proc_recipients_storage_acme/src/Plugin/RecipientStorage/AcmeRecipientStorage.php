<?php

namespace Drupal\proc_recipients_storage_acme\Plugin\RecipientStorage;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\proc\Attribute\RecipientStorage;
use Drupal\proc\ProcInterface;
use Drupal\proc\RecipientStorageBase;
use Drupal\proc_recipients_storage_acme\AcmeGroupMembership;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Acme recipient storage plugin (realistic, fictional demonstration).
 *
 * Unlike the standard plugin, the CURRENT recipients of a cipher are never
 * stored in the database. They are always recomputed from a fictional
 * "eligibility group" membership (see AcmeGroupMembership), scoped to the
 * cipher's cryptographic timestamp:
 *
 * - The full group is {1, 2, 3}, each user carrying the timestamp at which
 *   they joined the group.
 * - When asked "who are the recipients of this cipher?", the plugin returns
 *   only the members who joined the group AT OR BEFORE the cipher's encryption
 *   timestamp (meta[0]['generation_timestamp']). Members who joined later were
 *   not eligible when the content was encrypted, so they are cryptographically
 *   impossible recipients and are filtered out.
 *
 * Because recipients are recomputed rather than stored, setRecipients() keeps
 * the "field_recipients_set" database field empty (no per-recipient rows are
 * ever written).
 *
 * The WISHED (target) recipient set is deliberately left as standard,
 * database-backed storage on "field_wished_recipients_set":
 * getWishedRecipients() and setWishedRecipients() behave exactly like the
 * standard plugin. The proc field widget fills the wished set from the fetcher
 * endpoint ("/proc_recipients_storage_acme/recipients"), which returns the
 * current (unfiltered) eligibility list; re-encryption is then driven by that
 * wished set as usual.
 *
 * @see \Drupal\proc_recipients_storage_acme\AcmeGroupMembership
 * @see \Drupal\proc_recipients_storage_acme\Controller\AcmeRecipientsController
 */
#[RecipientStorage(
  id: 'proc_recipients_storage_acme',
  description: new TranslatableMarkup('Acme: group membership scoped by encryption time'),
)]
class AcmeRecipientStorage extends RecipientStorageBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The wished recipients field name (standard, database-backed storage).
   */
  protected const WISHED_FIELD_NAME = 'field_wished_recipients_set';

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs an AcmeRecipientStorage plugin.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('proc')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Recompute the recipients from the fictional group membership, excluding
   * every member who joined the group after this cipher was encrypted.
   */
  public function getRecipients(ProcInterface $proc): array {
    $encryption_ts = $this->getEncryptionTimestamp($proc);
    $eligible_ids = AcmeGroupMembership::userIdsJoinedBy($encryption_ts);

    $recipients = [];
    foreach ($eligible_ids as $uid) {
      $recipients[] = ['target_id' => (int) $uid];
    }
    return $recipients;
  }

  /**
   * {@inheritdoc}
   *
   * Recipients are recomputed from the group membership, so there is nothing to
   * persist. We keep the database field empty so that no per-recipient rows are
   * ever created for this cipher.
   */
  public function setRecipients(ProcInterface $proc, array $recipients): void {
    if ($proc->hasField('field_recipients_set')) {
      $proc->set('field_recipients_set', []);
    }
  }

  /**
   * {@inheritdoc}
   *
   * Wished recipients use standard, database-backed storage (identical to the
   * standard plugin): read them straight from "field_wished_recipients_set".
   */
  public function getWishedRecipients(ProcInterface $proc): array {
    if (!$proc->hasField(self::WISHED_FIELD_NAME)) {
      return [];
    }
    return $proc->get(self::WISHED_FIELD_NAME)->getValue();
  }

  /**
   * {@inheritdoc}
   *
   * Wished recipients use standard, database-backed storage (identical to the
   * standard plugin): set the field value in-memory without saving. The caller
   * is responsible for persisting the entity.
   */
  public function setWishedRecipients(ProcInterface $proc, array $recipients): void {
    if (!$proc->hasField(self::WISHED_FIELD_NAME)) {
      return;
    }
    $proc->set(self::WISHED_FIELD_NAME, $this->normalizeRecipients($recipients));
  }

  /**
   * Returns the cipher's cryptographic (encryption) timestamp, if any.
   *
   * @param \Drupal\proc\ProcInterface $proc
   *   The cipher proc entity.
   *
   * @return int|null
   *   The encryption timestamp as an integer number of seconds, or NULL when
   *   the cipher has no known cryptographic timestamp (in which case no
   *   time-based filtering can be applied).
   */
  protected function getEncryptionTimestamp(ProcInterface $proc): ?int {
    if (!$proc->hasField('meta')) {
      return NULL;
    }
    $meta = $proc->get('meta')->getValue()[0] ?? [];
    $raw = $meta['generation_timestamp'] ?? NULL;
    if ($raw === NULL || $raw === '' || !is_numeric($raw)) {
      return NULL;
    }
    // Cryptographic timestamps may carry sub-second precision; floor to whole
    // seconds to compare against the (whole-second) group join timestamps.
    return (int) floor((float) $raw);
  }

}
