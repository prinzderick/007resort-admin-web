<?php

namespace App\Livewire;

use App\Auth\StaffSession;
use App\Services\R007Api\R007ApiClient;
use App\Support\Fetch;
use App\Support\Time;
use Livewire\Component;

/**
 * The always-visible "Live / Stale / Offline" pill in the top bar, with when the two nodes last talked.
 * Health is public to every signed-in staff member; the sync detail (last push/pull) needs config.manage.
 */
class SiteStatus extends Component
{
    public function render(R007ApiClient $api, StaffSession $staff)
    {
        $health = Fetch::of(fn () => $api->get('system/health'), ['GET', '/system/health']);
        $sync = $staff->can('config.manage') ? Fetch::of(fn () => $api->get('sync/status'), ['GET', '/sync/status']) : new Fetch(null, 'forbidden');

        $h = $health->ok() ? (string) ($health->data['status'] ?? '') : '';
        $s = $sync->ok() ? (array) $sync->data : [];
        $level = match (true) {
            ! $health->ok() => 'offline',
            $h === 'down' || ($s['health'] ?? null) === 'OFFLINE' => 'offline',
            $h === 'ok' && (($s['health'] ?? 'ONLINE') === 'ONLINE') => 'live',
            $h === 'ok', $h === 'degraded' => 'stale',
            default => 'stale',
        };
        $lastSync = $s['lastPushAt'] ?? $s['lastPullAt'] ?? $s['lastPeerHeartbeatAt'] ?? null;
        $detail = match ($level) {
            'live' => 'All services are healthy and the two nodes are in sync.',
            'offline' => $health->ok() ? 'The site reports it is down.' : 'The API cannot be reached from this portal.',
            default => ($s['lastError'] ?? null) ? 'Sync problem: '.(is_scalar($s['lastError']) ? $s['lastError'] : json_encode($s['lastError'])) : 'Running, but the cloud link is behind or not confirmed. Figures may be out of date.',
        };

        return view('livewire.site-status', [
            'level' => $level,
            'label' => ['live' => 'Live', 'stale' => 'Stale', 'offline' => 'Offline'][$level],
            'detail' => $detail,
            'lastSync' => $lastSync ? Time::ago($lastSync) : ($sync->ok() ? 'never synced' : null),
            'checks' => $health->ok() ? (array) ($health->data['checks'] ?? []) : [],
        ]);
    }
}
