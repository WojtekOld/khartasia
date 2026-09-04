<?php

/**
 * @file
 * Responsive images and styles - map breakpoints and image styles
 */

/**
 * Admin form
 */
function resp_img_admin_breakpoints($form, &$form_state, $breakpoint_group_name = '') {
  // Show a list of all groups if no group name is given.
  if ($breakpoint_group_name == '' || $breakpoint_group_name == 'global') {
    return resp_img_admin_breakpoints_overview_page();
  }
  $machine_name = 'mappings.' . $breakpoint_group_name;
  $form = array();

  $mappings = resp_img_mapping_load($machine_name);
  $mappings = $mappings ? $mappings : new stdClass();

  $form['resp_img_mapping'] = array(
    '#type' => 'container',
    '#tree' => TRUE,
  );
  $form['resp_img_mapping']['machine_name'] = array(
    '#type' => 'value',
    '#value' => isset($mappings->machine_name) ? $mappings->machine_name : $machine_name,
  );
  $form['resp_img_mapping']['breakpoint_group'] = array(
    '#type' => 'value',
    '#value' => isset($mappings->breakpoint_group) ? $mappings->breakpoint_group : $breakpoint_group_name,
  );
  if (isset($mappings->id)) {
    $form['resp_img_mapping']['id'] = array(
      '#type' => 'value',
      '#value' => $mappings->id,
    );
  }

  $breakpoints = array();
  $breakpoint_group = breakpoints_breakpoint_group_load($breakpoint_group_name);
  $weight = 0;
  foreach ($breakpoint_group->breakpoints as $breakpoint_name) {
    $breakpoint = breakpoints_breakpoint_load_by_fullkey($breakpoint_name);
    if ($breakpoint && isset($breakpoint->machine_name) && $breakpoint->status) {
      $breakpoint->global_weight = $breakpoint->weight;
      $breakpoint->weight = $weight++;
      $breakpoints[$breakpoint_name] = $breakpoint;
    }
  }

  $image_styles = image_style_options(TRUE);
  $options = array_filter($image_styles, '_resp_img_filter_styles');
  foreach ($breakpoints as $breakpoint_name => $breakpoint) {
    $label = '1x ' . $breakpoint->name . ' [' . $breakpoint->breakpoint . ']';
    $form['resp_img_mapping']['mapping'][$breakpoint_name]['1x'] = array(
      '#title' => check_plain($label),
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => isset($mappings->mapping[$breakpoint_name]['1x']) ? $mappings->mapping[$breakpoint_name]['1x'] : '',
    );
    if (isset($breakpoint->multipliers) && !empty($breakpoint->multipliers)) {
      foreach ($breakpoint->multipliers as $multiplier => $status) {
        if ($status) {
          $label = $multiplier . ' ' . $breakpoint->name . ' [' . $breakpoint->breakpoint . ']';
          $form['resp_img_mapping']['mapping'][$breakpoint_name][$multiplier] = array(
            '#title' => check_plain($label),
            '#type' => 'select',
            '#options' => $options,
            '#default_value' => isset($mappings->mapping[$breakpoint_name][$multiplier]) ? $mappings->mapping[$breakpoint_name][$multiplier] : '',
          );
        }
      }
    }
  }

  // Buttons
  $form['buttons'] = array(
    '#type' => 'container',
  );

  // Submit button
  $form['buttons']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save'),
  );

  return $form;
}

/**
 * Admin form overview page.
 */
function resp_img_admin_breakpoints_overview_page() {
  $links = $output = array();
  $breakpoint_groups = breakpoints_breakpoint_group_load_all();
  if (isset($breakpoint_groups) && !empty($breakpoint_groups)) {
    foreach ($breakpoint_groups as $breakpoint_group) {
      $links[] = l($breakpoint_group->name, 'admin/config/media/resp_img/groups/' . $breakpoint_group->machine_name);
    }
    $output =  array(
      '#type' => 'container',
      '#theme' => 'item_list',
      '#items' => $links,
    );
  }
  else {
    $output['info'] = array(
      '#type' => 'markup',
      '#markup' => t("There are no Breakpoint groups. !here to configure Breakpoint groups.", array('!here' => l(t('Click here'), 'admin/config/media/breakpoints/groups/add'))),
    );
  }
  return $output;
}

/**
 * Admin form submit.
 */
function resp_img_admin_breakpoints_submit($form, &$form_state) {
  $mapping = (object)$form_state['values']['resp_img_mapping'];
  resp_img_mapping_save($mapping);
  drupal_set_message(t('The configuration options have been saved.'));
}

function resp_img_admin_export_form($form, &$form_state, $mappings_name) {
  // Create the export code textarea.
  ctools_include('export');
  $mapping = resp_img_mapping_load($mappings_name);
  if (!$mapping) {
    $mapping = new stdClass();
  }
  $export = ctools_export_object('resp_img_mapping', $mapping);
  $form['mapping_export'] = array(
    '#type' => 'textarea',
    '#title' => t('Mapping code'),
    '#rows' => count(explode("\n", $export)),
    '#default_value' => $export,
    '#weight' => -1,
    '#description' => t('<strong>Warning!</strong> Only import these mappings if the breakpoint group below has been imported on that site already, or if they were manually created there.'),
  );

  // Also export the group it belongs to.
  module_load_include('inc', 'breakpoints', 'breakpoints.admin');
  $form += drupal_get_form('breakpoints_admin_breakpoint_group_export_form', $mapping->breakpoint_group);
  $form['export']['#description'] = t('If you want to import this mapping on an other site,
    you\'ll need to import the breakpoint group with its breakpoints as well,
    if it doesn\'t already exist on that site.');
  return $form;
}

function resp_img_admin_import_form($form, &$form_state) {
  $form['import'] = array(
    '#type' => 'textarea',
    '#rows' => 10,
  );
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Import')
  );
  return $form;
}


/**
 * Validate a mapping import.
 */
function resp_img_admin_import_form_validate($form, &$form_state) {
  ctools_include('export');
  $code = $form_state['values']['import'];
  $mapping = ctools_export_crud_import('resp_img_mapping', $code);
  if (!resp_img_mapping_validate($mapping)) {
    form_set_error('import', t('Not a valid mapping object'));
    return;
  }
  if (resp_img_mapping_load($mapping->machine_name)) {
    form_set_error('import', t('A mapping with machine name %name already exists', array('%name' => $mapping->machine_name)));
    return;
  }
  form_set_value($form['import'], $mapping, $form_state);
}

/**
 * Import mapping.
 */
function resp_img_admin_import_form_submit($form, &$form_state) {
  $mapping = $form_state['values']['import'];
  if (resp_img_mapping_save($mapping)) {
    drupal_set_message(t('Mapping %mapping saved.', array('%mapping' => $mapping->machine_name)));
    $form_state['redirect'] = 'admin/config/media/resp_img/';
  }
  else {
    drupal_set_message(t('Something went wrong, we could not save the mapping'), 'error');
  }
}
