<?php

namespace Drupal\xnttmanager\Form;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class IntegrityForm.
 *
 * Provides a simple form to inspect external entity integrity.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class IntegrityForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $incorrect_fields = [
      'dependencies' => [],
      'config' => [],
    ];

    $xntt_store = $this->entityTypeManager->getStorage('external_entity_type');
    $xntt_types = $xntt_store->getQuery()->execute();

    foreach ($xntt_types as $xntt_type) {
      // Get xntt.
      $xntt = $xntt_store->load($xntt_type);
      // Get field definitions.
      $field_defs = $this
        ->entityFieldManager
        ->getFieldDefinitions($xntt_type, $xntt_type);
      foreach ($field_defs as $field_id => $field_def) {
        $field_config = $field_def->getConfig($xntt_type);
        if (empty($field_config)) {
          $incorrect_fields['config'][$field_def->getUniqueIdentifier()] =
            $field_def->getLabel() . ' (' . $xntt->getLabel() . ')';
        }
        elseif ($field_def instanceof BaseFieldDefinition) {
          continue;
        }
        elseif ($field_def instanceof BaseFieldOverride) {
          $dependencies = $field_config->getDependencies();
          if (!in_array('external_entities', $dependencies['module'] ?? [])) {
            $incorrect_fields['dependencies'][$field_config->id()] =
              $field_def->getLabel() . ' (' . $xntt->getLabel() . ')';
          }
        }
        elseif ((!$field_def instanceof BaseFieldDefinition)
          && (!$field_def instanceof BaseFieldOverride)
        ) {
          $dependencies = $field_config->getDependencies();
          $req_dep_id = 'external_entities.external_entity_type.' . $xntt_type;
          if (!in_array($req_dep_id, $dependencies['config'] ?? [])) {
            $incorrect_fields['dependencies'][$field_config->id()] =
              $field_def->getLabel() . ' (' . $xntt->getLabel() . ') ' . get_class($field_def);
          }
        }
      }
    }

    $form['field'] = [
      '#type' => 'details',
      '#title' => $this->t('Field Integrity'),
      '#open' => TRUE,
    ];
    if (empty($incorrect_fields['dependencies'])
        && empty($incorrect_fields['config'])
    ) {
      $form['field']['field_ok'] = [
        '#type' => 'markup',
        '#markup' => '<p>No field problem detected.</p>',
      ];
    }
    else {
      if (!empty($incorrect_fields['dependencies'])) {
        $form['field']['missing_dep'] = [
          '#type' => 'markup',
          '#markup' =>
          "<p>\n"
          . $this->t(
              'Fields with missing dependencies (@count):',
              ['@count' => count($incorrect_fields['dependencies'])]
          )
          . "<ul>\n  <li>"
          . implode("</li>\n  <li>", $incorrect_fields['dependencies'])
          . "  </li>\n</ul>\n</p>\n",
        ];
      }
      if (!empty($incorrect_fields['config'])) {
        $form['field']['missing_conf'] = [
          '#type' => 'markup',
          '#markup' =>
          "<p>\n"
          . $this->t(
              'Fields with missing configuration (@count):',
              ['@count' => count($incorrect_fields['config'])]
          )
          . "<ul>\n  <li>"
          . implode("</li>\n  <li>", $incorrect_fields['config'])
          . "  </li>\n</ul>\n</p>\n",
        ];
      }

      $form['actions']['fix'] = [
        '#type' => 'submit',
        '#name' => 'fix_fields',
        '#value' => $this->t('Fix fields'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'xnttmanager_field_integrity_form';
  }

  /**
   * Default submit method.
   *
   * @param array $form
   *   The render array of the currently built form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object describing the current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $fixed = 0;
    $failed = 0;
    $total = 0;

    $xntt_store = $this->entityTypeManager->getStorage('external_entity_type');
    $xntt_types = $xntt_store->getQuery()->execute();
    $field_config_store = $this->entityTypeManager->getStorage('field_config');

    foreach ($xntt_types as $xntt_type) {
      // Get field definitions.
      $field_def = $this
        ->entityFieldManager
        ->getFieldDefinitions($xntt_type, $xntt_type);
      foreach ($field_def as $field_id => $field_def) {
        try {
          $field_config = $field_def->getConfig($xntt_type);
          if (($field_def instanceof BaseFieldDefinition)
            || ($field_def instanceof BaseFieldOverride)
          ) {
            // Base field.
            if (empty($field_config)) {
              // @todo Create missing base field config.
              /*
              $field_config = [...];
              $def_array = [
              'entity_type' => $xntt_type,
              'bundle' => $xntt_type,
              'dependencies' => [],
              ]
              + $field_config['definition'];
              $field_config_store
              ->create($def_array)
              ->save();
               */
              $this->logger('xnttmanager')->error('Unable to create base field config: not implemented.');
              ++$failed;
            }
            elseif ($field_def instanceof BaseFieldOverride) {
              $dependencies = $field_config->getDependencies();
              if (!in_array('external_entities', $dependencies['module'] ?? [])) {
                // Saving will recompute dependencies.
                $field_config->save();
                ++$fixed;
              }
            }
          }
          else {
            if (empty($field_config)) {
              // @todo Create missing field config.
              /*
              $field_config = [...];
              $def_array = [
              'entity_type' => $xntt_type,
              'bundle' => $xntt_type,
              'dependencies' => [],
              ]
              + $field_config['definition'];
              $field_config_store
              ->create($def_array)
              ->save();
               */
              $this->logger('xnttmanager')->error('Unable to create field config: not implemented.');
              ++$failed;
            }
            else {
              $dependencies = $field_config->getDependencies();
              $dependency = 'external_entities.external_entity_type.' . $xntt_type;
              if (!in_array($dependency, $dependencies['config'] ?? [])) {
                // Saving will recompute dependencies.
                $field_config->save();
                ++$fixed;
              }
            }
          }
        }
        catch (\Error $e) {
          $this->logger('xnttmanager')->error($e);
          ++$failed;
        }
      }
    }

    $total = $fixed + $failed;
    if ($total) {
      $this->messenger()->addMessage(
        $this->formatPlural(
          $total,
          '1 field with issues: ',
          '@count fields with issues: '
        )
        . ($fixed
          ? $this->formatPlural(
            $fixed,
            '1 field was fixed',
            '@count fields were fixed'
          )
          : ''
        )
        . (
          $failed
          ? $this->formatPlural(
            $failed,
            '1 field failed to be fixed',
            '@count fields failed to be fixed'
          )
          : ''
        )
      );
    }
  }

}
