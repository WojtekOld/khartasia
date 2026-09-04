<?php
\ = \Drupal::configFactory()->getEditable('views.view.plante_textes_multilingues');
\ = \->getRawData();
\ = \['display']['default']['display_options']['arguments'] ?? [];
echo 'Arguments:' . PHP_EOL;
foreach (\ as \ => \) {
  echo '  ' . \ . ':' . PHP_EOL;
  echo '    table: ' . (\['table'] ?? '') . PHP_EOL;
  echo '    field: ' . (\['field'] ?? '') . PHP_EOL;
  echo '    default_action: ' . (\['default_action'] ?? '') . PHP_EOL;
}
\ = \['display']['default']['display_options']['filters'] ?? [];
echo 'Filtres:' . PHP_EOL;
foreach (\ as \ => \) {
  echo '  ' . \ . ': table=' . (\['table'] ?? '') . ' value=' . json_encode(\['value'] ?? '') . PHP_EOL;
}
echo 'Done' . PHP_EOL;
