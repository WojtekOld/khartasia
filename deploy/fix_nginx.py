content = open('/etc/nginx/sites-available/khartasia').read()
old = 'location ~ /\\. { deny all; }'
new = '''location ~ /\\.well-known { allow all; }
    location ~ /\\. { deny all; }'''
content = content.replace(old, new)
open('/etc/nginx/sites-available/khartasia', 'w').write(content)
print('OK')
print(content)
