with open('/var/www/khartasia/web/autoload.php', 'w') as f:
    f.write("<?php\nreturn require __DIR__ . '/../vendor/autoload.php';\n")
print('OK')
