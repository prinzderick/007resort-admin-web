<?php

namespace App\Support;

/** One mapping from a status string to a semantic tone, so the same word looks the same on every screen. */
final class Status
{
    private const MAP = [
        'success' => ['ACTIVE', 'ONLINE', 'LIVE', 'CAPTURED', 'PAID', 'COMPLETED', 'APPROVED', 'APPLIED', 'SYNCED', 'OK', 'POSTED', 'CLOSED', 'RESOLVED', 'CONFIRMED', 'AVAILABLE', 'SETTLED', 'ENABLED', 'REACTIVATED', 'SERVED', 'READY', 'PUBLISHED', 'REPLIED'],
        'warning' => ['PENDING', 'PENDING_APPROVAL', 'QUEUED', 'SYNCING', 'DEGRADED', 'AUTHORIZING', 'INITIATED', 'NEEDS_REVIEW', 'DRAFT', 'LOCAL', 'UNKNOWN', 'HELD', 'STALE', 'SUSPENDED', 'DEFERRED', 'SENT', 'DISABLED'],
        'danger' => ['FAILED', 'OFFLINE', 'REVOKED', 'REJECTED', 'CONFLICT', 'TERMINATED', 'REVERSED', 'EXPIRED', 'DOWN', 'CANCELLED', 'VOIDED', 'VOID', 'INACTIVE', 'DEACTIVATED', 'SPAM'],
        'info' => ['OPEN', 'PARTIALLY_REFUNDED', 'REFUNDED', 'EXHAUSTED', 'ACCESS', 'RENTAL', 'SCHEDULED', 'NEW'],
    ];

    public static function tone(?string $status): string
    {
        $s = strtoupper((string) $status);
        foreach (self::MAP as $tone => $words) {
            if (in_array($s, $words, true)) {
                return $tone;
            }
        }

        return 'neutral';
    }

    /** @return array{pill: string, dot: string} Tailwind classes */
    public static function classes(string $tone): array
    {
        return match ($tone) {
            'success' => ['pill' => 'bg-brand-50 text-brand-800 ring-brand-200', 'dot' => 'bg-brand-600'],
            'warning' => ['pill' => 'bg-amber-50 text-amber-900 ring-amber-200', 'dot' => 'bg-amber-500'],
            'danger' => ['pill' => 'bg-red-50 text-red-900 ring-red-200', 'dot' => 'bg-red-600'],
            'info' => ['pill' => 'bg-sky-50 text-sky-900 ring-sky-200', 'dot' => 'bg-sky-600'],
            default => ['pill' => 'bg-stone-100 text-stone-700 ring-stone-200', 'dot' => 'bg-stone-400'],
        };
    }
}
