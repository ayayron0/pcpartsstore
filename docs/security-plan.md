# Security Plan

Authentication and authorization will be implemented in Build 3.
The rules below are planned and are not enforced yet.

## Roles

- Visitor: view parts and brands.
- Customer: view and update their own profile and view/create their own orders.
- Admin: manage parts, brands, users and order statuses.

## Access and ownership

- Parts and brands GET routes will be public.
- Parts and brands write operations will require an admin.
- User registration will be public.
- Listing or deleting users will require an admin.
- Customers will only access their own profile and orders.
- Customers cannot change shipping statuses or delete orders.
- The server will use the session's user ID instead of trusting a submitted user_id.

## Login

- Use PHP sessions for login and logout.
- Store password hashes, never plain-text passwords.
- Public registration will create customer accounts only.
- Return 401 when login is required and 403 when permission is denied.
- Protect state-changing requests against CSRF.
- Use secure session-cookie settings and restrict CORS for deployment.

## Logging

Request logs contain the request ID, method, path, status and duration.
Do not log passwords, cookies or request bodies.

## Build 3 checks

Test login/logout, admin restrictions and customer ownership.
Confirm one customer cannot access another customer's records.