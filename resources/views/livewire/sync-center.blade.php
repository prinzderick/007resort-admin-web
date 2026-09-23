<div wire:poll.15s>
    <x-page-header title="Sync & IT" subtitle="Two-node health, the sync queues, and conflicts that need a human." />

    @if ($message)
        <div class="mb-4 rounded-lg border px-4 py-3 text-sm {{ $messageTone === 'ok' ? 'border-emerald-300 bg-emerald-50 text-emerald-900' : 'border-red-300 bg-red-50 text-red-900' }}" role="status">{{ $message }}</div>
    @endif

    <div class="mb-4 inline-flex flex-wrap rounded-lg border border-stone-300 bg-white p-1 text-sm">
        @foreach (['health' => 'Node health', 'outbox' => 'Outbox', 'inbox' => 'Inbox', 'conflicts' => 'Conflicts'] as $k => $label)
            <button wire:click="$set('tab','{{ $k }}')" class="min-h-10 rounded-md px-4 {{ $tab === $k ? 'bg-stone-900 text-white' : '' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'health')
        <x-freshness :freshness="$freshness" />
        <x-fetch :of="$status" what="Sync status" />
        @if ($status->ok())
            @php $s = $status->data; @endphp
            <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4" data-testid="node-health">
                <x-stat label="Node" :value="strtoupper($s['node'])" />
                <x-stat label="Health" :value="$s['health']" :tone="$s['health'] === 'ONLINE' ? 'good' : ($s['health'] === 'DEGRADED' ? 'warn' : 'bad')" />
                <x-stat label="Peer reachable" :value="$s['peerReachable'] ? 'Yes' : 'No'" :tone="$s['peerReachable'] ? 'good' : 'bad'" />
                <x-stat label="Last peer heartbeat" :value="\App\Support\Time::ago($s['lastPeerHeartbeatAt'] ?? null)" :hint="\App\Support\Time::format($s['lastPeerHeartbeatAt'] ?? null)" />
                <x-stat label="Outbox queued" :value="$s['outbox']['queued']" />
                <x-stat label="Outbox failed" :value="$s['outbox']['failed']" :tone="$s['outbox']['failed'] ? 'bad' : 'default'" />
                <x-stat label="Outbox conflicts" :value="$s['outbox']['conflict']" :tone="$s['outbox']['conflict'] ? 'warn' : 'default'" />
                <x-stat label="Oldest queued" :value="\App\Support\Time::ago($s['outbox']['oldestQueuedAt'] ?? null)" />
            </div>
        @endif
        <x-card title="Service checks">
            <x-fetch :of="$health" what="System health" />
            @if ($health->ok())
                <div class="flex flex-wrap gap-2 text-sm">@foreach (($health->data['checks'] ?? []) as $name => $v)<span class="rounded-lg border border-stone-200 px-3 py-2">{{ $name }} <x-badge :status="$v" /></span>@endforeach</div>
            @endif
        </x-card>
    @endif

    @if ($tab === 'outbox')
        <x-card title="Outbox events" flush>
            <x-slot:aside>
                <select wire:model.live="outboxStatus" class="min-h-10 rounded-lg border border-stone-300 px-2 text-sm">
                    <option value="">All statuses</option>@foreach (['QUEUED', 'SYNCING', 'SYNCED', 'FAILED', 'CONFLICT'] as $st)<option>{{ $st }}</option>@endforeach
                </select>
                <x-btn type="button" variant="secondary" class="ml-2 min-h-10" wire:click="replayFailed" wire:confirm="Re-queue every FAILED outbox event and reprocess failed inbox events?">Replay all failed</x-btn>
            </x-slot:aside>
            <x-fetch :of="$outbox" what="Outbox" />
            @if ($outbox->ok())
            <div class="overflow-x-auto"><table class="data-table">
                <thead><tr><th>#</th><th>Event</th><th>Status</th><th>Retries</th><th>Last error</th><th>Created</th><th></th></tr></thead>
                <tbody>
                @forelse ($outbox->items() as $e)
                    <tr wire:key="o-{{ $e['id'] }}"><td>{{ $e['seq'] ?? '' }}</td><td>{{ $e['eventType'] }}<div class="text-xs text-stone-500">{{ $e['entityType'] ?? '' }} v{{ $e['entityVersion'] ?? '' }}</div></td>
                        <td><x-badge :status="$e['syncStatus']" /></td><td>{{ $e['retryCount'] ?? 0 }}</td>
                        <td class="max-w-xs truncate text-xs">{{ is_array($e['lastError'] ?? null) ? json_encode($e['lastError']) : ($e['lastError'] ?? '') }}</td>
                        <td><x-time :at="$e['createdAt'] ?? null" ago /></td>
                        <td>@if (in_array($e['syncStatus'], ['FAILED', 'CONFLICT'], true))<x-btn type="button" variant="secondary" class="min-h-10" wire:click="retryOutbox('{{ $e['id'] }}')">Retry</x-btn>@endif</td></tr>
                @empty<tr><td colspan="7" class="text-center text-stone-500">No events.</td></tr>@endforelse
                </tbody></table></div>
            @endif
        </x-card>
    @endif

    @if ($tab === 'inbox')
        <x-card title="Inbox events" flush>
            <x-slot:aside>
                <select wire:model.live="inboxResult" class="min-h-10 rounded-lg border border-stone-300 px-2 text-sm">
                    <option value="">All results</option>@foreach (['PENDING', 'APPLIED', 'CONFLICT', 'FAILED'] as $st)<option>{{ $st }}</option>@endforeach
                </select>
            </x-slot:aside>
            <x-fetch :of="$inbox" what="Inbox" />
            @if ($inbox->ok())
            <div class="overflow-x-auto"><table class="data-table">
                <thead><tr><th>Event</th><th>From</th><th>Result</th><th>Received</th><th>Detail</th><th></th></tr></thead>
                <tbody>
                @forelse ($inbox->items() as $e)
                    <tr wire:key="i-{{ $e['id'] }}"><td>{{ $e['eventType'] }}</td><td>{{ $e['sourceNode'] ?? '' }}</td><td><x-badge :status="$e['result']" /></td><td><x-time :at="$e['receivedAt'] ?? null" ago /></td>
                        <td class="max-w-xs truncate text-xs">{{ json_encode($e['conflictDetail'] ?? null) }}</td>
                        <td>@if (in_array($e['result'], ['FAILED', 'CONFLICT', 'PENDING'], true))<x-btn type="button" variant="secondary" class="min-h-10" wire:click="reprocessInbox('{{ $e['id'] }}')">Reprocess</x-btn>@endif</td></tr>
                @empty<tr><td colspan="6" class="text-center text-stone-500">No events.</td></tr>@endforelse
                </tbody></table></div>
            @endif
        </x-card>
    @endif

    @if ($tab === 'conflicts')
        <x-card title="Sync conflicts" flush>
            <x-slot:aside>
                <select wire:model.live="conflictStatus" class="min-h-10 rounded-lg border border-stone-300 px-2 text-sm">
                    <option value="">All</option>@foreach (['OPEN', 'RESOLVED', 'DISMISSED'] as $st)<option>{{ $st }}</option>@endforeach
                </select>
            </x-slot:aside>
            <x-fetch :of="$conflicts" what="Conflicts" />
            @if ($conflicts->ok())
                @forelse ($conflicts->items() as $c)
                    <div class="border-b border-stone-100 p-4" wire:key="c-{{ $c['id'] }}" data-testid="conflict-{{ $c['status'] }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div><x-badge :status="$c['status']" /> <b class="ml-1">{{ $c['entityType'] }}</b> <span class="text-sm text-stone-500">{{ $c['category'] }} &middot; local v{{ $c['localVersion'] ?? '?' }} vs incoming v{{ $c['incomingVersion'] ?? '?' }} &middot; <x-time :at="$c['createdAt']" ago /></span></div>
                            <button class="text-sm underline" wire:click="$set('openConflict', '{{ $openConflict === $c['id'] ? '' : $c['id'] }}')">{{ $openConflict === $c['id'] ? 'Hide' : 'Details' }}</button>
                        </div>
                        @if ($openConflict === $c['id'])
                            <div class="mt-3 grid gap-3 md:grid-cols-2">
                                <div><div class="mb-1 text-xs font-semibold uppercase text-stone-500">This node</div><pre class="overflow-x-auto rounded-lg bg-stone-50 p-3 text-xs">{{ json_encode($c['localPayload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div>
                                <div><div class="mb-1 text-xs font-semibold uppercase text-stone-500">Incoming from peer</div><pre class="overflow-x-auto rounded-lg bg-stone-50 p-3 text-xs">{{ json_encode($c['incomingPayload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div>
                            </div>
                            @if ($c['status'] === 'OPEN')
                                <div class="mt-3 flex flex-wrap items-end gap-2">
                                    <select wire:model="resolution.{{ $c['id'] }}" class="min-h-11 rounded-lg border border-stone-300 px-2 text-sm"><option value="">Resolution...</option><option value="KEEP_LOCAL">Keep this node's version</option><option value="MANUAL">Resolved manually</option><option value="DISMISSED">Dismiss</option></select>
                                    <input wire:model="notes.{{ $c['id'] }}" placeholder="Note" class="min-h-11 flex-1 rounded-lg border border-stone-300 px-3 text-sm">
                                    <x-btn type="button" wire:click="resolveConflict('{{ $c['id'] }}')">Record resolution</x-btn>
                                    <x-btn type="button" variant="secondary" wire:click="reprocessConflict('{{ $c['id'] }}')">Reprocess event</x-btn>
                                </div>
                            @elseif ($c['resolution'])
                                <p class="mt-3 text-sm text-stone-600">Resolution: <b>{{ $c['resolution'] }}</b> {{ $c['note'] ? '- '.$c['note'] : '' }}</p>
                            @endif
                        @endif
                    </div>
                @empty<p class="p-4 text-sm text-stone-500">No conflicts.</p>@endforelse
            @endif
        </x-card>
    @endif
</div>
