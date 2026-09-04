import subprocess

# Voir la config des themes installes
sql = "SELECT data FROM config WHERE name = 'core.extension';"
r = subprocess.run(['mysql', '-u', 'khartasia', '-pKhCanton2026x', 'khartasia', '-e', sql],
    capture_output=True, text=True)
print(r.stdout[:500])
