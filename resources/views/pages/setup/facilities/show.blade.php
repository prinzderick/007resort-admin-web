@php
    $name = $f['name'] ?? 'Facility';
    $status = $f['status'] ?? 'UNKNOWN';
    $inactive = in_array(strtoupper((string) $status), ['INACTIVE', 'DEACTIVATED'], true) || ! empty($f['deactivatedAt']);
@endphp
<x-layouts.app :title="$name">
    <x-page-header :title="$name" :crumbs="['Setup' => route('setup.index'), 'Facilities' => route('setup.facilities'), $name => null]">
        <x-slot:actions>
            @if ($fac->ok() && $canEdit)
                @if (\App\Support\Contract::has('POST', '/organization/facilities/{facilityId}/deactivate') || \App\Support\Contract::has('POST', '/organization/facilities/{facilityId}/reactivate'))
                    @if ($inactive)
                        <form method="POST" action="{{ route('setup.facilities.reactivate', $id) }}">@csrf<input type="hidden" name="etag" value="{{ $etag }}"><x-btn variant="secondary">Reactivate</x-btn></form>
                    @else
                        <form method="POST" action="{{ route('setup.facilities.deactivate', $id) }}" x-data="confirmSubmit('Deactivate {{ e($name) }}? It stops appearing in the apps; orders, payments and history are kept. You can reactivate it later.')" @submit="ask($event)">@csrf<input type="hidden" name="etag" value="{{ $etag }}"><x-btn variant="secondary" class="text-red-800">Deactivate</x-btn></form>
                    @endif
                @endif
            @endif
        </x-slot:actions>
    </x-page-header>
    <x-fetch :of="$fac" what="Facility" />
    @if ($fac->ok())
        <div class="-mt-3 mb-4 flex flex-wrap items-center gap-2 text-sm"><x-badge :status="$status" /><span class="text-stone-500">{{ str_replace('_', ' ', $f['kind'] ?? '') }} &middot; {{ $f['code'] ?? '' }}</span></div>
        <nav class="mb-5 flex flex-wrap gap-1 border-b border-stone-200 text-sm" aria-label="Facility settings" data-testid="facility-tabs">
            @foreach ($tabs as $k => $label)
                <a href="{{ route('setup.facilities.show', ['facility' => $id, 'tab' => $k]) }}" class="-mb-px border-b-2 px-3 py-2.5 {{ $tab === $k ? 'border-brand-600 font-semibold text-brand-700' : 'border-transparent text-stone-600 hover:text-stone-900' }}" @if ($tab === $k) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <x-settings-meta entity-type="Facility" :entity-id="$id" />
        @include('pages.setup.facilities.tabs.'.$tab)
    @endif
</x-layouts.app>
