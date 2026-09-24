<x-layouts.app title="Style guide">
    <x-page-header title="Style guide" subtitle="Every shared component and state. Development only: this page is not routed in production." />

    <x-card title="Type scale">
        <p class="t-page">Page title</p><p class="t-section mt-2">Section title</p><p class="t-body mt-2">Body text for tables and forms.</p><p class="t-caption mt-2">Caption and help text.</p><p class="t-label mt-2">Label</p>
    </x-card>
    <x-card title="Colour tokens"><div class="flex flex-wrap gap-2 text-xs">@foreach (['brand-600', 'brand-50', 'stone-900', 'stone-500', 'stone-100', 'amber-500', 'red-600', 'sky-600'] as $c)<span class="rounded-lg px-3 py-2 text-white bg-{{ $c }} {{ in_array($c, ['brand-50', 'stone-100']) ? '!text-stone-900' : '' }}">{{ $c }}</span>@endforeach</div></x-card>

    <x-card title="Stat cards" subtitle="With delta and sparkline"><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Net sales" value="₦1,250,000.00" :delta="12.4" :spark="[3, 5, 4, 7, 6, 9, 8]" />
        <x-stat label="Refunds" value="₦45,000.00" :delta="8.0" :invert="true" />
        <x-stat label="Orders" value="312" :delta="-3.2" />
        <x-stat label="Stale figure" value="₦0.00" tone="warn" hint="May be out of date" />
    </div></x-card>

    <x-card title="Status pills"><div class="flex flex-wrap gap-2">@foreach (['CAPTURED', 'PENDING', 'FAILED', 'VOIDED', 'REFUNDED', 'ACTIVE', 'INACTIVE', 'ONLINE', 'OFFLINE', 'DRAFT', 'SOMETHING ELSE'] as $s)<x-status-pill :status="$s" />@endforeach</div></x-card>

    <x-card title="Money, dates, ids"><div class="flex flex-wrap items-center gap-6 text-sm"><x-money value="12345" /><x-money value="-500.5" /><x-money value="0" /><x-datetime at="2026-09-24T00:15:01Z" /><x-datetime at="2026-09-24T00:15:01Z" :ago="true" /><x-copy value="01a0d0c4-01e4-78e1-bcf7-b155c28d2396" /><x-avatar-name name="Ebiye Owei" sub="S-0013" /></div></x-card>

    <x-card title="Alerts"><x-alert tone="info">Information for the reader.</x-alert><x-alert tone="success" title="Saved">Your change was recorded.</x-alert><x-alert tone="warning" title="Careful">This affects every receipt.</x-alert><x-alert tone="danger" title="Could not save">The API refused this request.</x-alert></x-card>

    <x-card title="Table" subtitle="Sort, quick filter, density, columns, selection, kebab menu, drawer, footer totals" flush x-data="tableTools">
        <x-table-tools :csv="true" :per-page="false" />
        <div class="table-scroll"><table class="data-table" data-testid="styleguide-table">
            <thead><tr><th data-sort>Reference</th><th data-sort>Customer</th><th>Status</th><th class="num" data-sort>Amount</th><th></th></tr></thead>
            <tbody x-ref="body">
                @foreach ([['PAY-0001', 'Amaka Okoro', 'CAPTURED', '12000'], ['PAY-0002', 'Emeka Obi', 'PENDING', '4500.5'], ['PAY-0003', 'Ngozi Eze', 'FAILED', '-800']] as [$ref, $who, $st, $amt])
                    <tr data-row><td><x-copy :value="$ref" :short="12" /></td><td><x-avatar-name :name="$who" /></td><td><x-status-pill :status="$st" /></td><td class="num"><x-money :value="$amt" /></td>
                        <td class="text-right"><x-row-menu><a href="#">Open</a><button type="button">Copy reference</button></x-row-menu></td></tr>
                @endforeach
            </tbody>
            <tfoot><tr><td colspan="3">Total</td><td class="num"><x-money value="15700.5" /></td><td></td></tr></tfoot>
        </table></div>
        <x-pagination :count="3" next="abc" />
    </x-card>

    <div class="grid gap-5 lg:grid-cols-2">
        <x-card title="Loading skeletons"><x-skeleton :rows="3" :cols="4" /><div class="mt-4"><x-skeleton :lines="3" /></div></x-card>
        <x-card title="Empty state"><x-empty-state title="Nothing here yet" text="Explain what will appear and how to get it." icon="box" action="Add the first one" href="#" /></x-card>
    </div>

    <x-card title="Form" subtitle="Aligned labels, help, validation, toggle, sticky save bar">
        <form x-data="dirtyGuard" onsubmit="return false">
            <div class="grid gap-x-4 sm:grid-cols-2"><x-input name="demo_name" label="Name" required hint="Shown on receipts." /><x-select name="demo_kind" label="Kind" :options="['a' => 'Option A', 'b' => 'Option B']" /></div>
            <x-toggle name="demo_toggle" label="Allow open tabs" :checked="true" hint="Customers can pay later." />
            <x-save-bar />
        </form>
    </x-card>

    <x-card title="Tabs, modal, toasts, drawer">
        <x-tabs :tabs="['one' => 'First', 'two' => 'Second']" current="one" />
        <div class="flex flex-wrap gap-2">
            <x-btn type="button" variant="secondary" @click="$dispatch('open-modal', 'demo')">Open modal</x-btn>
            <x-btn type="button" variant="secondary" @click="$dispatch('toast', { tone: 'success', text: 'Saved.' })">Success toast</x-btn>
            <x-btn type="button" variant="secondary" @click="$dispatch('toast', { tone: 'danger', text: 'Something failed.' })">Error toast</x-btn>
            <x-detail title="Example row" :fields="[['Reference', 'PAY-0001'], ['Amount', '₦12,000.00']]" label="Open drawer" />
        </div>
        <x-modal name="demo" title="Confirm something"><p class="text-sm">Modal content goes here.</p></x-modal>
    </x-card>
</x-layouts.app>
