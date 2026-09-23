<?php

namespace App\Services\R007Api\Mock;

use Carbon\CarbonImmutable;

/**
 * Fixture world for R007_MOCK=true. Shapes follow the API contract v1 exactly
 * (camelCase, decimal-string money, UTC ISO timestamps) so the UI exercises the
 * same code paths it will against the real API.
 */
final class MockData
{
    public const PASSWORD = 'password';

    public static function id(int $n): string
    {
        return sprintf('0192f6a0-7b1c-7d2e-9a3b-%012x', $n);
    }

    public static function now(int $minusSeconds = 0): string
    {
        return CarbonImmutable::now('UTC')->subSeconds($minusSeconds)->format('Y-m-d\TH:i:s.u\Z');
    }

    /** @return array<string, array{name: string, roles: list<string>, permissions: list<string>}> */
    public static function personas(): array
    {
        $ops = ['report.view', 'payment.view', 'order.view', 'booking.view', 'inventory.view', 'attendance.view', 'ticket.view'];

        return [
            'owner' => ['name' => 'Ngozi Okafor (Owner)', 'roles' => ['owner'], 'permissions' => array_merge($ops, [
                'report.view.all', 'finance.report.view', 'refund.execute', 'refund.approve', 'payment.reversal.execute', 'payment.reversal.approve',
                'settlement.reconcile', 'order.void.approve', 'order.discount.approve', 'inventory.adjustment.approve', 'inventory.adjustment.request',
                'inventory.purchase_receipt.create', 'inventory.transfer.create', 'inventory.count.create', 'inventory.count.post', 'inventory.wastage.create',
                'staff.manage', 'role_assignment.manage', 'audit.view', 'staff.clock_correction.approve', 'config.manage', 'facility.configure',
                'pricing.manage', 'membership.plan.manage', 'catalog.availability.manage', 'device.register', 'device.revoke', 'session.revoke',
                'attendance.device.manage', 'cash_session.view',
            ])],
            'manager' => ['name' => 'Tunde Adebayo (Manager)', 'roles' => ['manager'], 'permissions' => array_merge($ops, [
                'report.view.all', 'order.void.approve', 'order.discount.approve', 'inventory.adjustment.approve', 'inventory.adjustment.request',
                'inventory.transfer.create', 'inventory.count.create', 'inventory.count.post', 'staff.manage', 'role_assignment.manage', 'audit.view',
                'staff.clock_correction.approve', 'facility.configure', 'pricing.manage', 'membership.plan.manage', 'catalog.availability.manage', 'cash_session.view',
            ])],
            'accounts' => ['name' => 'Amaka Eze (Accountant)', 'roles' => ['accountant'], 'permissions' => [
                'report.view', 'payment.view', 'finance.report.view', 'settlement.reconcile', 'refund.approve', 'payment.reversal.approve', 'refund.execute', 'cash_session.view', 'audit.view',
            ]],
            'it' => ['name' => 'Emeka Nwosu (IT)', 'roles' => ['it'], 'permissions' => [
                'device.register', 'device.revoke', 'session.revoke', 'config.manage', 'attendance.device.manage', 'security_event.view', 'audit.view',
            ]],
            'cashier' => ['name' => 'Bisi Lawal (Cashier)', 'roles' => ['cashier'], 'permissions' => ['payment.take', 'order.create']],
        ];
    }

    /** @return array<string, mixed> */
    public static function seed(string $scenario): array
    {
        $f = [
            'restaurant' => self::id(0x101), 'poolbar' => self::id(0x102), 'sports' => self::id(0x103),
            'entrance' => self::id(0x104), 'store' => self::id(0x105), 'spa' => self::id(0x106), 'reception' => self::id(0x107),
        ];
        $site = self::id(0x1);

        $facilities = [
            ['id' => $f['restaurant'], 'siteId' => $site, 'parentId' => null, 'code' => 'REST', 'name' => 'Main Restaurant', 'kind' => 'RESTAURANT', 'status' => 'ACTIVE', 'capabilities' => ['TABLE_SERVICE', 'KDS'], 'rowVersion' => 3],
            ['id' => $f['poolbar'], 'siteId' => $site, 'parentId' => null, 'code' => 'POOL', 'name' => 'Pool Bar', 'kind' => 'BAR', 'status' => 'ACTIVE', 'capabilities' => ['TABLE_SERVICE', 'BAR_DISPENSE', 'POOL_TICKETS'], 'rowVersion' => 2],
            ['id' => $f['sports'], 'siteId' => $site, 'parentId' => null, 'code' => 'SPORT', 'name' => 'Sports Complex', 'kind' => 'SPORTS', 'status' => 'ACTIVE', 'capabilities' => ['BOOKINGS'], 'rowVersion' => 1],
            ['id' => $f['entrance'], 'siteId' => $site, 'parentId' => $f['sports'], 'code' => 'SPORT-ENT', 'name' => 'Sports Entrance', 'kind' => 'ENTRANCE', 'status' => 'ACTIVE', 'capabilities' => ['TICKET_VALIDATION'], 'rowVersion' => 1],
            ['id' => $f['store'], 'siteId' => $site, 'parentId' => $f['sports'], 'code' => 'SPORT-STORE', 'name' => 'Sports Store', 'kind' => 'STORE', 'status' => 'ACTIVE', 'capabilities' => ['RENTALS'], 'rowVersion' => 1],
            ['id' => $f['spa'], 'siteId' => $site, 'parentId' => null, 'code' => 'SPA', 'name' => 'Spa', 'kind' => 'SPA', 'status' => 'ACTIVE', 'capabilities' => ['BOOKINGS'], 'rowVersion' => 1],
            ['id' => $f['reception'], 'siteId' => $site, 'parentId' => null, 'code' => 'RECEP', 'name' => 'Reception', 'kind' => 'RECEPTION', 'status' => 'ACTIVE', 'capabilities' => ['MEMBERSHIP_SALES'], 'rowVersion' => 1],
        ];

        $rules = fn (string $thr): array => [
            'approvalThresholdAmount' => $thr, 'requireApprovalFor' => ['VOID', 'REFUND', 'DISCOUNT'], 'allowOpenTabs' => true, 'requireCashSession' => true,
            'allowOfflineOrders' => true, 'allowOfflinePayments' => 'CASH_ONLY', 'holdTtlSeconds' => 600, 'vatEnabled' => false, 'vatRatePercent' => '7.5',
        ];

        $staff = [];
        $names = [['S-0001', 'Ngozi', 'Okafor', 'ACTIVE', true, true, true], ['S-0002', 'Tunde', 'Adebayo', 'ACTIVE', true, true, false], ['S-0003', 'Amaka', 'Eze', 'ACTIVE', true, false, false],
            ['S-0004', 'Emeka', 'Nwosu', 'ACTIVE', true, false, false], ['S-0005', 'Bisi', 'Lawal', 'ACTIVE', false, true, true], ['S-0006', 'Chidi', 'Obi', 'ACTIVE', false, true, true],
            ['S-0007', 'Funke', 'Balogun', 'SUSPENDED', false, true, false], ['S-0008', 'Kelechi', 'Umeh', 'ACTIVE', false, true, true]];
        foreach ($names as $i => [$num, $first, $last, $status, $pw, $pin, $nfc]) {
            $staff[] = ['id' => self::id(0x200 + $i), 'displayName' => "$first $last", 'staffNumber' => $num, 'status' => $status, 'email' => strtolower("$first.$last").'@007resort.example',
                'phone' => '+23480000'.str_pad((string) (1000 + $i), 4, '0', STR_PAD_LEFT), 'rowVersion' => 1, 'firstName' => $first, 'lastName' => $last, 'siteId' => $site,
                'hasPassword' => $pw, 'hasPin' => $pin, 'hasNfcCard' => $nfc];
        }

        $roles = [
            ['id' => self::id(0x300), 'code' => 'owner', 'name' => 'Owner / super admin', 'permissions' => ['*']],
            ['id' => self::id(0x301), 'code' => 'manager', 'name' => 'Manager', 'permissions' => ['staff.manage', 'facility.configure', 'pricing.manage', 'report.view.all']],
            ['id' => self::id(0x302), 'code' => 'accountant', 'name' => 'Accountant', 'permissions' => ['finance.report.view', 'settlement.reconcile', 'refund.approve']],
            ['id' => self::id(0x303), 'code' => 'it', 'name' => 'IT / system administrator', 'permissions' => ['device.register', 'device.revoke', 'config.manage']],
            ['id' => self::id(0x304), 'code' => 'cashier', 'name' => 'Cashier', 'permissions' => ['payment.take', 'order.settle', 'cash_session.open', 'cash_session.close']],
            ['id' => self::id(0x305), 'code' => 'storekeeper', 'name' => 'Storekeeper', 'permissions' => ['inventory.receive', 'inventory.transfer.create', 'inventory.count.create']],
        ];

        $devices = [
            ['id' => self::id(0x400), 'name' => 'POS-01', 'kind' => 'POS_TERMINAL', 'status' => 'ACTIVE', 'facilityId' => $f['restaurant'], 'homeFacilityId' => $f['restaurant'], 'platform' => 'windows', 'appVersion' => '1.0.3', 'lastSeenAt' => self::now(20), 'checkout' => ['staffId' => self::id(0x204), 'facilityId' => $f['restaurant'], 'shiftId' => null, 'checkedOutAt' => self::now(14000), 'checkedInAt' => null], 'rowVersion' => 4],
            ['id' => self::id(0x401), 'name' => 'POS-02', 'kind' => 'POS_TERMINAL', 'status' => 'ACTIVE', 'facilityId' => $f['poolbar'], 'homeFacilityId' => $f['poolbar'], 'platform' => 'windows', 'appVersion' => '1.0.3', 'lastSeenAt' => self::now(45), 'checkout' => ['staffId' => self::id(0x205), 'facilityId' => $f['poolbar'], 'shiftId' => null, 'checkedOutAt' => self::now(9000), 'checkedInAt' => null], 'rowVersion' => 2],
            ['id' => self::id(0x402), 'name' => 'Tablet-03', 'kind' => 'MOBILE_TABLET', 'status' => 'ACTIVE', 'facilityId' => $f['restaurant'], 'homeFacilityId' => $f['restaurant'], 'platform' => 'android', 'appVersion' => '0.9.1', 'lastSeenAt' => self::now(600), 'checkout' => null, 'rowVersion' => 2],
            ['id' => self::id(0x403), 'name' => 'KDS-Kitchen', 'kind' => 'KDS_SCREEN', 'status' => 'ACTIVE', 'facilityId' => $f['restaurant'], 'homeFacilityId' => $f['restaurant'], 'platform' => 'web', 'appVersion' => '0.5.0', 'lastSeenAt' => self::now(12), 'checkout' => null, 'rowVersion' => 1],
            ['id' => self::id(0x404), 'name' => 'Entrance-Scanner-1', 'kind' => 'ENTRANCE_SCANNER', 'status' => 'ACTIVE', 'facilityId' => $f['entrance'], 'homeFacilityId' => $f['entrance'], 'platform' => 'android', 'appVersion' => '0.9.1', 'lastSeenAt' => self::now(90), 'checkout' => null, 'rowVersion' => 1],
            ['id' => self::id(0x405), 'name' => 'Tablet-lost', 'kind' => 'MOBILE_TABLET', 'status' => 'REVOKED', 'facilityId' => null, 'homeFacilityId' => $f['spa'], 'platform' => 'android', 'appVersion' => '0.8.0', 'lastSeenAt' => self::now(864000), 'checkout' => null, 'rowVersion' => 5],
        ];

        $items = [
            ['id' => self::id(0x500), 'sku' => 'BEV-001', 'name' => 'Star Lager 60cl', 'unit' => 'bottle', 'category' => 'Beverages', 'reorderLevel' => '48', 'active' => true, 'rowVersion' => 1],
            ['id' => self::id(0x501), 'sku' => 'BEV-002', 'name' => 'Bottled Water 75cl', 'unit' => 'bottle', 'category' => 'Beverages', 'reorderLevel' => '60', 'active' => true, 'rowVersion' => 1],
            ['id' => self::id(0x502), 'sku' => 'FOOD-001', 'name' => 'Long-grain Rice 50kg', 'unit' => 'bag', 'category' => 'Dry goods', 'reorderLevel' => '4', 'active' => true, 'rowVersion' => 1],
            ['id' => self::id(0x503), 'sku' => 'FOOD-002', 'name' => 'Chicken (frozen)', 'unit' => 'kg', 'category' => 'Protein', 'reorderLevel' => '25', 'active' => true, 'rowVersion' => 1],
            ['id' => self::id(0x504), 'sku' => 'SPA-001', 'name' => 'Massage oil 1L', 'unit' => 'bottle', 'category' => 'Spa', 'reorderLevel' => '6', 'active' => true, 'rowVersion' => 1],
        ];
        $locations = [
            ['id' => self::id(0x600), 'facilityId' => null, 'name' => 'Main Store', 'kind' => 'MAIN_STORE'],
            ['id' => self::id(0x601), 'facilityId' => $f['poolbar'], 'name' => 'Pool Bar Store', 'kind' => 'BAR'],
            ['id' => self::id(0x602), 'facilityId' => $f['restaurant'], 'name' => 'Kitchen Store', 'kind' => 'KITCHEN'],
        ];
        $q = [[0x500, 0x600, '240'], [0x500, 0x601, '30'], [0x501, 0x600, '400'], [0x501, 0x601, '18'], [0x502, 0x600, '9'], [0x502, 0x602, '2'], [0x503, 0x602, '14.5'], [0x504, 0x600, '3']];
        $balances = [];
        foreach ($q as [$i, $l, $qty]) {
            $item = collect($items)->firstWhere('id', self::id($i));
            $balances[] = ['itemId' => self::id($i), 'itemName' => $item['name'], 'locationId' => self::id($l), 'quantity' => $qty, 'unit' => $item['unit'], 'updatedAt' => self::now(3600)];
        }

        $payments = [];
        $sessions = [
            ['id' => self::id(0x700), 'facilityId' => $f['restaurant'], 'deviceId' => self::id(0x400), 'staffId' => self::id(0x204), 'status' => 'OPEN', 'openingFloat' => '20000.0000', 'expectedCash' => '68500.0000', 'countedCash' => null, 'variance' => null, 'openedAt' => self::now(14000), 'closedAt' => null],
            ['id' => self::id(0x701), 'facilityId' => $f['poolbar'], 'deviceId' => self::id(0x401), 'staffId' => self::id(0x205), 'status' => 'OPEN', 'openingFloat' => '10000.0000', 'expectedCash' => '31000.0000', 'countedCash' => null, 'variance' => null, 'openedAt' => self::now(9000), 'closedAt' => null],
            ['id' => self::id(0x702), 'facilityId' => $f['restaurant'], 'deviceId' => self::id(0x400), 'staffId' => self::id(0x207), 'status' => 'CLOSED', 'openingFloat' => '20000.0000', 'expectedCash' => '95000.0000', 'countedCash' => '94500.0000', 'variance' => '-500.0000', 'openedAt' => self::now(90000), 'closedAt' => self::now(60000)],
        ];
        $rows = [
            [0x800, 'restaurant', 'CASH', 'CAPTURED', '12500.0000', 0x700, null], [0x801, 'restaurant', 'CARD', 'CAPTURED', '38000.0000', 0x700, 'PAYSTACK'],
            [0x802, 'restaurant', 'TRANSFER', 'CAPTURED', '22000.0000', 0x700, null], [0x803, 'restaurant', 'CASH', 'PARTIALLY_REFUNDED', '9000.0000', 0x700, null],
            [0x804, 'poolbar', 'CASH', 'CAPTURED', '8000.0000', 0x701, null], [0x805, 'poolbar', 'CARD', 'AUTHORIZING', '15000.0000', 0x701, 'PAYSTACK'],
            [0x806, 'poolbar', 'POS_TERMINAL', 'CAPTURED', '27500.0000', 0x701, null], [0x807, 'restaurant', 'CASH', 'REVERSED', '4500.0000', 0x702, null],
            [0x808, 'sports', 'CARD', 'CAPTURED', '45000.0000', null, 'PAYSTACK'], [0x809, 'spa', 'TRANSFER', 'CAPTURED', '60000.0000', null, null],
        ];
        foreach ($rows as [$n, $fac, $tender, $status, $amount, $sess, $prov]) {
            $payments[] = ['id' => self::id($n), 'groupId' => self::id($n + 0x100), 'facilityId' => $f[$fac], 'tenderType' => $tender, 'provider' => $prov ?? 'MANUAL',
                'providerReference' => $prov ? 'PSK_'.strtoupper(dechex($n * 7919)) : null, 'reference' => null, 'status' => $status, 'amount' => $amount, 'tendered' => null,
                'changeGiven' => null, 'refundedAmount' => $status === 'PARTIALLY_REFUNDED' ? '2000.0000' : '0.0000', 'currency' => 'NGN',
                'allocations' => [['orderId' => self::id($n + 0x200), 'amount' => $amount]], 'cashSessionId' => $sess ? self::id($sess) : null, 'receiptId' => self::id($n + 0x300),
                'takenByStaffId' => self::id(0x204), 'createdAt' => self::now(($n - 0x7FF) * 1500), 'capturedAt' => $status === 'AUTHORIZING' ? null : self::now(($n - 0x7FF) * 1500 - 5)];
        }

        $approvals = [
            ['id' => self::id(0x900), 'action' => 'order.void', 'entityType' => 'order', 'entityId' => self::id(0xA00), 'facilityId' => $f['restaurant'], 'status' => 'PENDING', 'requestedByStaffId' => self::id(0x204), 'requestedByName' => 'Bisi Lawal', 'requestedAt' => self::now(420), 'reason' => 'Customer left before food was served', 'amount' => '14500.0000', 'summary' => 'Void order #R-1042 (Table 6)', 'decidedByStaffId' => null, 'decidedAt' => null, 'decisionNote' => null, 'requiredPermission' => 'order.void.approve'],
            ['id' => self::id(0x901), 'action' => 'payment.refund', 'entityType' => 'payment', 'entityId' => self::id(0x803), 'facilityId' => $f['restaurant'], 'status' => 'PENDING', 'requestedByStaffId' => self::id(0x204), 'requestedByName' => 'Bisi Lawal', 'requestedAt' => self::now(1800), 'reason' => 'Wrong item charged', 'amount' => '2000.0000', 'summary' => 'Refund NGN 2,000.00 on payment 0803', 'decidedByStaffId' => null, 'decidedAt' => null, 'decisionNote' => null, 'requiredPermission' => 'refund.approve'],
            ['id' => self::id(0x902), 'action' => 'inventory.adjustment', 'entityType' => 'stock_movement', 'entityId' => self::id(0xA02), 'facilityId' => $f['poolbar'], 'status' => 'PENDING', 'requestedByStaffId' => self::id(0x205), 'requestedByName' => 'Chidi Obi', 'requestedAt' => self::now(7200), 'reason' => 'Breakage during delivery', 'amount' => null, 'summary' => 'Adjust Star Lager 60cl by -12 at Pool Bar Store', 'decidedByStaffId' => null, 'decidedAt' => null, 'decisionNote' => null, 'requiredPermission' => 'inventory.adjustment.approve'],
            ['id' => self::id(0x903), 'action' => 'order.discount', 'entityType' => 'order_line', 'entityId' => self::id(0xA03), 'facilityId' => $f['restaurant'], 'status' => 'APPROVED', 'requestedByStaffId' => self::id(0x204), 'requestedByName' => 'Bisi Lawal', 'requestedAt' => self::now(90000), 'reason' => 'Loyal guest', 'amount' => '1500.0000', 'summary' => '10% discount on order #R-1002', 'decidedByStaffId' => self::id(0x201), 'decidedAt' => self::now(89000), 'decisionNote' => null, 'requiredPermission' => 'order.discount.approve'],
        ];

        $outbox = [];
        $st = ['SYNCED', 'SYNCED', 'QUEUED', 'FAILED', 'CONFLICT', 'SYNCING'];
        foreach ($st as $i => $s) {
            $outbox[] = ['id' => self::id(0xB00 + $i), 'seq' => 1000 + $i, 'eventType' => ['PaymentCompleted', 'StockAdjusted', 'OrderSettled', 'StaffUpdated', 'ProductPriceChanged', 'OrderCreated'][$i], 'entityType' => 'x', 'entityId' => self::id(0xC00 + $i), 'entityVersion' => 2,
                'syncStatus' => $s, 'retryCount' => $s === 'FAILED' ? 5 : 0, 'nextRetryAt' => null, 'lastAttemptAt' => self::now(300), 'lastError' => $s === 'FAILED' ? 'HTTP 502 from peer node' : null, 'createdAt' => self::now(3000 - $i * 100)];
        }
        $inbox = [
            ['id' => self::id(0xD00), 'eventType' => 'OnlineOrderCreated', 'sourceNode' => 'cloud', 'receivedAt' => self::now(500), 'processedAt' => self::now(499), 'result' => 'APPLIED', 'conflictDetail' => null],
            ['id' => self::id(0xD01), 'eventType' => 'ProductPriceChanged', 'sourceNode' => 'cloud', 'receivedAt' => self::now(400), 'processedAt' => null, 'result' => 'CONFLICT', 'conflictDetail' => ['reason' => 'version mismatch']],
            ['id' => self::id(0xD02), 'eventType' => 'StaffUpdated', 'sourceNode' => 'cloud', 'receivedAt' => self::now(300), 'processedAt' => null, 'result' => 'FAILED', 'conflictDetail' => ['error' => 'unknown role']],
        ];
        $conflicts = [
            ['id' => self::id(0xE00), 'category' => 'CONFIG_VERSION', 'entityType' => 'product', 'entityId' => self::id(0xF00), 'status' => 'OPEN', 'eventId' => self::id(0xD01), 'localVersion' => 7, 'incomingVersion' => 7,
                'localPayload' => ['name' => 'Jollof Rice', 'price' => '3500.0000'], 'incomingPayload' => ['name' => 'Jollof Rice', 'price' => '3800.0000'], 'resolution' => null, 'note' => null, 'createdAt' => self::now(400), 'resolvedAt' => null, 'resolvedByStaffId' => null],
            ['id' => self::id(0xE01), 'category' => 'STAFF_VERSION', 'entityType' => 'staff', 'entityId' => self::id(0x203), 'status' => 'RESOLVED', 'eventId' => null, 'localVersion' => 3, 'incomingVersion' => 3,
                'localPayload' => ['phone' => '+2348000001'], 'incomingPayload' => ['phone' => '+2348000002'], 'resolution' => 'KEEP_LOCAL', 'note' => 'Confirmed with HR', 'createdAt' => self::now(90000), 'resolvedAt' => self::now(80000), 'resolvedByStaffId' => self::id(0x201)],
        ];

        $audit = [];
        $acts = [['staff.update', 'staff'], ['payment.refund.request', 'payment'], ['order.void.approve', 'order'], ['device.revoke', 'device'], ['inventory.adjustment.request', 'stock_movement'], ['config.tax.update', 'setting'], ['auth.login', 'session'], ['role.grant', 'role_assignment']];
        $prev = str_repeat('0', 64);
        foreach ($acts as $i => [$a, $e]) {
            $hash = hash('sha256', $prev.$a.$i);
            $audit[] = ['id' => self::id(0x1000 + $i), 'seq' => 500 + $i, 'occurredAt' => self::now(($i + 1) * 3300), 'actorStaffId' => self::id(0x200 + ($i % 4)), 'actorName' => $names[$i % 4][1].' '.$names[$i % 4][2], 'deviceId' => null, 'action' => $a, 'entityType' => $e,
                'entityId' => self::id(0x2000 + $i), 'facilityId' => null, 'before' => $i === 0 ? ['status' => 'ACTIVE'] : null, 'after' => $i === 0 ? ['status' => 'SUSPENDED'] : ['note' => 'ok'], 'reason' => null, 'prevHash' => $prev, 'hash' => $hash, 'correlationId' => 'c-'.$i];
            $prev = $hash;
        }
        $audit = array_reverse($audit);

        $categories = [['id' => self::id(0x1100), 'parentId' => null, 'name' => 'Food', 'sortOrder' => 1], ['id' => self::id(0x1101), 'parentId' => null, 'name' => 'Drinks', 'sortOrder' => 2], ['id' => self::id(0x1102), 'parentId' => null, 'name' => 'Tickets', 'sortOrder' => 3]];
        $prods = [['Jollof Rice & Chicken', 0x1100, 'FOOD', '3500.0000', 'KITCHEN'], ['Suya Platter', 0x1100, 'FOOD', '4500.0000', 'KITCHEN'], ['Star Lager 60cl', 0x1101, 'DRINK', '1200.0000', 'BAR'], ['Bottled Water', 0x1101, 'DRINK', '400.0000', 'BAR'], ['Pool Day Pass', 0x1102, 'TICKET', '5000.0000', 'NONE']];
        $products = [];
        foreach ($prods as $i => [$n, $c, $k, $p, $route]) {
            $products[] = ['id' => self::id(0x1200 + $i), 'sku' => 'P-'.(100 + $i), 'name' => $n, 'categoryId' => self::id($c), 'kind' => $k, 'price' => $p, 'currency' => 'NGN', 'taxInclusive' => true, 'taxRatePercent' => '0', 'taxAmount' => '0.0000',
                'prepRoute' => ['stationId' => $route === 'NONE' ? null : self::id(0x1300 + ($route === 'BAR' ? 1 : 0)), 'stationName' => $route === 'BAR' ? 'Bar' : ($route === 'KITCHEN' ? 'Kitchen' : null), 'kind' => $route], 'trackStock' => $k !== 'TICKET', 'active' => true, 'rowVersion' => 2];
        }

        $att = [];
        foreach ([[0x204, 'Bisi Lawal', 'OPEN'], [0x205, 'Chidi Obi', 'OPEN'], [0x207, 'Kelechi Umeh', 'CLOSED'], [0x202, 'Amaka Eze', 'NEEDS_REVIEW'], [0x201, 'Tunde Adebayo', 'CLOSED']] as $i => [$sid, $nm, $s]) {
            $att[] = ['id' => self::id(0x1400 + $i), 'staffId' => self::id($sid), 'staffName' => $nm, 'workDate' => CarbonImmutable::now('Africa/Lagos')->toDateString(), 'clockIn' => self::now(30000 + $i * 600), 'clockOut' => $s === 'CLOSED' ? self::now(1200) : null, 'minutesWorked' => $s === 'CLOSED' ? 470 : null, 'source' => 'BIOMETRIC', 'status' => $s];
        }

        $orders = [];
        foreach (['SENT', 'IN_PREPARATION', 'READY', 'SERVED', 'SETTLED', 'SETTLED', 'VOIDED', 'PENDING_APPROVAL', 'DRAFT'] as $i => $s) {
            $orders[] = ['id' => self::id(0x1500 + $i), 'number' => 'R-'.(1040 + $i), 'facilityId' => $f['restaurant'], 'tableId' => null, 'tableLabel' => 'T'.($i + 1), 'tabId' => null, 'status' => $s, 'total' => (string) (3500 + $i * 1000).'.0000', 'balanceDue' => in_array($s, ['SETTLED', 'VOIDED'], true) ? '0.0000' : (string) (3500 + $i * 1000).'.0000', 'lineCount' => 2, 'createdAt' => self::now(600 + $i * 300)];
        }
        $bookings = [];
        foreach (['CONFIRMED', 'CONFIRMED', 'HELD', 'COMPLETED', 'CANCELLED'] as $i => $s) {
            $bookings[] = ['id' => self::id(0x1600 + $i), 'number' => 'B-'.(200 + $i), 'resourceId' => self::id(0x1700), 'resourceName' => 'Tennis Court 1', 'facilityId' => $f['sports'], 'start' => self::now(-3600 * ($i + 1)), 'end' => self::now(-3600 * ($i + 2)), 'quantity' => 1, 'status' => $s, 'holdExpiresAt' => null, 'total' => '8000.0000', 'amountPaid' => $s === 'HELD' ? '0.0000' : '8000.0000', 'source' => $i % 2 ? 'ONLINE' : 'STAFF', 'rowVersion' => 1, 'createdAt' => self::now(90000)];
        }

        return [
            'scenario' => $scenario,
            'site' => ['id' => $site, 'organizationId' => self::id(0x2), 'name' => '007 Resort & Spa', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN', 'address' => 'Otueke, Nigeria'],
            'facilities' => $facilities, 'rules' => array_values(array_map(fn () => $rules('5000.0000'), $f)),
            'staff' => $staff, 'roles' => $roles, 'assignments' => [], 'devices' => $devices,
            'items' => $items, 'locations' => $locations, 'balances' => $balances,
            'payments' => $payments, 'cashSessions' => $sessions, 'approvals' => $approvals, 'refunds' => [],
            'outbox' => $outbox, 'inbox' => $inbox, 'conflicts' => $conflicts, 'audit' => $audit,
            'categories' => $categories, 'products' => $products, 'attendance' => $att, 'orders' => $orders, 'bookings' => $bookings,
            'corrections' => [['id' => self::id(0x1800), 'staffId' => self::id(0x202), 'workDate' => CarbonImmutable::now('Africa/Lagos')->toDateString(), 'clockIn' => self::now(30000), 'clockOut' => self::now(2000), 'reason' => 'Forgot to clock out', 'status' => 'PENDING', 'requestedByStaffId' => self::id(0x202), 'decidedByStaffId' => null, 'createdAt' => self::now(1000)]],
            'plans' => [['id' => self::id(0x1900), 'name' => 'Monthly Gym', 'durationDays' => 30, 'price' => '25000.0000', 'currency' => 'NGN', 'facilityIds' => [$f['sports']], 'visitLimit' => null, 'active' => true], ['id' => self::id(0x1901), 'name' => 'Family Annual', 'durationDays' => 365, 'price' => '450000.0000', 'currency' => 'NGN', 'facilityIds' => [], 'visitLimit' => 120, 'active' => true]],
            'resources' => [['id' => self::id(0x1700), 'facilityId' => $f['sports'], 'name' => 'Tennis Court 1', 'mode' => 'TIME_SLOT', 'capacity' => 1, 'slotMinutes' => 60, 'productId' => null, 'price' => '8000.0000', 'active' => true], ['id' => self::id(0x1701), 'facilityId' => $f['spa'], 'name' => 'Massage Room A', 'mode' => 'TIME_SLOT', 'capacity' => 1, 'slotMinutes' => 90, 'productId' => null, 'price' => '30000.0000', 'active' => true]],
            'stations' => [['id' => self::id(0x1300), 'facilityId' => $f['restaurant'], 'name' => 'Kitchen', 'kind' => 'KITCHEN', 'active' => true], ['id' => self::id(0x1301), 'facilityId' => $f['restaurant'], 'name' => 'Bar', 'kind' => 'BAR', 'active' => true]],
            'tax' => ['vatEnabled' => false, 'vatRatePercent' => '7.5', 'pricesTaxInclusive' => true, 'vatNumber' => null, 'rowVersion' => 1],
            'attDevices' => [['id' => self::id(0x1A00), 'serial' => 'ZK-GATE-0001', 'adapter' => 'ZKTECO_ADMS', 'timeZone' => 'Africa/Lagos', 'status' => 'ACTIVE', 'lastSeenAt' => self::now(60)]],
            'seq' => 0,
        ];
    }
}
