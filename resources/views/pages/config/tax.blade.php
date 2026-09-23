<x-layouts.app title="Tax / VAT">
    <x-page-header title="Tax / VAT" subtitle="ADR-0011: VAT is an admin setting, off by default. Changing it affects every receipt and report from now on, and is audited." />
    <x-config-nav />
    <x-fetch :of="$setting" what="Tax setting" />
    @if ($setting->ok())
        @php $t = $setting->data; @endphp
        <x-card title="VAT setting" data-testid="tax-card">
            <form method="POST" action="{{ route('config.tax.update') }}" class="max-w-lg">
                @csrf @method('PUT')
                <input type="hidden" name="etag" value="{{ $etag }}">
                <label class="mb-3 flex min-h-11 items-center gap-2 text-sm font-medium"><input type="checkbox" name="vatEnabled" value="1" @checked(old('vatEnabled', $t['vatEnabled'])) class="size-5"> The property is VAT-registered (show VAT on receipts)</label>
                <x-field name="vatRatePercent" label="Default VAT rate (%)" :value="$t['vatRatePercent']" required hint="Nigeria standard rate is 7.5. Inactive until VAT is switched on." />
                <label class="mb-3 flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="pricesTaxInclusive" value="1" @checked(old('pricesTaxInclusive', $t['pricesTaxInclusive'])) class="size-5"> Menu prices already include tax</label>
                <x-field name="vatNumber" label="Tax identification number (TIN)" :value="$t['vatNumber'] ?? ''" />
                <x-btn onclick="return confirm('Save the tax setting? It applies to all new receipts.')">Save tax setting</x-btn>
            </form>
        </x-card>
    @endif
</x-layouts.app>
