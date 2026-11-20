# DoubleBolt — PC Parts Store

Web Services course project by Aaron Hossain and Daiyan Abrar Samin.

DoubleBolt is a PC component storefront with a dark yellow theme, a JSON API,
customer accounts, a shopping cart and an admin dashboard.

## Stack

PHP, Slim 4, Slim PSR-7, PDO, MariaDB/MySQL, Composer, HTML, CSS and JavaScript.
The frontend uses plain JavaScript; no frontend framework or build step is required.

## Run locally with Wampoon

1. Place the project in `D:\Wampoon\htdocs\pcpartsstore`.
2. Start Apache and MariaDB. Enable Apache `mod_rewrite` and `.htaccess` support.
3. Open a terminal in the `backend` folder and run:

   ```bat
   D:\Wampoon\composer.bat install
   ```

4. Create a database named `pcpartsstore_db` in phpMyAdmin, select it and import
   `database/pcpartsstore_db.sql`.
5. If your connection settings differ, copy `src/config/database.example.php` to
   `src/config/database.local.php` and update its values. The local file is ignored by Git.
6. Open <http://localhost/pcpartsstore/store/index.html>.

**Importing the seed resets the five project tables and deletes their existing
users, orders and products.** Back up any data you want to keep before importing.

The default database connection is `127.0.0.1:3306`, database `pcpartsstore_db`,
username `root`, with an empty password. PHP needs PDO MySQL and cURL enabled.
Adjust the URLs and API base path if you use a different project folder or port.

## Demo accounts

These accounts are included in the clean seed and are for local course demonstrations.

| Role | Email | Password |
| --- | --- | --- |
| Customer | customer1@example.com | Customer1!2026 |
| Admin | admin@example.com | Admin!2026 |

Passwords are stored as bcrypt hashes. The seed includes 48 products across GPU,
CPU, Motherboard, RAM, Storage, PC Case, PSU and Cooling categories, with seven
brands and local product images. Orders start empty and IDs start at 1.
Prices are sample CAD values, not live retail offers. Image sources are listed in
`store/images/products/SOURCES.md`.

## Pages

| Page | Local URL |
| --- | --- |
| Storefront and sign-in | <http://localhost/pcpartsstore/store/index.html> |
| Registration | <http://localhost/pcpartsstore/store/register.html> |
| Account | <http://localhost/pcpartsstore/store/account.html> |
| Cart and checkout | <http://localhost/pcpartsstore/store/cart.html> |
| My orders | <http://localhost/pcpartsstore/store/orders.html> |
| Admin dashboard | <http://localhost/pcpartsstore/admin/admin.html> |

The storefront supports search, category and brand filters, sorting, pagination,
product details and currency conversion. Customers can edit their profile, place
orders and view their itemized order details.

## API and Build 3 behavior

API base: `http://localhost/pcpartsstore`.
The agreed representation is JSON. Send `Accept: application/json` and, for JSON
request bodies, `Content-Type: application/json`.

- Resources: `/brands`, `/parts`, `/users` and `/orders`.
- Authentication: `/auth/login`, `/auth/me` and `/auth/logout`.
- Brand and part reads are public; writes require admin permission.
- User and order access is restricted by session, role and ownership checks.
- Protected writes require the session's `X-CSRF-Token`; browser clients also send
  the session cookie.
- Checkout accepts `checkout_key` and `items` containing `part_id` and `quantity`.
  It uses the signed-in customer and database prices, saving the order and items
  in one transaction. Retrying an identical key returns the existing order;
  changing its items returns a conflict.
- `/parts/{id}/converted-price?currency=USD` uses Frankfurter exchange rates.
  USD, EUR and GBP are supported; upstream failures return a safe 502 response.

See `docs/openapi.yaml` for request schemas, query parameters and responses.

## Project structure

- `index.php`: API entry point and middleware setup.
- `backend/composer.json` and `backend/composer.lock`: PHP dependencies.
- `src/controllers/`, `src/models/`, `src/routes/`: API implementation.
- `src/middlewares/`: authentication, authorization, CSRF and request handling.
- `src/services/`: exchange-rate integration.
- `src/config/`: database connection settings and example configuration.
- `database/pcpartsstore_db.sql`: complete schema and clean seed.
- `store/`: customer frontend and product images.
- `admin/`: admin dashboard.
- `client/` and `browse.html`: earlier course client pages.
- `docs/`: API contract and build verification records.

Commit the SQL seed, product images, source files and Composer lockfile.
`backend/vendor/` is ignored and is restored by Composer.

## Verification and remaining work

Local test results are recorded in `docs/build-3-checks.md` and
`docs/build-3-security-checks.md`. Earlier checkout evidence uses IDs and prices
from before the clean database reset; those values do not describe the current seed.
The QA report also contains earlier findings that need reconciliation with the
current implementation.

The uploaded project passed syntax checks for 23 application PHP files and 11
JavaScript files. All 48 seeded image paths exist and the image files decode.
These checks do not replace a full browser and API regression against Wampoon.

Before final submission:

- Update the older `client/` management pages to send CSRF tokens and use the
  current checkout request format.
- Run the final regression against the clean seed and record the assessed commit.
- Update the QA report and capture any remaining required evidence.
- Complete any deferred visual polish. Deliberate database lock-timeout/deadlock
  injection has not been tested.

Final submitted commit SHA: pending.
