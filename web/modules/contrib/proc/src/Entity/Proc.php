<?php

namespace Drupal\proc\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\file\FileInterface;
use Drupal\proc\ProcInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Proc entity.
 *
 * @ingroup proc
 *
 * @ContentEntityType(
 *   id = "proc",
 *   label = @Translation("Protected content"),
 *   label_collection = @Translation("Protected contents"),
 *   label_singular = @Translation("protected content"),
 *   label_plural = @Translation("protected contents"),
 *   label_count = @PluralTranslation(
 *     singular = "@count protected content",
 *     plural = "@count protected contents",
 *   ),
 *   bundle_label = @Translation("Protected content type"),
 *   handlers = {
 *     "list_builder" = "Drupal\proc\Entity\Controller\ProcListBuilder",
 *     "views_data" = "Drupal\proc\ProcViewsData",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "access" = "Drupal\proc\ProcAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\proc\Form\ProcForm",
 *       "delete" = "Drupal\proc\Form\ProcDeleteForm",
 *       "generate-keys" = "Drupal\proc\Form\ProcKeysGenerationForm",
 *       "encrypt" = "Drupal\proc\Form\ProcEncryptForm",
 *       "decrypt" = "Drupal\proc\Form\ProcDecryptForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "proc",
 *   admin_permission = "administer proc configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "owner" = "user_id"
 *   },
 *   list_cache_contexts = { "user" },
 *   links = {
 *     "canonical" = "/proc/{proc}",
 *     "edit-form" = "/proc/{proc}/edit",
 *     "delete-form" = "/proc/{proc}/delete",
 *     "collection" = "/admin/content/procs",
 *     "decrypt" = "/proc/{proc}"
 *   },
 *   constraints = {
 *     "UpdateJobs" = {}
 *   },
 * )
 */
class Proc extends ContentEntityBase implements ProcInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   *
   * When a new entity instance is added, set the user_id entity reference to
   * the current user as the creator of the instance.
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values): void {
    parent::preCreate($storage, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    // Standard field, used as unique if primary index.
    $fields = [];
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Proc entity.'))
      ->setReadOnly(TRUE);

    // Standard field, unique outside the scope of the current project.
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the Proc entity.'))
      ->setReadOnly(TRUE);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setDescription(t('The label of the Proc entity.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      // Set no default value.
      ->setDefaultValue(NULL)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -6,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Proc entity is published.'))
      ->setDefaultValue(TRUE)
      ->setSettings(['on_label' => 'Published', 'off_label' => 'Unpublished'])
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'boolean',
        'weight' => 2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Type of the proc'))
      ->setDescription(t('Defines a proc as keyring, ciphertext, keyring_1h or keyring_2h.'))
      ->setDefaultValue('keyring')
      ->setSettings([
        'allowed_values' => [
          'keyring' => 'Keyring',
          'cipher' => 'Cipher Text',
          'keyring_1h' => 'Keyring 1 hour cache',
          'keyring_2h' => 'Keyring 2 hours cache',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'list_default',
        'weight' => 6,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['meta'] = BaseFieldDefinition::create('map')
      ->setLabel((t('Metadata')))
      ->setDescription(t('Metadata for the proc'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'))
      ->setDefaultValue('browser_figerprint');

    $fields['armored'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Armored'))
      ->setDescription(t('The armored value of the Proc entity.'))
      // Set no default value.
      ->setDefaultValue(NULL)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -6,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Owner field of the proc.
    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User Name'))
      ->setDescription(t('The Name of the associated user.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => -3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'match_limit' => 10,
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_recipients_set'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Recipient Name'))
      ->setDescription(t('The set of recipients.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(FALSE)
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => -3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'match_limit' => 10,
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('The language code of Proc entity.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner(): UserInterface {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId(): ?int {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid): Proc|EntityOwnerInterface|static {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account): Proc|EntityOwnerInterface|static {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * Get proc type.
   *
   * @return string
   *   Returns one of: "cipher", "keyring", "keyring_1h", or "keyring_2h".
   *
   * @throws \RuntimeException
   *   If the type is invalid.
   *
   * @SuppressWarnings(PHPMD.MissingImport)
   */
  public function getType(): string {
    $type = $this->toArray()['type'][0]['value'];

    // Validate that the type is one of the allowed values.
    $allowedTypes = ['cipher', 'keyring', 'keyring_1h', 'keyring_2h'];
    if (!in_array($type, $allowedTypes, TRUE)) {
      throw new \RuntimeException('Invalid type value: {$type}');
    }

    return $type;
  }

  /**
   * Get status.
   */
  public function getStatus() {
    return (bool) $this->get('status')->value;
  }

  /**
   * Get created timestamp.
   */
  public function getCreated() {
    return $this->get('created')->value;
  }

  /**
   * Get metadata value.
   */
  public function getMeta() {
    return $this->get('meta')->getValue();
  }

  /**
   * Render proc ID for default values.
   */
  public function render() {
    return $this->get('id')->getValue()[0]['value'];
  }

  /**
   * Get cipher text from database or from file.
   *
   * @return bool|string
   *   Return cipher text or FALSE if error.
   */
  public function getCipherText(): bool|string {
    // @todo add support for database storage.
    $fileId = $this->get('armored')->getValue()[0]['cipher_fid'];
    try {
      $fileStorage = \Drupal::entityTypeManager()->getStorage('file');
      /** @var \Drupal\file\FileInterface|null $file */
      $file = $fileStorage->load($fileId);

      if ($file instanceof FileInterface) {
        $fileUri = $file->getFileUri();
        if ($fileUri && file_exists($fileUri)) {
          return file_get_contents($fileUri);
        }
      }
      // Log message on file not found.
      \Drupal::logger('proc')->error('File not found for proc ID: @id', ['@id' => $this->id()]);
    }
    catch (\Exception $e) {
      \Drupal::logger('proc')->error($e->getMessage());
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function bundle(): string {
    return 'proc';
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    if ($this->getEntityType()->getKey('label')) {
      $metadata = $this->getMeta()[0] ?? NULL;
      $re_encryption_plugin = $origin_field = NULL;
      if (isset($metadata)) {
        $origin_field = $metadata['proc_field_name'] ?? NULL;
        $re_encryption_plugin = $metadata['proc_re_encryption_plugin'] ?? NULL;
      }
      if ($origin_field && $re_encryption_plugin) {
        // Execute the method setWishedRecipients of the defined re-encryption
        // plugin.
        $re_encryption_plugin = \Drupal::service('plugin.manager.' . $re_encryption_plugin)
          ->createInstance($re_encryption_plugin);
        $re_encryption_plugin->setWishedRecipients([$this->id()]);
      }
      return $this->getEntityKey('label');
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities): void {
    parent::postDelete($storage, $entities);

    $file_storage = \Drupal::entityTypeManager()->getStorage('file');
    $file_usage = \Drupal::service('file.usage');

    // Collect cipher proc IDs for update_jobs cleanup.
    $deleted_cipher_ids = [];

    foreach ($entities as $entity) {
      if (!$entity instanceof self || $entity->get('type')->value !== 'cipher') {
        continue;
      }

      $deleted_cipher_ids[] = (string) $entity->id();

      $armored = $entity->get('armored')->getValue();
      $cipher_fid = $armored[0]['cipher_fid'] ?? NULL;
      if (!is_scalar($cipher_fid) || trim((string) $cipher_fid) === '') {
        continue;
      }

      $file = $file_storage->load((int) $cipher_fid);
      if (!$file instanceof FileInterface) {
        continue;
      }

      // Remove usage records tied to this proc ID so managed deletion can run.
      $usage = $file_usage->listUsage($file);
      foreach ($usage as $module => $types) {
        if ($module !== 'proc' || !is_array($types)) {
          continue;
        }

        foreach ($types as $type => $ids) {
          if (!is_array($ids)) {
            continue;
          }

          $count = (int) ($ids[$entity->id()] ?? 0);
          if ($count > 0) {
            $file_usage->delete($file, $module, $type, $entity->id(), $count);
          }
        }
      }

      $file->delete();
    }

    // Remove deleted cipher IDs from all keyrings' update_jobs in one pass.
    if (!empty($deleted_cipher_ids)) {
      try {
        /** @var \Drupal\proc\Service\RemoveUpdateJobForAllKeysServiceInterface $service */
        $service = \Drupal::service('proc.remove_update_job_for_all_keys_service');
        $service->removeMultipleUpdateJobsForAllKeys($deleted_cipher_ids);
      }
      catch (\Exception $e) {
        \Drupal::logger('proc')->error('Failed to clean update_jobs after cipher deletion: @message', [
          '@message' => $e->getMessage(),
        ]);
      }
    }
  }

}
