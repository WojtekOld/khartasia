<?php

namespace Drupal\xnttmanager\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\external_entities\Entity\ConfigurableExternalEntityTypeInterface;
use Drupal\external_entities\FieldMapper\FieldMapperInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for external entity field inspector.
 */
class XnttInspector extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  protected function getModuleName() {
    return 'xnttmanager';
  }

  /**
   * Performs external entity field inspection and display a result table.
   *
   * @param string $xntt_type
   *   The external entity type machine name.
   * @param string $xntt_id
   *   An optional external entity identifier to load for example values. If
   *   empty, the first entity of the external entity type will be used.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   If the parameters are invalid.
   */
  public function inspect(string $xntt_type, string $xntt_id = '') {
    // Make sure we got an appropriate value.
    if (!preg_match('/^[\w\-]+$/', $xntt_type)) {
      throw new NotFoundHttpException();
    }

    $entity_type = $this->entityTypeManager()
      ->getStorage('external_entity_type')
      ->load($xntt_type);
    if ($entity_type === NULL) {
      throw new NotFoundHttpException();
    }

    // We only support ConfigurableExternalEntityTypeInterface.
    if (!$entity_type instanceof ConfigurableExternalEntityTypeInterface) {
      return [
        'inspection' => [
          '#type' => 'markup',
          '#markup' => $this->t(
            'Unsupported external entity type (@type).',
            [
              '@type' => $xntt_type,
            ]
          ),
        ],
      ];
    }

    // Try to load an external entity instance...
    $aggregator = $entity_type->getDataAggregator();
    $id_field_mapper = $entity_type->getFieldMapper('id');
    if (empty($id_field_mapper)) {
      $this->messenger()->addWarning($this->t(
        'The external entity type does not define a mapping for the identifier field. No entity could be loaded.'
      ));
      return [
        'inspection' => [
          '#type' => 'markup',
          '#markup' => $this->t(
            'No external entity could be loaded for the given type (@type).',
            [
              '@type' => $xntt_type,
            ]
          ),
        ],
      ];
    }

    // Did the user provide an identifier?
    if (empty($xntt_id)) {
      // Nope, use the first entity.
      $raw_data = current($aggregator->query([], [], 0, 1));
      if (!is_array($raw_data)) {
        $raw_data = [];
      }
      $xntt_id = $id_field_mapper->extractFieldValuesFromRawData($raw_data)[0]['value'] ?? NULL;
      if (isset($xntt_id)) {
        $xntt_data = $aggregator->load($xntt_id);
      }
      if (empty($xntt_data)) {
        $xntt_data = [];
        if (empty($raw_data)) {
          $this->messenger()->addWarning($this->t(
            'No entity found for the given external entity type.'
          ));
        }
        else {
          $this->messenger()->addWarning($this->t(
            'The identifier field of the given external entity type is not properly mapped.'
          ));
        }
      }
      else {
        $xntt_entity = $this->entityTypeManager()
          ->getStorage($xntt_type)
          ->load($xntt_id);
      }
    }
    else {
      // Yep, try to load the corresponding raw entity.
      $xntt_data = $aggregator->load($xntt_id);
      if (empty($xntt_data)) {
        $xntt_data = [];
        $this->messenger()->addWarning($this->t(
          'The given entity identifier was not found or its corresponding entity is empty.'
        ));
      }
      else {
        // Load external entity instance.
        $xntt_entity = $this->entityTypeManager()
          ->getStorage($xntt_type)
          ->load($xntt_id);
      }
    }
    if (!empty($xntt_entity)) {
      $xntt_array = $xntt_entity->toArray();
    }
    else {
      $xntt_array = [];
    }
    // Here we are supposed to have either $xntt_data, $xntt_entity and
    // $xntt_array empty if loading failed or $xntt_data is an array containg
    // raw (unampped) data, $xntt_entity is its corresponding (mapped) Drupal
    // entity form, and $xntt_array contains values of $xntt_entity in an array
    // form.
    $render_array['xntt_type'] = [
      '#markup' => $this->t(
        '<h2>&quot;@entity_type&quot; Inspection</h2>',
        ['@entity_type' => $entity_type->getLabel()]
      ),
    ];

    // Now inspect field mapping.
    $fields = $entity_type->getMappableFields();
    $rows = [];
    $context = [
      FieldMapperInterface::CONTEXT_SOURCE_KEY => $xntt_data,
    ];
    $mapped_field_properties = [];
    $not_mapped_drupal_fields = [];
    $incorrectly_mapped_field_properties = [];
    $not_mapped_raw_values = [];
    foreach ($fields as $field_name => $field_def) {
      $field_mapper = $entity_type->getFieldMapper($field_name);
      if (empty($field_mapper)) {
        $rows[] = [
          'drupal_field' => $field_name,
          'property' => '',
          'raw_field' => '-',
          'inspection' => $this->t('Not mapped'),
          'srcdata' => '',
          'exvalue' => '',
        ];
        continue;
      }
      // Get corresponding part of the raw array.
      $raw_values = [];
      $field_mapper->addFieldValuesToRawData(
        $xntt_array[$field_name] ?? [],
        $raw_values,
        $context
      );
      if (empty($raw_values)) {
        $raw_values = NULL;
      }
      // Loop on field properties.
      $properties = $field_mapper->getProperties();
      $main_property_name = $field_mapper->getMainPropertyName();
      if (1 < count($properties)) {
        // Multiple properties.
        $first_line = TRUE;
        foreach ($properties as $property_name => $property_def) {
          $raw_field_name = $field_mapper->getMappedSourceFieldName($property_name) ?? '';
          if ($first_line) {
            $first_line = FALSE;
            $rows[] = [
              'drupal_field' => [
                'data' => [
                  '#plain_text' => $field_name,
                ],
              ],
              'property' => $property_name,
              'raw_field' => ('' === $raw_field_name) ? '-' : $raw_field_name,
              'inspection' => ('' === $raw_field_name) ? $this->t('No corresponding raw field') : '',
              'srcdata' => ['data' => $this->renderValue($raw_values)],
              'exvalue' => $this->renderValue($xntt_array[$field_name]),
            ];
          }
          else {
            $rows[] = [
              'drupal_field' => '"',
              'property' => $property_name,
              'raw_field' => ('' === $raw_field_name) ? '-' : $raw_field_name,
              'inspection' => ('' === $raw_field_name) ? $this->t('No corresponding raw field') : '',
              'srcdata' => ['data' => $this->renderValue($raw_values)],
              'exvalue' => $this->renderValue($xntt_array[$field_name]),
            ];
          }
        }
      }
      else {
        // Single property.
        $raw_field_name = $field_mapper->getMappedSourceFieldName() ?? '';
        $rows[] = [
          'drupal_field' => $field_name,
          'property' => '',
          'raw_field' => ('' === $raw_field_name) ? '-' : $raw_field_name,
          'inspection' => ('' === $raw_field_name) ? $this->t('No corresponding raw field') : '',
          'srcdata' => $this->renderValue($raw_values),
          'exvalue' => $this->renderValue($xntt_array[$field_name] ?? NULL),
        ];
      }
    }

    $url = Url::fromRoute("entity.$xntt_type.collection");
    $link = Link::createFromRoute($this->t('@path', ['@path' => $url->toString()]), "entity.$xntt_type.collection");
    $path_render = $link->toRenderable();
    $path_render['#prefix'] = $this->t("Entity list path:") . ' ';

    $render_array['inspection'] = [
      'path' => $path_render,
      'fields' => [
        '#theme' => 'table',
        '#header' => [
          $this->t('Drupal Field'),
          $this->t('Property'),
          $this->t('Raw Field Name'),
          $this->t('Inspection'),
          [
            'data' => $this->t('Source'),
            // Set a minimal width to display (collapsed) examples.
            'class' => 'xnttmanager-source-column',
          ],
          [
            'data' => $this->t('Example Value'),
            // Set a minimal width to display (collapsed) examples.
            'class' => 'xnttmanager-example-column',
          ],
        ],
        '#rows' => $rows,
        '#attached' => [
          'library' => [
            'xnttmanager/xnttmanager',
          ],
        ],
      ],
    ];
    $render_array['raw_data_details'] = [
      '#type' => 'details',
      '#title' => $this->t('Original raw data'),
      '#open' => FALSE,
      [
        'raw_data' => [
          '#type' => 'item',
          '#prefix' => '<pre>',
          '#suffix' => '</pre>',
          // @todo Display using a custom function for better indentation and
          // (not mapped) field highlight.
          '#plain_text' => var_export($xntt_data, TRUE),
        ],
      ],
    ];

    return $render_array;
  }

  /**
   * Renders a field property value for nice display.
   *
   * @param mixed $value
   *   The value to render.
   *
   * @return string|array
   *   A Drupal render element.
   */
  public function renderValue($value) {
    $rendered_value = 'n/a';
    if (is_scalar($value)) {
      $rendered_value = $value;
      if (is_string($value)) {
        // Check if the value is binary or too long to be displayed as is.
        $is_binary = preg_match('#[\x00-\x07\x0E-\x1F\x81\x8D\x8F\x90\x9D]#', $value);
        $only_regular_chars = preg_replace('#[\b\t\n\v\f\r\x20-\x7E]#', '', $value);
        // It is binary if it contains binary characters or if it contains
        // more than 13% of non-regular characters such as accents or special
        // signs.
        if ($is_binary
          || (!empty($value) && strlen($only_regular_chars) / strlen($value) > 0.13)
        ) {
          $rendered_value = $this->t('(binary data)');
        }
        elseif (80 < strlen($value)) {
          $rendered_value = [
            'data' => [
              '#type' => 'details',
              '#title' => substr($value, 0, 10) . '...',
              '#prefix' => '<pre>',
              '#suffix' => '</pre>',
              '#plain_text' => $value,
              '#attributes' => ['class' => ['xnttmanager-details']],
            ],
          ];
        }
      }
    }
    elseif (is_array($value)) {
      $array_data = var_export($value, TRUE);
      if (80 >= strlen($array_data)) {
        $rendered_value = $array_data;
      }
      else {
        $rendered_value = [
          'data' => [
            '#type' => 'details',
            '#title' => substr($array_data, 0, 10) . '...',
            '#prefix' => '<pre>',
            '#suffix' => '</pre>',
            '#plain_text' => $array_data,
            '#attributes' => ['class' => ['xnttmanager-details']],
          ],
        ];
      }
    }
    elseif (is_object($value)) {
      $rendered_value = '(' . get_class($value) . ' object)';
    }

    return $rendered_value;
  }

}
