<?php

if (module_exists('i18n')) {
  $plugin = array(
    'title' => t('Translation aware selection (with optional filter by bundle)'),
    'class' => 'EntityReference_SelectionHandler_Translation_Generic',
    'weight' => -100,
  );
}
