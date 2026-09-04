<?php

/**
 * A generic Entity handler.
 *
 * The generic base implementation has a variety of overrides to workaround
 * core's largely deficient entity handling.
 */
class EntityReference_SelectionHandler_Translation_Generic extends EntityReference_SelectionHandler_Generic {

  /**
   * Overrides EntityReference_SelectionHandler_Generic::getInstance().
   * We need to override this to be able to call our own EntityReference_SelectionHandler_Translation_Generic_node class.
   */
  public static function getInstance($field, $instance = NULL, $entity_type = NULL, $entity = NULL) {
    $target_entity_type = $field['settings']['target_type'];

    // Check if the entity type does exist and has a base table.
    $entity_info = entity_get_info($target_entity_type);
    if (empty($entity_info['base table'])) {
      return EntityReference_SelectionHandler_Broken::getInstance($field, $instance);
    }

    if (class_exists($class_name = 'EntityReference_SelectionHandler_Translation_Generic_' . $target_entity_type)) {
      return new $class_name($field, $instance, $entity_type, $entity);
    }
    else {
      return new EntityReference_SelectionHandler_Generic($field, $instance, $entity_type, $entity);
    }
  }
}

/**
 * Override for the Node type.
 *
 * This only exists to workaround core bugs.
 */
class EntityReference_SelectionHandler_Translation_Generic_node extends EntityReference_SelectionHandler_Generic_node {
  /**
   * Overrides EntityReference_SelectionHandler_Generic_node::entityFieldQueryAlter().
   * Adds language filtering logic.
   */
  public function entityFieldQueryAlter(SelectQueryInterface $query) {
    parent::entityFieldQueryAlter($query);

    $base_table = $this->ensureBaseTable($query);
    $entity_info = entity_get_info($this->field['settings']['target_type']);

    if ($this->field['settings']['handler_settings']['filter']['language'] == 1 && $entity_info['translation']['locale'] == TRUE) {
      $node_language = i18n_node_i18n_context_language();
      if (isset($node_language->language) && !is_null($node_language->language)) {
        $query->condition("$base_table.language", $node_language->language);
      }
    }
    else {
      global $language;
      $query->condition("$base_table.language", $language->language);
    }
  }

  /**
   * Overrides EntityReference_SelectionHandler_Generic::settingsForm().
   * 0 = Makes the filtering configurable. Either show all nodes (filtered on
   * interface language) or show only those nodes with the same language as the
   * referencing (parent) node.
   */
  public static function settingsForm($field, $instance) {
    $form = parent::settingsForm($field, $instance);

    $form['filter']['language'] = array(
      '#type' => 'select',
      '#title' => t('Language handling'),
      '#options' => array(
        0 => t('Select all nodes matching with the interface language'),
        1 => t('Select only nodes matching the parent\'s language'),
      ),
      '#default_value' => isset($field['settings']['handler_settings']['filter']['language']) ? $field['settings']['handler_settings']['filter']['language'] : 0,
    );

    return $form;
  }
}

class EntityReference_SelectionHandler_Translation_Generic_taxonomy_term extends EntityReference_SelectionHandler_Generic_taxonomy_term {
  /**
   * Overrides EntityReference_SelectionHandler_Generic_taxonomy_term::entityFieldQueryAlter().
   * Adds language filtering logic.
   */
  public function entityFieldQueryAlter(SelectQueryInterface $query) {
    parent::entityFieldQueryAlter($query);

    $base_table = $this->ensureBaseTable($query);
    $entity_info = entity_get_info($this->field['settings']['target_type']);

    if ($this->field['settings']['handler_settings']['filter']['language'] == 1 && $entity_info['translation']['locale'] == TRUE) {
      $term_language = i18n_taxonomy_i18n_context_language();
      if (isset($term_language->language) && !is_null($term_language->language)) {
        $query->condition("$base_table.language", $term_language->language);
      }
    }
    else {
      global $language;
      $query->condition("$base_table.language", $language->language);
    }
  }

  /**
   * Overrides EntityReference_SelectionHandler_Generic::settingsForm().
   * 0 = Makes the filtering configurable. Either show all terms (filtered on
   * interface language) or show only those terms with the same language as the
   * referencing (parent) node.
   */
  public static function settingsForm($field, $instance) {
    $form = parent::settingsForm($field, $instance);

    $form['filter']['language'] = array(
      '#type' => 'select',
      '#title' => t('Language handling'),
      '#options' => array(
        0 => t('Select all terms matching with the interface language'),
        1 => t('Select only terms matching the parent\'s language'),
      ),
      '#default_value' => isset($field['settings']['handler_settings']['filter']['language']) ? $field['settings']['handler_settings']['filter']['language'] : 0,
    );

    return $form;
  }
}
