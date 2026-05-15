DELETE FROM config WHERE name LIKE 'migrate_plus.migration.d6%';
SELECT name FROM config WHERE name LIKE 'migrate_plus.migration.%' ORDER BY name;
