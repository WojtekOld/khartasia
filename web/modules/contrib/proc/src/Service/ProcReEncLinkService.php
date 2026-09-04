<?php

namespace Drupal\proc\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;

/**
 * Service to add a "Request re-encryption" link to forms with proc fields.
 *
 * This service scans the form for fields of type 'proc_entity_reference_field'
 * that have protected entities as their default values. If such fields are
 * found, it adds a link to request re-encryption of the protected items. The
 * link opens a modal dialog for confirmation.
 */
class ProcReEncLinkService implements ProcReEncLinkServiceInterface {

  /**
   * {@inheritdoc}
   */
  public function addReEncLink(
    array &$form,
    EntityInterface $entity,
    array $link_class_attribute = [],
    int $weight = 0,
  ): void {

    // Collect proc IDs from protected fields in the form:
    $protectedDefaults = [];
    foreach (Element::children($form) as $fieldName) {
      // Only consider real entity fields.
      if (!$entity->hasField($fieldName)) {
        continue;
      }
      $fieldType = $entity->getFieldDefinition($fieldName)->getType();
      if ($fieldType !== 'proc_entity_reference_field') {
        continue;
      }

      $element = $form[$fieldName] ?? [];
      $widgets = $element['widget'] ?? [];
      if (!is_array($widgets)) {
        continue;
      }

      foreach ($widgets as $delta) {
        if (!is_array($delta)) {
          continue;
        }
        if (!isset($delta['target_id']['#default_value'])) {
          continue;
        }

        $default = $delta['target_id']['#default_value'];

        if (
          is_object($default) && method_exists($default, 'hasField') &&
          $default->hasField('field_wished_recipients_set')
        ) {
          $wished = $default->get('field_wished_recipients_set')->getValue();
          if (empty($wished)) {
            continue;
          }

          // Extract entity ID:
          if (method_exists($default, 'id')) {
            $protectedDefaults[] = (string) $default->id();
          }
          else {
            $id_value = $default->get('id')->getValue()[0]['value'] ?? NULL;
            if ($id_value !== NULL) {
              $protectedDefaults[] = (string) $id_value;
            }
          }
        }
      }
    }

    if (!empty($protectedDefaults)) {
      $protectedDefaults = implode(',', array_unique($protectedDefaults));

      $options = [
        'query' => ['destination' => Url::fromRoute('<current>')->toString()],
        'attributes' => [
          'class' => ['use-ajax', 'proc-re-enc-link'],
          'name' => 'proc-re-enc-link',
          'data-dialog-type' => 'modal',
          'data-dialog-options' => '{"width":600}',
        ],
      ];

      if (!empty($link_class_attribute)) {
        $options['attributes']['class'] = array_merge($options['attributes']['class'], $link_class_attribute);
      }

      $requestRencryptionUrl = Url::fromRoute(
        'proc.proc_request_re_encryption_confirm',
        ['proc_ids' => $protectedDefaults],
        $options
      );

      $form['r_re_encryption_link'] = [
        '#type' => 'pattern',
        '#attached' => [
          'library' => ['proc/proc-request-reencryption-link'],
        ],
        '#id' => 'link',
        '#weight' => $weight,
        '#fields' => [
          'text' => 'Request re-encryption',
          'url' => $requestRencryptionUrl,
        ],
      ];
    }
  }

}
