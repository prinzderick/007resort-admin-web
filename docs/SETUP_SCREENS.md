# Setup screens and the API they use

The Setup hub (`/setup`) lists every setting in one place, with a "Find a setting" box and the setup-progress card from `GET /admin/setup-status`.
All writes go to the API (validated, audited, synced there); this portal only shapes forms into requests. Every write to a versioned record sends `If-Match` with the version the form was built from, so a second administrator's change is reported instead of overwritten.

| Screen | Where | Endpoints |
| --- | --- | --- |
| Facilities: list, add wizard from templates | `/setup/facilities`, `/setup/facilities/new` | `GET /organization/facilities`, `facility-templates`, `capability-types`, `POST /organization/facilities` |
| Facility: details, hours, move, deactivate | `?tab=general` | `PATCH /organization/facilities/{id}`, `POST .../move`, `.../deactivate`, `.../reactivate` |
| Facility: capabilities (dependencies, in-use blockers) | `?tab=capabilities` | `GET/PUT /facilities/{id}/capabilities` |
| Facility: operating rules incl. waiter collection and booking | `?tab=rules` | `GET /organization/rule-definitions`, `GET/PUT /facilities/{id}/operating-rules` |
| Facility: operating points, kitchen/bar screens, tables (bulk) | `?tab=points` | `/organization/operating-points`, `/organization/tables`, `.../tables/bulk`, merge / unmerge |
| Devices: home facility, mode, operating point | `/devices`, facility `?tab=devices` | `GET /devices`, `PATCH /devices/{id}` |
| Card machines (payment terminals) | `/devices/payment-terminals` | `GET/POST/PATCH /payment-terminals` |
| Catalog: products, categories, prices, tax rates | `/setup/catalog` | `/admin/catalog/products`, `/catalog/*`, `PUT .../facilities/{id}`, `POST /catalog/prices`, `PUT .../stock-links` |
| Catalog: CSV import (dry run, then apply) and export | `/setup/catalog?tab=import` | `POST /catalog/{products,prices}/import?dryRun=`, `GET .../export` |
| Kitchen and bar routing | `/setup/kds` | `/catalog/prep-routes`, `/catalog/prep-route-stations` |
| Ticket types, membership plans | `/setup/tickets`, `/setup/memberships` | `/ticketing/ticket-types`, `/memberships/plans` |
| Booking resources: strategy A/B/C, schedule, blackouts, rule overrides | `/setup/bookings/{id}` | `/bookings/resources/{id}` (+ `/schedule`, `/rules`, `/blackouts`) |
| Payment methods per facility | `/setup/payments` | `GET/PUT /facilities/{id}/payment-methods` |
| Business profile, receipt, VAT | `/setup/business` | `/admin/settings/{business,receipt,tax}` |
| Roles and permissions (custom roles) | `/people/roles` | `/roles`, `GET/PUT /roles/{id}/permissions` |
| Collected by waiters (confirm / reject) | `/finance/collections` | `GET /payments?status=PENDING_CONFIRMATION`, `POST /payments/{id}/confirm|reject` |
| Cash handovers, cash in hand | `/finance/handovers` | `/cash-handovers`, `/cash-in-hand?facilityId=`, `POST .../receive|signoff` |
| Waiter cash policy | staff page | `GET/PATCH /staff/{id}/collection-policy`, `GET /staff/{id}/cash-in-hand` |
| Global search | header box, `/search` | `GET /admin/search` |
| Change history | `/system/audit` (and the link on every settings screen) | `GET /audit` (`entityType`, `entityId`, `entityTypes`) |

## Refusals are explained in words

The API's `detail` is shown as written. Blockers (`facility_in_use`, `capability_in_use`, `operating_point_in_use`) are listed one per line under a short headline; 422 `errors` land on the field; a stale version (412) offers "Reload the latest version".

## Testing

`php artisan r007:capture-fixtures --base=http://127.0.0.1:8080 --user=owner1 --password=...` records real responses into `tests/Fixtures/real` (secrets redacted; `--out=` to write elsewhere). The feature tests (`SetupConfigTest`, `CatalogSetupTest`, `CollectionsAdminTest`, `PeopleSetupTest`, `RealApiRenderTest`) render every screen against those recordings, as the owner and as every seeded role, and assert what each save sends. `tests/Fixtures/synthetic` holds the one shape the demo data cannot produce (a configuration sync conflict).
`node scripts/screenshots.mjs --base ... --user ... --password ... --out docs/screenshots/w1440 --width 1440` writes one PNG per screen and flags sideways scroll, clipped text, failed reads and crashes.

## Screenshots

`docs/screenshots/w1440`, `w1280`, `w820`: every screen after the Setup and polish pass (PNG). `docs/screenshots/before/w1440|w1280|w820`: the same screens before it (WebP, to keep the repository small); the 1440 set was recorded against the real API, the others are the previous agent's set.
