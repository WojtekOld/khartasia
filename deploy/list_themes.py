import subprocess
php = """
$theme_handler = \Drupal::service('theme_handler');
$theme_handler->refreshInfo();
$themes = $theme_handler->listInfo();
foreach ($themes as $name => $theme) {
  echo $name . "\n";
}
"""
with open('/tmp/list_themes.php', 'w') as f:
    f.write('<?php\n' + php)
print('OK')
