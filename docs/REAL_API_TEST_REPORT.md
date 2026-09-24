# Admin portal vs the REAL API: test report

Run against the local node code (`007resort-api`, `integration/mvp` + `fix/admin-real-api`), DB `r007_local`, portal on `:8071`
(`R007_MOCK=false`). Logged in through the real sign-in form as `owner1`, `manager1`, `cashier1`, `storekeeper1`.

## Screens verified (live, real data)

Dashboard, approvals (approve, reject, decided history), global search, orders (+detail), tables, bookings, tickets, memberships,
reports (property, facility, shift, CSV), payments (+detail, refund, reversal, filters, CSV), refunds & reversals, cash sessions,
settlements, stock, movements, adjustments, transfers, counts (create, post), suppliers, receive/transfer/adjust/wastage forms,
staff (create, edit with If-Match, password/PIN/NFC card, role grant/revoke, attendance, correction request/approve/reject),
roles & permissions, devices (registration code, register/rotate/disable terminal, revoke), setup hub, facilities (list, add wizard,
7 tabs), catalog (category, product, price, availability), booking resources (Booking Authority strategy), ticket types, membership
plans (create/edit), kitchen & bar routing, payment rules, business & receipts (VAT), sync & IT (outbox, inbox, conflicts:
resolve, replay), audit log. Roles checked: owner, manager, cashier (no admin navigation), storekeeper.

Screenshots: `docs/screenshots/w1440`, `w1280`, `w820` (one PNG per screen, same names), regenerated with
`node scripts/screenshots.mjs --user owner1 --password ... --out docs/screenshots/w1440 --width 1440`.

## Bugs found and fixed in the portal (real shapes are the source of truth)

| Screen | Was | Now |
| --- | --- | --- |
| Devices | crash `Undefined array key "serial"`; facility column blank | `serialNumber`, `name`, `homeFacility`, `mode`; terminal create sends `serialNumber` + `name` |
| Sync & IT | crash `Undefined array key "id"` | outbox/inbox use `eventId`, conflicts `detectedAt`/`resolutionNote`; resolve needs a note (API 422 otherwise); replay result is `{outboxRequeued, inbox:{reprocessed,applied}}`; only FAILED outbox events are retryable |
| Approvals | "Decided" tab always empty | history asks `filter[status]=APPROVED,REJECTED,...` (API defaults to PENDING) |
| Audit | crash on CSV (`hash`), no actor, date filters silently ignored | `rowHash`, `oldValue`/`newValue`, actor names resolved, newest first (see API changes) |
| Attendance | corrections showed all statuses, wrong time keys | `?status=PENDING`, `requestedClockIn/Out`; request-correction form added |
| Reports | period revenue used a nonexistent `data` wrapper | flat shape: `revenue`, `byFacility`, `byPaymentMethod`, `byOperatingPoint` |
| Payments/finance | empty for owners (unfiltered list = own takings only), date filter ignored | see API changes; dates converted Lagos day -> UTC instant |
| Catalog | error: `facilityId` required by the API | products/availability read per facility; writes wired (category, product, price) |
| Memberships plans | create failed (`code` is required) | code + all plan fields |
| Booking rules | wrong field names/values | `authority.offlineStrategy = A_OFFLINE_ALLOCATION | B_ONLINE_AUTHORITY_REQUIRED | C_DISABLE_ONLINE`, `localReserveUnits`, `onlineStaleAfterSeconds` |
| Inventory | count sheet kept in session (no GET) | real `GET /inventory/counts[/id]`, movements, adjustments, suppliers |
| Product edit | would have erased prep route / tax rate | blank = unchanged |
| Everywhere | undefined-index crashes possible | defensive accessors; `RealApiRenderTest` renders every page (owner + all 12 roles) against recorded real JSON |

## API changes needed (PR into `integration/mvp`, branch `fix/admin-real-api`)

1. `GET /payments` and `GET /cash-sessions` without a facility filter returned only the caller's own rows even for owner/accountant.
   Now a site/organization-wide `payment.view` / `cash_session.view` returns the whole property (facility-scoped holders unchanged).
2. `GET /payments` ignored the documented `filter[from]`/`filter[to]`: now honoured (`created_at`, bare date `to` = whole day).
3. `GET /audit` ignored documented `filter[from]`/`filter[to]`, and could not be read newest-first: both added (`order=desc`).
4. `docs/openapi/v1.yaml` lists six paths twice (`/catalog/categories`, `/catalog/products`, `/bookings/resources`, `/entitlements`,
   `/inventory/items`, `/inventory/locations`, `/inventory/adjustments`, `/inventory/counts`), which strict YAML parsers reject.
   Not changed; the portal's `r007:contract-sync` now tolerates it. The spec generator should merge those blocks.

## Other API deviations / things to know

* `GET /catalog/products` and `GET /tables` require `facilityId`; `GET /reports/revenue` without a facility needs `report.view.all`.
* Lists cap at 200 rows; `GET /inventory/balances` has more than 200 lines (cursor is `item:location`).
* `GET /entitlements` and `GET /memberships` return the raw `qrToken` (a credential): the portal never renders it and fixtures redact it.
* Endpoint documented but missing: facility rule/capability writes, ticket types (`ticket_type` table exists, no endpoint), receipt
  settings, role writes, operating point/table writes, business profile edit. These screens are ready and gated on the contract.
* Approvals: `EXPIRED` is only presentational (DB status stays PENDING).
* An unknown error redirects `back()`; the portal shows the API's title/detail and maps 422 `errors` onto the form fields.

## What is missing

* The facility management API (`feature/api-config-admin`) is not on the shared node: Add-facility wizard, capability toggles and the
  rule form are built and tested against hand-written contract fixtures (`tests/Fixtures/contract`) and light up when the endpoints
  and the refreshed contract snapshot land.
* `/admin/search` and `/admin/setup-status` (search falls back to pages/facilities/staff/roles/devices; setup progress is derived).
* Form controls come from the separate `feature/admin-form-controls` branch; until merged the portal uses simple wrappers.
* The mock backend (`R007_MOCK=true`) still serves only the original screens; new screens degrade to "not available".
