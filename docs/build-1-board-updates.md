# Build 1 board updates to apply

Board: https://github.com/users/ayayron0/projects/2
This is a proposed correction list based on the supplied board screenshot and repository inspection. No GitHub board items were changed automatically.

## Existing cards
| Issue | Proposed title | Proposed status | Reason |
|---|---|---|---|
| #3 | Initialize PHP/Slim project scaffold | Done | Actual stack is PHP/Slim, not Spring Boot. |
| #4 | Configure MariaDB/PDO database connection | Done | Local connection and retrieval verified. |
| #5 | Implement parts schema and database-backed reads | Done | parts table, list and item handlers verified. |
| #8 | Implement brands schema and database-backed reads | Done after teammate review | Table and handlers exist; verify brand requests before closing. |
| #9 | Implement GET routes for parts and brands | Done after teammate review | Parts GETs verified; write handlers exist but broader validation is deferred. |
| #6 | Implement users schema and API | Done after teammate review | User CRUD is implemented and tested; authentication remains Build 3. |
| #7 | Implement orders schema and API | Done after teammate review | Order record CRUD is implemented and tested; checkout/order-item workflows remain Build 2. |
| #10 | Implement user and order routes | Done after teammate review | Slim/PDO route handlers now exist; HTTP checks passed. |

The user/order routes use the same structure as the existing parts/brands handlers. Complete teammate review before closing the cards.

## Add Build 1 cards
- Basic DoubleBolt list/detail page — implemented and browser verified.
- Align and validate OpenAPI GET contracts — implemented and imported in Swagger Editor.
- Document Wampoon setup, schema relationships and local config — implemented.
- Record four GET checks and SQL comparison — passed; see build-1-checks.md.
- Review and publish Build 1 snapshot — pending final review, commit/push and course submission.

Assign Aaron to the page, documentation and handoff; assign Daiyan to the existing schema/backend work only after both teammates confirm. Give each card the other teammate as reviewer; do not mark peer review completed until it actually happens.

## Add later-build cards
Build 2: full CRUD validation; search/filter/sort/pagination navigation; cart and checkout/order workflows; JSON/XML/YAML representations; structured errors/logging; external-service composition and failure handling.
Build 3: customer/admin authentication and authorization; inventory/order integrity; comprehensive failure/security checks; final QA report and deployment readiness.

Ensure the teacher can access the private board using the course's access instructions. Keep repository and Project board links in the submission.
