# DoubleBolt — PC Parts Store

Team: Aaron Hossain and Daiyan Abrar Samin.

Build 1 demonstrates database-backed part lists and individual details through a basic web page. Accounts, carts, checkout, inventory administration and authorization are future work.

## Stack and structure
PHP, Slim 4, PDO, MariaDB/MySQL, Composer, HTML and JavaScript.
- index.php: API entry point; base path /pcpartsstore.
- src/routes/: handlers and database queries.
- src/config/: connection and configuration example.
- database/pcpartsstore_db.sql: schema, relationships and synthetic seeds.
- browse.html: list/detail page.
- docs/openapi.yaml: API contract.

## Wampoon setup
1. Place this folder at D:\Wampoon\htdocs\pcpartsstore.
2. Start Apache and MariaDB using WampoonControl.
3. In a Wampoon terminal, change to D:\Wampoon\htdocs\pcpartsstore\backend and run D:\Wampoon\composer.bat install.
4. Create a database named pcpartsstore_db in phpMyAdmin, select it, and import database/pcpartsstore_db.sql into that fresh database.
5. If credentials differ, copy src/config/database.example.php to src/config/database.local.php and edit that local file. It is ignored by Git. Environment variables DB_HOST, DB_PORT, DB_NAME, DB_USER and DB_PASSWORD are also supported.
6. Open http://localhost/pcpartsstore/browse.html. Include the Apache port if Wampoon uses another port.
7. API examples: http://localhost/pcpartsstore/parts and http://localhost/pcpartsstore/parts/1.

Apache needs mod_rewrite and AllowOverride for .htaccess.
Use the root API entry point. The existing backend/index.php is a separate local scaffold.

## Endpoint status
| Resource | Operations | Status |
|---|---|---|
| Parts | GET /parts, GET /parts/{id} | Implemented; four HTTP checks passed |
| Brands | GET /brands, GET /brands/{id} | Implemented; additional reads |
| Parts and brands | POST collection, PATCH item, DELETE item | Existing handlers; full validation deferred |
| Users and orders | GET collection/item, POST collection, PATCH item, DELETE item | Implemented and HTTP tested |
| Cart, checkout, inventory, authentication | Workflows | Planned |

Parts return an envelope with data, page, pageSize and total (two rows per page). A part has id, name, category, brand_id and price. PDO represents DECIMAL prices as JSON strings.

## Build 1 demonstration
1. Open browse.html and inspect the 200 JSON list with both seeded GPU rows.
2. Select View for ID 1. Compare its ID, name and price against the SQL and list.
3. Enter ID 999999. Expect 404 with PC part not found and visible feedback.
4. For an empty list, use a separate database with the same schema but no seed rows. Temporarily point database.local.php to it and reload. Expect 200 with data=[], page=1, pageSize=2, total=0 and No parts found. Restore the original configuration. Do not delete the normal seed rows.
5. Record all four actual HTTP results in docs/build-1-checks.md.
6. Validate docs/openapi.yaml in Swagger Editor.

## Relational model
brands 1 → many parts; users 1 → many orders; orders 1 → many order_items; parts 1 → many order_items. All tables have primary keys, relationships use foreign keys, and user emails are unique.

## Handoff
Record the final commit SHA, task owners/reviewers, completed PRs and actual checks. Confirm the Project board matches the PHP stack.
Build 2: CRUD validation; search/filter/sort/navigation; cart and orders; structured errors and external-service composition.
Build 3: customer/admin authentication and authorization; inventory/order integrity; failure handling and final QA.


## Users and orders
Users expose first_name, last_name and email. POST requires all three; PATCH accepts one or more of those fields. Duplicate emails return 409. Orders expose user_id and status; POST requires an existing user ID and defaults to Processing. PATCH changes status to Processing, Shipped, Delivered or Cancelled. Deleting a user with orders or an order with items returns 409. These APIs use the existing schema and do not implement login or checkout.

Run the temporary-row HTTP checks in PowerShell 7: ./tests/users-orders.ps1. Results are recorded in docs/users-orders-test-results.json. Tests remove their temporary rows in finally and compare the original data afterward.
