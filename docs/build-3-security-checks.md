# Build 3 security checks

Tested November 19, 2026 using Postman, Apache and MariaDB.
Base URL: http://localhost/pcpartsstore
Tested commit: pending commit.

Synthetic accounts:
- Customer: ID 1007
- Admin: ID 1008

| Check | Observed result |
|---|---|
| Brand POST without login | 401 |
| Brand POST as customer | 403 |
| Brand POST as admin | Created successfully |
| Brand DELETE as admin | Removed successfully |
| Logout followed by brand POST | 401 |
| Customer reads own profile | 200 |
| Customer reads another profile | 403 |
| Customer updates another profile | 403 |
| Customer lists all users | 403 |
| Customer attempts to change role | 422 |
| Order POST with another user_id | Order 1011 belongs to logged-in customer 1007 |
| Customer changes order status | 403 |
| Admin write without CSRF token | 403 |
| Admin write with incorrect CSRF token | 403 |
| Admin write with valid CSRF token | Created brand 1006 successfully |
| DELETE with valid CSRF token | Removed brand 1006 successfully |
| Logout without CSRF token | 403 |
| Logout with valid CSRF token | 200 |

These checks cover the listed API cases. Browser-client integration,
session-cookie hardening and remaining security cases still need verification.