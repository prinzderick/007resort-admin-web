@php
    $facOptions = collect($facilities)->map(fn ($f) => ['value' => $f['id'], 'label' => $f['name'] ?? $f['code']])->values()->all();
    $allModes = collect(\App\Http\Controllers\DevicesController::MODES)->flatMap(fn ($m) => $m)->unique()->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()->all();
    $ptOptions = collect($points->items())->where('active', true)->map(fn ($p) => ['value' => $p['id'], 'label' => ($facilityNames[$p['facilityId'] ?? ''] ?? '').' / '.$p['name']])->values()->all();
@endphp
{{-- ONE dialog for every device row: the row's data arrives in payload (see x-dialog), so the page does not carry a form per device. --}}
<x-dialog name="edit-device" title="Edit device" subtitle="The device picks the change up the next time it connects.">
    <form method="POST" :action="'{{ url('/devices') }}/' + payload.id" class="grid gap-4" novalidate>@csrf @method('PATCH')
        <input type="hidden" name="rowVersion" :value="payload.rowVersion"><input type="hidden" name="back" value="{{ $back ?? '' }}">
        <p class="text-sm font-medium text-stone-700"><span x-text="payload.kind"></span></p>
        <x-form.text name="name" label="Device name" required x-model="payload.name" />
        <x-form.select name="facilityId" label="Home facility" :options="$facOptions" x-model="payload.facilityId" :clearable="true" placeholder="Not assigned" hint="Where this device normally works. Tablets are checked out to a facility each shift." />
        <x-form.select name="mode" label="What this device does" :options="$allModes" x-model="payload.mode" :searchable="false" hint="It must suit the kind of device: the system tells you if it does not." />
        @if ($ptOptions !== [])<x-form.select name="operatingPointId" label="Operating point" :options="$ptOptions" x-model="payload.operatingPointId" :clearable="true" placeholder="None" hint="The counter, gate or kitchen/bar screen it belongs to. It must be inside the home facility." />@endif
        <p x-show="payload.checkedOut" x-cloak class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-950">This tablet is checked out right now. It cannot move to another facility until it is checked back in.</p>
        <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Save device</x-btn></div>
    </form>
</x-dialog>
