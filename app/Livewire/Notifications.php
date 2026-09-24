<?php

namespace App\Livewire;

use App\Auth\StaffSession;
use App\Services\R007Api\R007ApiClient;
use App\Support\Fetch;
use Livewire\Component;

/**
 * The bell in the top bar: things that need a person, each linking to where to fix it. Only reads what the
 * signed-in account may read; anything else is simply not listed.
 */
class Notifications extends Component
{
    public function render(R007ApiClient $api, StaffSession $staff)
    {
        $items = [];

        if ($staff->canApproveAnything()) {
            $a = Fetch::of(fn () => $api->get('approvals', ['filter[status]' => 'PENDING', 'scope' => 'approvable', 'limit' => 100]), ['GET', '/approvals']);
            $n = count($a->items());
            $n > 0 && $items[] = ['count' => $n, 'text' => $n === 1 ? '1 request waiting for your decision' : "{$n} requests waiting for your decision", 'route' => 'approvals', 'tone' => 'warn'];
        }

        if ($staff->can('inventory.view')) {
            $low = Fetch::of(fn () => $api->get('inventory/balances', ['belowReorder' => 1, 'limit' => 200]), ['GET', '/inventory/balances']);
            $n = count($low->items());
            $n > 0 && $items[] = ['count' => $n, 'text' => ($n >= 200 ? '200+' : $n).' stock line(s) at or below reorder level', 'route' => 'inventory.index', 'tone' => 'warn'];
        }

        if ($staff->can('config.manage')) {
            $s = Fetch::of(fn () => $api->get('sync/status'), ['GET', '/sync/status']);
            if ($s->ok()) {
                $d = (array) $s->data;
                $failed = (int) ($d['outbox']['failed'] ?? 0) + (int) ($d['inbox']['failed'] ?? 0);
                $conf = (int) ($d['openConflicts'] ?? 0);
                $failed > 0 && $items[] = ['count' => $failed, 'text' => "{$failed} sync event(s) failed", 'route' => 'sync', 'tone' => 'bad'];
                $conf > 0 && $items[] = ['count' => $conf, 'text' => "{$conf} sync conflict(s) need a decision", 'route' => 'sync', 'tone' => 'bad'];
                if (($d['health'] ?? null) !== 'ONLINE' && ! empty($d['lastError'])) {
                    $items[] = ['count' => 1, 'text' => 'The two nodes are not syncing: '.(is_scalar($d['lastError']) ? $d['lastError'] : 'see Sync & IT'), 'route' => 'sync', 'tone' => 'warn'];
                }
            }
        }

        return view('livewire.notifications', ['items' => $items, 'total' => array_sum(array_column($items, 'count'))]);
    }
}
