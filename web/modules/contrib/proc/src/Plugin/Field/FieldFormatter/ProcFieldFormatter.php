<?php

namespace Drupal\proc\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'Proc entity reference label' formatter.
 *
 * @FieldFormatter(
 *   id = "proc_entity_reference_label_formatter",
 *   label = @Translation("Label"),
 *   description = @Translation("Display the label of the referenced proc entities."),
 *   field_types = {
 *     "proc_entity_reference_field"
 *   }
 * )
 */
class ProcFieldFormatter extends EntityReferenceLabelFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'decrypt' => FALSE,
      'new_tab' => TRUE,
      'modal' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['new_tab'] = [
      '#title' => $this->t('Open in a new tab'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('new_tab'),
    ];

    $elements['decrypt'] = [
      '#title' => $this->t('Switch link destination to the decrypt form'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('decrypt'),
    ];

    $elements['modal'] = [
      '#title' => $this->t('Use decrypt modal form'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('modal'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    $summary[] = $this->getSetting('decrypt') ? $this->t('Switch link to the decrypt form') : '';
    $summary[] = $this->getSetting('new_tab') ? $this->t('Open in a new tab') : $this->t('Open in current tab');
    $summary[] = $this->getSetting('modal') ? $this->t('Use decrypt modal form') : '';
    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $output_as_link = $this->getSetting('link');

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      $label = $entity->label();
      $uri = $this->setDecryptUrl($output_as_link, $entity);

      $elements[$delta] = ['#plain_text' => $label];
      if ($output_as_link && isset($uri) && !$entity->isNew()) {
        if ($this->getSetting('new_tab')) {
          $options = [
            'attributes' => [
              'target' => '_blank',
            ],
          ];
          $uri->MergeOptions($options);
        }

        $this->setDataAttributes($uri, $items, $entity);

        $elements[$delta] = [
          '#type' => 'link',
          '#title' => $label,
          '#url' => $uri,
          '#options' => $uri->getOptions(),
        ];

        // Attach Proc decrypt dialog and field settings.
        if ($this->getSetting('modal')) {
          $elements[$delta]['#attached']['library'][] = 'proc/proc-decrypt-dialog';
          $elements[$delta]['#attached']['drupalSettings']['proc'][$items->getName()] = [];
          if (
            $this->getFieldSettings()['handler'] == 'default:proc'
            && isset($this->getFieldSettings()['handler_settings'])
            ) {
            $elements[$delta]['#attached']['drupalSettings']['proc'][$items->getName()]['settings'] = $this
              ->getFieldSettings()['handler_settings'];
          }
        }

        if (!empty($items[$delta]->_attributes)) {
          $elements[$delta]['#options'] += ['attributes' => []];
          $elements[$delta]['#options']['attributes'] += $items[$delta]->_attributes;
          // Unset field item attributes since they have been included in the
          // formatter output and shouldn't be rendered in the field template.
          unset($items[$delta]->_attributes);
        }
      }

      $elements[$delta]['#entity'] = $entity;
      $elements[$delta]['#cache']['tags'] = $entity->getCacheTags();
      $elements[$delta]['#cache']['contexts'][] = 'user.permissions';
    }

    return $elements;
  }

  /**
   * Set data attributes for the link.
   *
   * @param object $uri
   *   The URI object.
   * @param object $items
   *   The field items.
   * @param object $entity
   *   The entity to be linked.
   */
  public function setDataAttributes(object &$uri, object $items, object $entity): void {
    // Add proc info as data-atributes.
    if ($this->getSetting('modal')) {
      $options = [
        'attributes' => [
          'id' => 'decrypt-' . $items->getName(),
          'data-proc-id' => $entity->id(),
          'data-proc-field-name' => $items->getName(),
          'class' => [
            'proc-attach-dialog',
          ],
          'target' => '',
        ],
      ];
      $uri->MergeOptions($options);
    }
  }

  /**
   * Set the decrypt URL.
   *
   * @param bool $output_as_link
   *   Whether the link should be displayed.
   * @param object $entity
   *   The entity to be linked.
   */
  public function setDecryptUrl(bool &$output_as_link, object $entity) {
    // If the link is to be displayed and the entity has an uri, display a
    // link.
    if ($output_as_link && !$entity->isNew()) {
      try {
        $uri = $entity->toUrl();
        // Set link target to Decrypt page.
        if ($this->getSetting('decrypt')) {
          $uri = $entity->toUrl('decrypt');
        }
        return $uri;
      }
      catch (UndefinedLinkTemplateException $e) {
        // This exception is thrown by \Drupal\Core\Entity\Entity::urlInfo()
        // and it means that the entity type doesn't have a link template nor
        // a valid "uri_callback", so don't bother trying to output a link for
        // the rest of the referenced entities.
        $output_as_link = FALSE;
      }
    }
    return NULL;
  }

}
