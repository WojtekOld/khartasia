import subprocess
sql = "CREATE DATABASE IF NOT EXISTS khartasia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS khartasia@localhost IDENTIFIED BY 'KhCanton2026x'; GRANT ALL PRIVILEGES ON khartasia.* TO khartasia@localhost; FLUSH PRIVILEGES;"
result = subprocess.run(['mysql', '-u', 'root', '-e', sql], capture_output=True, text=True)
print(result.stdout or 'OK')
print(result.stderr or '')
