<?php

namespace Drupal\proc_recipients_storage_example\Plugin\RecipientStorage;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\proc\Attribute\RecipientStorage;
use Drupal\proc\ProcInterface;
use Drupal\proc\RecipientStorageBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Example recipient storage plugin that delegates storage to the application.
 *
 * This is a SKELETON meant to be copied and adapted by the implementing
 * application. It illustrates how to keep the recipients of a cipher OUT of
 * the "field_recipients_set" database table by computing them, on demand, from
 * a predictable and reproducible business rule (for example: "all users that
 * belong to groups A and B", as resolved by an internal service or a remote
 * endpoint).
 *
 * Trade-offs to keep in mind when writing a real plugin of this kind:
 * - The application becomes fully responsible for always being able to return
 *   the exact set of recipients a cipher was encrypted for. If the rule or the
 *   underlying data changes, recipients may gain or lose access to content.
 * - Recipients are NOT stored per cipher, so reverse lookups that rely on the
 *   database table (the "proc:get-procs-user-is-recipient" drush command and
 *   the recipients columns in the shipped Views) will not see ciphers governed
 *   by this plugin.
 * - Both "field_recipients_set" (current recipients) and
 *   "field_wished_recipients_set" (the target set for re-encryption) are
 *   governed here. This skeleton keeps the standard database behavior for the
 *   wished set (see getWishedRecipients()/setWishedRecipients() below); a real
 *   plugin may instead delegate it to the application, e.g. to drive
 *   re-encryption from a rule-based target set.
 */
#[RecipientStorage(
  id: 'proc_recipients_storage_example',
  description: new TranslatableMarkup('Example: application-provided recipients'),
)]
class ExampleRecipientStorage extends RecipientStorageBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs an ExampleRecipientStorage plugin.
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
   * Compute the recipients for this cipher from the application's rule.
   *
   * Replace the body below with a call to your own service or endpoint. The
   * cipher entity and its meta are available to scope the computation (for
   * example, the host entity, the field, or any identifier your rule needs),
   * e.g. $proc->getMeta()[0]['host_page_url'] ?? NULL.
   */
  public function getRecipients(ProcInterface $proc): array {
    // Example placeholder: no recipients are computed by the skeleton itself.
    // A real implementation would return the current members of the relevant
    // groups as a list of ['target_id' => (int) $uid] items, resolved from an
    // internal service or a remote endpoint, optionally scoping the query with
    // data from the cipher meta (e.g. $proc->getMeta()[0]['host_page_url']).
    return [];
  }

  /**
   * {@inheritdoc}
   *
   * Recipients are owned by the application and derived from a rule, so there
   * is nothing to persist here. We still make sure the database field stays
   * empty so that no per-recipient rows are ever created for this cipher.
   *
   * If your application does need to persist something per cipher (for example
   * a snapshot of the rule inputs), do it here — note that a brand-new cipher
   * only has an ID after it is saved, so persist ID-dependent data from a
   * hook_proc entity insert/update hook instead.
   */
  public function setRecipients(ProcInterface $proc, array $recipients): void {
    if ($proc->hasField('field_recipients_set')) {
      $proc->set('field_recipients_set', []);
    }
  }

  /**
   * {@inheritdoc}
   *
   * The wished recipients are the target set a cipher should be
   * (re-)encrypted for. This skeleton keeps the standard, database-backed
   * behavior: it simply reads the "field_wished_recipients_set" field.
   *
   * A real plugin may instead compute the target set from the application's
   * rule (for example the up-to-date group membership) so that re-encryption —
   * including recipient removals — is driven entirely by the application. See
   * the "Limitation: removing a recipient via re-encryption" section of this
   * module's README for the reasoning behind owning the wished set.
   */
  public function getWishedRecipients(ProcInterface $proc): array {
    if (!$proc->hasField('field_wished_recipients_set')) {
      return [];
    }
    return $proc->get('field_wished_recipients_set')->getValue();
  }

  /**
   * {@inheritdoc}
   *
   * This skeleton keeps the standard, database-backed behavior and writes the
   * wished recipients to the "field_wished_recipients_set" field in-memory
   * (the caller saves the entity). A real plugin that owns the wished set in
   * the application should persist it in its own store here and keep the field
   * empty so that no per-recipient rows are created.
   */
  public function setWishedRecipients(ProcInterface $proc, array $recipients): void {
    if ($proc->hasField('field_wished_recipients_set')) {
      $proc->set('field_wished_recipients_set', $this->normalizeRecipients($recipients));
    }
  }

}
