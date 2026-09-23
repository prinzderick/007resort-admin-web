<?php

namespace App\Livewire;

use App\Services\R007Api\R007ApiClient;
use App\Services\R007Api\R007ApiException;
use App\Support\DataFreshness;
use App\Support\Fetch;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Sync & IT: node health, outbox/inbox queues and the conflicts list with
 * retry / reprocess / resolve actions (all audited by the API, config.manage).
 */
#[Layout('components.layouts.app')]
#[Title('Sync & IT')]
class SyncCenter extends Component
{
    public string $tab = 'health';

    public string $outboxStatus = '';

    public string $inboxResult = '';

    public string $conflictStatus = 'OPEN';

    public ?string $openConflict = null;

    /** @var array<string, string> */
    public array $resolution = [];

    /** @var array<string, string> */
    public array $notes = [];

    public ?string $message = null;

    public string $messageTone = 'ok';

    private function run(callable $fn, ?string $ok): void
    {
        try {
            $fn();
            $ok !== null && $this->message = $ok;
            $this->messageTone = 'ok';
        } catch (R007ApiException $e) {
            $this->message = $e->isForbidden() ? 'You do not have permission for this action.' : ($e->detail ?: $e->title);
            $this->messageTone = 'error';
        }
    }

    public function retryOutbox(string $id, R007ApiClient $api): void
    {
        $this->run(fn () => $api->post("sync/outbox/{$id}/retry"), 'Event re-queued for sending.');
    }

    public function replayFailed(R007ApiClient $api): void
    {
        $this->run(function () use ($api): void {
            $r = $api->post('sync/outbox/replay-failed');
            $this->message = ($r['outboxRequeued'] ?? 0).' outbox event(s) re-queued, '.($r['inboxReprocessed'] ?? 0).' inbox event(s) reprocessed.';
        }, null);
    }

    public function reprocessInbox(string $id, R007ApiClient $api): void
    {
        $this->run(fn () => $api->post("sync/inbox-events/{$id}/reprocess"), 'Inbox event reprocessed.');
    }

    public function reprocessConflict(string $id, R007ApiClient $api): void
    {
        $this->run(fn () => $api->post("sync/conflicts/{$id}/reprocess"), 'Conflicting event re-run.');
    }

    public function resolveConflict(string $id, R007ApiClient $api): void
    {
        $choice = $this->resolution[$id] ?? '';
        if (! in_array($choice, ['KEEP_LOCAL', 'MANUAL', 'DISMISSED'], true)) {
            $this->message = 'Choose how to resolve the conflict first.';
            $this->messageTone = 'error';

            return;
        }
        $this->run(fn () => $api->post("sync/conflicts/{$id}/resolve", array_filter(['resolution' => $choice, 'note' => trim($this->notes[$id] ?? '') ?: null])), 'Conflict resolution recorded.');
        $this->openConflict = null;
    }

    public function render(R007ApiClient $api)
    {
        $status = Fetch::of(fn () => $api->get('sync/status'), ['GET', '/sync/status']);
        $health = Fetch::of(fn () => $api->get('system/health'), ['GET', '/system/health']);
        $d = ['status' => $status, 'health' => $health, 'freshness' => DataFreshness::forNode($status->ok() ? (array) $status->data : null)];

        if ($this->tab === 'outbox') {
            $d['outbox'] = Fetch::of(fn () => $api->get('sync/outbox', ['status' => $this->outboxStatus, 'limit' => 100]), ['GET', '/sync/outbox']);
        }
        if ($this->tab === 'inbox') {
            $d['inbox'] = Fetch::of(fn () => $api->get('sync/inbox-events', ['result' => $this->inboxResult, 'limit' => 100]), ['GET', '/sync/inbox-events']);
        }
        if ($this->tab === 'conflicts') {
            $d['conflicts'] = Fetch::of(fn () => $api->get('sync/conflicts', ['status' => $this->conflictStatus, 'limit' => 100]), ['GET', '/sync/conflicts']);
        }

        return view('livewire.sync-center', $d);
    }
}
