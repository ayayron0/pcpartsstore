# Build 1 verification

Verified October 14, 2026 against Apache and MariaDB in Wampoon.
Base URL: http://localhost/pcpartsstore

| Case | Expected | Actual |
|---|---|---|
| GET /parts/1 | 200; seeded ID 1 and matching fields | PASS: 200 application/json; ID 1, GeForce RTX 5070, GPU, brand_id 1, price "749.99" |
| GET /parts/999999 | 404; missing-item error | PASS: 404 application/json; {"error":"PC part not found"} |
| GET /parts | 200; seeded IDs 1 and 2 in envelope | PASS: 200 application/json; both seed rows, page=1, pageSize=2, total=2 |
| GET /parts in isolated empty database | 200; empty envelope | PASS: 200; {"data":[],"page":1,"pageSize":2,"total":0} |

The empty fixture is pcpartsstore_build1_empty_20261014, with a parts table copied using CREATE TABLE ... LIKE. It has no rows. A temporary ignored database.local.php selected it for one HTTP request; that file was removed in finally, restoring the normal configuration. The populated endpoint was checked again afterward. Normal seed rows were never deleted or changed.

SQL comparison: ID 1 = GeForce RTX 5070, GPU, brand 1, 749.99; ID 2 = Radeon RX 8800 XT, GPU, brand 2, 699.99. Both match list output; ID 1 matches detail output. PDO returns DECIMAL prices as JSON strings.

browse.html: HTTP 200 text/html. Browser verification passed: both seeded rows displayed, View opened ID 1 with matching fields, ID 999999 showed 404 feedback, and the isolated empty database showed No parts found. Normal configuration restored afterward.
PHP syntax checks: passed for the entry point, route files and configuration files.
Source SHA before changes: 869071afd5d039f654714b8d83ef487253b2d0b0.
Final submitted SHA: pending review and commit.
Owners/reviewers: confirm with both teammates.
Swagger Editor 5.9.0: actual openapi.yaml imported on October 14, 2026; all four GET operations and schemas rendered with no reported validation errors.
