-- Run once using mysql -h 127.0.0.1 -u root -e "source D:/Wampoon/htdocs/pcpartsstore/database/build-1-empty-fixture.sql"
-- The normal pcpartsstore_db schema must already be imported.
-- This fixture leaves normal rows untouched; its parts table deliberately has no rows.
CREATE DATABASE IF NOT EXISTS pcpartsstore_build1_empty_20261014;
CREATE TABLE IF NOT EXISTS pcpartsstore_build1_empty_20261014.parts LIKE pcpartsstore_db.parts;
SELECT COUNT(*) AS expected_zero_rows FROM pcpartsstore_build1_empty_20261014.parts;
