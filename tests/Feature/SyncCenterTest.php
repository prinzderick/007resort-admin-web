<?php

namespace Tests\Feature;

use App\Livewire\SyncCenter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\Fixtures as F;
use Tests\TestCase;

class SyncCenterTest extends TestCase
{
    private function syncStatusBody(string $health = 'ONLINE'): array
    {
        return ['node' => 'local', 'peerReachable' => $health !== 'OFFLINE', 'lastHeartbeatAt' => now()->toIso8601ZuluString(), 'lastPeerHeartbeatAt' => now()->subMinutes(2)->toIso8601ZuluString(),
            'outbox' => ['queued' => 42, 'failed' => 2, 'conflict' => 1, 'oldestQueuedAt' => now()->subMinutes(6)->toIso8601ZuluString()], 'inbox' => ['conflict' => 1, 'deferred' => 0], 'health' => $health];
    }

    private function api(array $o = []): void
    {
        $this->fakeApi($o + [
            'GET /sync/status' => $this->syncStatusBody(),
            'GET /system/health' => ['status' => 'ok', 'checks' => ['database' => 'ok', 'redis' => 'ok', 'cloudLink' => 'degraded']],
            'GET /sync/outbox' => F::page([
                ['id' => 'e1', 'seq' => 1, 'eventType' => 'PaymentCompleted', 'entityType' => 'payment', 'entityVersion' => 2, 'syncStatus' => 'FAILED', 'retryCount' => 5, 'lastError' => 'HTTP 502 from peer', 'createdAt' => '2026-09-23T08:00:00.000000Z'],
                ['id' => 'e2', 'seq' => 2, 'eventType' => 'OrderSettled', 'syncStatus' => 'SYNCED', 'retryCount' => 0, 'createdAt' => '2026-09-23T08:01:00.000000Z']]),
            'GET /sync/inbox-events' => F::page([['id' => 'i1', 'eventType' => 'StaffUpdated', 'sourceNode' => 'cloud', 'receivedAt' => '2026-09-23T08:00:00.000000Z', 'result' => 'FAILED', 'conflictDetail' => ['error' => 'unknown role']]]),
            'GET /sync/conflicts' => F::page([['id' => 'c1', 'category' => 'CONFIG_VERSION', 'entityType' => 'product', 'entityId' => 'p1', 'status' => 'OPEN', 'localVersion' => 7, 'incomingVersion' => 7,
                'localPayload' => ['price' => '3500.0000'], 'incomingPayload' => ['price' => '3800.0000'], 'createdAt' => '2026-09-23T08:00:00.000000Z']]),
        ]);
    }

    public function test_node_health_shows_heartbeat_queue_and_service_checks(): void
    {
        $this->api();
        $this->signIn(['config.manage'])->get('/sync')->assertOk()->assertSee('Node health')->assertSee('2 minutes ago')->assertSee('42')->assertSee('cloudLink')->assertSee('data-level="live"', false);
    }

    public function test_offline_peer_is_not_shown_as_live(): void
    {
        $this->api(['GET /sync/status' => $this->syncStatusBody('OFFLINE')]);
        $this->signIn(['config.manage'])->get('/sync')->assertSee('data-level="offline"', false)->assertSee('OFFLINE');
    }

    public function test_degraded_peer_is_flagged(): void
    {
        $this->api(['GET /sync/status' => $this->syncStatusBody('DEGRADED')]);
        $this->signIn(['config.manage'])->get('/sync')->assertSee('data-level="stale"', false);
    }

    public function test_outbox_lists_events_filters_and_retries_failed_ones(): void
    {
        $this->api(['POST /sync/outbox/e1/retry' => ['id' => 'e1', 'syncStatus' => 'QUEUED']]);
        $this->signIn(['config.manage']);

        Livewire::test(SyncCenter::class)->set('tab', 'outbox')->assertSee('PaymentCompleted')->assertSee('HTTP 502 from peer')
            ->set('outboxStatus', 'FAILED')->call('retryOutbox', 'e1')->assertSee('Event re-queued for sending.');

        $this->assertTrue($this->sentTo('GET', '/sync/outbox', fn (Request $r) => str_contains($r->url(), 'status=FAILED')));
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/sync/outbox/e1/retry') && $r->hasHeader('Idempotency-Key'));
    }

    public function test_replay_all_failed_reports_counts(): void
    {
        $this->api(['POST /sync/outbox/replay-failed' => ['outboxRequeued' => 3, 'inboxReprocessed' => 1]]);
        $this->signIn(['config.manage']);

        Livewire::test(SyncCenter::class)->set('tab', 'outbox')->call('replayFailed')->assertSee('3 outbox event(s) re-queued, 1 inbox event(s) reprocessed.');
    }

    public function test_inbox_reprocess(): void
    {
        $this->api(['POST /sync/inbox-events/i1/reprocess' => ['id' => 'i1', 'result' => 'APPLIED']]);
        $this->signIn(['config.manage']);

        Livewire::test(SyncCenter::class)->set('tab', 'inbox')->assertSee('StaffUpdated')->call('reprocessInbox', 'i1')->assertSee('Inbox event reprocessed.');
    }

    public function test_conflict_details_and_resolution(): void
    {
        $this->api(['POST /sync/conflicts/c1/resolve' => ['id' => 'c1', 'status' => 'RESOLVED']]);
        $this->signIn(['config.manage']);

        Livewire::test(SyncCenter::class)->set('tab', 'conflicts')->assertSee('CONFIG_VERSION')->assertSee('local v7 vs incoming v7')
            ->set('openConflict', 'c1')->assertSee('3800.0000')->assertSee('3500.0000')
            ->call('resolveConflict', 'c1')->assertSee('Choose how to resolve the conflict first.')
            ->set('resolution.c1', 'KEEP_LOCAL')->set('notes.c1', 'Confirmed with the owner')->call('resolveConflict', 'c1')->assertSee('Conflict resolution recorded.');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/sync/conflicts/c1/resolve') && $r['resolution'] === 'KEEP_LOCAL' && $r['note'] === 'Confirmed with the owner');
    }

    public function test_invalid_resolution_never_reaches_the_api(): void
    {
        $this->api();
        $this->signIn(['config.manage']);

        Livewire::test(SyncCenter::class)->set('tab', 'conflicts')->set('resolution.c1', 'DROP_TABLE')->call('resolveConflict', 'c1')->assertSee('Choose how to resolve');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/resolve'));
    }

    public function test_reprocess_conflict_and_api_errors_are_shown(): void
    {
        $this->api(['POST /sync/conflicts/c1/reprocess' => $this->problem(409, 'concurrency_conflict', 'Event is already applied.')]);
        $this->signIn(['config.manage']);

        Livewire::test(SyncCenter::class)->set('tab', 'conflicts')->call('reprocessConflict', 'c1')->assertSee('Event is already applied.');
    }

    public function test_missing_sync_endpoints_degrade(): void
    {
        $this->api(['GET /sync/outbox' => [501, []], 'GET /sync/status' => [501, []]]);
        $this->signIn(['config.manage']);

        Livewire::test(SyncCenter::class)->set('tab', 'outbox')->assertSee('Outbox is not available: this API build has not implemented');
    }
}
