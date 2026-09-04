<?php
$databases['default']['default'] = [
  'driver'   => 'mysql',
  'database' => 'khartasia',
  'username' => 'khartasia',
  'password' => 'KhCanton2026x',
  'host'     => 'localhost',
  'port'     => '3306',
  'prefix'   => '',
  'namespace'=> 'Drupal\mysql\Driver\Database\mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
];
$settings['hash_salt'] = 'khartasia_prod_2026_secure_hash';
$settings['trusted_host_patterns'] = [
  '^khartasia\.org$',
  '^www\.khartasia\.org$',
  '^khartasia\.fr$',
  '^www\.khartasia\.fr$',
  '^khartasia\.com$',
  '^www\.khartasia\.com$',
  '^khartasia\.online$',
  '^www\.khartasia\.online$',
];
$settings['config_sync_directory'] = 'sites/default/files/config_sync';
$settings['file_public_path'] = 'sites/default/files';
$config['system.performance']['css']['preprocess'] = TRUE;
$config['system.performance']['js']['preprocess'] = TRUE;
