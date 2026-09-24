@php
    $b = $business->ok() ? (array) $business->data : [];
    $r = $receipt->ok() ? (array) $receipt->data : [];
    $t = $setting->ok() ? (array) $setting->data : [];
    $tzs = array_values(array_unique(array_filter([$b['timezone'] ?? null, 'Africa/Lagos', 'UTC', 'Africa/Accra', 'Europe/London'])));
@endphp
<x-layouts.app title="Business & receipts">
    <x-page-header title="Business & receipts" subtitle="Who the business is, what prints on every receipt, and whether VAT is charged. Each part saves on its own." :crumbs="['Setup' => route('setup.index'), 'Business & receipts' => null]">
        <x-slot:actions>
            <nav class="flex gap-2 text-sm" aria-label="On this page"><a class="f-preset" href="#business">Business</a><a class="f-preset" href="#receipt">Receipt</a><a class="f-preset" href="#tax">VAT</a></nav>
        </x-slot:actions>
    </x-page-header>

    <div id="business" class="scroll-mt-24">
        <x-fetch :of="$site" what="Business profile" />
        @if ($business->ok())
            <x-settings-meta entity-type="BusinessProfile" />
            <form method="POST" action="{{ route('setup.business.update') }}" novalidate data-testid="business-form">@csrf @method('PUT')
                <input type="hidden" name="etag" value="{{ $businessEtag }}">
                <x-form.section title="Business profile" description="The name and contact details used on reports and receipts.">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form.text name="organizationName" label="Company name" required :value="old('organizationName', $b['organizationName'] ?? '')" :disabled="! $canEdit" />
                        <x-form.text name="siteName" label="Trading name (this site)" required :value="old('siteName', $b['siteName'] ?? '')" :disabled="! $canEdit" />
                    </div>
                    <x-form.text name="address" label="Address" :value="old('address', $b['address'] ?? '')" :disabled="! $canEdit" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form.text name="phone" label="Phone" :value="old('phone', $b['phone'] ?? '')" inputmode="tel" :disabled="! $canEdit" />
                        <x-form.text name="email" label="Email" type="email" :value="old('email', $b['email'] ?? '')" :disabled="! $canEdit" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form.select name="timezone" label="Time zone" :options="array_combine($tzs, $tzs)" :value="old('timezone', $b['timezone'] ?? 'Africa/Lagos')" hint="Days, opening hours and reports follow this zone." :disabled="! $canEdit" />
                        <x-form.text name="currency" label="Currency" :value="$b['currency'] ?? 'NGN'" disabled hint="All amounts are in naira." />
                    </div>
                </x-form.section>
                @if ($canEdit)<x-form.actions submit="Save business profile" />@endif
            </form>
        @elseif ($site->ok())
            <x-card title="Business profile" subtitle="Read-only">
                <dl class="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">@foreach (['name' => 'Trading name', 'address' => 'Address', 'timezone' => 'Time zone', 'currency' => 'Currency'] as $k => $l)<div><dt class="t-label">{{ $l }}</dt><dd class="mt-0.5 font-medium">{{ $site->data[$k] ?? '-' }}</dd></div>@endforeach</dl>
            </x-card>
        @endif
    </div>

    <div id="receipt" class="mt-10 scroll-mt-24">
        <x-fetch :of="$receipt" what="Receipt settings" />
        @if ($receipt->ok())
            <form method="POST" action="{{ route('setup.receipt.update') }}" novalidate data-testid="receipt-form" x-data="{ cols: '{{ $r['paperColumns'] ?? 48 }}' }">@csrf @method('PUT')
                <input type="hidden" name="etag" value="{{ $receiptEtag }}">
                <x-form.section title="Receipt" description="What prints at the top and bottom of every receipt. Receipts already issued are never changed; reprints keep the original." stacked>
                    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
                        <div class="grid gap-4">
                            <x-form.text name="businessName" label="Name on the receipt" required :value="old('businessName', $r['businessName'] ?? '')" :disabled="! $canEdit" />
                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-form.text name="address" label="Address" :value="old('address', $r['address'] ?? '')" :disabled="! $canEdit" />
                                <x-form.text name="phone" label="Phone" :value="old('phone', $r['phone'] ?? '')" :disabled="! $canEdit" />
                            </div>
                            <x-form.text name="headerNote" label="Line under the name" :value="old('headerNote', $r['headerNote'] ?? '')" :maxlength="300" hint="For example a slogan or your opening hours." :disabled="! $canEdit" />
                            <x-form.text name="footer" label="Footer" :value="old('footer', $r['footer'] ?? '')" :multiline="true" :rows="2" :maxlength="300" :disabled="! $canEdit" />
                            <x-form.text name="logoUrl" label="Logo web address" type="url" :value="old('logoUrl', $r['logoUrl'] ?? '')" hint="A link to a small black-and-white image. Leave empty for no logo." :disabled="! $canEdit" />
                            <x-form.segmented name="paperColumns" label="Paper width" :options="['48' => 'Wide (80 mm)', '32' => 'Narrow (58 mm)']" :value="(string) ($r['paperColumns'] ?? 48)" x-model="cols" :disabled="! $canEdit" />
                            <x-form.toggle name="showTin" label="Show the tax number" :description="empty($r['tin']) ? 'No tax number is set yet (add it under VAT below).' : 'Prints TIN '.$r['tin'].'.'" :value="old('showTin', $r['showTin'] ?? true)" :disabled="! $canEdit" />
                        </div>
                        <aside aria-label="Receipt preview" class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
                            <div class="t-label mb-2">Preview</div>
                            <pre class="overflow-hidden whitespace-pre-wrap rounded-lg bg-stone-50 p-3 text-center font-mono text-[11px] leading-snug text-stone-800" :style="cols === '32' ? 'width:15rem;margin:auto' : ''">{{ $r['businessName'] ?? 'Your business' }}
{{ $r['address'] ?? '' }}
{{ $r['headerNote'] ?? '' }}
--------------------------
Order RESTAU-000001
2 x Jollof rice ........ 9,000.00
TOTAL ................. 12,000.00
--------------------------
{{ $r['footer'] ?? '' }}</pre>
                            <p class="mt-2 text-xs text-stone-500">Saved values. Save to update the preview.</p>
                        </aside>
                    </div>
                </x-form.section>
                @if ($canEdit)<x-form.actions submit="Save receipt settings" />@endif
            </form>
        @endif
    </div>

    <div id="tax" class="mt-10 scroll-mt-24">
        <x-fetch :of="$setting" what="Tax setting" />
        @if ($setting->ok())
            <form method="POST" action="{{ route('setup.tax.update') }}" novalidate data-testid="tax-card" x-data="{ on: {{ old('vatEnabled', $t['vatEnabled'] ?? false) ? 'true' : 'false' }} }">@csrf @method('PUT')
                <input type="hidden" name="etag" value="{{ $etag }}">
                <x-form.section title="VAT" description="Off by default. Switching it on adds VAT to new receipts and reports from now on. Everything is audited.">
                    <x-form.toggle name="vatEnabled" label="The property charges VAT" description="Show VAT on receipts and in reports." x-model="on" :value="old('vatEnabled', $t['vatEnabled'] ?? false)" :disabled="! $canTax" />
                    <div class="grid gap-4 sm:grid-cols-2" x-show="on" x-cloak>
                        <x-form.percent name="vatRatePercent" label="Standard VAT rate" :value="(float) old('vatRatePercent', $t['vatRatePercent'] ?? 7.5)" :step="0.5" hint="Nigeria's standard rate is 7.5%." :disabled="! $canTax" />
                        <x-form.text name="vatNumber" label="Tax identification number (TIN)" :value="old('vatNumber', $t['vatNumber'] ?? '')" :disabled="! $canTax" />
                    </div>
                    <div x-show="on" x-cloak><x-form.toggle name="pricesTaxInclusive" label="Menu prices already include VAT" description="Off adds VAT on top of the price shown." :value="old('pricesTaxInclusive', $t['pricesTaxInclusive'] ?? false)" :disabled="! $canTax" /></div>
                    <input type="hidden" name="vatRatePercent" value="{{ $t['vatRatePercent'] ?? '7.5' }}" x-bind:disabled="on">
                </x-form.section>
                @if ($canTax)<x-form.actions submit="Save VAT settings" />@else<x-pending-api title="Read-only" :items="['Changing VAT needs the config.manage permission.']" />@endif
            </form>
        @endif
    </div>
</x-layouts.app>
