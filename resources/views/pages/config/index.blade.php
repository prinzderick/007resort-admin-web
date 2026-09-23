<x-layouts.app title="Configuration">
    <x-page-header title="Configuration" subtitle="Everything here is changed through the API, validated and audited there. Nothing is stored in this app." />
    <x-config-nav />
    @php
        $cards = [
            ['config.facilities', 'Facilities & operating points', 'Facility tree, capabilities and operating rules.', ['facility.configure', 'config.manage'], 'Read-only until the API adds rule editing'],
            ['config.catalog', 'Products, prices & categories', 'Prices, tax and prep routing per product; mark items unavailable per facility.', ['pricing.manage', 'config.manage', 'catalog.availability.manage'], 'Availability editable; prices read-only'],
            ['config.tax', 'Tax / VAT (ADR-0011)', 'VAT registered on/off (default off), rate, TIN.', ['config.manage'], 'Editable'],
            ['config.memberships', 'Membership plans', 'Durations, prices, visit limits, facility scope.', ['membership.plan.manage', 'config.manage'], 'Editable'],
            ['config.bookings', 'Booking resources & rules', 'Resources, slots and the offline-allocation strategy (A/B/C).', ['facility.configure', 'config.manage'], 'Read-only until the API adds rule editing'],
            ['config.tickets', 'Ticket types', 'Entitlement types, validity, validation mode.', ['facility.configure', 'config.manage'], 'Waiting on API'],
            ['config.kds', 'KDS routing', 'Which station prepares which product.', ['facility.configure', 'config.manage'], 'Read-only until the API adds routing editing'],
            ['config.payments', 'Payment timing & approval rules', 'Approval thresholds, tabs, offline payment policy per facility.', ['facility.configure', 'config.manage'], 'Read-only until the API adds rule editing'],
        ];
    @endphp
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($cards as [$route, $title, $desc, $perms, $state])
            @if (auth_staff()->canAny(...$perms))
                <a href="{{ route($route) }}" class="block rounded-xl border border-stone-200 bg-white p-4 shadow-sm hover:border-stone-400">
                    <div class="font-semibold">{{ $title }}</div><p class="mt-1 text-sm text-stone-600">{{ $desc }}</p>
                    <div class="mt-2"><x-badge :tone="str_starts_with($state, 'Editable') ? 'good' : 'default'">{{ $state }}</x-badge></div>
                </a>
            @endif
        @endforeach
    </div>
    <p class="mt-4 text-xs text-stone-500">API contract version {{ $contract ?? '?' }}.</p>
</x-layouts.app>
