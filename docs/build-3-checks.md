# Build 3 Checks — DoubleBolt

Tested locally using Wampoon, Postman, a browser, phpMyAdmin and PowerShell.
Agreed API format: JSON.
Final commit: pending.

## API validation

Passed:

- Unsupported response format → 406.
- Unsupported request format → 415.
- Malformed JSON → 400.
- Invalid email → 422 with field details.
- Duplicate email → 409; original database row unchanged.

## Security

Passed:

- Protected requests require login.
- Customers cannot perform admin-only writes.
- Customers cannot update another user's profile or list all users.
- Customers cannot change their role or order status.
- Missing or incorrect CSRF tokens are rejected.
- Valid login, session restoration and logout work.

See build-3-security-checks.md for earlier security results.

## Database and checkout

Passed:

- Deleting a referenced brand returns 409 and preserves its parts.
- Checkout saves the order, items and server purchase prices together.
- A missing part rolls back the checkout.
- Retrying a corrected failed checkout succeeds.
- Repeating a successful checkout returns the same order.
- Reusing its key with different items returns 409.

## Concurrent checkout

Two separate sessions submitted the same checkout key.

- One response: 201, replayed=false.
- Other response: 200, replayed=true.
- Both returned order 1013.
- Database contained one order and one item.
- Item: part 1, quantity 1, saved price 749.99 CAD.

Result: PASS.

## Currency conversion

Passed:

- Existing part converts successfully.
- Unsupported currency returns 400.
- Missing part returns 404.
- Connection failure, malformed data, negative rate, HTTP 429 and timeout
  return a safe 502.
- Restoring the real service allows conversion again.

Failure cases used temporary local fixtures.

## Frontend

Passed:

- Catalog, search, filters, sorting and product details.
- Registration, login, logout and session restoration.
- Profile editing.
- Orders list and itemized order-details popup.
- Currency conversion.
- Add to cart, quantity changes and checkout.
- Successful checkout clears the cart and appears in My Orders.

Visual polish is deferred.

Database lock-timeout/deadlock responses are implemented, but deliberate
database lock-timeout/deadlock injection has not been tested.