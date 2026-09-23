@php
    $multi = in_array($action, ['receive', 'transfer', 'count'], true);
    $itemOpts = ['' => 'Choose item...'] + collect($items->items())->mapWithKeys(fn ($i) => [$i['id'] => $i['name'].' ('.($i['unit'] ?? '').')'])->all();
    $locOpts = ['' => 'Choose location...'] + collect($locations->items())->mapWithKeys(fn ($l) => [$l['id'] => $l['name']])->all();
@endphp
<x-layouts.app :title="$title">
    <x-page-header :title="$title" :subtitle="$action === 'adjust' ? 'Adjustments over the facility threshold wait for a supervisor to approve.' : null">
        <x-slot:actions><x-btn variant="secondary" :href="route('inventory.index')">Back to balances</x-btn></x-slot:actions>
    </x-page-header>
    <x-fetch :of="$items" what="Items" />
    <x-fetch :of="$locations" what="Locations" />
    <x-card>
        <form method="POST" action="{{ route('inventory.submit', $action) }}" x-data="{ rows: {{ $multi ? 2 : 0 }} }">
            @csrf
            <div class="grid gap-x-4 sm:grid-cols-2">
                @if ($action === 'transfer')
                    <x-field name="fromLocationId" label="From" :options="$locOpts" required />
                    <x-field name="toLocationId" label="To" :options="$locOpts" required />
                @else
                    <x-field name="locationId" label="Location" :options="$locOpts" required />
                @endif
                @if ($action === 'receive')
                    <x-field name="supplierName" label="Supplier" />
                    <x-field name="supplierInvoice" label="Supplier invoice no." />
                @endif
            </div>

            @if ($multi)
                <div class="mb-2 text-sm font-medium">Lines</div>
                <template x-for="i in rows" :key="i">
                    <div class="mb-2 grid gap-2 sm:grid-cols-[2fr_1fr_1fr]">
                        <select :name="`lines[${i}][itemId]`" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3 text-sm">@foreach ($itemOpts as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                        <input :name="`lines[${i}][{{ $action === 'count' ? 'countedQuantity' : 'quantity' }}]`" placeholder="{{ $action === 'count' ? 'Counted qty' : 'Quantity' }}" inputmode="decimal" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm">
                        @if ($action === 'receive')<input :name="`lines[${i}][unitCost]`" placeholder="Unit cost" inputmode="decimal" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm">@endif
                    </div>
                </template>
                <button type="button" class="mb-4 text-sm underline" @click="rows++">Add line</button>
                @error('lines')<p class="mb-2 text-xs text-red-700">{{ $message }}</p>@enderror
                @foreach ($errors->keys() as $k)@if (str_starts_with($k, 'lines.'))<p class="text-xs text-red-700">{{ $errors->first($k) }}</p>@endif @endforeach
            @else
                <x-field name="itemId" label="Item" :options="$itemOpts" required />
                @if ($action === 'adjust')
                    <x-field name="quantityDelta" label="Quantity change (+/-)" required hint="Use a negative number to remove stock." />
                    <x-field name="reason" label="Reason" :options="['DAMAGE' => 'Damage', 'THEFT' => 'Theft', 'CORRECTION' => 'Correction', 'EXPIRY' => 'Expiry', 'OTHER' => 'Other']" required />
                    <x-field name="note" label="Note" type="textarea" required />
                @else
                    <x-field name="quantity" label="Quantity" required />
                    <x-field name="reason" label="Reason" :options="['SPOILAGE' => 'Spoilage', 'BREAKAGE' => 'Breakage', 'PREP_WASTE' => 'Prep waste', 'EXPIRY' => 'Expiry', 'OTHER' => 'Other']" required />
                    <x-field name="note" label="Note" type="textarea" />
                @endif
            @endif
            @if ($action === 'transfer' || $action === 'count')<x-field name="note" label="Note" />@endif
            <x-btn>{{ $action === 'count' ? 'Create count sheet' : 'Submit' }}</x-btn>
        </form>
    </x-card>
</x-layouts.app>
