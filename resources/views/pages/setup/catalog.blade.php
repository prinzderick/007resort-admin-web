<x-layouts.app title="Catalog">
    <x-page-header title="Products, prices & categories" subtitle="Products are listed per facility. Prices and tax are resolved by the API; changes are audited there." />
    <form method="GET" class="mb-5 flex items-end gap-3"><div><label class="mb-1 block text-sm font-medium">Facility</label>
        <select name="facility" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3 text-sm" onchange="this.form.submit()">@foreach ($facilities as $f)<option value="{{ $f['id'] ?? '' }}" @selected($facilityId === ($f['id'] ?? ''))>{{ $f['name'] ?? '' }}</option>@endforeach</select></div></form>
    <x-card title="Products" flush>
        <x-fetch :of="$products" what="Products" />
        <x-fetch :of="$avail" what="Availability" />
        @if ($products->ok())
            <div class="overflow-x-auto"><table class="data-table" data-testid="products"><thead><tr><th>Product</th><th>Category</th><th>Kind</th><th class="text-right">Price</th><th>Tax</th><th>Prep route</th><th>Available here</th>@if ($canManage)<th></th>@endif</tr></thead><tbody>
            @forelse ($products->items() as $p)
                @php $on = $availability[$p['id'] ?? ''] ?? true; $pid = $p['id'] ?? ''; @endphp
                <tr class="{{ ($p['active'] ?? true) ? '' : 'opacity-60' }}"><td class="font-medium">{{ $p['name'] ?? '' }}@if (! ($p['active'] ?? true)) <x-badge>inactive</x-badge>@endif<div class="text-xs font-normal text-stone-500">{{ $p['sku'] ?? '' }}</div></td><td>{{ $catNames[$p['categoryId'] ?? ''] ?? '' }}</td><td>{{ $p['kind'] ?? '' }}</td>
                    <td class="text-right">
                        @if ($canPrice && $pid !== '')
                            <form method="POST" action="{{ route('setup.catalog.price', $pid) }}" class="flex items-center justify-end gap-1">@csrf @method('PUT')<input type="hidden" name="facilityId" value="{{ $facilityId }}">
                                <input name="amount" value="{{ $p['price'] ?? '' }}" inputmode="decimal" aria-label="Price" class="min-h-10 w-28 rounded-lg border border-stone-300 px-2 text-right text-sm">
                                <label class="text-xs text-stone-600" title="Untick to change the property-wide price"><input type="checkbox" name="onlyHere" value="1"> here only</label>
                                <button class="min-h-10 rounded-lg border border-stone-300 px-2 text-xs hover:bg-stone-50">Set</button></form>
                        @else<x-money :value="$p['price'] ?? '0'" />@endif
                    </td>
                    <td class="text-xs">{{ ($p['taxInclusive'] ?? false) ? 'incl. ' : 'excl. ' }}{{ $p['taxRatePercent'] ?? '0' }}%</td><td>{{ $p['prepRoute']['stationName'] ?? $p['prepRoute']['kind'] ?? '-' }}</td>
                    <td>@if (auth_staff()->can('catalog.availability.manage') && $facilityId && $pid !== '')
                        <form method="POST" action="{{ route('setup.availability', $pid) }}" class="flex items-center gap-2">@csrf @method('PUT')<input type="hidden" name="facilityId" value="{{ $facilityId }}"><input type="hidden" name="available" value="{{ $on ? 0 : 1 }}">
                            <x-badge :tone="$on ? 'good' : 'bad'">{{ $on ? 'Yes' : 'No (86)' }}</x-badge><button class="min-h-10 rounded-lg border border-stone-300 px-3 text-xs hover:bg-stone-50">{{ $on ? 'Mark unavailable' : 'Make available' }}</button></form>
                        @else<x-badge :tone="$on ? 'good' : 'bad'">{{ $on ? 'Yes' : 'No' }}</x-badge>@endif</td>
                    @if ($canManage)<td>@if ($pid !== '')<a class="underline" href="{{ route('setup.catalog', ['facility' => $facilityId, 'edit' => $pid]) }}#product-form">Edit</a>@endif</td>@endif</tr>
            @empty<tr><td colspan="8" class="text-center text-stone-500">No products at this facility.</td></tr>@endforelse
            </tbody></table></div>
        @endif
    </x-card>

    @if ($canManage)
        @php
            $catOpts = ['' => 'Choose...'] + collect($categories->items())->pluck('name', 'id')->all();
            $routeOpts = ['' => $edit ? '(unchanged)' : '(none)'] + collect($prepRoutes->items())->mapWithKeys(fn ($r) => [($r['id'] ?? '') => ($r['name'] ?? '').' ('.($r['kind'] ?? '').')'])->all();
            $taxOpts = ['' => $edit ? '(unchanged)' : '(default)'] + collect($taxRates->items())->mapWithKeys(fn ($r) => [($r['id'] ?? '') => ($r['name'] ?? '').' ('.($r['ratePercent'] ?? '0').'%)'])->all();
            $kinds = ['GOOD' => 'Good', 'SERVICE' => 'Service', 'TICKET' => 'Ticket', 'RENTAL' => 'Rental', 'MEMBERSHIP' => 'Membership', 'FEE' => 'Fee'];
        @endphp
        <x-card :title="$edit ? 'Edit product' : 'New product'" id="product-form">
            <form method="POST" action="{{ $edit ? route('setup.catalog.product.update', $edit['id']) : route('setup.catalog.product.create') }}" class="grid gap-x-4 sm:grid-cols-2" data-testid="product-form">
                @csrf @if ($edit) @method('PATCH') @endif
                <input type="hidden" name="facilityId" value="{{ $facilityId }}">
                @unless ($edit)<x-field name="sku" label="SKU" required />@endunless
                <x-field name="name" label="Name" :value="$edit['name'] ?? ''" required />
                <x-field name="categoryId" label="Category" :options="$catOpts" :value="$edit['categoryId'] ?? ''" required />
                <x-field name="kind" label="Kind" :options="$kinds" :value="$edit['kind'] ?? 'GOOD'" required />
                @unless ($edit)<x-field name="price" label="Price (NGN)" required hint="Also becomes available at the selected facility." />@endunless
                <x-field name="prepRouteId" label="Prep route" :options="$routeOpts" :hint="$edit ? 'Currently: '.($edit['prepRoute']['stationName'] ?? $edit['prepRoute']['kind'] ?? 'none') : null" />
                <x-field name="taxRateId" label="Tax rate" :options="$taxOpts" />
                <div class="flex flex-wrap items-center gap-5 sm:col-span-2">
                    <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="trackStock" value="1" @checked($edit['trackStock'] ?? false)> Tracks stock</label>
                    <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="taxExempt" value="1"> Tax exempt</label>
                    @if ($edit)<label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" @checked($edit['active'] ?? true)> Active (can be sold)</label>@endif
                </div>
                <div class="sm:col-span-2"><x-btn>{{ $edit ? 'Save product' : 'Create product' }}</x-btn> @if ($edit)<x-btn variant="secondary" :href="route('setup.catalog', ['facility' => $facilityId])">Cancel</x-btn>@endif</div>
            </form>
        </x-card>
    @endif

    <x-card title="Categories" flush>
        <x-fetch :of="$categories" what="Categories" />
        @if ($categories->ok())<ul class="flex flex-wrap gap-2 p-4 text-sm">@foreach ($categories->items() as $c)<li class="rounded-lg border border-stone-200 px-3 py-1.5">{{ $c['name'] ?? '' }}</li>@endforeach</ul>@endif
        @if ($canManage)
            <form method="POST" action="{{ route('setup.catalog.category.create') }}" class="flex flex-wrap items-end gap-3 border-t border-stone-100 p-4">@csrf<input type="hidden" name="facilityId" value="{{ $facilityId }}">
                <div><label class="mb-1 block text-sm font-medium">New category</label><input name="name" required maxlength="120" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm"></div>
                <div><label class="mb-1 block text-sm font-medium">Sort order</label><input name="sortOrder" type="number" class="min-h-11 w-28 rounded-lg border border-stone-300 px-3 text-sm"></div>
                <x-btn>Add category</x-btn></form>
        @endif
    </x-card>
    @unless ($canManage)
        <x-pending-api title="Not offered to this account" :items="['Creating and editing products and categories needs the catalog.manage permission; changing prices needs pricing.manage']" />
    @endunless
    <x-pending-api :items="['Per-product or per-category tax overrides beyond the tax rate chosen on a product', 'Renaming or deactivating a category (PATCH /catalog/categories/{id} exists; not wired to a form yet)']" />
</x-layouts.app>
