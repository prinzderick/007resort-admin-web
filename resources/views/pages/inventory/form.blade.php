@php
    $multi = in_array($action, ['receive', 'transfer', 'count'], true);
    $itemOpts = ['' => 'Choose item...'] + collect($items->items())->mapWithKeys(fn ($i) => [($i['id'] ?? '') => ($i['name'] ?? '').' ('.($i['unit'] ?? '').')'])->all();
    $locOpts = ['' => 'Choose location...'] + collect($locations->items())->mapWithKeys(fn ($l) => [($l['id'] ?? '') => ($l['name'] ?? '')])->all();
    // Fed to the barcode/SKU scan box (resources/js/inventory/scan.js): every item this staff member can pick from,
    // keyed by SKU so a USB barcode scanner (types the code, then Enter) resolves straight to a line.
    $scanItems = collect($items->items())->map(fn ($i) => ['value' => $i['id'] ?? '', 'label' => ($i['name'] ?? '').' ('.($i['unit'] ?? '').')', 'sku' => $i['sku'] ?? ''])->values()->all();
@endphp
<x-layouts.app :title="$title">
    <x-page-header :title="$title" :subtitle="$action === 'adjust' ? 'Adjustments over the facility threshold wait for a supervisor to approve.' : null">
        <x-slot:actions><x-btn variant="secondary" :href="route('inventory.index')">Back to balances</x-btn></x-slot:actions>
    </x-page-header>
    <x-fetch :of="$items" what="Items" />
    <x-fetch :of="$locations" what="Locations" />
    <x-card>
        <form method="POST" action="{{ route('inventory.submit', $action) }}" x-data="lineRows({{ $multi ? 2 : 1 }}, {{ $multi ? 12 : 1 }})" x-on:inventory-scan.window="onScan($event.detail)">
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

            <div class="mb-4 mt-1" x-data="barcodeScan(@js($scanItems))">
                <label class="f-label" for="{{ $action }}-scan">Scan or type item code</label>
                <div class="f-box"><input id="{{ $action }}-scan" type="text" class="f-input" x-model="code" x-on:keydown.enter.prevent="resolve()" placeholder="Scan a barcode, or type the SKU, then press Enter" autocomplete="off" inputmode="text" data-testid="barcode-scan"></div>
                <p class="f-hint" x-show="!notFound" x-cloak>A USB barcode scanner works here: scanning fills the next empty line below and clears the box for the next item.</p>
                <p class="f-error" x-show="notFound" x-cloak data-testid="barcode-not-found">No item matches &quot;<span x-text="lastCode"></span>&quot;. Try again, or pick it from the list below.</p>
            </div>
            <p class="f-hint mb-4" x-show="scanMsg" x-cloak x-text="scanMsg" data-testid="scan-message"></p>

            @if ($multi)
                @php $qtyKey = $action === 'count' ? 'countedQuantity' : 'quantity'; $cleanItems = collect($itemOpts)->except('')->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()->all(); @endphp
                <div class="mb-2 mt-2 text-sm font-semibold">Lines</div>
                <div class="grid gap-3" data-testid="lines">
                    @for ($i = 0; $i < 12; $i++)
                        <div class="grid items-start gap-3 {{ $action === 'receive' ? 'sm:grid-cols-[2fr_1fr_1fr]' : 'sm:grid-cols-[2fr_1fr]' }}" x-show="rows > {{ $i }}" @if ($i >= 2) x-cloak @endif>
                            <x-form.select :name="'lines['.$i.'][itemId]'" :label="'Item '.($i + 1)" :options="$cleanItems" :bare="true" placeholder="Choose an item" data-line-item="{{ $i }}" />
                            <x-form.text :name="'lines['.$i.']['.$qtyKey.']'" :label="$action === 'count' ? 'Counted quantity '.($i + 1) : 'Quantity '.($i + 1)" :bare="true" :placeholder="$action === 'count' ? 'Counted quantity' : 'Quantity'" inputmode="decimal" data-line-qty="{{ $i }}" />
                            @if ($action === 'receive')<x-form.money :name="'lines['.$i.'][unitCost]'" :label="'Unit cost '.($i + 1)" :bare="true" :scale="2" placeholder="Unit cost (optional)" />@endif
                        </div>
                    @endfor
                </div>
                <div class="mb-4 mt-3"><button type="button" class="f-btn" x-show="rows < 12" @click="rows++">Add a line</button></div>
                @error('lines')<p class="mb-2 text-xs text-red-700">{{ $message }}</p>@enderror
                @foreach ($errors->keys() as $k)@if (str_starts_with($k, 'lines.'))<p class="text-xs text-red-700">{{ $errors->first($k) }}</p>@endif @endforeach
            @else
                <x-field name="itemId" label="Item" :options="$itemOpts" required data-line-item="0" />
                @if ($action === 'adjust')
                    <x-field name="quantityDelta" label="Quantity change (+/-)" required hint="Use a negative number to remove stock." data-line-qty="0" />
                    <x-field name="reason" label="Reason" :options="['DAMAGE' => 'Damage', 'THEFT' => 'Theft', 'CORRECTION' => 'Correction', 'EXPIRY' => 'Expiry', 'OTHER' => 'Other']" required />
                    <x-field name="note" label="Note" type="textarea" required />
                @else
                    <x-field name="quantity" label="Quantity" required data-line-qty="0" />
                    <x-field name="reason" label="Reason" :options="['SPOILAGE' => 'Spoilage', 'BREAKAGE' => 'Breakage', 'PREP_WASTE' => 'Prep waste', 'EXPIRY' => 'Expiry', 'OTHER' => 'Other']" required />
                    <x-field name="note" label="Note" type="textarea" />
                @endif
            @endif
            @if ($action === 'transfer' || $action === 'count')<x-field name="note" label="Note" />@endif
            <x-form.actions :submit="$action === 'count' ? 'Create count sheet' : 'Submit'" :show-cancel="false" />
        </form>
    </x-card>
</x-layouts.app>
