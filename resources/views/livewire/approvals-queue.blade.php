<div wire:poll.20s>
    <x-page-header title="Approvals" subtitle="Sensitive actions that were accepted but wait for a second person to approve. Nothing below has taken effect until it is approved." />

    @if ($message)
        <div class="mb-4 rounded-lg border px-4 py-3 text-sm {{ $messageTone === 'ok' ? 'border-emerald-300 bg-emerald-50 text-emerald-900' : 'border-red-300 bg-red-50 text-red-900' }}" role="status">{{ $message }}</div>
    @endif

    <div class="mb-4 inline-flex rounded-lg border border-stone-300 bg-white p-1 text-sm">
        <button wire:click="$set('tab','pending')" class="min-h-10 rounded-md px-4 {{ $tab === 'pending' ? 'bg-stone-900 text-white' : '' }}">Waiting for a decision</button>
        <button wire:click="$set('tab','history')" class="min-h-10 rounded-md px-4 {{ $tab === 'history' ? 'bg-stone-900 text-white' : '' }}">Decided</button>
    </div>

    <x-fetch :of="$list" what="Approvals" />

    @if ($list->ok())
        @forelse ($items as $a)
            <x-card :wire:key="$a['id']" data-testid="approval-{{ $a['status'] }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2"><x-badge :status="$a['status']">{{ str_replace('_', ' ', $a['status']) }}</x-badge><span class="font-medium">{{ $a['summary'] ?? $a['action'] }}</span></div>
                        <div class="mt-1 text-sm text-stone-600">Requested by {{ $a['requestedByName'] ?? 'staff' }} {{ \App\Support\Time::ago($a['requestedAt']) }} &middot; needs <code class="text-xs">{{ $a['requiredPermission'] ?? '?' }}</code></div>
                        <div class="mt-1 text-sm">Reason: {{ $a['reason'] }}</div>
                        @if (! empty($a['amount']))<div class="mt-1 text-sm">Amount: <b><x-money :value="$a['amount']" /></b></div>@endif
                        @if (! empty($a['decisionNote']))<div class="mt-1 text-sm text-stone-600">Decision note: {{ $a['decisionNote'] }}</div>@endif
                    </div>
                    @if ($a['status'] === 'PENDING')
                        <div class="w-full sm:w-72">
                            <input type="text" wire:model="notes.{{ $a['id'] }}" placeholder="Note (optional)" class="mb-2 min-h-11 w-full rounded-lg border border-stone-300 px-3 text-sm">
                            <div class="flex gap-2">
                                <x-btn type="button" class="flex-1" wire:click="decide('{{ $a['id'] }}','APPROVE')" wire:confirm="Approve this request?">Approve</x-btn>
                                <x-btn type="button" variant="danger" class="flex-1" wire:click="decide('{{ $a['id'] }}','REJECT')">Reject</x-btn>
                            </div>
                        </div>
                    @endif
                </div>
            </x-card>
        @empty
            <x-card><p class="text-sm text-stone-600">{{ $tab === 'pending' ? 'Nothing is waiting for your decision.' : 'No decided approvals to show.' }}</p></x-card>
        @endforelse
    @endif
</div>
