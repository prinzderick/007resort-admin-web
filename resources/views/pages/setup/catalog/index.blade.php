@php
    $catOpts = collect($categories->items())->map(fn ($c) => ['value' => $c['id'], 'label' => $c['name']])->values()->all();
    $routeOpts = collect($prepRoutes->items())->map(fn ($r) => ['value' => $r['id'], 'label' => $r['name']])->values()->all();
    $taxOpts = collect($taxRates->items())->where('active', true)->map(fn ($r) => ['value' => $r['id'], 'label' => $r['name'].' ('.rtrim(rtrim($r['ratePercent'], '0'), '.').'%)'])->values()->all();
    $routeNames = collect($prepRoutes->items())->pluck('name', 'id')->all();
    $taxNames = collect($taxRates->items())->pluck('name', 'id')->all();
    $facOpts = collect($facilities)->map(fn ($f) => ['value' => $f['id'], 'label' => $f['name'] ?? $f['code']])->values()->all();
@endphp
<x-layouts.app title="Catalog & prices">
    <x-page-header title="Catalog & prices" subtitle="What you sell, where you sell it and for how much. Products are shared across the property; each facility chooses which ones it sells." :crumbs="['Setup' => route('setup.index'), 'Catalog & prices' => null]">
        <x-slot:actions>@if ($canManage && $tab === 'products')<x-btn type="button" icon="plus" @click="$dispatch('open-modal', 'add-product')" data-testid="add-product">Add product</x-btn>@endif</x-slot:actions>
    </x-page-header>
    <x-tabs :tabs="$tabs" :current="$tab" />

    @if ($tab === 'products')
        <x-filter-form :reset="route('setup.catalog')">
            <x-filter-text name="q" label="Search" :value="request('q')" placeholder="Name, SKU or barcode" />
            <x-filter-select name="category" label="Category" :options="$catNames" :value="request('category')" all="All categories" width="13rem" />
            <x-filter-select name="facility" label="Show availability at" :options="collect($facilities)->pluck('name', 'id')->all()" :value="$facilityId" all="No facility" width="14rem" />
            <x-filter-select name="active" label="Status" :options="['1' => 'Active', '0' => 'Switched off']" :value="request('active')" all="All" width="9rem" />
        </x-filter-form>
        <x-card flush x-data="tableTools">
            <x-fetch :of="$products" what="Products" />
            @if ($products->ok())
                <div class="table-scroll"><table class="data-table" data-testid="products"><thead><tr><th>Product</th><th>Category</th><th class="num">Price</th><th>Tax</th><th>Prepared by</th><th>Sold at</th>@if ($facilityId)<th>Here</th>@endif<th class="w-12"></th></tr></thead><tbody>
                @forelse ($products->items() as $p)
                    @php $pid = $p['id']; $here = $sold[$pid] ?? null; $on = $availability[$pid] ?? true; @endphp
                    <tr data-row class="{{ ($p['active'] ?? true) ? '' : 'opacity-60' }}">
                        <td><a class="font-medium text-brand-700 underline decoration-brand-200 underline-offset-2" href="{{ route('setup.catalog.product', $pid) }}">{{ $p['name'] }}</a>@if (! ($p['active'] ?? true)) <x-badge>off</x-badge>@endif<div class="text-xs text-stone-500">{{ $p['sku'] }}@if (! empty($p['barcode'])) &middot; {{ $p['barcode'] }}@endif</div></td>
                        <td>{{ $p['categoryName'] ?? ($catNames[$p['categoryId'] ?? ''] ?? '') }}</td>
                        <td class="num"><x-money :value="$p['defaultPrice'] ?? '0'" /></td>
                        <td class="text-sm text-stone-600">{{ $taxNames[$p['taxRateId'] ?? ''] ?? '-' }}</td>
                        <td class="text-sm text-stone-600">{{ $routeNames[$p['prepRouteId'] ?? ''] ?? '-' }}</td>
                        <td class="text-sm">{{ (int) ($p['facilityCount'] ?? 0) }} {{ ($p['facilityCount'] ?? 0) == 1 ? 'facility' : 'facilities' }}@if ($p['trackStock'] ?? false)<span class="ml-1 text-xs {{ ($p['hasStockLink'] ?? false) ? 'text-brand-700' : 'text-amber-700' }}" title="{{ ($p['hasStockLink'] ?? false) ? 'Deducts stock when sold' : 'Tracks stock but has no stock link yet' }}">{{ ($p['hasStockLink'] ?? false) ? 'stock linked' : 'no stock link' }}</span>@endif</td>
                        @if ($facilityId)<td>@if ($here === null)<span class="text-stone-400">Not sold here</span>@elseif ($canAvail)<form method="POST" action="{{ route('setup.availability', $pid) }}" class="flex items-center gap-2">@csrf @method('PUT')<input type="hidden" name="facilityId" value="{{ $facilityId }}"><input type="hidden" name="available" value="{{ $on ? 0 : 1 }}"><x-badge :tone="$on ? 'good' : 'bad'">{{ $on ? 'Available' : 'Unavailable' }}</x-badge><button class="text-xs font-medium text-brand-700 underline">{{ $on ? 'Mark unavailable' : 'Make available' }}</button></form>@else<x-badge :tone="$on ? 'good' : 'bad'">{{ $on ? 'Available' : 'Unavailable' }}</x-badge>@endif</td>@endif
                        <td class="text-right"><a class="text-sm font-medium text-brand-700 underline" href="{{ route('setup.catalog.product', $pid) }}">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty title="No products match" text="Change the filters, or add a product." icon="tag" /></td></tr>
                @endforelse
                </tbody></table></div>
            @endif
        </x-card>
        @if ($canManage)
            <x-dialog name="add-product" title="Add a product" subtitle="Create it here, then choose which facilities sell it." maxWidth="max-w-2xl">
                <form method="POST" action="{{ route('setup.catalog.product.create') }}" class="grid gap-4" novalidate>@csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form.text name="name" label="Name" required :maxlength="200" />
                        <x-form.text name="sku" label="SKU" required :maxlength="64" hint="A short unique code, e.g. FD-JOL-CH." />
                        <x-form.select name="categoryId" label="Category" required :options="$catOpts" placeholder="Choose a category" />
                        <x-form.select name="kind" label="Kind" :options="$kinds" value="GOOD" :searchable="false" />
                    </div>
                    <x-form.money name="price" label="Price" required :scale="2" :quick="['500', '1000', '2000', '5000']" hint="The standard price everywhere. You can set a different price at a facility afterwards." />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form.select name="prepRouteId" label="Prepared by" :options="$routeOpts" :clearable="true" placeholder="Not prepared (e.g. bottled drinks)" />
                        <x-form.select name="taxRateId" label="Tax rate" :options="$taxOpts" :clearable="true" placeholder="Default" />
                    </div>
                    <x-form.select name="facilityIds" label="Sold at" :options="$facOpts" :multiple="true" placeholder="Choose facilities (you can add more later)" />
                    <x-form.toggle name="trackStock" label="Track stock" description="Selling it deducts stock. You link the stock items after creating it." :value="false" />
                    <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Create product</x-btn></div>
                </form>
            </x-dialog>
        @endif

    @elseif ($tab === 'categories')
        <x-card title="Categories" subtitle="How products are grouped on the menu. Set a kitchen or bar route for a whole category at once." flush>
            <x-slot:aside>@if ($canManage)<x-btn type="button" icon="plus" @click="$dispatch('open-modal', 'add-category')">Add category</x-btn>@endif</x-slot:aside>
            <x-fetch :of="$categories" what="Categories" />
            @if ($categories->ok())
                <div class="table-scroll"><table class="data-table" data-testid="categories"><thead><tr><th>Category</th><th class="num">Order</th><th class="w-44"></th></tr></thead><tbody>
                @forelse ($categories->items() as $c)
                    <tr><td class="font-medium">{{ $c['name'] }}</td><td class="num">{{ $c['sortOrder'] ?? 0 }}</td><td class="text-right">@if ($canManage)<button type="button" class="text-sm font-medium text-brand-700 underline" @click="$dispatch('open-modal', 'cat-route-{{ $c['id'] }}')">Set kitchen / bar route</button>@endif</td></tr>
                @empty<tr><td colspan="3"><x-empty title="No categories yet" text="Add a category such as Main dishes or Drinks." icon="tag" /></td></tr>@endforelse
                </tbody></table></div>
            @endif
        </x-card>
        @if ($canManage)
            <x-dialog name="add-category" title="Add a category">
                <form method="POST" action="{{ route('setup.catalog.category.create') }}" class="grid gap-4" novalidate>@csrf
                    <x-form.text name="name" label="Name" required :maxlength="120" />
                    <x-form.stepper name="sortOrder" label="Position on the menu" :value="0" :min="0" :max="999" hint="Lower numbers come first." />
                    <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Add category</x-btn></div>
                </form>
            </x-dialog>
            @foreach ($categories->items() as $c)
                <x-dialog name="cat-route-{{ $c['id'] }}" title="Route for {{ $c['name'] }}" subtitle="Decides whether orders for these products go to the kitchen, the bar, or nowhere.">
                    <form method="POST" action="{{ route('setup.catalog.category.route', $c['id']) }}" class="grid gap-4" novalidate>@csrf
                        <x-form.select name="prepRouteId" label="Prepared by" :options="$routeOpts" :clearable="true" placeholder="Not prepared" />
                        <x-form.toggle name="applyToProducts" label="Apply to every product in this category" description="Off: only products you add later inherit it." :value="true" />
                        <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Save route</x-btn></div>
                    </form>
                </x-dialog>
            @endforeach
        @endif

    @elseif ($tab === 'prices')
        <x-card title="Price lists" subtitle="The default list is used at checkout. Other lists are kept for later." flush>
            <x-slot:aside>@if ($canPrice)<x-btn type="button" variant="secondary" icon="plus" @click="$dispatch('open-modal', 'add-price-list')">Add list</x-btn>@endif</x-slot:aside>
            <x-fetch :of="$priceLists" what="Price lists" />
            @if ($priceLists->ok())
                <div class="table-scroll"><table class="data-table"><thead><tr><th>Name</th><th>Currency</th><th>Status</th></tr></thead><tbody>
                @foreach ($priceLists->items() as $l)<tr><td class="font-medium">{{ $l['name'] }}</td><td>{{ $l['currency'] ?? 'NGN' }}</td><td>@if ($l['isDefault'] ?? false)<x-badge tone="good">Default</x-badge>@else<x-badge>{{ ($l['active'] ?? true) ? 'Active' : 'Off' }}</x-badge>@endif</td></tr>@endforeach
                </tbody></table></div>
            @endif
        </x-card>
        @if ($canPrice)
            <x-dialog name="add-price-list" title="Add a price list"><form method="POST" action="{{ route('setup.catalog.price-list') }}" class="grid gap-4" novalidate>@csrf
                <x-form.text name="name" label="Name" required :maxlength="80" placeholder="e.g. Weekend" />
                <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Add list</x-btn></div></form></x-dialog>
        @endif
        <x-card title="Prices by product" subtitle="Open a product to change its price, set a price for one facility, or see the price history." flush x-data="tableTools">
            <x-table-tools placeholder="Filter products..." :perPage="false" :selectable="false" />
            @if ($products->ok())
                <div class="table-scroll"><table class="data-table"><thead><tr><th>Product</th><th>Category</th><th class="num">Standard price</th><th class="w-12"></th></tr></thead><tbody>
                @foreach ($products->items() as $p)<tr data-row><td class="font-medium">{{ $p['name'] }}<div class="text-xs font-normal text-stone-500">{{ $p['sku'] }}</div></td><td>{{ $p['categoryName'] ?? '' }}</td><td class="num"><x-money :value="$p['defaultPrice'] ?? '0'" /></td><td class="text-right"><a class="text-sm font-medium text-brand-700 underline" href="{{ route('setup.catalog.product', $p['id']) }}#prices">Change</a></td></tr>@endforeach
                </tbody></table></div>
            @endif
        </x-card>

    @elseif ($tab === 'tax')
        <x-card title="Tax rates" subtitle="Rates a product can use. A change applies to new orders only. Whether VAT is charged at all is set under Business & receipts." flush>
            <x-slot:aside>@if ($canManage)<x-btn type="button" icon="plus" @click="$dispatch('open-modal', 'add-tax')">Add tax rate</x-btn>@endif</x-slot:aside>
            <x-fetch :of="$taxRates" what="Tax rates" />
            @if ($taxRates->ok())
                <div class="table-scroll"><table class="data-table" data-testid="tax-rates"><thead><tr><th>Name</th><th>Code</th><th class="num">Rate</th><th>Status</th><th class="w-12"></th></tr></thead><tbody>
                @foreach ($taxRates->items() as $r)<tr><td class="font-medium">{{ $r['name'] }}</td><td class="font-mono text-xs">{{ $r['code'] }}</td><td class="num">{{ rtrim(rtrim($r['ratePercent'], '0'), '.') ?: '0' }}%</td><td><x-badge :tone="($r['active'] ?? true) ? 'good' : 'default'">{{ ($r['active'] ?? true) ? 'Active' : 'Off' }}</x-badge></td><td class="text-right">@if ($canManage)<button type="button" class="text-sm font-medium text-brand-700 underline" @click="$dispatch('open-modal', 'tax-{{ $r['id'] }}')">Edit</button>@endif</td></tr>@endforeach
                </tbody></table></div>
            @endif
        </x-card>
        @if ($canManage)
            <x-dialog name="add-tax" title="Add a tax rate"><form method="POST" action="{{ route('setup.catalog.tax.create') }}" class="grid gap-4" novalidate>@csrf
                <x-form.text name="name" label="Name" required :maxlength="80" placeholder="e.g. Tourism levy" />
                <x-form.text name="code" label="Code" required :maxlength="32" hint="Letters, numbers and underscores. Cannot be changed later." />
                <x-form.percent name="ratePercent" label="Rate" :value="0" :step="0.5" />
                <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Add tax rate</x-btn></div></form></x-dialog>
            @foreach ($taxRates->items() as $r)
                <x-dialog name="tax-{{ $r['id'] }}" title="Edit {{ $r['name'] }}"><form method="POST" action="{{ route('setup.catalog.tax.update', $r['id']) }}" class="grid gap-4" novalidate>@csrf @method('PATCH')
                    <x-form.text name="name" label="Name" required :value="$r['name']" :maxlength="80" />
                    <x-form.percent name="ratePercent" label="Rate" :value="(float) $r['ratePercent']" :step="0.5" />
                    <x-form.toggle name="active" label="In use" description="Off hides it from the product form. Products already using it keep it." :value="$r['active'] ?? true" />
                    <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Save</x-btn></div></form></x-dialog>
            @endforeach
        @endif

    @else
        @include('pages.setup.catalog.import')
    @endif
</x-layouts.app>
