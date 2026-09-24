# 007resort-admin-web

Management web application for the **007 Resort & Spa Integrated Facility Operations Platform**.

> Status: **management portal**: dashboard, reports, finance (incl. waiter collections and cash handovers), inventory, staff, devices, sync & IT, approvals, the full Setup area (see docs/SETUP_SCREENS.md) and the **Website** area that edits everything on the public site (see docs/MANAGING_THE_WEBSITE.md). Runs against the real API or, with `R007_MOCK=true`, on built-in fixtures (Setup and collections need the real API).

## Purpose

`007resort-admin-web` is the back-office UI used to run the property: reporting, finance and
reconciliation, inventory oversight, staff management and system configuration. It is a
Laravel **UI / backend-for-frontend (BFF)** over the **007 Resort & Spa API** (ASP.NET Core), which is
the single "brain" of the platform and the **only** owner of the MySQL schema.

## Users and roles

| Role | Typical use |
| --- | --- |
| Owner | Cross-facility reports, financial overview, approvals |
| Manager | Facility/operating-point performance, staff rosters, stock oversight |
| Accounts | Settlements, reconciliation, refunds review, exports |
| IT | Terminals/devices, configuration, integrations, audit logs |
| Supervisor | Shift reports, voids/discount review, operational follow-up |

Roles and permissions are **issued by the API**. This app only renders what the API says the
signed-in staff member may see or do; it never makes authorization decisions on its own.

## Architecture rules

- **PHP never mutates business data directly - it calls the API.** Payments, refunds,
  inventory, tickets, bookings, memberships and order state are changed only via
  `/api/v1/...` using `App\Services\R007Api\R007ApiClient`.
- **No application database.** There are no business migrations or Eloquent models
  (enforced by `tests/Unit/NoBusinessTablesTest.php`). An optional **read-only** reporting
  connection (`R007_REPORTING_DB_*`) may be introduced by ADR.
- **Staff sign in via the API.** The API access token is stored **server-side in the
  session** and sent as a bearer token; it is never exposed to the browser.
- **Idempotency.** Every `POST/PUT/PATCH/DELETE` sends an `Idempotency-Key` header.
- **Errors.** API errors use RFC 7807 problem details and are mapped to
  `R007ApiException`.
- **Money** is received as decimal strings and must never be handled with float arithmetic.
- **Time.** The API stores/returns UTC; this app converts to local time for display only.

### Reporting hierarchy

```
Property -> Facility -> Operating Point -> Terminal -> Staff -> Transaction
```

All reports roll up / drill down along this hierarchy (e.g. Property > Pool Facility >
Pool Bar > POS-03 > cashier > sale).

## Requirements

- PHP 8.4+ with `mbstring`, `intl`, `bcmath`, `curl`, `dom`, `fileinfo`, `openssl`, `xml`, `zip`
- Composer 2
- Node.js 24 + npm (front-end assets)
- A reachable 007 Resort & Spa API instance (on-site server or local dev instance)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
npm ci && npm run build   # or `npm run dev` while developing
```

Set `R007_API_BASE_URL` in `.env` to the API you want to use.

## Run

```bash
php artisan serve          # http://localhost:8000
```

### Against the real API

Set `R007_API_BASE_URL` (and `R007_INSTANCE=local|cloud`) in `.env`, then sign in with a staff
number/username and password. Login, refresh and logout are all `POST /api/v1/auth/staff/*`.

### Mock API mode (no backend needed)

```bash
# .env
R007_MOCK=true
R007_MOCK_SCENARIO=normal   # normal | stale | offline
php artisan serve
```

Sign in as `owner`, `manager`, `marketing`, `accounts`, `it` or `cashier` with password `password`. The fixture backend
(`app/Services/R007Api/Mock`) speaks the contract's shapes, keeps state in the cache (approve an
approval, retry an outbox event, create staff ...) and only answers endpoints the contract defines.
`R007_MOCK_SCENARIO=stale` (with `R007_INSTANCE=cloud`) and `offline` show how the dashboard refuses
to present mirrored figures as live. A `MOCK DATA` badge is always visible. **Never enable in production.**

- `GET /health` - `{"status":"ok","service":"007resort-admin-web"}` (liveness, no auth)

## Design

**Blade + Livewire 4, Tailwind 4, no SPA.** Chosen over Inertia because this is a server-rendered BFF whose
only job is to call an API: pages are plain Blade (fast, tablet-friendly, no client build beyond Tailwind
and Livewire's bundled Alpine), Livewire is used only where a screen must stay live without a reload
(Dashboard, Approvals queue, Sync & IT: `wire:poll`, inline actions), and API tokens never touch JavaScript
because all state lives in the server-side session. Inertia would have added a JS page/router layer and a
second place to duplicate permission logic for no gain here.

| Piece | Where |
| --- | --- |
| API client (bearer from session, Idempotency-Key, refresh-on-401, ETag/If-Match, 202 detection, problem+json) | `app/Services/R007Api/R007ApiClient.php` |
| Session-held staff/permissions, `/auth/me` refresh | `app/Auth/StaffSession.php`, `AuthService.php` |
| MFA hook (cloud, sensitive permissions, fails closed) | `app/Auth/Mfa.php` |
| Permission-driven nav and route gates | `app/Support/Navigation.php`, `permit:` middleware |
| Contract snapshot + graceful degradation | `resources/contract/endpoints.json`, `app/Support/Contract.php`, `Fetch.php` |
| Data-freshness verdicts (live / unverified / stale / offline) | `app/Support/DataFreshness.php` |
| Mock API | `app/Services/R007Api/Mock/` |

### Website (CMS) area, for developers

Staff guide: `docs/MANAGING_THE_WEBSITE.md`. Everything is one API module (`/api/v1/admin/cms/*`); this app keeps no content.

| Piece | Where |
| --- | --- |
| API wrapper (If-Match quoting, multipart upload, CSV, paging) | `app/Services/Cms/CmsApi.php`, `R007ApiClient::upload()` |
| Pages, blog, events, categories: one list + one editor driven by definitions | `app/Support/Cms/Resources.php`, `ContentController`, `resources/views/pages/website/content/*` |
| Settings (8 groups), homepage block types | `SettingsGroups.php`, `HomeBlocks.php`, `SettingsController`, `HomepageController` |
| Shared components: image picker, Markdown editor (toolbar + live preview), drag-reorder list, uploader with progress, datetime (Lagos), SEO snippet, status chips | `resources/views/components/cms/*`, `resources/js/cms/{index,lib}.js` (pure logic unit-tested in `tests/js/cms.test.mjs`) |
| Lagos wall clock in the forms, UTC on the wire | `App\Support\Cms\Cms` |
| Mock CMS (contract shapes + rules, no backend) | `app/Services/R007Api/Mock/MockCms.php`, `MockCmsData.php` |
| Real recorded API JSON for tests | `tests/Fixtures/cms/`, refreshed with `php artisan r007:capture-cms-fixtures --base=... --user=owner1 --password=...` |

Permissions (from the API): `cms.view`, `cms.manage`, `cms.publish`, `cms.media.manage`, `cms.subscribers.view`, `cms.subscribers.export`, `cms.messages.manage`. Optional `R007_SITE_URL` enables "View on site" links. Known API behaviour worked around: `sortOrder: null` is answered with a 500, so blank order fields are simply not sent.

### Rules the portal follows

- **No business database, no business rules.** Every read/write is an API call; the API validates, authorises
  and audits. Hidden buttons are convenience; a 403 from the API is always surfaced. (`NoBusinessTablesTest`,
  `NoBusinessDatabaseTest`.)
- **Permissions come from `/auth/me`**, re-read at login and every `R007_ME_TTL` seconds; navigation and buttons
  follow the permission strings, never role names.
- **202 / `PENDING_APPROVAL` is never shown as success.** It becomes a prominent "Awaiting approval" notice with
  the approval reference, and the request lands in the Approvals queue (`/approvals`), where an approver
  decides through `POST /approvals/{id}/decision`.
- **Stale data is never presented as live.** Reports carry the API's `freshness` block; the dashboard also reads
  `/sync/status` (IT) or `/system/health`. Missing freshness is "unverified", not "live". Figures are tinted and
  labelled "as of last sync" when stale/offline. Site status shows last heartbeat and last successful sync.
- **Money is a decimal string** end to end (bcmath for sums; formatting by string). CSV exports keep amounts
  exact and neutralise spreadsheet formula injection.
- **Endpoints not in the contract are not faked.** A screen either hides the action or shows a "waiting on the
  API" panel. Writes a later contract is expected to add (facility rules, booking strategy A/B/C, MFA verify)
  are built behind `Contract::has()` and appear the moment the snapshot includes them.
- Secrets (registration codes, terminal tokens) are shown once via flash and never stored.

### Screens and the API they use

| Screen | Route | Endpoints (contract v1) |
| --- | --- | --- |
| Dashboard | `/` | `organization/facilities`, `reports/facility-daily-summary`, `orders`, `bookings`, `inventory/items+balances`, `attendance`, `approvals`, `sync/status`, `system/health` |
| Approvals | `/approvals` | `approvals`, `approvals/{id}/decision` |
| Reports | `/reports`, `/reports/facility/{id}`, `/reports/shift/{id}` | `reports/facility-daily-summary`, `reports/revenue`, `reports/cashier-shift/{id}`, `cash-sessions`, `payments`, `devices`, operating points; CSV via `?format=csv` |
| Finance | `/finance/payments`, `/finance/reconciliation` | `payments`, `payments/{id}/refund`, `.../reversal`, `payments/paystack/verify/{ref}` |
| Inventory | `/inventory`, `/inventory/{receive,transfer,adjust,wastage,count}` | `inventory/items|locations|balances`, `purchase-receipts`, `transfers`, `adjustments`, `wastage`, `counts`, `counts/{id}/post` |
| Staff | `/staff`, `/staff/attendance`, `/staff/audit` | `staff`, `roles`, `role-assignments`, `credentials/*`, `attendance`, `attendance/corrections`, `audit`, `devices` |
| Configuration | `/config/*` | `organization/*`, `facilities/{id}/capabilities`, `catalog/*`, `admin/settings/tax`, `memberships/plans`, `bookings/resources`, `kds/stations` |
| Devices | `/devices` | `devices`, `devices/registration-codes`, `devices/{id}/revoke`, `attendance/devices*` |
| Sync & IT | `/sync` | `sync/status`, `sync/outbox*`, `sync/inbox-events*`, `sync/conflicts*`, `system/health` |
| Website | `/website/*` | the CMS admin API (`docs/CMS_API.md` in the API repo): `admin/cms/settings/{group}`, `home-sections`, `pages`, `posts`, `post-categories`, `events`, `gallery/albums` + `items`, `media` (multipart upload), `subscribers` (+ CSV export), `messages`, `summary` |

### Not yet possible (contract gaps, shown as "waiting on the API" in the UI)

Operating-point revenue report; settlement/bank reconciliation and sign-off; inventory movement history and
saved count sheets; product/price/category/facility/operating-point/ticket-type/KDS-routing writes; editing
operating rules incl. payment timing; offline-allocation strategy A/B/C storage; MFA verification endpoint;
sessions list and security-events viewer; tickets-sold report.

Refresh the snapshot after the contract changes:
`php artisan r007:contract-sync ../007resort-docs/api/openapi/v1.yaml`.

## Test

```bash
vendor/bin/pint --test     # code style
php artisan test           # PHPUnit
```

CI (`.github/workflows/ci.yml`) runs Pint, the test suite, a front-end build and a gitleaks
secret scan on every push/PR.

Feature tests use `Http::fake` (helper `fakeApi()` in `tests/TestCase.php`: anything unmapped answers like an
unbuilt route), so no backend is needed. `MockModeSmokeTest` walks every screen on the fixture backend with
real HTTP forbidden. Set `R007_CONTRACT_SPEC=/path/to/v1.yaml` to also check the contract snapshot for drift.

## Configuration

All configuration comes from the environment (see `.env.example` - placeholders only).

| Variable | Default | Description |
| --- | --- | --- |
| `R007_API_BASE_URL` | `http://127.0.0.1:5080` | Base URL of the 007 Resort & Spa API |
| `R007_API_PREFIX` | `/api/v1` | Versioned API path prefix |
| `R007_API_TIMEOUT` | `10` | Request timeout (seconds) |
| `R007_API_CONNECT_TIMEOUT` | `3` | Connect timeout (seconds) |
| `R007_API_CLIENT_ID` | `007resort-admin-web` | Public client identifier registered in the API (not a secret) |
| `R007_DISPLAY_TIMEZONE` | `Africa/Lagos` | Timezone used when rendering dates |
| `R007_INSTANCE` | `local` | `local` (on-site) or `cloud` (remote); cloud enforces MFA when enabled |
| `R007_MFA_ENFORCE` | `false` | Require a second factor for sensitive permissions on the cloud instance (fails closed) |
| `R007_ME_TTL` | `300` | Seconds between `/auth/me` permission refreshes |
| `R007_MOCK` | `false` | Serve fixture data instead of calling the API |
| `R007_MOCK_SCENARIO` | `normal` | `normal`, `stale` or `offline` (mock freshness/site status) |
| `SESSION_DRIVER` | `file` | Session store (holds the API token server-side) |
| `CACHE_STORE` | `file` | Cache store |
| `QUEUE_CONNECTION` | `sync` | Queue driver |

See `config/r007.php`.

## Deployment

The same build is deployed in two places:

- **On-site:** served from the Windows local application server alongside the on-site
  007 Resort & Spa API, reachable only from the STAFF/management network. Keeps working during an
  internet outage because it talks to the local API.
- **Cloud (remote admin):** a cloud instance pointed at the cloud-mode 007 Resort & Spa API for
  owners/managers working off-site. The on-site server is never exposed to the internet;
  data reaches the cloud through outbound sync from the site.

Environment templates and runbooks live in
[prinzderick/007resort-infrastructure](https://github.com/prinzderick/007resort-infrastructure).

## Related

- Architecture, ADRs and domain docs: [prinzderick/007resort-docs](https://github.com/prinzderick/007resort-docs)
- API: [prinzderick/007resort-api](https://github.com/prinzderick/007resort-api)
- Contributing: [CONTRIBUTING.md](CONTRIBUTING.md)
