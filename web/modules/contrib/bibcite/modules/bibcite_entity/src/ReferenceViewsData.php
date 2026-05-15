<?php

namespace Drupal\bibcite_entity;

use Drupal\Core\Entity\Sql\TableMappingInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\views\EntityViewsData;

/**
 * Provides the views data for the reference entity type.
 *
 * Provides the missing delta field for multi-value fields,
 * as a workaround for core issue #3097568.
 */
class ReferenceViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['bibcite_reference']['id']['argument'] = [
      'id' => 'bibcite_reference',
      'name field' => 'title',
      'numeric' => TRUE,
    ];

    $data['bibcite_reference']['citation'] = [
      'title' => $this->t('Citation'),
      'help' => $this->t('Render reference as citation'),
      'field' => [
        'id' => 'bibcite_citation',
      ],
    ];

    $data['bibcite_reference']['links'] = [
      'title' => $this->t('Links'),
      'help' => $this->t('Render reference links'),
      'field' => [
        'id' => 'bibcite_links',
      ],
    ];

    $data['bibcite_reference']['bulk_form'] = [
      'title' => $this->t('Operations bulk form'),
      'help' => $this->t('Add a form element that lets you run operations on multiple reference entries.'),
      'field' => [
        'id' => 'bulk_form',
      ],
    ];

    // @todo Use $this->entityTypeManager only, once Drupal 8.9.0 is released.
    $entity_manager = isset($this->entityTypeManager) ? $this->entityTypeManager : $this->entityManager;
    $entity_type = $entity_manager->getDefinition('bibcite_keyword');
    $data['bibcite_reference__keywords']['keywords_target_id']['relationship'] = [
      'base' => $this->getViewsTableForEntityType($entity_type),
      'base field' => $entity_type->getKey('id'),
      'label' => $entity_type->getLabel(),
      'title' => $entity_type->getLabel(),
      'id' => 'standard',
    ];

    $entity_manager = isset($this->entityTypeManager) ? $this->entityTypeManager : $this->entityManager;
    $entity_type = $entity_manager->getDefinition('bibcite_contributor');
    $data['bibcite_reference__author']['author_target_id']['relationship'] = [
      'base' => $this->getViewsTableForEntityType($entity_type),
      'base field' => $entity_type->getKey('id'),
      'label' => $entity_type->getLabel(),
      'title' => $entity_type->getLabel(),
      'id' => 'standard',
    ];

    $data['bibcite_reference__bibcite_citekey']['bibcite_citekey'] = [
      'title' => $this->t('Citation key'),
      'field' => [
        'id' => 'field',
      ],
      'argument' => [
        'field' => 'bibcite_citekey_value',
        'id' => 'string',
      ],
      'filter' => [
        'field' => 'bibcite_citekey_value',
        'id' => 'string',
      ],
      'sort' => [
        'field' => 'bibcite_citekey_value',
        'id' => 'standard',
      ],
      'entity field' => 'bibcite_citekey',
    ];

    $data['bibcite_reference_revision__bibcite_citekey']['bibcite_citekey'] = $data['bibcite_reference__bibcite_citekey']['bibcite_citekey'];

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function mapFieldDefinition($table, $field_name, FieldDefinitionInterface $field_definition, TableMappingInterface $table_mapping, &$table_data) {
    parent::mapFieldDefinition($table, $field_name, $field_definition, $table_mapping, $table_data);

    // Expose additional delta column for multiple value fields.
    // Workaround for core issue #3097568.
    if ($this->getFieldStorageDefinitions()[$field_name]->isMultiple()) {
      $label = $field_definition->getLabel();

      $table_data['delta'] = [
        'title' => $this->t('@label (@name:delta)', ['@label' => $label, '@name' => $field_name]),
        'title short' => $this->t('@label:delta', ['@label' => $label]),
      ];
      $table_data['delta']['field'] = [
        'id' => 'numeric',
      ];
      $table_data['delta']['argument'] = [
        'field' => 'delta',
        'table' => $table,
        'id' => 'numeric',
        'empty field name' => $this->t('- No value -'),
        'field_name' => $field_name,
        'entity_type' => $this->entityType->id(),
      ];
      $table_data['delta']['filter'] = [
        'field' => 'delta',
        'table' => $table,
        'id' => 'numeric',
        'field_name' => $field_name,
        'entity_type' => $this->entityType->id(),
        'allow empty' => TRUE,
      ];
      $table_data['delta']['sort'] = [
        'field' => 'delta',
        'table' => $table,
        'id' => 'standard',
        'field_name' => $field_name,
        'entity_type' => $this->entityType->id(),
      ];
    }
  }

}
