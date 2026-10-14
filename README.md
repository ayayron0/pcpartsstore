# DoubleBolt — PC Parts Store

Team: Aaron Hossain and Daiyan Abrar Samin.

## Stack and structure
PHP, Slim 4, PDO, MariaDB/MySQL, Composer, HTML and JavaScript.
- index.php: API entry point; base path /pcpartsstore.
- src/routes/: handlers and database queries.
- src/config/: connection and configuration example.
- database/pcpartsstore_db.sql: schema, relationships and synthetic seeds.
- browse.html: list/detail page.
- docs/openapi.yaml: API contract.

## Run with Wampoon

1. Put the project in `D:\Wampoon\htdocs\pcpartsstore`.
2. Start Apache and MariaDB.
3. From the `backend` folder, run `D:\Wampoon\composer.bat install`.
4. Create `pcpartsstore_db` in phpMyAdmin and import `database/pcpartsstore_db.sql`.
5. If needed, copy `src/config/database.example.php` to `database.local.php` in the same folder and adjust credentials.
6. Open http://localhost/pcpartsstore/browse.html.

Apache needs `mod_rewrite` and `.htaccess` support. Adjust the URL if using another port.

## Build 1

The page displays database-backed parts and individual details, including missing-item and empty-list feedback.

API base: `/pcpartsstore`. Parts, brands, users and orders support collection/item GET, collection POST, and item PATCH/DELETE. Parts/brands write validation needs further work.

The schema contains `brands`, `parts`, `users`, `orders` and `order_items`, with primary/foreign keys and synthetic seeds. Brands link to parts; users to orders; order items link orders and parts.

## Verification

Passed: item 200, missing item 404, populated list 200 and isolated empty list 200. Page behavior and seed values were verified. OpenAPI was validated in Swagger Editor.

- API design: `docs/openapi.yaml`
- Results: `docs/build-1-checks.md`
- Saved requests: `docs/build-1-requests.http`
- Empty fixture: `database/build-1-empty-fixture.sql`

For the empty check, select the fixture database in local configuration, then restore normal configuration afterward.

## Handoff

Teammate review completed. 
Submitted commit SHA:
