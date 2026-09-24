<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Record REAL API responses (from a running node) into tests/Fixtures/real/, so the
 * regression suite renders every screen against what the API actually sends, not
 * against hand-written mock data. Secrets are redacted before anything is written.
 *
 *   php artisan r007:capture-fixtures --base=http://127.0.0.1:8080 --user=owner1 --password='...'
 */
class CaptureFixtures extends Command
{
    protected $signature = 'r007:capture-fixtures {--base=http://127.0.0.1:8080} {--user=owner1} {--password=} {--out=}';

    protected $description = 'Capture real API responses into tests/Fixtures/real (secrets redacted)';

    private string $base;

    private string $token = '';

    private string $out;

    /** @var array<string, mixed> */
    private array $ids = [];

    public function handle(): int
    {
        $this->base = rtrim((string) $this->option('base'), '/').'/api/v1/';
        $this->out = (string) ($this->option('out') ?: base_path('tests/Fixtures/real'));
        @mkdir($this->out, 0775, true);

        $login = Http::acceptJson()->post($this->base.'auth/staff/login', [
            'credentialType' => 'PASSWORD', 'identifier' => $this->option('user'), 'secret' => (string) $this->option('password'),
        ]);
        if ($login->failed()) {
            $this->error('Login failed: '.$login->status());

            return self::FAILURE;
        }
        $this->token = (string) $login->json('accessToken');

        $today = gmdate('Y-m-d');
        $week = gmdate('Y-m-d', strtotime('-7 days'));

        $this->grab('auth-me', 'auth/me');
        $this->grab('system-health', 'system/health');
        $this->grab('sync-status', 'sync/status');
        $this->grab('sync-outbox', 'sync/outbox', ['limit' => 100]);
        $this->grab('sync-outbox-failed', 'sync/outbox', ['limit' => 100, 'status' => 'FAILED']);
        $this->grab('sync-inbox-events', 'sync/inbox-events', ['limit' => 100]);
        $this->grab('sync-conflicts', 'sync/conflicts', ['limit' => 100]);
        $this->grab('sync-conflicts-open', 'sync/conflicts', ['limit' => 100, 'status' => 'OPEN']);

        $fac = $this->grab('organization-facilities', 'organization/facilities');
        $this->grab('organization-site', 'organization/site');
        $restaurant = $this->find($fac, 'code', 'RESTAURANT') ?? ($fac['items'][0] ?? []);
        $fid = $restaurant['id'] ?? null;
        if ($fid) {
            $this->ids['facility'] = $fid;
            $this->grab('organization-facility', "organization/facilities/{$fid}");
            $this->grab('organization-operating-points', "organization/facilities/{$fid}/operating-points");
            $this->grab('facility-capabilities', "facilities/{$fid}/capabilities");
            $this->grab('report-facility-daily-summary', 'reports/facility-daily-summary', ['date' => $today, 'facilityId' => $fid]);
        }
        $this->grab('report-revenue', 'reports/revenue', ['from' => $week, 'to' => $today]);

        $sessions = $this->grab('cash-sessions', 'cash-sessions', ['limit' => 50]);
        if ($sid = $sessions['items'][0]['id'] ?? null) {
            $this->grab('report-cashier-shift', "reports/cashier-shift/{$sid}");
            $this->grab('payments-by-session', 'payments', ['filter[cashSessionId]' => $sid, 'limit' => 200]);
        }
        $payments = $this->grab('payments', 'payments', ['limit' => 200]);
        if ($pid = $payments['items'][0]['id'] ?? null) {
            $this->grab('payment', "payments/{$pid}");
        }

        $this->grab('devices', 'devices', ['limit' => 200]);
        $this->grab('attendance-devices', 'attendance/devices', ['limit' => 100]);
        $orders = $this->grab('orders', 'orders', ['limit' => 100]);
        if ($oid = $orders['items'][0]['id'] ?? null) {
            $this->grab('order', "orders/{$oid}");
        }
        if ($fid) {
            $this->grab('tables', 'tables', ['facilityId' => $fid, 'limit' => 100]);
        }
        $this->grab('memberships', 'memberships', ['limit' => 50]);
        $this->grab('report-attendance-summary', 'reports/attendance-summary', ['from' => $week, 'to' => $today]);
        $this->grab('report-membership-summary', 'reports/membership-summary', ['from' => $week, 'to' => $today]);
        $this->grab('bookings', 'bookings', ['limit' => 100]);
        $this->grab('booking-resources', 'bookings/resources', ['limit' => 100]);
        $this->grab('catalog-categories', 'catalog/categories', ['limit' => 200]);
        if ($fid) {
            $this->grab('catalog-products', 'catalog/products', ['limit' => 200, 'facilityId' => $fid]);
            $this->grab('catalog-availability', 'catalog/availability', ['facilityId' => $fid, 'limit' => 200]);
        }
        $this->grab('admin-settings-tax', 'admin/settings/tax');
        $this->grab('membership-plans', 'memberships/plans', ['limit' => 100]);
        $this->grab('inventory-items', 'inventory/items', ['limit' => 200]);
        $this->grab('inventory-locations', 'inventory/locations', ['limit' => 200]);
        $this->grab('inventory-balances', 'inventory/balances', ['limit' => 200]);
        $this->grab('kds-stations', 'kds/stations', ['limit' => 100]);
        $this->grab('catalog-tax-rates', 'catalog/tax-rates');
        $this->grab('catalog-prep-routes', 'catalog/prep-routes');
        $this->grab('inventory-movements', 'inventory/movements', ['limit' => 50]);
        $this->grab('inventory-adjustments', 'inventory/adjustments', ['limit' => 50]);
        $this->grab('inventory-counts', 'inventory/counts', ['limit' => 50]);
        $this->grab('inventory-suppliers', 'inventory/suppliers', ['limit' => 50]);
        $this->grab('entitlements', 'entitlements', ['limit' => 50]);
        $this->grab('attendance-links', 'attendance/links');
        $this->grab('permissions', 'permissions', ['limit' => 300]);

        $staff = $this->grab('staff', 'staff', ['limit' => 100]);
        if ($stid = $staff['items'][0]['id'] ?? null) {
            $this->grab('staff-member', "staff/{$stid}");
            $this->grab('staff-role-assignments', "staff/{$stid}/role-assignments", ['limit' => 100]);
            $this->grab('audit-for-staff', 'audit', ['entityType' => 'staff', 'entityId' => $stid, 'limit' => 20]);
        }
        $this->grab('roles', 'roles', ['limit' => 100]);
        $this->grab('audit', 'audit', ['limit' => 100]);
        $this->grab('attendance', 'attendance', ['filter[from]' => $week, 'filter[to]' => $today, 'limit' => 200]);
        $this->grab('attendance-corrections', 'attendance/corrections', ['limit' => 50]);
        $this->grab('approvals', 'approvals', ['limit' => 100]);
        $this->grab('approvals-pending', 'approvals', ['filter[status]' => 'PENDING', 'limit' => 100]);

        $this->captureConfig($fid);
        $this->captureCollections($fid);

        $this->info('Fixtures written to '.$this->out);

        return self::SUCCESS;
    }

    /** Configuration-management API (docs/CONFIG_ADMIN_API.md): everything the Setup screens read. */
    private function captureConfig(?string $fid): void
    {
        $this->grab('organization-facility-templates', 'organization/facility-templates');
        $this->grab('organization-capability-types', 'organization/capability-types');
        $this->grab('organization-rule-definitions', 'organization/rule-definitions');
        $this->grab('admin-setup-status', 'admin/setup-status');
        $this->grab('admin-search', 'admin/search', ['q' => 'rest', 'limit' => 5]);
        $this->grab('admin-settings-business', 'admin/settings/business');
        $this->grab('admin-settings-receipt', 'admin/settings/receipt');
        $this->grab('ticket-types', 'ticketing/ticket-types');
        $this->grab('catalog-price-lists', 'catalog/price-lists');
        $this->grab('catalog-prices', 'catalog/prices', ['limit' => 100]);
        $products = $this->grab('admin-catalog-products', 'admin/catalog/products', ['limit' => 100]);
        if ($pid = $products['items'][0]['id'] ?? null) {
            $this->grab('admin-catalog-product', "admin/catalog/products/{$pid}");
            $this->grab('catalog-product-stock-links', "catalog/products/{$pid}/stock-links");
        }
        if ($fid) {
            $this->grab('operating-rules', "facilities/{$fid}/operating-rules");
            $this->grab('facility-payment-methods', "facilities/{$fid}/payment-methods");
            $this->grab('config-operating-points', 'organization/operating-points', ['facilityId' => $fid]);
            $this->grab('config-tables', 'organization/tables', ['facilityId' => $fid]);
            $this->grab('catalog-prep-route-stations', 'catalog/prep-route-stations', ['facilityId' => $fid]);
            $this->grab('audit-facility', 'audit', ['entityType' => 'Facility', 'entityId' => $fid, 'order' => 'desc', 'limit' => 20]);
        }
        $roles = $this->grab('roles', 'roles', ['limit' => 100]);
        $role = $this->find($roles, 'code', 'MANAGER') ?? ($roles['items'][0] ?? []);
        if (! empty($role['id'])) {
            $this->grab('role-permissions', "roles/{$role['id']}/permissions");
        }
        $resources = $this->grab('booking-resources', 'bookings/resources', ['limit' => 100]);
        if ($rid = $resources['items'][0]['id'] ?? null) {
            $this->grab('booking-resource-schedule', "bookings/resources/{$rid}/schedule");
            $this->grab('booking-resource-blackouts', "bookings/resources/{$rid}/blackouts");
            $this->grab('booking-resource-rules', "bookings/resources/{$rid}/rules");
        }
    }

    /** Waiter collection (docs/WAITER_COLLECTION.md): pending list, terminals, handovers, cash-in-hand, collection policy. */
    private function captureCollections(?string $fid): void
    {
        $pending = $this->grab('payments-pending-confirmation', 'payments', ['status' => 'PENDING_CONFIRMATION', 'limit' => 100]);
        $this->grab('payment-terminals', 'payment-terminals', ['limit' => 100]);
        $this->grab('cash-handovers', 'cash-handovers', ['limit' => 100]);
        $staff = $this->grab('staff', 'staff', ['limit' => 100]);
        $waiter = $this->find($staff, 'username', 'wait1') ?? $this->find($staff, 'staffNumber', 'S-0001');
        if (! empty($waiter['id'])) {
            $this->grab('staff-collection-policy', "staff/{$waiter['id']}/collection-policy");
            $this->grab('staff-cash-in-hand', "staff/{$waiter['id']}/cash-in-hand");
        }
        unset($pending);
    }

    /** @return array<mixed> */
    private function grab(string $name, string $path, array $query = []): array
    {
        $r = Http::acceptJson()->withToken($this->token)->get($this->base.$path, $query);
        if ($r->failed()) {
            $this->warn("{$name}: HTTP {$r->status()} (skipped)");
            $body = ['__status' => $r->status(), '__problem' => $r->json()];
            file_put_contents("{$this->out}/{$name}.error.json", json_encode(self::redact($body), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

            return [];
        }
        $json = self::redact((array) $r->json());
        file_put_contents("{$this->out}/{$name}.json", json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        $this->line("ok {$name}");

        return $json;
    }

    /** @param  array<mixed>  $list */
    private function find(array $list, string $key, string $value): ?array
    {
        foreach ($list['items'] ?? [] as $i) {
            if (($i[$key] ?? null) === $value) {
                return $i;
            }
        }

        return null;
    }

    /** Blank anything secret-looking (tokens, passwords, one-time codes, QR payloads, hashes of secrets). */
    public static function redact(mixed $v): mixed
    {
        if (! is_array($v)) {
            return $v;
        }
        foreach ($v as $k => $x) {
            if (is_string($k) && preg_match('/token|secret|password|pin$|qr|registrationCode|cardUid/i', $k) && ! preg_match('/^(tokenExpires|hasToken)/i', $k) && is_string($x)) {
                $v[$k] = 'REDACTED';
            } else {
                $v[$k] = self::redact($x);
            }
        }

        return $v;
    }
}
