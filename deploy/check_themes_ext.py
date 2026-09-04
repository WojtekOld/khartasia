import subprocess

sql = "SELECT data FROM config WHERE name = 'core.extension';"
r = subprocess.run(['mysql', '-u', 'khartasia', '-pKhCanton2026x', 'khartasia', '-e', sql, '--skip-column-names'],
    capture_output=True, text=True)

data = r.stdout
# Trouver la partie themes
start = data.find('"theme"')
if start == -1:
    start = data.find('s:5:"theme"')
print("Themes section:")
print(data[start:start+500])
