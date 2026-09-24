@php
    // Every setting lives in exactly one obvious place. Each row: title, plain-language description, route, permissions (any), keywords for "find a setting".
    $map = [
        'Your business' => [
            ['Business profile', 'Business name, site, time zone, address, phone and email.', 'setup.business', '#business', ['settings.manage', 'config.manage', 'config.view'], 'name address phone timezone company'],
            ['VAT and tax', 'Switch VAT on or off, the rate, and whether prices include tax. Off by default.', 'setup.business', '#tax', ['config.manage'], 'vat tax rate inclusive tin'],
            ['Receipts', 'What prints on every receipt: name, address, header, footer, paper width.', 'setup.business', '#receipt', ['settings.manage', 'config.manage', 'config.view'], 'receipt footer header logo paper'],
            ['Facilities', 'Add a facility from a template, edit its details, hours and where it sits.', 'setup.facilities', null, ['facility.manage', 'facility.configure', 'config.manage', 'config.view'], 'restaurant bar pool spa add new deactivate hours'],
        ],
        'How each facility works' => [
            ['Capabilities', 'What a facility can do: take orders, bookings, tickets, stock. Pick the facility, open Capabilities.', 'setup.facilities', null, ['config.manage.capabilities', 'facility.manage', 'config.view'], 'pos booking ticketing inventory turn on off'],
            ['Operating rules', 'Payment timing, approvals, tabs, stock deduction, receipts. Pick the facility, open Operating rules.', 'setup.facilities', null, ['config.manage.rules', 'config.view'], 'rules approval threshold tab timing offline'],
            ['Waiter collection and cash', 'Whether waiters collect at the table, hold cash, cash limits, handover variance. In Operating rules.', 'setup.facilities', null, ['config.manage.rules', 'config.view'], 'waiter cash holding limit handover confirm collect'],
            ['Booking rules', 'Hold time, notice, cancellation fee, offline strategy. In Operating rules of a bookable facility.', 'setup.facilities', null, ['config.manage.rules', 'config.view'], 'booking hold cancel fee reschedule offline strategy'],
            ['Payment methods', 'Cash, card, transfer, POS: which ones each facility accepts.', 'setup.payments', null, ['settings.manage', 'config.view', 'facility.configure', 'config.manage'], 'payment methods cash card transfer paystack'],
            ['Operating points and tables', 'Counters, gates, table areas, kitchen and bar screens, and numbered tables. Pick the facility.', 'setup.facilities', null, ['facility.manage', 'config.view'], 'table counter gate station numbering bulk'],
        ],
        'What you sell' => [
            ['Products and prices', 'Products, categories, prices per facility, tax rate, kitchen or bar routing and stock link.', 'setup.catalog', null, ['catalog.manage', 'pricing.manage', 'catalog.availability.manage', 'config.manage'], 'product price menu category sku barcode 86 unavailable'],
            ['Import and export', 'Upload a CSV of products or prices with a dry-run check, or download the current list.', 'setup.catalog', '#import', ['catalog.manage', 'pricing.manage'], 'csv import export spreadsheet upload'],
            ['Kitchen and bar routing', 'Which screen prepares which kind of order at each facility.', 'setup.kds', null, ['facility.configure', 'config.manage', 'catalog.manage'], 'kitchen bar kds route station'],
            ['Ticket types', 'Pool passes, sports entry, event tickets: validity and how they are scanned.', 'setup.tickets', null, ['ticket_type.manage', 'config.view', 'facility.configure', 'config.manage'], 'ticket pass entry validity scan'],
            ['Membership plans', 'Durations, prices, visit limits, discounts and where a plan is valid.', 'setup.memberships', null, ['membership.plan.manage', 'config.manage'], 'membership plan gold silver discount visit'],
            ['Bookable resources', 'Courts, pitches, chairs, halls: capacity, slots, opening windows, blackout dates, offline strategy.', 'setup.bookings', null, ['booking.configure', 'config.manage'], 'booking resource court schedule blackout slot capacity'],
        ],
        'People and devices' => [
            ['Staff', 'People, sign-in (password, PIN, card), status, and each waiter\'s cash policy.', 'staff.index', null, ['staff.manage'], 'staff pin password card waiter cash policy'],
            ['Roles and permissions', 'What each role can do; create your own roles.', 'people.roles', null, ['role_assignment.manage', 'role.manage'], 'role permission matrix custom'],
            ['Devices', 'Tablets, POS terminals and screens: home facility and what each does.', 'devices.index', null, ['device.manage', 'device.register', 'device.revoke'], 'device tablet pos kds mode home'],
            ['Card machines', 'The bank card machines waiters carry, and who has which.', 'devices.payment-terminals', null, ['device.manage', 'payment.collect'], 'card machine terminal pos serial'],
        ],
        'Watching over it' => [
            ['Change history', 'Who changed which setting and when.', 'audit.index', null, ['audit.view', 'config.view'], 'audit history log who changed'],
            ['Sync and IT', 'The two-node connection, queues and conflicts.', 'sync', null, ['config.manage'], 'sync outbox conflict cloud'],
        ],
    ];
    $stepNames = ['business_profile' => 'Business profile', 'facilities' => 'Facilities', 'products' => 'Products', 'prices' => 'Prices', 'tax' => 'Tax', 'staff' => 'Staff', 'roles' => 'Roles', 'devices' => 'Devices', 'kds_stations' => 'Kitchen and bar screens', 'payment_methods' => 'Payment methods', 'receipt_settings' => 'Receipt', 'booking_resources' => 'Bookable resources', 'tables' => 'Dining tables'];
@endphp
<x-layouts.app title="Setup">
    <x-page-header title="Setup" subtitle="Every setting, in one place. Find it here, open it, change it. Each screen shows who last changed it, and changes take effect straight away." />

    @if ($progress)
        @php $steps = $progress['steps'] ?? []; $pct = (int) ($progress['percent'] ?? 0); $missing = array_values(array_filter($steps, fn ($x) => ! ($x['done'] ?? false) && ($x['required'] ?? true))); @endphp
        <x-card :title="$pct >= 100 ? 'Setup complete' : 'Finish setting up'" :subtitle="$pct >= 100 ? 'Everything the property needs is in place. You can still change any of it below.' : count($missing).' step'.(count($missing) === 1 ? '' : 's').' left before you are ready to trade.'" data-testid="setup-progress">
            <div class="mb-4 flex items-center gap-3"><div class="h-2.5 flex-1 overflow-hidden rounded-full bg-stone-100" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full bg-brand-600" style="width: {{ $pct }}%"></div></div><span class="text-sm font-semibold tabular-nums">{{ $pct }}%</span></div>
            <ul class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($steps as $st)
                    @php $req = $st['required'] ?? true; $done = $st['done'] ?? false; @endphp
                    <li><a href="{{ route($st['route'] ?? 'setup.index') }}" class="flex items-start gap-3 rounded-lg border p-3 text-sm hover:border-brand-300 hover:bg-brand-50/40 {{ $done ? 'border-stone-200' : ($req ? 'border-amber-300 bg-amber-50/40' : 'border-stone-200') }} {{ ! $req && ! $done ? 'opacity-60' : '' }}">
                        <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full {{ $done ? 'bg-brand-600 text-white' : 'border-2 border-stone-300' }}">@if ($done)<x-icon name="check" class="size-3" />@endif</span>
                        <span class="min-w-0"><span class="block font-medium">{{ $st['label'] ?? '' }}@if (isset($st['count']) && $st['count'] !== null)<span class="ml-1 font-normal text-stone-500">({{ $st['count'] }})</span>@endif</span><span class="block text-xs text-stone-500">{{ $st['hint'] ?? (! $req ? 'Not needed for this property' : ($done ? 'Done' : 'Not done yet')) }}</span></span></a></li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <div x-data="{ q: '' }" data-testid="settings-map">
        <div class="mb-2 max-w-md"><x-form.text name="find" label="Find a setting" placeholder="e.g. cash limit, receipt footer, VAT" x-model="q" :clearable="true" /></div>
        @foreach ($map as $title => $cards)
            <section x-show="[...$el.querySelectorAll('[data-setting]')].some(e => !e.hidden)" data-group>
                <h2 class="mb-2 mt-6 text-xs font-semibold uppercase tracking-wider text-stone-500">{{ $title }}</h2>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($cards as [$label, $desc, $route, $hash, $perms, $kw])
                        @php $ok = auth_staff()->canAny(...$perms); @endphp
                        @continue(! $ok)
                        <a href="{{ route($route) }}{{ $hash }}" data-setting :hidden="q.trim() !== '' && !{{ \Illuminate\Support\Js::from(mb_strtolower($label.' '.$desc.' '.$kw)) }}.includes(q.trim().toLowerCase())"
                           class="flex flex-col gap-1 rounded-xl border border-stone-200 bg-white p-4 shadow-sm transition-colors hover:border-brand-300 hover:bg-brand-50/30">
                            <span class="flex items-center justify-between gap-2"><span class="font-semibold">{{ $label }}</span><x-icon name="chevron" class="size-4 -rotate-90 text-stone-400" /></span>
                            <span class="text-sm text-stone-600">{{ $desc }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
    <p class="mt-6 text-xs text-stone-500">API contract version {{ $contract ?? '?' }}.</p>
</x-layouts.app>
