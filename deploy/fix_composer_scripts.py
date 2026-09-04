import json

composer_path = '/var/www/khartasia/composer.json'
with open(composer_path) as f:
    data = json.load(f)

# Ajouter scripts post-install et post-update
if 'scripts' not in data:
    data['scripts'] = {}

cmd = "rm -rf vendor/drupal/core && ln -sfn web/core vendor/drupal/core"

data['scripts']['post-install-cmd'] = [cmd]
data['scripts']['post-update-cmd'] = [cmd]

with open(composer_path, 'w') as f:
    json.dump(data, f, indent=4)

print('OK')
