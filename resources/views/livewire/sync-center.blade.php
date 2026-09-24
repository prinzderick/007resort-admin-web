<div wire:poll.15s>
    <x-page-header title="Sync & IT" subtitle="Two-node health, the sync queues, and conflicts that need a human." />

    @if ($message)
        <div class="mb-4 rounded-lg border px-4 py-3 text-sm {{ $messageTone === 'ok' ? 'border-emerald-300 bg-emerald-50 text-emerald-900' : 'border-red-300 bg-red-50 text-red-900' }}" role="status">{{ $message }}</div>
    @endif

    <div class="mb-4 inline-flex flex-wrap rounded-lg border border-stone-300 bg-white p-1 text-sm">
        @foreach (['health' => 'Node health', 'outbox' => 'Outbox', 'inbox' => 'Inbox', 'conflicts' => 'Conflicts'] as $k => $label)
            <button wire:click="$set('tab','{{ $k }}')" class="min-h-10 rounded-md px-4 {{ $tab === $k ? 'bg-brand-600 text-white' : '' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'health')
        <x-freshness :freshness="$freshness" />
        <x-fetch :of="$status" what="Sync status" />
        @if ($status->ok())
            @php $s = $status->data; @endphp
            <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4" data-testid="node-health">
                <x-stat label="Node" :value="strtoupper((string) ($s['node'] ?? '?'))" />
                <x-stat label="Health" :value="$s['health'] ?? 'UNKNOWN'" :tone="($s['health'] ?? null) === 'ONLINE' ? 'good' : (($s['health'] ?? null) === 'DEGRADED' ? 'warn' : 'bad')" />
                <x-stat label="Peer reachable" :value="($s['peerReachable'] ?? false) ? 'Yes' : 'No'" :tone="($s['peerReachable'] ?? false) ? 'good' : 'bad'" />
                <x-stat label="Last peer heartbeat" :value="\App\Support\Time::ago($s['lastPeerHeartbeatAt'] ?? null)" :hint="\App\Support\Time::format($s['lastPeerHeartbeatAt'] ?? null)" />
                <x-stat label="Outbox queued" :value="$s['outbox']['queued'] ?? 0" />
                <x-stat label="Outbox failed" :value="$s['outbox']['failed'] ?? 0" :tone="($s['outbox']['failed'] ?? 0) ? 'bad' : 'default'" />
                <x-stat label="Outbox conflicts" :value="$s['outbox']['conflict'] ?? 0" :tone="($s['outbox']['conflict'] ?? 0) ? 'warn' : 'default'" />
                <x-stat label="Oldest queued" :value="\App\Support\Time::ago($s['outbox']['oldestQueuedAt'] ?? null)" />
                <x-stat label="Inbox failed" :value="$s['inbox']['failed'] ?? 0" :tone="($s['inbox']['failed'] ?? 0) ? 'bad' : 'default'" />
                <x-stat label="Inbox conflicts / deferred" :value="($s['inbox']['conflict'] ?? 0).' / '.($s['inbox']['deferred'] ?? 0)" :tone="($s['inbox']['conflict'] ?? 0) ? 'warn' : 'default'" />
                <x-stat label="Last push" :value="\App\Support\Time::ago($s['lastPushAt'] ?? null)" :hint="\App\Support\Time::format($s['lastPushAt'] ?? null)" />
                <x-stat label="Last pull" :value="\App\Support\Time::ago($s['lastPullAt'] ?? null)" :hint="\App\Support\Time::format($s['lastPullAt'] ?? null)" />
            </div>
            @if (! empty($s['lastError']))
                <div class="mb-5 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-testid="sync-last-error">Last sync error{{ ! empty($s['lastErrorAt']) ? ' ('.\App\Support\Time::ago($s['lastErrorAt']).')' : '' }}: {{ is_scalar($s['lastError']) ? $s['lastError'] : json_encode($s['lastError']) }}</div>
            @endif
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
                    <option value="">All statuses</option>@foreach (['LOCAL', 'QUEUED', 'SYNCING', 'SYNCED', 'FAILED', 'CONFLICT'] as $st)<option>{{ $st }}</option>@endforeach
                </select>
                <x-btn type="button" variant="secondary" class="ml-2 min-h-10" wire:click="replayFailed" wire:confirm="Re-queue every FAILED outbox event and reprocess failed inbox events?">Replay all failed</x-btn>
            </x-slot:aside>
            <x-fetch :of="$outbox" what="Outbox" />
            @if ($outbox->ok())
            <div class="table-scroll"><table class="data-table">
                <thead><tr><th>#</th><th>Event</th><th>Status</th><th>Retries</th><th>Last error</th><th>Created</th><th></th></tr></thead>
                <tbody>
                @forelse ($outbox->items() as $e)
                    <tr wire:key="o-{{ $e['eventId'] ?? $loop->index }}"><td>{{ $e['seq'] ?? '' }}</td><td>{{ $e['eventType'] ?? '?' }}<div class="text-xs text-stone-500">{{ $e['entityType'] ?? '' }} v{{ $e['entityVersion'] ?? '' }}</div></td>
                        <td><x-badge :status="$e['syncStatus'] ?? 'UNKNOWN'" /></td><td>{{ $e['retryCount'] ?? 0 }}</td>
                        <td class="max-w-xs truncate text-xs">{{ is_array($e['lastError'] ?? null) ? ($e['lastError']['message'] ?? json_encode($e['lastError'])) : ($e['lastError'] ?? '') }}</td>
                        <td><x-time :at="$e['createdAt'] ?? null" ago /></td>
                        {{-- the API only retries FAILED events (409 outbox_event_not_retryable otherwise) --}}
                        <td>@if (($e['syncStatus'] ?? null) === 'FAILED' && ! empty($e['eventId']))<x-btn type="button" variant="secondary" class="min-h-10" wire:click="retryOutbox('{{ $e['eventId'] }}')">Retry</x-btn>@endif</td></tr>
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
            <div class="table-scroll"><table class="data-table">
                <thead><tr><th>Event</th><th>From</th><th>Result</th><th>Attempts</th><th>Received</th><th>Last error</th><th></th></tr></thead>
                <tbody>
                @forelse ($inbox->items() as $e)
                    <tr wire:key="i-{{ $e['eventId'] ?? $loop->index }}"><td>{{ $e['eventType'] ?? '?' }}<div class="text-xs text-stone-500">{{ $e['entityType'] ?? '' }}</div></td><td>{{ $e['sourceNode'] ?? '' }}</td><td><x-badge :status="$e['result'] ?? 'UNKNOWN'" /></td><td>{{ $e['attempts'] ?? 0 }}</td><td><x-time :at="$e['receivedAt'] ?? null" ago /></td>
                        <td class="max-w-xs truncate text-xs">{{ is_array($e['lastError'] ?? null) ? ($e['lastError']['message'] ?? json_encode($e['lastError'])) : ($e['lastError'] ?? '') }}</td>
                        <td>@if (in_array($e['result'] ?? null, ['FAILED', 'CONFLICT', 'PENDING'], true) && ! empty($e['eventId']))<x-btn type="button" variant="secondary" class="min-h-10" wire:click="reprocessInbox('{{ $e['eventId'] }}')">Reprocess</x-btn>@endif</td></tr>
                @empty<tr><td colspan="7" class="text-center text-stone-500">No events.</td></tr>@endforelse
                </tbody></table></div>
            @endif
        </x-card>
    @endif

    @if ($tab === 'conflicts')
        <x-card title="Sync conflicts" flush>
            <x-slot:aside>
                <select wire:model.live="conflictStatus" class="min-h-10 rounded-lg border border-stone-300 px-2 text-sm">
                    <option value="">All</option>@foreach (['OPEN', 'RESOLVED'] as $st)<option>{{ $st }}</option>@endforeach
                </select>
            </x-slot:aside>
            <x-fetch :of="$conflicts" what="Conflicts" />
            @if ($conflicts->ok())
                @forelse ($conflicts->items() as $c)
                    @php $cid = $c['id'] ?? ''; @endphp
                    <div class="border-b border-stone-100 p-4" wire:key="c-{{ $cid ?: $loop->index }}" data-testid="conflict-{{ $c['status'] ?? 'UNKNOWN' }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div><x-badge :status="$c['status'] ?? 'UNKNOWN'" /> <b class="ml-1">{{ $c['entityType'] ?? '?' }}</b> <span class="text-sm text-stone-500">{{ $c['category'] ?? '' }} &middot; local v{{ $c['localVersion'] ?? '?' }} vs incoming v{{ $c['incomingVersion'] ?? '?' }} &middot; <x-time :at="$c['detectedAt'] ?? null" ago /></span></div>
                            @if ($cid !== '')<button class="text-sm underline" wire:click="$set('openConflict', '{{ $openConflict === $cid ? '' : $cid }}')">{{ $openConflict === $cid ? 'Hide' : 'Details' }}</button>@endif
                        </div>
                        @if (! empty($c['detail']))<p class="mt-2 text-sm text-stone-700">{{ $c['detail'] }}</p>@endif
                        @if ($cid !== '' && $openConflict === $cid)
                            <div class="mt-3 grid gap-3 md:grid-cols-2">
                                <div><div class="mb-1 text-xs font-semibold uppercase text-stone-500">This node</div><pre class="overflow-x-auto rounded-lg bg-stone-50 p-3 text-xs">{{ json_encode($c['localPayload'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div>
                                <div><div class="mb-1 text-xs font-semibold uppercase text-stone-500">Incoming from peer</div><pre class="overflow-x-auto rounded-lg bg-stone-50 p-3 text-xs">{{ json_encode($c['incomingPayload'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div>
                            </div>
                            @if (($c['status'] ?? null) === 'OPEN')
                                <div class="mt-3 flex flex-wrap items-end gap-2">
                                    <select wire:model="resolution.{{ $cid }}" class="min-h-11 rounded-lg border border-stone-300 px-2 text-sm"><option value="">Resolution...</option><option value="KEEP_LOCAL">Keep this node's version</option><option value="MANUAL">Resolved manually</option><option value="DISMISSED">Dismiss</option></select>
                                    <input wire:model="notes.{{ $cid }}" placeholder="Note (required)" required class="min-h-11 flex-1 rounded-lg border border-stone-300 px-3 text-sm">
                                    <x-btn type="button" wire:click="resolveConflict('{{ $cid }}')">Record resolution</x-btn>
                                    <x-btn type="button" variant="secondary" wire:click="reprocessConflict('{{ $cid }}')">Reprocess event</x-btn>
                                </div>
                            @elseif (! empty($c['resolution']))
                                <p class="mt-3 text-sm text-stone-600">Resolution: <b>{{ $c['resolution'] }}</b> {{ ! empty($c['resolutionNote']) ? '- '.$c['resolutionNote'] : '' }}@if (! empty($c['resolvedAt'])) &middot; <x-time :at="$c['resolvedAt']" ago />@endif</p>
                            @endif
                        @endif
                    </div>
                @empty<p class="p-4 text-sm text-stone-500">No conflicts.</p>@endforelse
            @endif
        </x-card>
    @endif
</div>
