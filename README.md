# otueke-admin-web

Management web application for the **Otueke Integrated Facility Operations Platform**.

> Status: **Phase 0 - scaffolding only.** No business features yet.

## Purpose

`otueke-admin-web` is the back-office UI used to run the property: reporting, finance and
reconciliation, inventory oversight, staff management and system configuration. It is a
Laravel **UI / backend-for-frontend (BFF)** over the **Otueke API** (ASP.NET Core), which is
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
  `/api/v1/...` using `App\Services\OtuekeApi\OtuekeApiClient`.
- **No application database.** There are no business migrations or Eloquent models
  (enforced by `tests/Unit/NoBusinessTablesTest.php`). An optional **read-only** reporting
  connection (`OTUEKE_REPORTING_DB_*`) may be introduced by ADR.
- **Staff sign in via the API.** The API access token is stored **server-side in the
  session** and sent as a bearer token; it is never exposed to the browser.
- **Idempotency.** Every `POST/PUT/PATCH/DELETE` sends an `Idempotency-Key` header.
- **Errors.** API errors use RFC 7807 problem details and are mapped to
  `OtuekeApiException`.
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
- A reachable Otueke API instance (on-site server or local dev instance)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
npm ci && npm run build   # or `npm run dev` while developing
```

Set `OTUEKE_API_BASE_URL` in `.env` to the API you want to use.

## Run

```bash
php artisan serve          # http://localhost:8000
```

- `GET /` - placeholder dashboard
- `GET /health` - `{"status":"ok","service":"otueke-admin-web"}`

## Test

```bash
vendor/bin/pint --test     # code style
php artisan test           # PHPUnit
```

CI (`.github/workflows/ci.yml`) runs Pint, the test suite, a front-end build and a gitleaks
secret scan on every push/PR.

## Configuration

All configuration comes from the environment (see `.env.example` - placeholders only).

| Variable | Default | Description |
| --- | --- | --- |
| `OTUEKE_API_BASE_URL` | `http://127.0.0.1:5080` | Base URL of the Otueke API |
| `OTUEKE_API_PREFIX` | `/api/v1` | Versioned API path prefix |
| `OTUEKE_API_TIMEOUT` | `10` | Request timeout (seconds) |
| `OTUEKE_API_CONNECT_TIMEOUT` | `3` | Connect timeout (seconds) |
| `OTUEKE_API_CLIENT_ID` | `otueke-admin-web` | Public client identifier registered in the API (not a secret) |
| `OTUEKE_DISPLAY_TIMEZONE` | `Africa/Lagos` | Timezone used when rendering dates |
| `SESSION_DRIVER` | `file` | Session store (holds the API token server-side) |
| `CACHE_STORE` | `file` | Cache store |
| `QUEUE_CONNECTION` | `sync` | Queue driver |

See `config/otueke.php`.

## Deployment

The same build is deployed in two places:

- **On-site:** served from the Windows local application server alongside the on-site
  Otueke API, reachable only from the STAFF/management network. Keeps working during an
  internet outage because it talks to the local API.
- **Cloud (remote admin):** a cloud instance pointed at the cloud-mode Otueke API for
  owners/managers working off-site. The on-site server is never exposed to the internet;
  data reaches the cloud through outbound sync from the site.

Environment templates and runbooks live in
[prinzderick/otueke-infrastructure](https://github.com/prinzderick/otueke-infrastructure).

## Related

- Architecture, ADRs and domain docs: [prinzderick/otueke-docs](https://github.com/prinzderick/otueke-docs)
- API: [prinzderick/otueke-api](https://github.com/prinzderick/otueke-api)
- Contributing: [CONTRIBUTING.md](CONTRIBUTING.md)
