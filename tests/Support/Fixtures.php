<?php

namespace Tests\Support;

/** Small contract-shaped payloads for Http::fake based tests. */
final class Fixtures
{
    public const FAC = '0192f6a0-7b1c-7d2e-9a3b-000000000101';

    public const FAC2 = '0192f6a0-7b1c-7d2e-9a3b-000000000102';

    public const PAY = '0192f6a0-7b1c-7d2e-9a3b-000000000800';

    public const SESSION = '0192f6a0-7b1c-7d2e-9a3b-000000000700';

    public const STAFF = '0192f6a0-7b1c-7d2e-9a3b-000000000201';

    public static function freshness(bool $stale = false, string $node = 'local', array $over = []): array
    {
        return $over + ['generatedAt' => now()->utc()->toIso8601ZuluString(), 'sourceNode' => $node, 'lastSyncAt' => now()->utc()->subSeconds(20)->toIso8601ZuluString(),
            'stale' => $stale, 'staleReason' => $stale ? 'sync is behind' : null, 'ageSeconds' => $stale ? 1500 : 20, 'staleAfterSeconds' => 300];
    }

    public static function facilities(): array
    {
        return ['items' => [
            ['id' => self::FAC, 'siteId' => 's', 'parentId' => null, 'code' => 'REST', 'name' => 'Main Restaurant', 'kind' => 'RESTAURANT', 'status' => 'ACTIVE', 'capabilities' => [], 'children' => []],
            ['id' => self::FAC2, 'siteId' => 's', 'parentId' => null, 'code' => 'POOL', 'name' => 'Pool Bar', 'kind' => 'BAR', 'status' => 'ACTIVE', 'capabilities' => [], 'children' => []],
        ]];
    }

    public static function summary(string $facility, string $net = '100000.0000', array $freshness = []): array
    {
        return ['facilityId' => $facility, 'date' => '2026-09-23', 'orders' => 10, 'grossSales' => $net, 'discounts' => '0.0000', 'tax' => '0.0000', 'netSales' => $net, 'refunds' => '500.0000',
            'voids' => ['count' => 1, 'amount' => '1000.0000'], 'byTender' => [['tenderType' => 'CASH', 'amount' => '60000.0000', 'count' => 6], ['tenderType' => 'CARD', 'amount' => '40000.0000', 'count' => 4]],
            'topProducts' => [['productId' => 'p1', 'name' => 'Jollof Rice', 'quantity' => 12, 'revenue' => '42000.0000']], 'ticketsRedeemed' => 3, 'currency' => 'NGN', 'freshness' => $freshness ?: self::freshness()];
    }

    public static function payment(string $id = self::PAY, string $status = 'CAPTURED', string $amount = '12500.0000', array $over = []): array
    {
        return $over + ['id' => $id, 'facilityId' => self::FAC, 'tenderType' => 'CASH', 'provider' => 'MANUAL', 'providerReference' => null, 'status' => $status, 'amount' => $amount, 'refundedAmount' => '0.0000', 'currency' => 'NGN',
            'allocations' => [['orderId' => 'o1', 'amount' => $amount]], 'cashSessionId' => self::SESSION, 'receiptId' => 'r1', 'createdAt' => '2026-09-23T09:00:00.000000Z', 'capturedAt' => '2026-09-23T09:00:01.000000Z'];
    }

    public static function approval(string $id = 'a1', string $status = 'PENDING'): array
    {
        return ['id' => $id, 'action' => 'payment.refund', 'entityType' => 'payment', 'entityId' => self::PAY, 'status' => $status, 'requestedByName' => 'Bisi', 'requestedAt' => '2026-09-23T09:00:00.000000Z',
            'reason' => 'Wrong item', 'amount' => '2000.0000', 'summary' => 'Refund 2,000 on payment', 'requiredPermission' => 'refund.approve'];
    }

    public static function page(array $items): array
    {
        return ['items' => $items, 'nextCursor' => null];
    }
}
