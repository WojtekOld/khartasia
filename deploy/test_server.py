import subprocess
result = subprocess.run(
    ['curl', '-s', '-o', '/dev/null', '-w', '%{http_code}',
     'http://localhost', '-H', 'Host: khartasia.org'],
    capture_output=True, text=True
)
print('HTTP:', result.stdout)

# Test Drush avec chemin absolu
result2 = subprocess.run(
    ['/var/www/khartasia/vendor/bin/drush',
     '--root=/var/www/khartasia/web',
     'version'],
    capture_output=True, text=True,
    cwd='/var/www/khartasia'
)
print('Drush:', result2.stdout[:200])
print('Err:', result2.stderr[:200])
