<?php

namespace App\Support;

/**
 * Honest data-freshness assessment (heartbeat-and-node-health.md s5).
 *
 * A figure that originated at the site and is being shown from a mirror must
 * never look "live" when the sync is stale, the site is offline, or the API
 * did not say. Levels, worst first: offline > stale > unknown > live.
 */
final class DataFreshness
{
    public const LEVELS = ['live' => 0, 'unknown' => 1, 'stale' => 2, 'offline' => 3];

    public function __construct(
        public readonly string $level,
        public readonly string $message,
        public readonly ?string $lastSyncAt = null,
    ) {}

    public function isLive(): bool
    {
        return $this->level === 'live';
    }

    /**
     * Verdict about the node link itself (Sync & IT page), where there are no
     * report freshness blocks: only the sync status matters.
     *
     * @param  array<string, mixed>|null  $syncStatus
     */
    public static function forNode(?array $syncStatus): self
    {
        if ($syncStatus === null) {
            return new self('unknown', 'Node status is not available.');
        }

        $ref = $syncStatus['lastPeerHeartbeatAt'] ?? null;

        return match ($syncStatus['health'] ?? null) {
            'OFFLINE' => new self('offline', 'The peer node is OFFLINE'.($ref ? ' (last heartbeat '.Time::ago($ref).')' : '').'. Data on the other side is as of the last sync.', $ref),
            'DEGRADED' => new self('stale', 'Sync is degraded: the peer is reachable but the queues are behind.', $ref),
            'ONLINE' => new self('live', 'Peer node online'.($ref ? ', last heartbeat '.Time::ago($ref) : '').'.', $ref),
            default => new self('unknown', 'Node health is unknown.'),
        };
    }

    /**
     * @param  list<array<string, mixed>|null>  $freshnessBlocks  `freshness` blocks from report responses
     * @param  array<string, mixed>|null  $syncStatus  /sync/status (needs config.manage), when known
     */
    public static function assess(array $freshnessBlocks, ?array $syncStatus = null, ?string $instance = null): self
    {
        $instance ??= (string) config('r007.instance', 'local');
        $worst = new self('live', 'Live');
        $lastSync = null;

        $consider = function (self $c) use (&$worst): void {
            if (self::LEVELS[$c->level] > self::LEVELS[$worst->level]) {
                $worst = $c;
            }
        };

        if ($syncStatus !== null && ($syncStatus['health'] ?? null) === 'OFFLINE') {
            $ref = $syncStatus['lastPeerHeartbeatAt'] ?? $syncStatus['lastHeartbeatAt'] ?? null;
            $consider(new self('offline', 'Site is OFFLINE. Figures below are as of the last sync'.($ref ? ' ('.Time::ago($ref).')' : '').', not live.', $ref));
        } elseif ($syncStatus !== null && ($syncStatus['health'] ?? null) === 'DEGRADED') {
            $consider(new self('stale', 'Site sync is degraded. Figures may be behind.'));
        }

        $blocks = array_values(array_filter($freshnessBlocks));

        if ($blocks === []) {
            $consider(new self('unknown', 'The API did not report data freshness. Treat figures as unverified.'));
        }

        foreach ($blocks as $f) {
            $lastSync = $f['lastSyncAt'] ?? $lastSync;
            $age = $f['ageSeconds'] ?? null;
            $limit = $f['staleAfterSeconds'] ?? null;

            if (($f['stale'] ?? false) === true || ($age !== null && $limit !== null && $age > $limit)) {
                $why = $f['staleReason'] ?? null;
                $consider(new self('stale', 'Data may be out of date'.($why ? ": {$why}" : '').($f['lastSyncAt'] ?? null ? '. Last sync '.Time::ago($f['lastSyncAt']).'.' : '.'), $f['lastSyncAt'] ?? null));
            } elseif (($f['sourceNode'] ?? null) === 'cloud' && ($f['lastSyncAt'] ?? null) === null && $instance === 'cloud') {
                $consider(new self('unknown', 'This mirror has never confirmed a sync with the site.'));
            }
        }

        if ($worst->level === 'live') {
            $stamp = $blocks[0]['generatedAt'] ?? null;

            return new self('live', $stamp ? 'Live as of '.Time::format($stamp, 'H:i:s') : 'Live', $lastSync);
        }

        return new self($worst->level, $worst->message, $worst->lastSyncAt ?? $lastSync);
    }
}
