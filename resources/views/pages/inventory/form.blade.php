@php
    $multi = in_array($action, ['receive', 'transfer', 'count'], true);
    $itemOpts = ['' => 'Choose item...'] + collect($items->items())->mapWithKeys(fn ($i) => [($i['id'] ?? '') => ($i['name'] ?? '').' ('.($i['unit'] ?? '').')'])->all();
    $locOpts = ['' => 'Choose location...'] + collect($locations->items())->mapWithKeys(fn ($l) => [($l['id'] ?? '') => ($l['name'] ?? '')])->all();
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
                @php $qtyKey = $action === 'count' ? 'countedQuantity' : 'quantity'; $cleanItems = collect($itemOpts)->except('')->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()->all(); @endphp
                <div class="mb-2 mt-2 text-sm font-semibold">Lines</div>
                <div class="grid gap-3" data-testid="lines">
                    @for ($i = 0; $i < 12; $i++)
                        <div class="grid items-start gap-3 {{ $action === 'receive' ? 'sm:grid-cols-[2fr_1fr_1fr]' : 'sm:grid-cols-[2fr_1fr]' }}" x-show="rows > {{ $i }}" @if ($i >= 2) x-cloak @endif>
                            <x-form.select :name="'lines['.$i.'][itemId]'" :label="'Item '.($i + 1)" :options="$cleanItems" :bare="true" placeholder="Choose an item" />
                            <x-form.text :name="'lines['.$i.']['.$qtyKey.']'" :label="$action === 'count' ? 'Counted quantity '.($i + 1) : 'Quantity '.($i + 1)" :bare="true" :placeholder="$action === 'count' ? 'Counted quantity' : 'Quantity'" inputmode="decimal" />
                            @if ($action === 'receive')<x-form.money :name="'lines['.$i.'][unitCost]'" :label="'Unit cost '.($i + 1)" :bare="true" :scale="2" placeholder="Unit cost (optional)" />@endif
                        </div>
                    @endfor
                </div>
                <div class="mb-4 mt-3"><button type="button" class="f-btn" x-show="rows < 12" @click="rows++">Add a line</button></div>
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
            <x-form.actions :submit="$action === 'count' ? 'Create count sheet' : 'Submit'" :show-cancel="false" />
        </form>
    </x-card>
</x-layouts.app>
