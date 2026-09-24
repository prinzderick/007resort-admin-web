<?php

namespace Tests\Support;

use Illuminate\Http\Client\Request;

/**
 * The API as it REALLY answers: JSON recorded from a running node (`php artisan r007:capture-fixtures`) into
 * tests/Fixtures/real, secrets redacted. A screen that reads a key the real API does not send fails here, not in production.
 */
final class RealApi
{
    public const FAC = 'a206b41c-f916-5185-b405-db8a5b67b4db';

    public static function load(string $name, string $dir = 'real'): array
    {
        $file = __DIR__.'/../Fixtures/'.$dir.'/'.$name.'.json';

        return is_file($file) ? (array) json_decode((string) file_get_contents($file), true) : [];
    }

    /** @return list<string> the recorded owner's permissions */
    public static function ownerPermissions(): array
    {
        return self::load('auth-me')['staff']['permissions'] ?? [];
    }

    /** @return array<string, list<string>> role code => permissions (from GET /roles) */
    public static function rolePermissions(): array
    {
        $out = [];
        foreach (self::load('roles')['items'] ?? [] as $r) {
            $out[$r['code']] = $r['permissions'];
        }

        return $out;
    }

    /**
     * Route map for TestCase::fakeApi(): every read the portal makes, answered with the recording. More specific patterns first.
     *
     * @return array<string, mixed>
     */
    public static function routes(bool $withConfig = false): array
    {
        $r = fn (string $n) => self::load($n);
        $routes = [
            'GET /auth/me' => $r('auth-me'),
            'GET /system/health' => $r('system-health'),
            'GET /sync/status' => $r('sync-status'),
            'GET /sync/outbox' => $r('sync-outbox'),
            'GET /sync/inbox-events' => $r('sync-inbox-events'),
            'GET /sync/conflicts' => self::load('sync-conflicts-open', 'synthetic'),
            'GET /organization/operating-points' => $r('config-operating-points'),
            'GET /organization/tables' => $r('config-tables'),
            'GET /admin/catalog/products/*' => $r('admin-catalog-product'),
            'GET /admin/catalog/products' => $r('admin-catalog-products'),
            'GET /catalog/price-lists' => $r('catalog-price-lists'),
            'GET /catalog/prices' => $r('catalog-prices'),
            'GET /catalog/products/*/stock-links' => $r('catalog-product-stock-links'),
            'GET /catalog/prep-route-stations' => $r('catalog-prep-route-stations'),
            'GET /admin/settings/business' => $r('admin-settings-business'),
            'GET /admin/settings/receipt' => $r('admin-settings-receipt'),
            'GET /admin/setup-status' => $r('admin-setup-status'),
            'GET /admin/search' => $r('admin-search'),
            'GET /facilities/*/payment-methods' => $r('facility-payment-methods'),
            'GET /ticketing/ticket-types' => $r('ticket-types'),
            'GET /bookings/resources/*/schedule' => $r('booking-resource-schedule'),
            'GET /bookings/resources/*/blackouts' => $r('booking-resource-blackouts'),
            'GET /bookings/resources/*/rules' => $r('booking-resource-rules'),
            'GET /roles/*/permissions' => $r('role-permissions'),
            'GET /organization/facilities/*/operating-points' => $r('organization-operating-points'),
            'GET /organization/facilities/*' => $r('organization-facility'),
            'GET /organization/facilities' => $r('organization-facilities'),
            'GET /organization/site' => $r('organization-site'),
            'GET /facilities/*/capabilities' => $r('facility-capabilities'),
            'GET /reports/facility-daily-summary' => $r('report-facility-daily-summary'),
            'GET /reports/revenue' => $r('report-revenue'),
            'GET /reports/cashier-shift/*' => $r('report-cashier-shift'),
            'GET /reports/attendance-summary' => $r('report-attendance-summary'),
            'GET /reports/membership-summary' => $r('report-membership-summary'),
            'GET /cash-sessions' => $r('cash-sessions'),
            'GET /payments/paystack/*' => $r('payment'),
            'GET /payments/*' => $r('payment'),
            'GET /payments' => fn (Request $q) => str_contains($q->url(), 'status=PENDING_CONFIRMATION') ? $r('payments-pending-confirmation') : $r('payments'),
            'GET /payment-terminals' => $r('payment-terminals'),
            'GET /cash-handovers' => $r('cash-handovers'),
            'GET /staff/*/collection-policy' => $r('staff-collection-policy'),
            'GET /staff/*/cash-in-hand' => $r('staff-cash-in-hand'),
            'GET /devices' => $r('devices'),
            'GET /attendance/devices' => $r('attendance-devices'),
            'GET /attendance/corrections' => $r('attendance-corrections'),
            'GET /attendance' => $r('attendance'),
            'GET /orders/*' => $r('order'),
            'GET /orders' => $r('orders'),
            'GET /tables' => $r('tables'),
            'GET /bookings/resources' => $r('booking-resources'),
            'GET /bookings' => $r('bookings'),
            'GET /catalog/availability' => $r('catalog-availability'),
            'GET /catalog/categories' => $r('catalog-categories'),
            'GET /catalog/products' => $r('catalog-products'),
            'GET /catalog/prep-routes' => $r('catalog-prep-routes'),
            'GET /catalog/tax-rates' => $r('catalog-tax-rates'),
            'GET /admin/settings/tax' => $r('admin-settings-tax'),
            'GET /memberships/plans' => $r('membership-plans'),
            'GET /memberships' => $r('memberships'),
            'GET /entitlements' => $r('entitlements'),
            'GET /inventory/items' => $r('inventory-items'),
            'GET /inventory/locations' => $r('inventory-locations'),
            'GET /inventory/balances' => $r('inventory-balances'),
            'GET /inventory/movements' => $r('inventory-movements'),
            'GET /inventory/adjustments' => $r('inventory-adjustments'),
            'GET /inventory/counts/*' => ['id' => 'c1', 'locationId' => self::load('inventory-locations')['items'][0]['id'], 'status' => 'DRAFT', 'lines' => [['itemId' => self::load('inventory-items')['items'][0]['id'], 'expectedQuantity' => '96.0000', 'countedQuantity' => '70.0000', 'variance' => null, 'varianceStatus' => null]], 'note' => null, 'createdAt' => '2026-09-23T23:51:37.389Z', 'postedAt' => null, 'adjustmentId' => null, 'adjustmentStatus' => null, 'approvalId' => null],
            'GET /inventory/counts' => $r('inventory-counts') ?: ['items' => [], 'nextCursor' => null],
            'GET /inventory/suppliers' => $r('inventory-suppliers'),
            'GET /kds/stations' => $r('kds-stations'),
            'GET /staff/*/role-assignments' => $r('staff-role-assignments'),
            'GET /staff/*' => $r('staff-member'),
            'GET /staff' => $r('staff'),
            'GET /roles' => $r('roles'),
            'GET /permissions' => $r('permissions'),
            'GET /audit' => fn (Request $q) => str_contains($q->url(), 'entityType=Facility') ? $r('audit-facility') : $r('audit'),
            'GET /approvals' => $r('approvals'),
        ];
        if ($withConfig) {
            $routes = [
                'GET /organization/facility-templates' => $r('organization-facility-templates'),
                'GET /organization/capability-types' => $r('organization-capability-types'),
                'GET /organization/rule-definitions' => $r('organization-rule-definitions'),
                'GET /facilities/*/operating-rules' => $r('operating-rules'),
            ] + $routes;
        }

        return $routes;
    }

    /** Every screen of the portal, with the placeholders the recordings fill in. */
    public static function pages(): array
    {
        $staff = self::load('staff')['items'][0]['id'] ?? 'x';
        $order = self::load('order')['id'] ?? 'x';
        $payment = self::load('payment')['id'] ?? 'x';
        $shift = self::load('cash-sessions')['items'][0]['id'] ?? 'x';
        $count = 'c1';
        $f = self::FAC;

        return [
            '/', '/approvals', '/search?q=rest', '/operations/orders', "/operations/orders/{$order}", '/operations/tables', '/operations/bookings', '/operations/tickets', '/operations/memberships',
            '/reports', "/reports/facility/{$f}", "/reports/shift/{$shift}", '/finance/payments', "/finance/payments/{$payment}", '/finance/refunds', '/finance/cash-sessions', '/finance/settlements',
            '/inventory', '/inventory/movements', '/inventory/adjustments', '/inventory/transfers', '/inventory/counts', "/inventory/count/{$count}", '/inventory/suppliers',
            '/inventory/receive', '/inventory/transfer', '/inventory/adjust', '/inventory/wastage', '/inventory/count',
            '/staff', "/staff/{$staff}", '/staff/attendance', '/people/roles', '/devices', '/system/audit',
            '/setup', '/setup/facilities', '/setup/facilities/new', "/setup/facilities/{$f}", "/setup/facilities/{$f}?tab=capabilities", "/setup/facilities/{$f}?tab=rules", "/setup/facilities/{$f}?tab=points",
            "/setup/facilities/{$f}?tab=devices", "/setup/facilities/{$f}?tab=products", "/setup/facilities/{$f}?tab=access",
            '/setup/catalog', '/setup/catalog?tab=categories', '/setup/catalog?tab=prices', '/setup/catalog?tab=tax', '/setup/catalog?tab=import', '/setup/catalog/products/'.(self::load('admin-catalog-product')['id'] ?? 'x'),
            '/setup/bookings', '/setup/bookings/'.(self::load('booking-resources')['items'][0]['id'] ?? 'x'), '/setup/tickets', '/setup/tickets?tab=issued', '/setup/memberships', '/setup/kds', '/setup/payments', '/setup/business',
            '/finance/collections', '/finance/handovers', '/devices/payment-terminals', '/sync',
        ];
    }
}
