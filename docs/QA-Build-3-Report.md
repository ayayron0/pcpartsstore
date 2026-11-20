# DoubleBolt Build 3 QA Report

## 1. Execution identity

| Field | Recorded value |
|---|---|
| Project | DoubleBolt PC Parts Store API |
| Build | Build 3: authentication/security and QA readiness |
| API contract | OpenAPI API version 1.1.0 |
| Assessed commit/tag | Not recorded in this report |
| Tester | Project team |
| Test date | 2026-11-19 (report preparation date; individual execution timestamps were not recorded) |
| Environment | Local Windows/Wampoon with Apache and MariaDB; exact versions were not recorded |
| Base URL | `http://localhost/pcpartsstore` |
| Data | Synthetic/local database records |
| Tools | Postman, browser, and phpMyAdmin/MariaDB; exact versions were not recorded |

## 2. Focused test cases

These cases cover DoubleBolt’s key risks: retrieving correct data, handling missing and empty results, and enforcing access controls. Use synthetic data only. Do not record passwords, session cookies, or CSRF tokens.

“Pass (reported)” means the tester said the case was tested and looked good. The supporting response, database evidence, and assessed commit were not supplied, so these are not independently verified passes.

| Case | Requirement / expected observation | Actual observation and evidence | Status |
|---|---|---|---|
| T01 | Existing part: `GET /parts/{known_part_id}` returns 200; ID, name, category, brand ID, and price match the synthetic database row. | Tester reports the case was tested and looked good; exact response and saved evidence were not supplied. | Pass (reported) |
| T02 | Missing part: `GET /parts/{absent_part_id}` returns 404 with the documented JSON error; database remains unchanged. | Tester reports the case was tested and looked good; exact response and saved evidence were not supplied. | Pass (reported) |
| T03 | Populated collection: `GET /parts?page=1&limit=5&sort=id&direction=asc` returns 200 with `metadata` and array-valued `data`; rows and metadata match the database. | Tester reports the case was tested and looked good; exact response and saved evidence were not supplied. | Pass (reported) |
| T04 | Empty collection selection: `GET /parts?search=<term_with_no_match>` returns 200 with `data: []` and zero matching items in metadata. | Tester reports the case was tested and looked good; exact response and saved evidence were not supplied. | Pass (reported) |
| T05 | Invalid query: `GET /parts?sort=not_allowed` returns 400 with a safe JSON error and no SQL details. | Tester reports the case was tested and looked good; exact response and saved evidence were not supplied. | Pass (reported) |
| T06 | Missing authentication: remove the session cookie, then request `GET /orders`; expect 401 and no order data. | Tester reports the case was tested and looked good; an earlier observation was 401. Response export and run details were not supplied. | Pass (reported) |
| T07 | Object permission: as a customer, request `GET /users/{other_customer_id}`; expect 403 under the ownership policy and no disclosure. | Tester reports the case was tested and looked good; exact response and saved evidence were not supplied. | Pass (reported) |
| T08 | Role/CSRF and database state: a customer attempts an admin-only write; an admin attempts without CSRF; then the admin retries with valid CSRF. Expect 403 and no mutation for the first two, then success and a matching database row. | Tester reports the case was tested and looked good; exact response and saved evidence were not supplied. | Pass (reported) |

## 3. Evidence and defect log

The tester reports that the cases were exercised and looked good. No request/response exports, screenshots, database observations, or individual execution timestamps were supplied. Save those when available; redact secrets and personal data.

| Defect | Case | Impact / severity | Priority | Owner | Correction commit | Retest / regression evidence |
|---|---|---|---|---|---|---|
| No defect has been formally logged in this report. | — | — | — | — | — | — |

## 4. Known limitations and scope gaps

| Item | Current status and risk |
|---|---|
| Order DELETE authorization | The previously shared `OrdersController` DELETE method showed session/CSRF routing but no ownership/admin check in the method. Verify the current controller enforces the intended policy before claiming object-level authorization is complete. |
| API representations | The middleware and OpenAPI contract are JSON-only. XML/YAML remain out of scope only if the team did not agree to support them. |
| QA evidence and assessed commit | The tester reports all eight cases looked good, but response exports, database observations, execution timestamps, and the assessed commit SHA were not supplied. The results are reported, not independently reproducible from this report yet. |

## 5. QA conclusion and release decision

**QA decision: Evidence incomplete; no independently verified pass claim is made.** The tester reports eight cases passed, but supporting evidence and the assessed commit were not recorded. The earlier 401 observation for unauthenticated `GET /orders` is included as a reported result.

Before presenting a verified QA result, record the actual responses, verify database state for writes, identify the assessed commit, and review Order DELETE authorization. Record any remaining not-run or blocked work and any risk accepted by the team.

## 6. Reproduction handoff

Teammate review completed. Submitted commit SHA: not provided in the QA details shared here.

The project is expected to run locally at `http://localhost/pcpartsstore` under Wampoon/Apache with MariaDB. Exact runtime versions, seed/import commands, and Postman collection/environment details were not provided, so a teammate will still need those repository-specific instructions to reproduce the cases. Coordinate completion with the teammate responsible for release instructions.
