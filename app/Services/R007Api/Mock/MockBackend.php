<?php

namespace App\Services\R007Api\Mock;

use App\Services\R007Api\ApiResponse;
use App\Services\R007Api\R007ApiException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * In-process stand-in for the API (R007_MOCK=true). Stateful for the life of
 * the cache (approvals can be decided, outbox retried, staff created ...), and
 * honest about freshness: R007_MOCK_SCENARIO=stale|offline flips the site
 * status and the `freshness` blocks so the UI's stale-data handling can be
 * seen and demonstrated. It only answers endpoints defined in the contract.
 */
class MockBackend
{
    /** @var array<string, mixed> */
    private array $s = [];

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     */
    public function handle(string $method, string $path, array $query, array $body, ?string $token): ApiResponse
    {
        $this->load();
        $path = '/'.trim($path, '/');

        // ---- unauthenticated --------------------------------------------------
        if ($method === 'POST' && $path === '/auth/staff/login') {
            return $this->login($body);
        }
        if ($method === 'POST' && $path === '/auth/staff/refresh') {
            $persona = Str::after((string) ($body['refreshToken'] ?? ''), 'mock-refresh-');

            return $this->ok(['accessToken' => 'mock-access-'.$persona, 'refreshToken' => 'mock-refresh-'.$persona, 'expiresInSeconds' => 900] + $this->authPayload($persona));
        }
        if ($method === 'GET' && $path === '/system/info') {
            return $this->ok(['service' => 'r007-api (mock)', 'apiVersion' => '1.0.0', 'deploymentMode' => config('r007.instance'), 'serverTime' => MockData::now(), 'timezone' => 'Africa/Lagos', 'currency' => 'NGN', 'vatEnabled' => false]);
        }
        if ($method === 'GET' && $path === '/system/health') {
            return $this->ok(['status' => $this->peer()['reachable'] ? 'ok' : 'degraded', 'checks' => ['database' => 'ok', 'redis' => 'ok', 'queue' => 'ok', 'cloudLink' => $this->peer()['reachable'] ? 'ok' : 'down'], 'serverTime' => MockData::now()]);
        }

        $persona = str_starts_with((string) $token, 'mock-access-') ? Str::after((string) $token, 'mock-access-') : null;
        if ($persona === null || ! isset(MockData::personas()[$persona])) {
            $this->fail(401, 'unauthenticated', 'Sign in required.');
        }
        $perms = MockData::personas()[$persona]['permissions'];

        if ($method === 'GET' && $path === '/auth/me') {
            return $this->ok($this->authPayload($persona));
        }
        if ($method === 'POST' && $path === '/auth/staff/logout') {
            return new ApiResponse(204, []);
        }
        if ($method === 'POST' && $path === '/auth/staff/mfa/verify') {
            ($body['code'] ?? '') === '000000' ? null : $this->fail(422, 'validation_failed', 'Invalid verification code.');

            return $this->ok(['verified' => true]);
        }

        foreach ($this->routes() as [$m, $pattern, $perm, $handler]) {
            if ($m !== $method || ! preg_match('#^'.$pattern.'$#', $path, $match)) {
                continue;
            }
            if ($perm !== null && ! in_array($perm, $perms, true) && ! in_array('*', $perms, true)) {
                $this->fail(403, 'permission_denied', "Requires permission {$perm}.");
            }
            $args = array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);
            $res = $handler($args, $query, $body, $persona, $perms);
            $this->save();

            return $res;
        }

        $this->fail(404, null, 'No such endpoint in the mock API.');
    }

    // ---------------------------------------------------------------- routes

    /** @return list<array{0: string, 1: string, 2: ?string, 3: callable}> */
    private function routes(): array
    {
        $u = '(?<id>[0-9a-f-]{36})';

        return [
            ['GET', '/organization/site', null, fn () => $this->ok($this->s['site'])],
            ['GET', '/organization/facilities', null, fn () => $this->ok(['items' => $this->tree()])],
            ['GET', "/organization/facilities/{$u}/operating-points", null, fn ($a) => $this->page([
                ['id' => MockData::id(hexdec(substr($a['id'], -3)) + 0x3000), 'facilityId' => $a['id'], 'code' => 'FLOOR', 'name' => 'Main floor', 'kind' => 'TABLE_AREA', 'defaultPrepStationId' => null],
                ['id' => MockData::id(hexdec(substr($a['id'], -3)) + 0x3100), 'facilityId' => $a['id'], 'code' => 'COUNTER', 'name' => 'Counter', 'kind' => 'COUNTER', 'defaultPrepStationId' => null]])],
            ['GET', "/facilities/{$u}/capabilities", null, fn ($a) => $this->ok(['facilityId' => $a['id'], 'capabilities' => collect($this->s['facilities'])->firstWhere('id', $a['id'])['capabilities'] ?? [], 'operatingRules' => $this->s['rules'][array_search($a['id'], array_column($this->s['facilities'], 'id'), true)] ?? array_values($this->s['rules'])[0], 'version' => 1])],

            // reports
            ['GET', '/reports/facility-daily-summary', 'report.view', fn ($a, $q) => $this->ok($this->summary((string) ($q['facilityId'] ?? ''), (string) ($q['date'] ?? '')))],
            ['GET', '/reports/revenue', 'report.view', fn ($a, $q) => $this->ok(['from' => $q['from'] ?? null, 'to' => $q['to'] ?? null, 'data' => ['currency' => 'NGN', 'total' => '412500.0000', 'byFacility' => collect($this->s['facilities'])->take(4)->map(fn ($f, $i) => ['facilityId' => $f['id'], 'facility' => $f['name'], 'revenue' => (string) (180000 - $i * 40000).'.0000'])->all()], 'freshness' => $this->freshness()])],
            ['GET', "/reports/cashier-shift/{$u}", 'report.view', fn ($a) => $this->ok($this->shiftReport($a['id']))],

            // finance
            ['GET', '/payments', 'payment.view', function ($a, $q) {
                $rows = array_values(array_filter($this->s['payments'], fn ($p) => (! isset($q['filter[facilityId]']) || $p['facilityId'] === $q['filter[facilityId]'])
                    && (! isset($q['filter[status]']) || $p['status'] === $q['filter[status]'])
                    && (! isset($q['filter[cashSessionId]']) || $p['cashSessionId'] === $q['filter[cashSessionId]'])));

                return $this->page($rows);
            }],
            ['GET', "/payments/{$u}", 'payment.view', fn ($a) => $this->ok($this->find('payments', $a['id']))],
            ['POST', "/payments/{$u}/refund", 'refund.execute', fn ($a, $q, $b, $p) => $this->requestSensitive('payment.refund', 'payment', $a['id'], (string) ($b['reason'] ?? ''), (string) ($b['amount'] ?? '0'), $p, 'refund.approve', 'Refund '.($b['amount'] ?? '').' on payment '.substr($a['id'], -4))],
            ['POST', "/payments/{$u}/reversal", 'payment.reversal.execute', fn ($a, $q, $b, $p) => $this->requestSensitive('payment.reversal', 'payment', $a['id'], (string) ($b['reason'] ?? ''), null, $p, 'payment.reversal.approve', 'Reverse payment '.substr($a['id'], -4))],
            ['GET', '/payments/paystack/verify/(?<ref>[A-Za-z0-9_]+)', null, fn ($a) => $this->ok(['reference' => $a['ref'], 'status' => 'CAPTURED', 'amount' => '15000.0000'])],
            ['GET', '/cash-sessions', 'cash_session.view', fn ($a, $q) => $this->page(array_values(array_filter($this->s['cashSessions'], fn ($c) => (! isset($q['filter[facilityId]']) || $c['facilityId'] === $q['filter[facilityId]']) && (! isset($q['filter[status]']) || $c['status'] === $q['filter[status]']))))],
            ['GET', "/cash-sessions/{$u}", 'cash_session.view', fn ($a) => $this->ok($this->find('cashSessions', $a['id']))],
            ['GET', '/approvals', null, fn ($a, $q) => $this->page(array_values(array_filter($this->s['approvals'], fn ($x) => ! isset($q['filter[status]']) || $x['status'] === $q['filter[status]'])))],
            ['GET', "/approvals/{$u}", null, fn ($a) => $this->ok($this->find('approvals', $a['id']))],
            ['POST', "/approvals/{$u}/decision", null, fn ($a, $q, $b, $p, $perms) => $this->decide($a['id'], $b, $p, $perms)],
            ['POST', "/approvals/{$u}/cancel", null, fn ($a) => $this->setApproval($a['id'], 'CANCELLED', null)],

            // operations
            ['GET', '/orders', 'order.view', fn () => $this->page($this->s['orders'])],
            ['GET', '/bookings', 'booking.view', fn () => $this->page($this->s['bookings'])],
            ['GET', '/bookings/resources', null, fn () => $this->page($this->s['resources'])],
            ['GET', '/attendance', 'attendance.view', fn () => $this->page($this->s['attendance'])],
            ['GET', '/attendance/corrections', 'attendance.view', fn () => $this->page($this->s['corrections'])],
            ['POST', "/attendance/corrections/{$u}/approve", 'staff.clock_correction.approve', fn ($a) => $this->setCorrection($a['id'], 'APPROVED')],
            ['POST', "/attendance/corrections/{$u}/reject", 'staff.clock_correction.approve', fn ($a) => $this->setCorrection($a['id'], 'REJECTED')],
            ['GET', '/attendance/devices', 'attendance.device.manage', fn () => $this->page($this->s['attDevices'])],
            ['POST', '/attendance/devices', 'attendance.device.manage', function ($a, $q, $b) {
                $d = ['id' => (string) Str::uuid(), 'serial' => (string) ($b['serial'] ?? ''), 'adapter' => $b['adapter'] ?? 'ZKTECO_ADMS', 'timeZone' => 'Africa/Lagos', 'status' => 'ACTIVE', 'lastSeenAt' => null];
                $this->s['attDevices'][] = $d;

                return $this->ok(['device' => $d, 'deviceToken' => 'mock-terminal-token-'.Str::random(12)], 201);
            }],
            ['POST', "/attendance/devices/{$u}/rotate-token", 'attendance.device.manage', fn ($a) => $this->ok(['device' => $this->find('attDevices', $a['id']), 'deviceToken' => 'mock-terminal-token-'.Str::random(12)])],
            ['POST', "/attendance/devices/{$u}/status", 'attendance.device.manage', function ($a, $q, $b) {
                foreach ($this->s['attDevices'] as &$d) {
                    if ($d['id'] === $a['id']) {
                        $d['status'] = $b['status'] ?? $d['status'];

                        return $this->ok($d);
                    }
                }
                $this->fail(404, 'not_found', 'Attendance device not found.');
            }],

            // inventory
            ['GET', '/inventory/items', 'inventory.view', fn () => $this->page($this->s['items'])],
            ['GET', '/inventory/locations', 'inventory.view', fn () => $this->page($this->s['locations'])],
            ['GET', '/inventory/balances', 'inventory.view', fn ($a, $q) => $this->page(array_values(array_filter($this->s['balances'], fn ($b) => (! isset($q['locationId']) || $b['locationId'] === $q['locationId']) && (! isset($q['itemId']) || $b['itemId'] === $q['itemId']))))],
            ['POST', '/inventory/purchase-receipts', 'inventory.purchase_receipt.create', fn ($a, $q, $b) => $this->movement('PURCHASE_RECEIPT', $b['locationId'], $b['lines'], 1)],
            ['POST', '/inventory/transfers', 'inventory.transfer.create', function ($a, $q, $b) {
                $this->movement('TRANSFER_OUT', $b['fromLocationId'], $b['lines'], -1);

                return $this->movement('TRANSFER_IN', $b['toLocationId'], $b['lines'], 1);
            }],
            ['POST', '/inventory/wastage', 'inventory.wastage.create', fn ($a, $q, $b) => $this->movement('WASTAGE', $b['locationId'], [['itemId' => $b['itemId'], 'quantity' => $b['quantity']]], -1)],
            ['POST', '/inventory/adjustments', 'inventory.adjustment.request', function ($a, $q, $b, $p, $perms) {
                $delta = (string) ($b['quantityDelta'] ?? '0');
                if (! in_array('inventory.adjustment.approve', $perms, true)) {
                    $id = (string) Str::uuid();
                    $appr = $this->newApproval('inventory.adjustment', 'stock_movement', $id, (string) ($b['note'] ?? ''), null, $p, 'inventory.adjustment.approve', "Adjust item by {$delta}");

                    return $this->ok(['status' => 'PENDING_APPROVAL', 'approval' => $appr], 202);
                }

                return $this->movement('ADJUSTMENT', $b['locationId'], [['itemId' => $b['itemId'], 'quantity' => ltrim($delta, '-')]], str_starts_with($delta, '-') ? -1 : 1);
            }],
            ['POST', '/inventory/counts', 'inventory.count.create', function ($a, $q, $b) {
                $lines = [];
                foreach ($b['lines'] ?? [] as $l) {
                    $exp = collect($this->s['balances'])->first(fn ($x) => $x['itemId'] === $l['itemId'] && $x['locationId'] === $b['locationId'])['quantity'] ?? '0';
                    $lines[] = ['itemId' => $l['itemId'], 'expectedQuantity' => $exp, 'countedQuantity' => $l['countedQuantity'], 'variance' => bcsub((string) $l['countedQuantity'], (string) $exp, 4)];
                }
                $c = ['id' => (string) Str::uuid(), 'locationId' => $b['locationId'], 'status' => 'DRAFT', 'lines' => $lines, 'createdAt' => MockData::now(), 'postedAt' => null];
                $this->s['counts'][$c['id']] = $c;

                return $this->ok($c, 201);
            }],
            ['POST', "/inventory/counts/{$u}/post", 'inventory.count.post', function ($a) {
                $c = $this->s['counts'][$a['id']] ?? $this->fail(404, 'not_found', 'Count not found.');
                $c['status'] = 'POSTED';
                $c['postedAt'] = MockData::now();
                $this->s['counts'][$a['id']] = $c;

                return $this->ok($c);
            }],

            // staff
            ['GET', '/staff', 'staff.manage', fn ($a, $q) => $this->page(array_values(array_filter($this->s['staff'], fn ($x) => (! isset($q['filter[status]']) || $x['status'] === $q['filter[status]']) && (! isset($q['q']) || stripos($x['displayName'].$x['staffNumber'], (string) $q['q']) !== false))))],
            ['POST', '/staff', 'staff.manage', function ($a, $q, $b) {
                $n = count($this->s['staff']);
                $m = ['id' => (string) Str::uuid(), 'displayName' => trim(($b['firstName'] ?? '').' '.($b['lastName'] ?? '')), 'staffNumber' => $b['staffNumber'] ?? 'S-'.($n + 1), 'status' => 'ACTIVE', 'email' => $b['email'] ?? null, 'phone' => $b['phone'] ?? null, 'rowVersion' => 1,
                    'firstName' => $b['firstName'] ?? '', 'lastName' => $b['lastName'] ?? '', 'siteId' => $this->s['site']['id'], 'hasPassword' => false, 'hasPin' => false, 'hasNfcCard' => false];
                $this->s['staff'][] = $m;

                return $this->ok($m, 201);
            }],
            ['GET', "/staff/{$u}", 'staff.manage', function ($a) {
                $m = $this->find('staff', $a['id']);

                return $this->ok($m, 200, ['etag' => '"'.$m['rowVersion'].'"']);
            }],
            ['PATCH', "/staff/{$u}", 'staff.manage', function ($a, $q, $b) {
                foreach ($this->s['staff'] as &$m) {
                    if ($m['id'] === $a['id']) {
                        $m = array_merge($m, array_intersect_key($b, array_flip(['firstName', 'lastName', 'email', 'phone', 'status'])));
                        $m['displayName'] = trim($m['firstName'].' '.$m['lastName']);
                        $m['rowVersion']++;

                        return $this->ok($m);
                    }
                }
                $this->fail(404, 'not_found', 'Staff member not found.');
            }],
            ['GET', '/roles', 'role_assignment.manage', fn () => $this->page($this->s['roles'])],
            ['GET', '/permissions', 'role_assignment.manage', fn () => $this->page(array_map(fn ($c) => ['code' => $c, 'description' => null], array_values(array_unique(array_merge(...array_column(MockData::personas(), 'permissions'))))))],
            ['GET', "/staff/{$u}/role-assignments", 'role_assignment.manage', fn ($a) => $this->page(array_values(array_filter($this->s['assignments'], fn ($x) => $x['staffId'] === $a['id'])))],
            ['POST', "/staff/{$u}/role-assignments", 'role_assignment.manage', function ($a, $q, $b, $p) {
                $r = ['id' => (string) Str::uuid(), 'staffId' => $a['id'], 'roleId' => $b['roleId'], 'scopeType' => $b['scopeType'], 'scopeId' => $b['scopeId'], 'grantedByStaffId' => null, 'grantedAt' => MockData::now(), 'revokedAt' => null];
                $this->s['assignments'][] = $r;

                return $this->ok($r, 201);
            }],
            ['DELETE', "/staff/{$u}/role-assignments/(?<aid>[0-9a-f-]{36})", 'role_assignment.manage', function ($a) {
                $this->s['assignments'] = array_values(array_filter($this->s['assignments'], fn ($x) => $x['id'] !== $a['aid']));

                return new ApiResponse(204, []);
            }],
            ['PUT', "/staff/{$u}/credentials/(?<kind>password|pin|nfc-card)", 'staff.manage', function ($a) {
                $flag = ['password' => 'hasPassword', 'pin' => 'hasPin', 'nfc-card' => 'hasNfcCard'][$a['kind']];
                foreach ($this->s['staff'] as &$m) {
                    $m['id'] === $a['id'] && $m[$flag] = true;
                }

                return new ApiResponse(204, []);
            }],
            ['DELETE', "/staff/{$u}/credentials/nfc-card", 'staff.manage', function ($a) {
                foreach ($this->s['staff'] as &$m) {
                    $m['id'] === $a['id'] && $m['hasNfcCard'] = false;
                }

                return new ApiResponse(204, []);
            }],
            ['GET', '/audit', 'audit.view', fn ($a, $q) => $this->page(array_values(array_filter($this->s['audit'], fn ($x) => (! isset($q['action']) || str_contains($x['action'], (string) $q['action'])) && (! isset($q['entityType']) || $x['entityType'] === $q['entityType']))))],

            // devices
            ['GET', '/devices', 'device.register', fn ($a, $q) => $this->page(array_values(array_filter($this->s['devices'], fn ($d) => ! isset($q['filter[status]']) || $d['status'] === $q['filter[status]'])))],
            ['GET', "/devices/{$u}", null, fn ($a) => $this->ok($this->find('devices', $a['id']))],
            ['GET', "/devices/{$u}/commands", null, fn () => $this->ok(['items' => []])],
            ['POST', "/devices/{$u}/revoke", 'device.revoke', function ($a) {
                foreach ($this->s['devices'] as &$d) {
                    if ($d['id'] === $a['id']) {
                        $d['status'] = 'REVOKED';
                        $d['checkout'] = null;

                        return $this->ok($d);
                    }
                }
                $this->fail(404, 'not_found', 'Device not found.');
            }],
            ['POST', '/devices/registration-codes', 'device.register', fn () => $this->ok(['code' => strtoupper(Str::random(4)).'-'.random_int(1000, 9999), 'expiresAt' => CarbonImmutable::now('UTC')->addMinutes(15)->format('Y-m-d\TH:i:s.u\Z')], 201)],

            // configuration
            ['GET', '/catalog/categories', null, fn () => $this->page($this->s['categories'])],
            ['GET', '/catalog/products', null, fn () => $this->page($this->s['products'])],
            ['GET', '/catalog/availability', null, fn ($a, $q) => $this->page(array_map(fn ($p) => ['productId' => $p['id'], 'facilityId' => $q['facilityId'] ?? $this->s['facilities'][0]['id'], 'available' => ! in_array($p['id'], $this->s['unavailable'] ?? [], true), 'reason' => null, 'quantityOnHand' => null], $this->s['products']))],
            ['PUT', "/catalog/products/{$u}/availability/(?<fid>[0-9a-f-]{36})", 'catalog.availability.manage', function ($a, $q, $b) {
                $this->s['unavailable'] = array_values(array_diff($this->s['unavailable'] ?? [], [$a['id']]));
                ($b['available'] ?? true) || $this->s['unavailable'][] = $a['id'];

                return $this->ok(['productId' => $a['id'], 'facilityId' => $a['fid'], 'available' => (bool) ($b['available'] ?? true), 'reason' => $b['reason'] ?? null, 'quantityOnHand' => null]);
            }],
            ['GET', '/kds/stations', null, fn () => $this->page($this->s['stations'])],
            ['GET', '/memberships/plans', null, fn () => $this->page($this->s['plans'])],
            ['POST', '/memberships/plans', 'membership.plan.manage', function ($a, $q, $b) {
                $p = ['id' => (string) Str::uuid(), 'name' => $b['name'] ?? '', 'durationDays' => (int) ($b['durationDays'] ?? 30), 'price' => $b['price'] ?? '0.0000', 'currency' => 'NGN', 'facilityIds' => $b['facilityIds'] ?? [], 'visitLimit' => $b['visitLimit'] ?? null, 'active' => (bool) ($b['active'] ?? true)];
                $this->s['plans'][] = $p;

                return $this->ok($p, 201);
            }],
            ['PATCH', "/memberships/plans/{$u}", 'membership.plan.manage', function ($a, $q, $b) {
                foreach ($this->s['plans'] as &$p) {
                    if ($p['id'] === $a['id']) {
                        $p = array_merge($p, array_intersect_key($b, $p));

                        return $this->ok($p);
                    }
                }
                $this->fail(404, 'not_found', 'Plan not found.');
            }],
            ['GET', '/admin/settings/tax', 'config.manage', fn () => $this->ok($this->s['tax'], 200, ['etag' => '"'.$this->s['tax']['rowVersion'].'"'])],
            ['PUT', '/admin/settings/tax', 'config.manage', function ($a, $q, $b) {
                $this->s['tax'] = array_merge($this->s['tax'], array_intersect_key($b, $this->s['tax']));
                $this->s['tax']['rowVersion']++;

                return $this->ok($this->s['tax']);
            }],

            // sync
            ['GET', '/sync/status', 'config.manage', fn () => $this->ok($this->syncStatus())],
            ['GET', '/sync/outbox', 'config.manage', fn ($a, $q) => $this->page(array_values(array_filter($this->s['outbox'], fn ($e) => ! isset($q['status']) || $e['syncStatus'] === $q['status'])))],
            ['POST', "/sync/outbox/{$u}/retry", 'config.manage', function ($a) {
                foreach ($this->s['outbox'] as &$e) {
                    if ($e['id'] === $a['id']) {
                        $e['syncStatus'] = 'QUEUED';
                        $e['retryCount'] = 0;
                        $e['lastError'] = null;

                        return $this->ok($e);
                    }
                }
                $this->fail(404, 'not_found', 'Event not found.');
            }],
            ['POST', '/sync/outbox/replay-failed', 'config.manage', function () {
                $n = 0;
                foreach ($this->s['outbox'] as &$e) {
                    if ($e['syncStatus'] === 'FAILED') {
                        $e['syncStatus'] = 'QUEUED';
                        $e['lastError'] = null;
                        $n++;
                    }
                }
                $m = 0;
                foreach ($this->s['inbox'] as &$e) {
                    if ($e['result'] === 'FAILED') {
                        $e['result'] = 'PENDING';
                        $m++;
                    }
                }

                return $this->ok(['outboxRequeued' => $n, 'inboxReprocessed' => $m]);
            }],
            ['GET', '/sync/inbox-events', 'config.manage', fn ($a, $q) => $this->page(array_values(array_filter($this->s['inbox'], fn ($e) => ! isset($q['result']) || $e['result'] === $q['result'])))],
            ['POST', "/sync/inbox-events/{$u}/reprocess", 'config.manage', function ($a) {
                foreach ($this->s['inbox'] as &$e) {
                    if ($e['id'] === $a['id']) {
                        $e['result'] = 'APPLIED';
                        $e['processedAt'] = MockData::now();

                        return $this->ok($e);
                    }
                }
                $this->fail(404, 'not_found', 'Event not found.');
            }],
            ['GET', '/sync/conflicts', 'config.manage', fn ($a, $q) => $this->page(array_values(array_filter($this->s['conflicts'], fn ($c) => ! isset($q['status']) || $c['status'] === $q['status'])))],
            ['GET', "/sync/conflicts/{$u}", 'config.manage', fn ($a) => $this->ok($this->find('conflicts', $a['id']))],
            ['POST', "/sync/conflicts/{$u}/resolve", 'config.manage', function ($a, $q, $b) {
                foreach ($this->s['conflicts'] as &$c) {
                    if ($c['id'] === $a['id']) {
                        $c['status'] = ($b['resolution'] ?? '') === 'DISMISSED' ? 'DISMISSED' : 'RESOLVED';
                        $c['resolution'] = $b['resolution'] ?? null;
                        $c['note'] = $b['note'] ?? null;
                        $c['resolvedAt'] = MockData::now();

                        return $this->ok($c);
                    }
                }
                $this->fail(404, 'not_found', 'Conflict not found.');
            }],
            ['POST', "/sync/conflicts/{$u}/reprocess", 'config.manage', fn ($a) => $this->ok($this->find('conflicts', $a['id']))],
        ];
    }

    // --------------------------------------------------------------- helpers

    /**
     * Peer link state relative to NOW (so timestamps stay believable however
     * long the mock cache lives).
     *
     * @return array{reachable: bool, lastHeartbeatAt: string, lastSyncAt: string}
     */
    private function peer(): array
    {
        return match ($this->s['scenario']) {
            'offline' => ['reachable' => false, 'lastHeartbeatAt' => MockData::now(1500), 'lastSyncAt' => MockData::now(1560)],
            'stale' => ['reachable' => true, 'lastHeartbeatAt' => MockData::now(12), 'lastSyncAt' => MockData::now(2400)],
            default => ['reachable' => true, 'lastHeartbeatAt' => MockData::now(12), 'lastSyncAt' => MockData::now(17)],
        };
    }

    /** @return array<string, mixed> */
    private function syncStatus(): array
    {
        $peer = $this->peer();
        $failed = count(array_filter($this->s['outbox'], fn ($e) => $e['syncStatus'] === 'FAILED'));
        $queued = count(array_filter($this->s['outbox'], fn ($e) => in_array($e['syncStatus'], ['QUEUED', 'SYNCING'], true)));

        return ['node' => config('r007.instance'), 'peerReachable' => $peer['reachable'], 'lastHeartbeatAt' => MockData::now(5), 'lastPeerHeartbeatAt' => $peer['lastHeartbeatAt'],
            'outbox' => ['queued' => $queued, 'failed' => $failed, 'conflict' => count(array_filter($this->s['outbox'], fn ($e) => $e['syncStatus'] === 'CONFLICT')), 'oldestQueuedAt' => MockData::now(2800)],
            'inbox' => ['conflict' => count(array_filter($this->s['inbox'], fn ($e) => $e['result'] === 'CONFLICT')), 'deferred' => 0],
            'health' => ! $peer['reachable'] ? 'OFFLINE' : ($this->s['scenario'] === 'stale' ? 'DEGRADED' : 'ONLINE')];
    }

    /** @return array<string, mixed> */
    private function freshness(): array
    {
        $cloud = config('r007.instance') === 'cloud';
        $stale = $this->s['scenario'] !== 'normal';
        $age = $stale ? ($this->s['scenario'] === 'offline' ? 1560 : 2400) : 17;

        return ['generatedAt' => MockData::now(), 'sourceNode' => $cloud ? 'cloud' : 'local', 'lastSyncAt' => $this->peer()['lastSyncAt'], 'stale' => $stale && $cloud || $this->s['scenario'] === 'offline',
            'staleReason' => $this->s['scenario'] === 'offline' ? 'site OFFLINE since last heartbeat' : ($stale ? 'sync is behind' : null), 'ageSeconds' => $age, 'staleAfterSeconds' => 300];
    }

    /** @return array<string, mixed> */
    private function summary(string $facilityId, string $date): array
    {
        $i = max(0, (int) hexdec(substr($facilityId, -3)) - 0x101);
        $gross = 250000 - $i * 27500;
        $by = [['tenderType' => 'CASH', 'amount' => (string) ($gross * 0.35).'.0000', 'count' => 14 + $i], ['tenderType' => 'CARD', 'amount' => (string) ($gross * 0.4).'.0000', 'count' => 11 + $i], ['tenderType' => 'TRANSFER', 'amount' => (string) ($gross * 0.25).'.0000', 'count' => 6]];

        return ['facilityId' => $facilityId, 'date' => $date, 'orders' => 31 - $i, 'grossSales' => $gross.'.0000', 'discounts' => '3500.0000', 'tax' => '0.0000', 'netSales' => ($gross - 3500).'.0000', 'refunds' => '2000.0000', 'voids' => ['count' => 1, 'amount' => '14500.0000'],
            'byTender' => $by, 'topProducts' => [['productId' => MockData::id(0x1200), 'name' => 'Jollof Rice & Chicken', 'quantity' => 22, 'revenue' => '77000.0000'], ['productId' => MockData::id(0x1202), 'name' => 'Star Lager 60cl', 'quantity' => 61, 'revenue' => '73200.0000']],
            'ticketsRedeemed' => 40 - $i * 4, 'currency' => 'NGN', 'freshness' => $this->freshness()];
    }

    /** @return array<string, mixed> */
    private function shiftReport(string $id): array
    {
        $c = $this->find('cashSessions', $id);
        $staff = collect($this->s['staff'])->firstWhere('id', $c['staffId']);

        return ['shiftId' => $id, 'staffId' => $c['staffId'], 'staffName' => $staff['displayName'] ?? 'Staff', 'facilityId' => $c['facilityId'], 'openedAt' => $c['openedAt'], 'closedAt' => $c['closedAt'], 'openingFloat' => $c['openingFloat'],
            'byTender' => [['tenderType' => 'CASH', 'amount' => '48500.0000', 'count' => 9], ['tenderType' => 'CARD', 'amount' => '38000.0000', 'count' => 3]], 'expectedCash' => $c['expectedCash'], 'countedCash' => $c['countedCash'], 'variance' => $c['variance'],
            'refunds' => '2000.0000', 'voids' => 1, 'paymentIds' => array_column(array_filter($this->s['payments'], fn ($p) => $p['cashSessionId'] === $id), 'id'), 'freshness' => $this->freshness()];
    }

    /** @return list<array<string, mixed>> */
    private function tree(): array
    {
        $byParent = [];
        foreach ($this->s['facilities'] as $f) {
            $byParent[$f['parentId'] ?? ''][] = $f;
        }
        $build = function (?string $parent) use (&$build, $byParent): array {
            return array_map(fn ($f) => $f + ['children' => $build($f['id'])], $byParent[$parent ?? ''] ?? []);
        };

        return $build(null);
    }

    /** @param  array<string, mixed>  $body */
    private function decide(string $id, array $body, string $persona, array $perms): ApiResponse
    {
        $a = $this->find('approvals', $id);
        if (! in_array($a['requiredPermission'], $perms, true) && ! in_array('*', $perms, true)) {
            $this->fail(403, 'permission_denied', "Requires permission {$a['requiredPermission']}.");
        }
        if ($a['status'] !== 'PENDING') {
            $this->fail(409, 'concurrency_conflict', 'This approval has already been decided.');
        }

        return $this->setApproval($id, ($body['decision'] ?? '') === 'APPROVE' ? 'APPROVED' : 'REJECTED', $body['note'] ?? null);
    }

    private function setApproval(string $id, string $status, ?string $note): ApiResponse
    {
        foreach ($this->s['approvals'] as &$a) {
            if ($a['id'] === $id) {
                $a['status'] = $status;
                $a['decidedAt'] = MockData::now();
                $a['decisionNote'] = $note;

                return $this->ok($a);
            }
        }
        $this->fail(404, 'not_found', 'Approval not found.');
    }

    private function setCorrection(string $id, string $status): ApiResponse
    {
        foreach ($this->s['corrections'] as &$c) {
            if ($c['id'] === $id) {
                $c['status'] = $status;

                return $this->ok($c);
            }
        }
        $this->fail(404, 'not_found', 'Correction not found.');
    }

    /** Sensitive action: 202 + approval unless the caller can approve it themselves. */
    private function requestSensitive(string $action, string $type, string $entityId, string $reason, ?string $amount, string $persona, string $approvePerm, string $summary): ApiResponse
    {
        $perms = MockData::personas()[$persona]['permissions'];
        if (in_array($approvePerm, $perms, true) && $persona === 'owner') {
            return $this->ok(['id' => (string) Str::uuid(), 'paymentId' => $entityId, 'amount' => $amount ?? '0.0000', 'reason' => $reason, 'status' => 'COMPLETED', 'approvalId' => null, 'createdAt' => MockData::now()], 201);
        }
        $appr = $this->newApproval($action, $type, $entityId, $reason, $amount, $persona, $approvePerm, $summary);

        return $this->ok(['status' => 'PENDING_APPROVAL', 'approval' => $appr], 202);
    }

    /** @return array<string, mixed> */
    private function newApproval(string $action, string $type, string $entityId, string $reason, ?string $amount, string $persona, string $perm, string $summary): array
    {
        $a = ['id' => (string) Str::uuid(), 'action' => $action, 'entityType' => $type, 'entityId' => $entityId, 'facilityId' => $this->s['facilities'][0]['id'], 'status' => 'PENDING', 'requestedByStaffId' => null,
            'requestedByName' => MockData::personas()[$persona]['name'], 'requestedAt' => MockData::now(), 'reason' => $reason, 'amount' => $amount, 'summary' => $summary, 'decidedByStaffId' => null, 'decidedAt' => null, 'decisionNote' => null, 'requiredPermission' => $perm];
        array_unshift($this->s['approvals'], $a);

        return $a;
    }

    /** @param  list<array<string, mixed>>  $lines */
    private function movement(string $kind, string $locationId, array $lines, int $sign): ApiResponse
    {
        $out = [];
        foreach ($lines as $l) {
            $found = false;
            foreach ($this->s['balances'] as &$b) {
                if ($b['itemId'] === $l['itemId'] && $b['locationId'] === $locationId) {
                    $b['quantity'] = bcadd($b['quantity'], $sign > 0 ? (string) $l['quantity'] : '-'.$l['quantity'], 4);
                    if (bccomp($b['quantity'], '0', 4) < 0) {
                        $b['quantity'] = bcsub($b['quantity'], $sign > 0 ? (string) $l['quantity'] : '-'.$l['quantity'], 4);
                        $this->fail(409, 'insufficient_stock', 'Not enough stock at this location.');
                    }
                    $b['updatedAt'] = MockData::now();
                    $found = true;
                }
            }
            unset($b);
            if (! $found && $sign > 0) {
                $item = $this->find('items', $l['itemId']);
                $this->s['balances'][] = ['itemId' => $l['itemId'], 'itemName' => $item['name'], 'locationId' => $locationId, 'quantity' => (string) $l['quantity'], 'unit' => $item['unit'], 'updatedAt' => MockData::now()];
            }
            $out[] = ['itemId' => $l['itemId'], 'locationId' => $locationId, 'quantityDelta' => ($sign > 0 ? '' : '-').$l['quantity'], 'unitCost' => $l['unitCost'] ?? '0.0000'];
        }

        return $this->ok(['id' => (string) Str::uuid(), 'kind' => $kind, 'documentId' => (string) Str::uuid(), 'status' => 'POSTED', 'approvalId' => null, 'lines' => $out, 'createdAt' => MockData::now()], 201);
    }

    /** @return array<string, mixed> */
    private function login(array $body): ApiResponse
    {
        $id = strtolower((string) ($body['identifier'] ?? $body['username'] ?? ''));
        $id = Str::before($id, '@');
        $secret = (string) ($body['secret'] ?? $body['password'] ?? '');
        if (! isset(MockData::personas()[$id]) || $secret !== MockData::PASSWORD) {
            $this->fail(401, 'invalid_credentials', 'Invalid staff number or password.');
        }

        return $this->ok(['accessToken' => 'mock-access-'.$id, 'refreshToken' => 'mock-refresh-'.$id, 'expiresInSeconds' => 900] + $this->authPayload($id));
    }

    /** @return array<string, mixed> */
    private function authPayload(string $persona): array
    {
        $p = MockData::personas()[$persona];

        return ['staff' => ['id' => MockData::id(0x200 + array_search($persona, array_keys(MockData::personas()), true)), 'displayName' => $p['name'], 'staffNumber' => 'S-000'.(array_search($persona, array_keys(MockData::personas()), true) + 1), 'roles' => $p['roles'], 'permissions' => $p['permissions'], 'facilityIds' => []],
            'session' => ['id' => MockData::id(0x9999), 'expiresAt' => CarbonImmutable::now('UTC')->addMinutes(15)->format('Y-m-d\TH:i:s.u\Z'), 'deviceId' => null], 'device' => null];
    }

    /** @return array<string, mixed> */
    private function find(string $bucket, string $id): array
    {
        foreach ($this->s[$bucket] as $row) {
            if (($row['id'] ?? null) === $id) {
                return $row;
            }
        }
        $this->fail(404, 'not_found', 'Resource not found.');
    }

    /** @param  list<array<string, mixed>>  $items */
    private function page(array $items): ApiResponse
    {
        return $this->ok(['items' => $items, 'nextCursor' => null]);
    }

    /**
     * @param  array<mixed>  $body
     * @param  array<string, string>  $headers
     */
    private function ok(array $body, int $status = 200, array $headers = []): ApiResponse
    {
        return new ApiResponse($status, $body, $headers);
    }

    private function fail(int $status, ?string $code, string $detail): never
    {
        throw R007ApiException::fromProblem($status, array_filter([
            'type' => 'about:blank', 'title' => Str::headline((string) ($code ?? 'not found')), 'status' => $status, 'detail' => $detail, 'code' => $code,
        ], fn ($v) => $v !== null));
    }

    private function load(): void
    {
        $scenario = (string) config('r007.mock_scenario', 'normal');
        $this->s = Cache::get('r007.mock.state') ?? [];
        if (($this->s['scenario'] ?? null) !== $scenario) {
            $this->s = MockData::seed($scenario);
            $this->save();
        }
    }

    private function save(): void
    {
        Cache::forever('r007.mock.state', $this->s);
    }
}
