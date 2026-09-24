@php
    $contact = (array) ($f['contact'] ?? []);
    $weekly = (array) (($f['openingHours']['weekly'] ?? []));
    $exceptions = (array) (($f['openingHours']['exceptions'] ?? []));
    $tzs = array_values(array_unique(array_filter([$f['timezone'] ?? null, 'Africa/Lagos', 'UTC', 'Africa/Accra', 'Europe/London'])));
    $kindList = array_values(array_unique(array_filter([...($kinds ?? []), $f['kind'] ?? null])));
@endphp
<form method="POST" action="{{ route('setup.facilities.update', $id) }}" x-data="dirtyGuard" data-testid="general-form">
    @csrf @method('PATCH')
    <input type="hidden" name="etag" value="{{ $etag }}">
    <div class="grid gap-5 lg:grid-cols-2">
        <x-card title="Basics" subtitle="How this facility is named and described everywhere">
            <x-field name="name" label="Name" :value="$f['name'] ?? ''" required :hint="$canGeneral ? null : 'Read-only: the API has no endpoint to edit facilities yet.'" />
            <div class="mb-3"><label class="mb-1 block text-sm font-medium text-stone-700">Code</label><input value="{{ $f['code'] ?? '' }}" disabled class="min-h-11 w-full rounded-lg border border-stone-200 bg-stone-50 px-3 text-sm text-stone-500"><p class="mt-1 text-xs text-stone-500">The code is fixed once created; it identifies the facility in reports and on devices.</p></div>
            <x-field name="kind" label="Kind" :options="array_combine($kindList, array_map(fn ($k) => ucwords(strtolower(str_replace('_', ' ', $k))), $kindList))" :value="$f['kind'] ?? ''" />
            <x-field name="description" label="Description" type="textarea" :value="$f['description'] ?? ''" />
            <x-field name="timezone" label="Time zone" :options="array_combine($tzs, $tzs)" :value="$f['timezone'] ?? 'Africa/Lagos'" hint="Used for opening hours and this facility's daily reports." />
            <x-field name="sortOrder" label="Order in lists" type="number" :value="$f['sortOrder'] ?? 0" hint="Lower numbers appear first." />
        </x-card>
        <x-card title="Contact" subtitle="Shown to guests where relevant (receipts, website)">
            <x-field name="contact[phone]" label="Phone" :value="$contact['phone'] ?? ''" /><x-field name="contact[email]" label="Email" type="email" :value="$contact['email'] ?? ''" />
            <x-field name="contact[address]" label="Address" :value="$contact['address'] ?? ''" /><x-field name="contact[managerName]" label="Manager" :value="$contact['managerName'] ?? ''" />
        </x-card>
    </div>
    <x-card title="Opening hours" subtitle="Leave a day blank for closed. 24-hour times, property time.">
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($days as $k => $label)
                @php $w = $weekly[$k][0] ?? []; @endphp
                <div class="rounded-lg border border-stone-200 p-3"><div class="mb-2 text-sm font-medium">{{ $label }}</div>
                    <div class="flex items-center gap-2"><input type="time" name="hours[{{ $k }}][open]" value="{{ $w['open'] ?? '' }}" aria-label="{{ $label }} opens" class="min-h-10 w-full rounded-lg border border-stone-300 px-2 text-sm"><span class="text-stone-400">&ndash;</span><input type="time" name="hours[{{ $k }}][close]" value="{{ $w['close'] ?? '' }}" aria-label="{{ $label }} closes" class="min-h-10 w-full rounded-lg border border-stone-300 px-2 text-sm"></div></div>
            @endforeach
        </div>
        <div class="mt-5"><div class="mb-2 text-sm font-medium">Special days (holidays, events)</div>
            @php $rows = array_pad($exceptions, count($exceptions) + 2, []); @endphp
            @foreach ($rows as $i => $ex)
                <div class="mb-2 grid gap-2 sm:grid-cols-[10rem_auto_8rem_8rem_1fr]">
                    <input type="date" name="exceptions[{{ $i }}][date]" value="{{ $ex['date'] ?? '' }}" aria-label="Date" class="min-h-10 rounded-lg border border-stone-300 px-2 text-sm">
                    <label class="flex min-h-10 items-center gap-2 text-sm"><input type="checkbox" name="exceptions[{{ $i }}][closed]" value="1" @checked($ex['closed'] ?? false)> Closed</label>
                    <input type="time" name="exceptions[{{ $i }}][open]" value="{{ $ex['windows'][0]['open'] ?? '' }}" aria-label="Opens" class="min-h-10 rounded-lg border border-stone-300 px-2 text-sm"><input type="time" name="exceptions[{{ $i }}][close]" value="{{ $ex['windows'][0]['close'] ?? '' }}" aria-label="Closes" class="min-h-10 rounded-lg border border-stone-300 px-2 text-sm">
                    <input name="exceptions[{{ $i }}][note]" value="{{ $ex['note'] ?? '' }}" placeholder="Note (e.g. Christmas Day)" class="min-h-10 rounded-lg border border-stone-300 px-2 text-sm">
                </div>
            @endforeach
        </div>
    </x-card>
    @if ($canGeneral)<x-save-bar />
    @else<x-pending-api :items="['Editing a facility (name, kind, contact, opening hours) needs PATCH /organization/facilities/{facilityId}, which is not in the contract this portal was built against yet']" />@endif
</form>
@if ($canEdit && \App\Support\Contract::has('POST', '/organization/facilities/{facilityId}/move'))
    <x-card title="Where it sits" subtitle="Facilities can sit under another facility (for example the Bush Bar under the Event Centre). Rules and reports roll up along this tree.">
        <form method="POST" action="{{ route('setup.facilities.move', $id) }}" class="flex flex-wrap items-end gap-3">@csrf<input type="hidden" name="etag" value="{{ $etag }}">
            <div><label class="mb-1 block text-sm font-medium">Parent facility</label><select name="parentId" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3 text-sm"><option value="">(top level)</option>@foreach ($flat as $p)@if (($p['id'] ?? '') !== $id)<option value="{{ $p['id'] ?? '' }}" @selected(($f['parentId'] ?? null) === ($p['id'] ?? ''))>{{ $p['name'] ?? '' }}</option>@endif @endforeach</select></div>
            <x-btn variant="secondary">Move</x-btn></form>
    </x-card>
@endif
