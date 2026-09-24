@php
    $facOptions = collect($facilities)->map(fn ($f) => ['value' => $f['id'], 'label' => $f['name'] ?? $f['code']])->values()->all();
    $modeOptions = \App\Http\Controllers\DevicesController::MODES[$d['kind'] ?? ''] ?? [];
    $ptOptions = collect($points->items())->where('active', true)->map(fn ($p) => ['value' => $p['id'], 'label' => ($facilityNames[$p['facilityId'] ?? ''] ?? '').' / '.$p['name'], 'group' => $facilityNames[$p['facilityId'] ?? ''] ?? null])->values()->all();
    $home = $d['homeFacilityId'] ?? ($d['homeFacility']['id'] ?? null);
@endphp
<x-dialog name="edit-device-{{ $d['id'] }}" title="Edit {{ $d['name'] ?? 'device' }}" subtitle="{{ str_replace('_', ' ', $d['kind'] ?? '') }}. The device picks the change up the next time it connects.">
    <form method="POST" action="{{ route('devices.update', $d['id']) }}" class="grid gap-4" novalidate>@csrf @method('PATCH')
        <input type="hidden" name="rowVersion" value="{{ $d['rowVersion'] ?? '' }}"><input type="hidden" name="back" value="{{ $back ?? '' }}">
        <x-form.text name="name" label="Device name" required :value="$d['name'] ?? ''" />
        <x-form.select name="facilityId" label="Home facility" :options="$facOptions" :value="$home" :clearable="true" placeholder="Not assigned" hint="Where this device normally works. Tablets are checked out to a facility each shift." />
        @if ($modeOptions !== [])<x-form.radio-cards name="mode" label="What this device does" :options="collect($modeOptions)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()->all()" :value="$d['mode'] ?? null" />@endif
        @if ($ptOptions !== [])<x-form.select name="operatingPointId" label="Operating point" :options="$ptOptions" :value="$d['operatingPointId'] ?? null" :clearable="true" placeholder="None" hint="The counter, gate or kitchen/bar screen it belongs to. It must be inside the home facility." />@endif
        @if (! empty($d['checkout']) && empty($d['checkout']['checkedInAt']))<p class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-950">This tablet is checked out right now. It cannot move to another facility until it is checked back in.</p>@endif
        <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Save device</x-btn></div>
    </form>
</x-dialog>
