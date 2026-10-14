# DoubleBolt — Build 1 manifest

Repository: https://github.com/ayayron0/pcpartsstore
Project board: https://github.com/users/ayayron0/projects/2 (private; teacher access must be confirmed).
Course scope: https://511va.pages.dev/project/#builds

## Completed and verified
- PHP/Slim scaffold and PDO connection to MariaDB.
- Relational SQL schema, foreign keys and synthetic seed rows.
- GET /parts and GET /parts/{id}, including missing-item 404 and empty-list 200.
- Basic browse.html list/detail page with readable error and empty feedback.
- OpenAPI GET contracts imported in Swagger Editor without reported validation errors.
- Installation/run instructions, secret-free config example and saved HTTP requests.
- Four actual checks and matching database evidence in build-1-checks.md.

## Contributors and review
Daiyan Abrar Samin authored the existing database and route implementation; source history includes PRs #12 and #13. Aaron Hossain's working copy includes the Build 1 page, API documentation, configuration/handoff improvements and pagination binding fix. Confirm task ownership and complete teammate review before claiming approval. Proposed board corrections are in build-1-board-updates.md.

## Snapshot and submission
Starting SHA: 869071afd5d039f654714b8d83ef487253b2d0b0.
Final build SHA: obtain with git rev-parse HEAD after committing the reviewed files. Put that exact SHA in the course submission; a commit cannot contain its own resulting SHA.
Push the reviewed snapshot, confirm teacher repository/board access, then submit through the channel specified in the announcement/LEA. A Git push does not by itself prove course submission.

## Demo order
1. Show repository, exact commit, board owners/reviewers and this manifest.
2. Show SQL tables, foreign keys and seed rows.
3. Show OpenAPI collection envelope and individual object with 200/404 examples.
4. Open http://localhost/pcpartsstore/browse.html. Inspect list request and select View for ID 1.
5. Compare name, ID and price against SQL, list and detail responses.
6. Enter 999999 and show visible 404 feedback.
7. Run database/build-1-empty-fixture.sql if needed. Copy database.example.php to ignored database.local.php; set database to pcpartsstore_build1_empty_20261014. Reload to show empty-list 200 and No parts found. Remove only that temporary local config afterward (or restore your original config), reload, and show seed rows again.
8. Show build-1-checks.md and saved requests; identify Build 2/3 tasks.

## Partial and deferred
Existing POST/PATCH/DELETE handlers need full validation and broader verification. Users/orders now have matching Slim/PDO CRUD handlers; temporary-row HTTP checks passed. This is additional scope beyond the required Build 1 reads. Accounts, cart, checkout, inventory roles, security, multiple response formats and external-service composition remain later-build work. See the named task list in build-1-board-updates.md.
