@php
    $facOpts = collect($facilities)->map(fn ($f) => ['value' => $f['id'], 'label' => $f['name'] ?? $f['code']])->values()->all();
    $devOpts = collect($devices->items())->map(fn ($d) => ['value' => $d['id'], 'label' => $d['name'] ?? $d['id']])->values()->all();
    $staffOpts = collect($staffNames)->map(fn ($n, $id) => ['value' => $id, 'label' => $n])->values()->all();
@endphp
<x-layouts.app title="Card machines">
    <x-page-header title="Card machines" subtitle="The card machines waiters carry to tables. Give each one to a facility, and to a tablet or a waiter, so every collection shows which machine took it." :crumbs="['Devices' => route('devices.index'), 'Card machines' => null]">
        <x-slot:actions>@if ($canManage)<x-btn type="button" icon="plus" @click="$dispatch('open-modal', 'add-terminal')" data-testid="add-card-machine">Add card machine</x-btn>@endif</x-slot:actions>
    </x-page-header>
    <x-filter-form :reset="route('devices.payment-terminals')">
        <x-filter-select name="facilityId" label="Facility" :options="collect($facilities)->pluck('name', 'id')->all()" :value="$facilityId" all="All facilities" />
        <x-filter-select name="status" label="Status" :options="$statuses" :value="$status" all="All" />
    </x-filter-form>
    <x-card flush x-data="tableTools">
        <x-fetch :of="$terminals" what="Card machines" />
        @if ($terminals->ok())
            <div class="table-scroll"><table class="data-table" data-testid="terminals-table">
                <thead><tr><th>Card machine</th><th>Type</th><th>Facility</th><th>Given to</th><th>Status</th><th class="w-12"></th></tr></thead><tbody>
                @forelse ($terminals->items() as $t)
                    <tr data-row>
                        <td><div class="font-medium">{{ $t['label'] ?? '' }}</div><div class="font-mono text-xs text-stone-500">{{ $t['serial'] ?? '' }}</div></td>
                        <td class="text-sm text-stone-600">{{ ($t['provider'] ?? '') === 'MANUAL_BANK' ? 'Bank card machine' : 'Paystack terminal' }}</td>
                        <td>{{ $facilityNames[$t['facilityId'] ?? ''] ?? '-' }}</td>
                        <td class="text-sm">@if (! empty($t['assignedStaffId']))<div>{{ $staffNames[$t['assignedStaffId']] ?? 'A waiter' }}</div>@endif @if (! empty($t['assignedDeviceId']))<div class="text-xs text-stone-500">{{ $deviceNames[$t['assignedDeviceId']] ?? 'A tablet' }}</div>@endif @if (empty($t['assignedStaffId']) && empty($t['assignedDeviceId']))<span class="text-stone-400">Shared: anyone at the facility</span>@endif</td>
                        <td><x-badge :tone="match ($t['status'] ?? '') { 'ACTIVE' => 'good', 'RETIRED' => 'default', default => 'warn' }">{{ $statuses[$t['status'] ?? ''] ?? $t['status'] }}</x-badge></td>
                        <td class="text-right">@if ($canManage)<button type="button" class="text-sm font-medium text-brand-700 underline" @click="$dispatch('open-modal', 'edit-terminal-{{ $t['id'] }}')">Edit</button>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty title="No card machines yet" text="Add the bank card machines your waiters carry, then choose who uses each." icon="card" /></td></tr>
                @endforelse
                </tbody></table></div>
        @endif
    </x-card>
    @if ($canManage)
        <x-dialog name="add-terminal" title="Add a card machine" subtitle="Register the machine by its serial number.">
            <form method="POST" action="{{ route('devices.payment-terminals.store') }}" class="grid gap-4" novalidate>@csrf
                <x-form.text name="label" label="Name" required :maxlength="120" placeholder="e.g. Restaurant machine 2" />
                <x-form.text name="serial" label="Serial number" required :maxlength="80" hint="Printed on the back of the machine." />
                <x-form.select name="facilityId" label="Facility" required :options="$facOpts" placeholder="Choose a facility" />
                <x-form.radio-cards name="provider" label="Type" :options="[['value' => 'MANUAL_BANK', 'label' => 'Bank card machine', 'description' => 'A normal bank machine. The cashier checks the slip and confirms.', 'icon' => 'card'], ['value' => 'PAYSTACK_TERMINAL', 'label' => 'Paystack terminal', 'description' => 'Confirms the payment by itself (needs the Paystack integration switched on).', 'icon' => 'bolt']]" value="MANUAL_BANK" />
                @if ($devOpts !== [])<x-form.select name="assignedDeviceId" label="Give to a tablet (optional)" :options="$devOpts" :clearable="true" placeholder="Not assigned" />@endif
                @if ($staffOpts !== [])<x-form.select name="assignedStaffId" label="Give to a waiter (optional)" :options="$staffOpts" :clearable="true" placeholder="Not assigned" />@endif
                <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Add card machine</x-btn></div>
            </form>
        </x-dialog>
        @foreach ($terminals->items() as $t)
            <x-dialog name="edit-terminal-{{ $t['id'] }}" title="Edit {{ $t['label'] ?? 'card machine' }}" subtitle="Serial {{ $t['serial'] ?? '' }} and facility cannot be changed.">
                <form method="POST" action="{{ route('devices.payment-terminals.update', $t['id']) }}" class="grid gap-4" novalidate>@csrf @method('PATCH')
                    <input type="hidden" name="rowVersion" value="{{ $t['rowVersion'] ?? '' }}">
                    <x-form.text name="label" label="Name" required :value="$t['label'] ?? ''" :maxlength="120" />
                    <x-form.segmented name="status" label="Status" :options="$statuses" :value="$t['status'] ?? 'ACTIVE'" />
                    @if ($devOpts !== [])<x-form.select name="assignedDeviceId" label="Given to tablet" :options="$devOpts" :value="$t['assignedDeviceId'] ?? null" :clearable="true" placeholder="Not assigned" />@endif
                    @if ($staffOpts !== [])<x-form.select name="assignedStaffId" label="Given to waiter" :options="$staffOpts" :value="$t['assignedStaffId'] ?? null" :clearable="true" placeholder="Not assigned" />@endif
                    <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Save</x-btn></div>
                </form>
            </x-dialog>
        @endforeach
    @endif
</x-layouts.app>
