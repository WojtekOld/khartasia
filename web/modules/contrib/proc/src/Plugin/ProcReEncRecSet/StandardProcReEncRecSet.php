<?php

namespace Drupal\proc\Plugin\ProcReEncRecSet;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\proc\Attribute\ProcReEncRecSet;
use Drupal\proc\ProcKeyManagerInterface;
use Drupal\proc\ProcReEncRecSetBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides definition for the wished set of recipients.
 */
#[ProcReEncRecSet(
  id: 'proc_re_enc_rec_set',
  description: new TranslatableMarkup('Standard Re-encryption recipient set'),
)]
class StandardProcReEncRecSet extends ProcReEncRecSetBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The ProcKeyManager service.
   *
   * @var \Drupal\proc\ProcKeyManagerInterface
   */
  protected ProcKeyManagerInterface $procKeyManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The translation manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\proc\ProcKeyManagerInterface $procKeyManager
   *   The ProcKeyManager service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    TranslationInterface $translation,
    EntityTypeManagerInterface $entity_type_manager,
    ProcKeyManagerInterface $procKeyManager,
    LoggerInterface $logger,
  ) {
    $this->setStringTranslation($translation);
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->procKeyManager = $procKeyManager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('string_translation'),
      $container->get('entity_type.manager'),
      $container->get('proc.key_manager'),
      $container->get('logger.factory')->get('proc')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function description(): string {
    return $this->t('Standard Re-encryption recipient set');
  }

  /**
   * Set the wished recipients, including those who lost their access.
   *
   * @param array $proc_ids
   *   The list of proc IDs.
   *
   * @return array
   *   The list of recipients who lost access.
   */
  public function setWishedRecipients(array $proc_ids): array {
    // Collect recipients that lost access due to key renewal:
    $lost_acc_recs = [];
    $recipient_ids = [];
    $proc_objects = [];
    foreach ($proc_ids as $proc_id) {
      $proc_object = $this->loadProcObject($proc_id);
      if (!$proc_object) {
        continue;
      }
      $proc_objects[$proc_id] = $proc_object;
      // Get the created or changed date of the encrypted content:
      $reference_date = $this->getReferenceDate($proc_objects[$proc_id]);
      // We will only proceed if this content is older than one day (86400
      // seconds):
      if (time() - $reference_date <= 1) {
        continue;
      }
      // Get the recipients:
      $recipient_ids[$proc_id] = $this->getRecipientIds($proc_objects[$proc_id]);
      if (empty($recipient_ids[$proc_id])) {
        continue;
      }
      // For each recipient, check if the recipient key is older than the
      // encrypted content:
      $lost_access = $this->getLostAccessRecipients($recipient_ids[$proc_id], $reference_date);
      if (!empty($lost_access)) {
        $lost_acc_recs[$proc_id] = $lost_access;
      }
    }
    // If no one lost access, no further verification is needed, return:
    if (empty($lost_acc_recs)) {
      return [];
    }
    $this->checkFullyLostAccess($lost_acc_recs, $recipient_ids, $proc_objects);
    $this->updateRecipientsFields($lost_acc_recs, $proc_objects, $recipient_ids);
    return $lost_acc_recs;
  }

  /**
   * Get the reference date for a proc object.
   *
   * @param object $proc_object
   *   The proc object.
   *
   * @return int
   *   The reference date as a timestamp.
   */
  private function getReferenceDate(object $proc_object): int {
    $changed_date = $proc_object->get('changed')->getValue()[0]['value'];
    return $changed_date != '0' ? $changed_date : $proc_object->get('created')->getValue()[0]['value'];
  }

  /**
   * Get recipient IDs from a proc object.
   *
   * @param object $proc_object
   *   The proc object.
   *
   * @return array
   *   An array of recipient IDs.
   */
  private function getRecipientIds(object $proc_object): array {
    $recipient_ids = [];
    $recipients = $proc_object->get('field_recipients_set')->getValue();
    foreach ($recipients as $recipient) {
      if (isset($recipient['target_id'])) {
        $recipient_ids[] = $recipient['target_id'];
      }
    }
    return $recipient_ids;
  }

  /**
   * Get the recipients who lost access to the encrypted content.
   *
   * @param array $recipient_ids
   *   The recipient IDs.
   * @param int $reference_date
   *   The reference date.
   *
   * @return array
   *   The list of recipients who lost access.
   */
  private function getLostAccessRecipients(array $recipient_ids, int $reference_date): array {
    $lost_acc_recs = [];
    // For each recipient, check if the recipient key is older than the
    // encrypted content:
    $dates = $this->procKeyManager->getKeyCreationDates($recipient_ids);
    foreach ($dates as $recipient_id => $date) {
      if ($date > $reference_date) {
        $lost_acc_recs[] = $recipient_id;
      }
    }
    return $lost_acc_recs;
  }

  /**
   * Check if all recipients lost access for any proc.
   *
   * @param array $lost_acc_recs
   *   An array of recipients who lost access, keyed by proc ID.
   * @param array $recipient_ids
   *   An array of recipients IDs by proc ID.
   * @param array $proc_objects
   *   An array of proc objects keyed by proc ID.
   */
  private function checkFullyLostAccess(array $lost_acc_recs, array $recipient_ids, array $proc_objects): void {
    $proc_fully_lost = [];
    foreach ($lost_acc_recs as $proc_id => $lost_ids) {
      if (count($lost_ids) == count($recipient_ids[$proc_id])) {
        $proc_fully_lost[] = $proc_id;
        // Empty the recipients field:
        $proc_objects[$proc_id]->set('field_recipients_set', []);
        $proc_objects[$proc_id]->save();
      }
    }
    // Log a warning if everyone lost access:
    if (!empty($proc_fully_lost)) {
      $this->logger->warning('All recipients lost access to the encrypted content in the following procs: @proc_ids', [
        '@proc_ids' => implode(',', $proc_fully_lost),
      ]);
    }
  }

  /**
   * Update the recipients fields for the procs.
   *
   * @param array $lost_acc_recs
   *   An array of recipients who lost access, keyed by proc ID.
   * @param array $proc_objects
   *   An array of proc objects keyed by proc ID.
   * @param array $recipient_ids
   *   An array of recipients IDs by proc ID.
   */
  private function updateRecipientsFields(array $lost_acc_recs, array $proc_objects, array $recipient_ids): void {
    $new_recipients = [];
    $new_wished_recs = [];
    // Move the user who lost access from the recipients field to the wished
    // recipients field:
    foreach ($lost_acc_recs as $proc_id => $lost_ids) {
      foreach ($recipient_ids[$proc_id] as $recipient_id) {
        $new_wished_recs[$proc_id][] = ['target_id' => $recipient_id];
        if (!in_array($recipient_id, $lost_ids)) {
          $new_recipients[$proc_id][] = ['target_id' => $recipient_id];
        }
      }
    }
    foreach ($proc_objects as $proc_id => $proc_object) {
      // Set the new recipients:
      if (isset($new_recipients[$proc_id])) {
        $proc_object->set('field_recipients_set', $new_recipients[$proc_id]);
      }
      if (isset($new_wished_recs[$proc_id])) {
        $proc_object->set('field_wished_recipients_set', $new_wished_recs[$proc_id]);
      }
      if (!empty($new_recipients[$proc_id]) || !empty($new_wished_recs[$proc_id])) {
        // Log the update:
        $this->logger->info('Updated wished recipients for proc entity @proc_id.', ['@proc_id' => $proc_id]);
        $proc_object->save();
      }
    }
  }

  /**
   * Load a proc object.
   *
   * @param int $proc_id
   *   The proc ID.
   *
   * @return object|null
   *   The proc object or NULL if not found.
   */
  private function loadProcObject(int $proc_id): object|null {
    try {
      return $this->entityTypeManager->getStorage('proc')->load($proc_id);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      // Log the error.
      $this->logger->error('Error loading proc entity with ID @proc_id: @error', [
        '@proc_id' => $proc_id,
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

}
