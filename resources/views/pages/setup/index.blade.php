<x-layouts.app title="Setup">
    <x-page-header title="Setup" subtitle="Everything you configure, in one place. Each screen shows who last changed it and lets you see the full history. Changes are validated and audited by the API; nothing is stored in this portal." />

    @if ($progress)
        @php $steps = $progress['steps'] ?? []; $pct = (int) ($progress['percent'] ?? 0); @endphp
        <x-card title="Setup progress" :subtitle="$pct >= 100 ? 'Everything is set up.' : 'Finish these to be ready to trade.'" data-testid="setup-progress">
            <div class="mb-4 flex items-center gap-3"><div class="h-2.5 flex-1 overflow-hidden rounded-full bg-stone-100"><div class="h-full rounded-full bg-brand-600" style="width: {{ $pct }}%"></div></div><span class="text-sm font-semibold tabular-nums">{{ $pct }}%</span></div>
            <ul class="grid gap-2 sm:grid-cols-2">
                @foreach ($steps as $st)
                    <li><a href="{{ route($st['route'] ?? 'setup.index') }}" class="flex items-start gap-3 rounded-lg border border-stone-200 p-3 text-sm hover:border-brand-300 hover:bg-brand-50/40">
                        <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full {{ ($st['done'] ?? false) ? 'bg-brand-600 text-white' : 'border-2 border-stone-300' }}">@if ($st['done'] ?? false)<x-icon name="check" class="size-3" />@endif</span>
                        <span><span class="block font-medium">{{ $st['label'] ?? '' }}</span><span class="block text-xs text-stone-500">{{ $st['hint'] ?? '' }}</span></span></a></li>
                @endforeach
            </ul>
        </x-card>
    @endif

    @php
        $sections = [
            'Your business' => [
                ['setup.facilities', 'Facilities', 'building', 'Add a facility, edit its details, capabilities, operating rules, operating points, devices and products.', ['facility.configure', 'config.manage', 'booking.configure'], 'Editable'],
                ['setup.business', 'Business & receipts', 'file', 'Business profile, VAT (off by default), receipt settings.', ['config.manage'], 'VAT editable'],
            ],
            'What you sell' => [
                ['setup.catalog', 'Catalog & prices', 'tag', 'Products, categories, prices, tax, prep routing; mark items unavailable per facility.', ['catalog.manage', 'pricing.manage', 'catalog.availability.manage', 'config.manage'], 'Editable'],
                ['setup.tickets', 'Ticket types', 'ticket', 'Pool passes, sports entry, rentals: validity and validation mode.', ['facility.configure', 'config.manage'], 'Waiting on API'],
                ['setup.memberships', 'Membership plans', 'users', 'Durations, prices, visit limits, discounts and where a plan is valid.', ['membership.plan.manage', 'config.manage'], 'Editable'],
                ['setup.bookings', 'Booking resources', 'layers', 'Courts, pitches, chairs, halls: slots, capacity, price and the offline strategy.', ['booking.configure', 'config.manage'], 'Editable'],
            ],
            'How you operate' => [
                ['setup.kds', 'Kitchen & bar routing', 'flame', 'Which station prepares which product.', ['facility.configure', 'config.manage', 'catalog.manage'], 'Read-only'],
                ['setup.payments', 'Payment rules', 'sliders', 'Payment timing, approval thresholds, tabs and offline policy per facility.', ['facility.configure', 'config.manage'], 'Read-only until the API adds rule editing'],
            ],
        ];
    @endphp
    @foreach ($sections as $title => $cards)
        <h2 class="mb-2 mt-6 text-xs font-semibold uppercase tracking-wider text-stone-500">{{ $title }}</h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($cards as [$route, $label, $icon, $desc, $perms, $state])
                @php $ok = auth_staff()->canAny(...$perms); @endphp
                <a @if ($ok) href="{{ route($route) }}" @else aria-disabled="true" title="Needs permission: {{ implode(' or ', $perms) }}" @endif class="flex gap-4 rounded-xl border border-stone-200 bg-white p-5 shadow-sm {{ $ok ? 'hover:border-brand-300 hover:shadow' : 'opacity-50' }}">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700"><x-icon :name="$icon" class="size-6" /></span>
                    <span><span class="block font-semibold">{{ $label }}</span><span class="mt-1 block text-sm text-stone-600">{{ $desc }}</span><span class="mt-2 inline-block"><x-badge :tone="str_starts_with($state, 'Editable') || str_contains($state, 'editable') ? 'good' : 'default'">{{ $state }}</x-badge></span></span>
                </a>
            @endforeach
        </div>
    @endforeach
    <p class="mt-6 text-xs text-stone-500">API contract version {{ $contract ?? '?' }}.</p>
</x-layouts.app>
