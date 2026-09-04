import subprocess
sql = "UPDATE config SET data = REPLACE(data, 'wallpaper_ui', 'khartasia_ui') WHERE name = 'system.theme';"
result = subprocess.run(
    ['mysql', '-u', 'khartasia', '-pKhCanton2026x', 'khartasia', '-e', sql],
    capture_output=True, text=True
)
print(result.stdout or 'OK')
print(result.stderr or '')

# Verifier
sql2 = "SELECT data FROM config WHERE name = 'system.theme';"
result2 = subprocess.run(
    ['mysql', '-u', 'khartasia', '-pKhCanton2026x', 'khartasia', '-e', sql2],
    capture_output=True, text=True
)
print(result2.stdout)
