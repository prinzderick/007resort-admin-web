@php
    $catOpts = collect($categories->items())->map(fn ($c) => ['value' => $c['id'], 'label' => $c['name']])->values()->all();
    $routeOpts = collect($prepRoutes->items())->map(fn ($r) => ['value' => $r['id'], 'label' => $r['name']])->values()->all();
    $taxOpts = collect($taxRates->items())->map(fn ($r) => ['value' => $r['id'], 'label' => $r['name'].' ('.rtrim(rtrim($r['ratePercent'], '0'), '.').'%)'])->values()->all();
    $facName = collect($facilities)->pluck('name', 'id')->all();
    $soldIds = collect($p['facilities'] ?? [])->pluck('facilityId')->all();
    $notSold = collect($facilities)->reject(fn ($f) => in_array($f['id'], $soldIds, true))->map(fn ($f) => ['value' => $f['id'], 'label' => $f['name'] ?? $f['code']])->values()->all();
    $listName = collect($priceLists->items())->pluck('name', 'id')->all();
    $itemOpts = collect($stockItems->items())->map(fn ($i) => ['value' => $i['id'], 'label' => $i['name'].' ('.$i['unit'].')'])->values()->all();
    $links = collect($stock->ok() ? ($stock->data['links'] ?? []) : [])->map(fn ($l) => ['stockItemId' => $l['stockItemId'] ?? '', 'quantityPerUnit' => rtrim(rtrim((string) ($l['quantityPerUnit'] ?? ''), '0'), '.')])->values()->all();
@endphp
<x-layouts.app :title="$p['name'] ?? 'Product'">
    <x-page-header :title="$p['name'] ?? 'Product'" :subtitle="($p['sku'] ?? '').' · '.($p['categoryName'] ?? '')" :crumbs="['Setup' => route('setup.index'), 'Catalog & prices' => route('setup.catalog'), ($p['name'] ?? 'Product') => null]">
        <x-slot:actions><x-badge :tone="($p['active'] ?? true) ? 'good' : 'default'">{{ ($p['active'] ?? true) ? 'Active' : 'Switched off' }}</x-badge></x-slot:actions>
    </x-page-header>
    <x-fetch :of="$fetch" what="Product" />
    @if ($fetch->ok())
    <x-settings-meta entity-type="Product" :entity-id="$id" />
    <form method="POST" action="{{ route('setup.catalog.product.update', $id) }}" novalidate data-testid="product-form">@csrf @method('PATCH')
        <input type="hidden" name="etag" value="{{ $etag }}">
        <x-form.section title="Details" description="What it is called and how it is grouped, taxed and prepared.">
            <x-form.text name="name" label="Name" required :value="old('name', $p['name'] ?? '')" :disabled="! $canManage" />
            <x-form.text name="description" label="Description" :value="old('description', $p['description'] ?? '')" :multiline="true" :rows="2" :maxlength="500" :disabled="! $canManage" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.select name="categoryId" label="Category" required :options="$catOpts" :value="old('categoryId', $p['categoryId'] ?? null)" :disabled="! $canManage" />
                <x-form.select name="kind" label="Kind" :options="$kinds" :value="old('kind', $p['commercialKind'] ?? 'GOOD')" :searchable="false" :disabled="! $canManage" />
                <x-form.text name="barcode" label="Barcode" :value="old('barcode', $p['barcode'] ?? '')" :maxlength="64" hint="Scanned at the till. Unique across the property." :disabled="! $canManage" />
                <x-form.select name="taxRateId" label="Tax rate" :options="$taxOpts" :value="old('taxRateId', $p['taxRateId'] ?? null)" :clearable="true" placeholder="Default" :disabled="! $canManage" />
            </div>
            <x-form.select name="prepRouteId" label="Prepared by" :options="$routeOpts" :value="old('prepRouteId', $p['prepRouteId'] ?? null)" :clearable="true" placeholder="Not prepared" hint="The kitchen or bar that makes it. Orders appear on that screen." :disabled="! $canManage" />
            <x-form.toggle name="taxExempt" label="Tax exempt" description="Never add VAT to this product." :value="old('taxExempt', $p['taxExempt'] ?? false)" :disabled="! $canManage" />
            <x-form.toggle name="trackStock" label="Track stock" description="Selling it deducts stock (set the stock items further down)." :value="old('trackStock', $p['trackStock'] ?? false)" :disabled="! $canManage" />
            <x-form.toggle name="active" label="Active" description="Off removes it from every menu. History is kept." :value="old('active', $p['active'] ?? true)" :disabled="! $canManage" />
        </x-form.section>
        @if ($canManage)<x-form.actions submit="Save product" />@endif
    </form>

    <x-card title="Where it is sold" subtitle="Each facility sells its own selection. A price here overrides the standard price at that facility only." flush class="mt-8" data-testid="sold-at">
        <x-slot:aside>@if ($canManage && $notSold !== [])<x-btn type="button" icon="plus" variant="secondary" @click="$dispatch('open-modal', 'sell-at')">Sell at another facility</x-btn>@endif</x-slot:aside>
        <div class="table-scroll"><table class="data-table"><thead><tr><th>Facility</th><th>Availability</th><th class="num">Price here</th><th class="w-12"></th></tr></thead><tbody>
        @forelse ($p['facilities'] ?? [] as $f)
            <tr><td class="font-medium">{{ $f['facilityName'] ?? $facName[$f['facilityId']] ?? '' }}</td>
                <td><x-badge :tone="($f['available'] ?? true) ? 'good' : 'bad'">{{ ($f['available'] ?? true) ? 'Available' : 'Unavailable' }}</x-badge>@if (! empty($f['unavailableReason']))<span class="ml-2 text-xs text-stone-500">{{ $f['unavailableReason'] }}</span>@endif</td>
                <td class="num">@if (! empty($f['priceOverride']))<x-money :value="$f['priceOverride']" />@else<span class="text-stone-400">Standard</span>@endif</td>
                <td class="text-right">@if ($canManage)<x-row-menu><button type="button" @click="$dispatch('open-modal', 'fac-{{ $f['facilityId'] }}')">Edit</button>
                    <form method="POST" action="{{ route('setup.catalog.product.facility.remove', [$id, $f['facilityId']]) }}" x-data="confirmSubmit('Stop selling this product at {{ e($f['facilityName'] ?? 'this facility') }}?')" @submit="ask($event)">@csrf @method('DELETE')<button class="w-full text-left text-red-800">Stop selling here</button></form></x-row-menu>@endif</td></tr>
        @empty<tr><td colspan="4"><x-empty title="Not sold anywhere yet" text="Choose the facilities that sell it." icon="building" /></td></tr>@endforelse
        </tbody></table></div>
    </x-card>
    @if ($canManage)
        @foreach ($p['facilities'] ?? [] as $f)
            <x-dialog name="fac-{{ $f['facilityId'] }}" title="{{ $p['name'] }} at {{ $f['facilityName'] ?? '' }}">
                <form method="POST" action="{{ route('setup.catalog.product.facility', [$id, $f['facilityId']]) }}" class="grid gap-4" novalidate>@csrf @method('PUT')
                    <x-form.toggle name="available" label="Available to sell" description="Off marks it unavailable (86) here, e.g. sold out." :value="$f['available'] ?? true" />
                    <x-form.text name="unavailableReason" label="Reason (shown to staff)" :value="$f['unavailableReason'] ?? ''" :maxlength="120" placeholder="e.g. Sold out" />
                    <x-form.money name="price" label="Price at this facility" :value="$f['priceOverride'] ?? null" :scale="2" hint="Leave empty to use the standard price." />
                    <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Save</x-btn></div>
                </form>
            </x-dialog>
        @endforeach
        <x-dialog name="sell-at" title="Sell at another facility">
            <form method="POST" x-data="{ f: '' }" :action="'{{ url('/setup/catalog/products/'.$id.'/facilities') }}/' + f" class="grid gap-4" novalidate>@csrf @method('PUT')
                <input type="hidden" name="available" value="1">
                <x-form.select name="_facility" label="Facility" :options="$notSold" x-model="f" placeholder="Choose a facility" />
                <x-form.money name="price" label="Price there (optional)" :scale="2" hint="Leave empty to use the standard price." />
                <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn ::disabled="!f">Start selling</x-btn></div>
            </form>
        </x-dialog>
    @endif

    <x-card title="Prices" subtitle="A new price starts now and closes the previous one. Orders already open keep the price they were taken at." flush class="mt-8" id="prices" data-testid="prices">
        <x-slot:aside>@if ($canPrice)<x-btn type="button" icon="plus" @click="$dispatch('open-modal', 'new-price')">Set a new price</x-btn>@endif</x-slot:aside>
        <div class="table-scroll"><table class="data-table"><thead><tr><th>Applies to</th><th class="num">Amount</th><th>From</th><th>Until</th><th>Status</th></tr></thead><tbody>
        @forelse (collect($p['prices'] ?? [])->sortByDesc('validFrom') as $pr)
            <tr><td>{{ ! empty($pr['facilityId']) ? ($facName[$pr['facilityId']] ?? 'One facility') : 'Everywhere' }}<div class="text-xs text-stone-500">{{ $listName[$pr['priceListId'] ?? ''] ?? '' }}</div></td><td class="num"><x-money :value="$pr['amount']" /></td><td><x-time :at="$pr['validFrom'] ?? null" /></td><td>@if (! empty($pr['validTo']))<x-time :at="$pr['validTo']" />@else<span class="text-stone-400">Open-ended</span>@endif</td><td><x-badge :tone="($pr['active'] ?? true) && empty($pr['validTo']) ? 'good' : 'default'">{{ ($pr['active'] ?? true) && empty($pr['validTo']) ? 'Current' : 'Past' }}</x-badge></td></tr>
        @empty<tr><td colspan="5"><x-empty title="No price yet" text="Set a price so it can be sold." icon="tag" /></td></tr>@endforelse
        </tbody></table></div>
    </x-card>
    @if ($canPrice)
        <x-dialog name="new-price" title="Set a new price" subtitle="{{ $p['name'] }}">
            <form method="POST" action="{{ route('setup.catalog.product.price', $id) }}" class="grid gap-4" x-data="{ scope: 'ALL' }" novalidate>@csrf
                <x-form.money name="amount" label="New price" required :scale="2" :quick="['500', '1000', '2000', '5000']" />
                <x-form.segmented name="scope" label="Applies to" :options="['ALL' => 'Every facility', 'ONE' => 'One facility']" value="ALL" x-model="scope" />
                <div x-show="scope === 'ONE'" x-cloak><x-form.select name="facilityId" label="Facility" :options="collect($facilities)->map(fn ($f) => ['value' => $f['id'], 'label' => $f['name'] ?? $f['code']])->values()->all()" placeholder="Choose a facility" /></div>
                <x-form.date name="validFrom" label="Starts on (optional)" hint="Leave empty to start now." />
                <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Save price</x-btn></div>
            </form>
        </x-dialog>
    @endif

    <form method="POST" action="{{ route('setup.catalog.product.stock', $id) }}" novalidate data-testid="stock-links" x-data="{ rows: @js($links === [] ? [] : $links), add() { this.rows.push({ stockItemId: '', quantityPerUnit: '1' }) } }">@csrf @method('PUT')
        <x-form.section class="mt-8" title="Stock used per sale" description="Which stock items one sale of this product uses up, and how much. Leave empty if it does not use stock.">
            <template x-for="(r, i) in rows" :key="i">
                <div class="grid items-end gap-3 sm:grid-cols-[1fr_10rem_auto]">
                    <div><label class="f-label" style="display:block;margin-bottom:0.25rem">Stock item</label><div class="f-box"><select class="f-input" :name="`links[${i}][stockItemId]`" x-model="r.stockItemId">
                        <option value="">Choose...</option>@foreach ($itemOpts as $o)<option value="{{ $o['value'] }}">{{ $o['label'] }}</option>@endforeach</select></div></div>
                    <div><label class="f-label" style="display:block;margin-bottom:0.25rem">Used per sale</label><div class="f-box"><input class="f-input f-num" inputmode="decimal" :name="`links[${i}][quantityPerUnit]`" x-model="r.quantityPerUnit"></div></div>
                    <button type="button" class="f-btn" x-on:click="rows.splice(i, 1)" aria-label="Remove">Remove</button>
                </div>
            </template>
            <p class="f-hint" x-show="rows.length === 0">No stock items linked.</p>
            @if ($canManage)<div class="flex flex-wrap gap-2"><button type="button" class="f-btn" x-on:click="add()">Add a stock item</button></div>@endif
        </x-form.section>
        @if ($canManage)<x-form.actions submit="Save stock links" :show-cancel="false" />@endif
    </form>
    @endif
</x-layouts.app>
