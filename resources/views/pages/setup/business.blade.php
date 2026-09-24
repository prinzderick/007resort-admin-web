<x-layouts.app title="Business & receipts">
    <x-page-header title="Business & receipts" subtitle="Who the business is on receipts and reports, and whether VAT is charged (ADR-0011: VAT is an admin setting, off by default)." :crumbs="['Setup' => route('setup.index'), 'Business & receipts' => null]" />

    <x-card title="Business profile" subtitle="Read from the site record">
        <x-fetch :of="$site" what="Business profile" />
        @if ($site->ok())
            @php $b = (array) $site->data; @endphp
            <dl class="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                <div><dt class="text-xs font-medium uppercase tracking-wide text-stone-500">Trading name</dt><dd class="mt-0.5 font-medium">{{ $b['name'] ?? '-' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-stone-500">Address</dt><dd class="mt-0.5">{{ $b['address'] ?? '-' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-stone-500">Time zone</dt><dd class="mt-0.5">{{ $b['timezone'] ?? '-' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-stone-500">Currency</dt><dd class="mt-0.5">{{ $b['currency'] ?? '-' }}</dd></div>
            </dl>
            <p class="mt-4 text-xs text-stone-500">Editing the business profile needs an API endpoint (PATCH /organization/site) that is not in the contract yet.</p>
        @endif
    </x-card>

    <div id="tax"></div>
    <x-fetch :of="$setting" what="Tax setting" />
    @if ($setting->ok())
        @php $t = $setting->data; @endphp
        <x-card title="VAT" subtitle="Changing this affects every receipt and report from now on, and is audited." data-testid="tax-card">
            <x-settings-meta entity-type="OrganizationTaxSetting" />
            <form method="POST" action="{{ route('setup.tax.update') }}" class="max-w-lg" x-data="dirtyGuard">
                @csrf @method('PUT')
                <input type="hidden" name="etag" value="{{ $etag }}">
                <label class="mb-3 flex min-h-11 items-center gap-2 text-sm font-medium"><input type="checkbox" name="vatEnabled" value="1" @checked(old('vatEnabled', $t['vatEnabled'] ?? false)) class="size-5"> The property is VAT-registered (show VAT on receipts)</label>
                <x-field name="vatRatePercent" label="Default VAT rate (%)" :value="$t['vatRatePercent'] ?? '7.5'" required hint="Nigeria standard rate is 7.5. Inactive until VAT is switched on." />
                <label class="mb-3 flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="pricesTaxInclusive" value="1" @checked(old('pricesTaxInclusive', $t['pricesTaxInclusive'] ?? false)) class="size-5"> Menu prices already include tax</label>
                <x-field name="vatNumber" label="Tax identification number (TIN)" :value="$t['vatNumber'] ?? ''" />
                <x-btn onclick="return confirm('Save the tax setting? It applies to all new receipts.')">Save tax setting</x-btn>
            </form>
        </x-card>
    @endif

    <x-pending-api :items="['Receipt settings (header, footer, logo, what prints) and per-facility payment methods: the API contract has no receipt-settings endpoint yet', 'Editing the business profile (name, address, contacts)']" />
</x-layouts.app>
