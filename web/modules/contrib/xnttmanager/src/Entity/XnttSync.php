<?php

namespace Drupal\xnttmanager\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * External entity synchronization cron.
 *
 * Each instance is dedicated to a specific external entity type.
 *
 * @ConfigEntityType(
 *   id = "xnttsync",
 *   label = @Translation("External Entity Synchronization Cron"),
 *   admin_permission = "Administer external entity types",
 *   handlers = {
 *     "list_builder" = "Drupal\xnttmanager\Controller\SyncListBuilder",
 *     "form" = {
 *       "add" = "Drupal\xnttmanager\Form\SyncAddForm",
 *       "edit" = "Drupal\xnttmanager\Form\SyncEditForm",
 *       "delete" = "Drupal\xnttmanager\Form\SyncDeleteForm",
 *       "sync" = "Drupal\xnttmanager\Form\SyncForm"
 *     }
 *   },
 *   entity_keys = {
 *     "id" = "xnttType",
 *     "label" = "label"
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/external-entity-types/sync/manage/{xnttsync}",
 *     "edit-form" = "/admin/structure/external-entity-types/sync/manage/{xnttsync}",
 *     "delete-form" = "/admin/structure/external-entity-types/sync/manage/{xnttsync}/delete"
 *   },
 *   config_export = {
 *     "xnttType",
 *     "uuid",
 *     "label",
 *     "contentTarget",
 *     "syncAddMissing",
 *     "syncUpdateExisting",
 *     "syncRemoveOrphans",
 *     "frequency",
 *     "weight",
 *   }
 * )
 */
class XnttSync extends ConfigEntityBase {

  /**
   * The external entity type machine name handled by the cron.
   *
   * @var string
   */
  protected $xnttType;

  /**
   * The human-readable label of the synchronization cron.
   *
   * @var string
   */
  protected $label;

  /**
   * The target content type machine name used for synchronization.
   *
   * It uses the format "content type machine name" + "slash" + "bundle machine
   * name". Ex.: 'node/page'
   *
   * @var string
   */
  protected $contentTarget;

  /**
   * If set, add missing records during synchronization.
   *
   * @var bool
   */
  protected $syncAddMissing;

  /**
   * If set, update existing records during synchronization.
   *
   * @var bool
   */
  protected $syncUpdateExisting;

  /**
   * If set, remove orphan records during synchronization.
   *
   * @var bool
   */
  protected $syncRemoveOrphans;

  /**
   * The synchronization operation frequency in seconds.
   *
   * @var int
   */
  protected $frequency;

  /**
   * Last time the synchronization process was launched as timestamp.
   *
   * @var int
   */
  protected $lastRunTime;

  /**
   * True if currently synchronizing.
   *
   * @var bool
   */
  protected $inUse;

  /**
   * The synchronization cron weight.
   *
   * @var int
   */
  protected $weight;

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->xnttType;
  }

  /**
   * Synchronize local content with external entities content.
   */
  public function synchronize() {
    $this->set('inUse', TRUE);
    $this->set('lastRunTime', time());
    $this->save();
    \Drupal::logger('xnttmanager')->notice(
      'Starting '
      . $this->label()
      . '.'
    );
    $created = 0;
    $updated = 0;
    $removed = 0;

    try {
      $xntt_type = $this->get('xnttType');
      $entity_type = \Drupal::service('entity_type.manager')
        ->getStorage('external_entity_type')
        ->load($xntt_type);
      $field_mapper = $entity_type->getFieldMapper('id');
      if (!empty($field_mapper)) {
        $id_field = $field_mapper->getMappedSourceFieldName();
      }
      [$content_type, $bundle_name] = explode(
        '/',
        $this->get('contentTarget')
      );
      $content_store = \Drupal::service('entity_type.manager')
        ->getStorage($content_type);
      $xntt_store = \Drupal::service('entity_type.manager')
        ->getStorage($xntt_type);

      // Get number of external entities to synchronize.
      $data_aggregator = $entity_type->getDataAggregator();
      $sync_count = $data_aggregator->countQuery();
      $existing_xntt_ids = [];

      for ($i = 0; $i < $sync_count; ++$i) {
        $content_entity = NULL;
        try {
          // Load entities one by one.
          $xntt_entities = $data_aggregator->query([], [], $i, 1);
          $entity_id = current($xntt_entities)[$id_field];
          if (empty($entity_id)) {
            throw new \Exception('No external entity id for mapped field "' . $id_field . '" (at ' . ($offset + $i) . '): ' . print_r(current($xntt_entities), TRUE));
          }
          $existing_xntt_ids[] = $entity_id;
          // Load external entity.
          $entity = $xntt_store->load($entity_id);

          // Load corresponding content.
          $content_entity = current(
            $content_store->loadByProperties(
              ['xnttid' => $entity_id]
            )
          );
          if (empty($content_entity)) {
            // Content not found, add missing content.
            if (!empty($this->get('syncAddMissing'))) {
              // We set default uid to admin if not set from external entity.
              $content_data = [
                'type' => $bundle_name,
                'xnttid' => $entity_id,
                'xntttype' => $xntt_type,
              ]
                + $entity->toArray()
                + [
                  'uid' => 1,
                ];
              // We clear entity id to avoid conflicts.
              // @todo find a way to get the entitiy key field ("nid" for
              // nodes) instead of hard-coding it. Is 'type' and 'uid' key name
              // above also variable in some ways? Use base fields?
              unset($content_data['nid']);
              unset($content_data['uuid']);
              $content_entity = $content_store->create($content_data);
              $content_entity->save();
              ++$created;
            }
          }
          elseif (!empty($this->get('syncUpdateExisting'))) {
            // Check for changes.
            $need_update = FALSE;
            if ($content_entity->langcode->value !== $entity->langcode->value) {
              if ($content_entity->hasTranslation($entity->langcode->value)) {
                $content_entity = $content_entity->getTranslation($entity->langcode->value);
              }
              else {
                $translation_default_values = $content_entity->toArray();
                $content_entity = $content_entity->addTranslation($entity->langcode->value, $translation_default_values);
              }
            }
            $local_values = $content_entity->toArray();
            foreach ($entity->toArray() as $field_name => $field_value) {
              // Skip identifier and translation fields.
              // @todo removed 'nid' hard-coding.
              if (('id' == $field_name)
                || ('uuid' == $field_name)
                || ('xnttid' == $field_name)
                || ('nid' == $field_name)
                || ('default_langcode' == $field_name)
              ) {
                continue;
              }
              if (array_key_exists($field_name, $local_values)
                  && ($field_value != $local_values[$field_name])
              ) {
                $content_entity->set($field_name, $field_value);
                $need_update = TRUE;
              }
            }
            if ($content_entity->get('xnttid')->isEmpty()) {
              $content_entity->set('xnttid', $entity_id);
              $need_update = TRUE;
            }
            if ($content_entity->get('xntttype')->isEmpty()) {
              $content_entity->set('xntttype', $xntt_type);
              $need_update = TRUE;
            }
            if ($need_update) {
              $content_entity->save();
              ++$updated;
            }
          }
        }
        catch (\Exception $e) {
          \Drupal::logger('xnttmanager')->error(
            'An error occurred during synchronization of '
            . $this->label()
            . ' for external entity "'
            . $entity_id
            . '": '
            . $e->getMessage()
          );
        }
      }
      // Clean orphans.
      if (!empty($this->get('syncRemoveOrphans'))) {
        $orphan_ids = $content_store->getQuery()
          ->accessCheck(FALSE)
          ->condition('xnttid', $existing_xntt_ids, 'NOT IN')
          ->condition('type', $bundle_name)
          ->condition('xntttype', $xntt_type)
          ->execute();
        $orphan_entities = $content_store->loadMultiple($orphan_ids);
        $content_store->delete($orphan_entities);
        $removed = count($orphan_entities);
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('xnttmanager')->error(
        'An error occurred during synchronization of '
        . $this->label()
        . ': '
        . $e->getMessage()
      );
    }

    $this->set('inUse', FALSE);
    $this->save();

    \Drupal::logger('xnttmanager')->notice(
      $this->label()
      . ' done. Created content: '
      . $created
      . '. Updated content: '
      . $updated
      . '. Removed content: '
      . $removed
      . '.'
    );
  }

}
