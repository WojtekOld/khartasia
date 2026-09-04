import subprocess

settings = """<?php
$databases['default']['default'] = [
  'driver'          => 'mysql',
  'database'        => 'khartasia',
  'username'        => 'khartasia',
  'password'        => 'KhCanton2026x',
  'host'            => 'localhost',
  'port'            => '3306',
  'prefix'          => '',
  'namespace'       => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload'        => 'core/modules/mysql/src/Driver/Database/mysql/',
  'isolation_level' => 'READ COMMITTED',
];
$settings['hash_salt'] = 'khartasia_prod_2026_secure_hash_x9z';
$settings['trusted_host_patterns'] = [
  '^khartasia\\\\.org$',
  '^www\\\\.khartasia\\\\.org$',
  '^khartasia\\\\.fr$',
  '^www\\\\.khartasia\\\\.fr$',
  '^khartasia\\\\.com$',
  '^www\\\\.khartasia\\\\.com$',
];
$settings['config_sync_directory'] = 'sites/default/files/config_sync';
$settings['file_public_path'] = 'sites/default/files';
$settings['file_private_path'] = '/var/www/khartasia/private';
"""

with open('/var/www/khartasia/web/sites/default/settings.php', 'w') as f:
    f.write(settings)
print('OK')
