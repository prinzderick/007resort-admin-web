@php
    $contact = (array) ($f['contact'] ?? []);
    $tzs = array_values(array_unique(array_filter([$f['timezone'] ?? null, 'Africa/Lagos', 'UTC', 'Africa/Accra', 'Europe/London'])));
    $kindList = array_values(array_unique(array_filter([...($kinds ?? []), $f['kind'] ?? null])));
    $kindOptions = array_map(fn ($k) => ['value' => $k, 'label' => ucwords(strtolower(str_replace('_', ' ', $k)))], $kindList);
    $hours = \App\Support\Form\Hours::toControl($f['openingHours'] ?? null);
@endphp
<form method="POST" action="{{ route('setup.facilities.update', $id) }}" data-testid="general-form" novalidate>
    @csrf @method('PATCH')
    <input type="hidden" name="etag" value="{{ $etag }}">
    <x-form.section title="Basics" description="How this facility is named and described in the apps, receipts and reports.">
        <x-form.text name="name" label="Name" :value="old('name', $f['name'] ?? '')" required :disabled="! $canGeneral" />
        <x-form.text name="code" label="Code" :value="$f['code'] ?? ''" disabled hint="The code identifies the facility in reports and on devices. It is fixed once created." />
        <x-form.select name="kind" label="Kind" :options="$kindOptions" :value="old('kind', $f['kind'] ?? '')" :disabled="! $canGeneral" />
        <x-form.text name="description" label="Description" :value="old('description', $f['description'] ?? '')" :multiline="true" :rows="2" :maxlength="500" :disabled="! $canGeneral" />
        <x-form.select name="timezone" label="Time zone" :options="array_combine($tzs, $tzs)" :value="old('timezone', $f['timezone'] ?? 'Africa/Lagos')" hint="Used for opening hours and this facility's daily reports." :disabled="! $canGeneral" />
        <x-form.stepper name="sortOrder" label="Position in lists" :value="(int) old('sortOrder', $f['sortOrder'] ?? 0)" :min="0" :max="999" hint="Lower numbers appear first." :disabled="! $canGeneral" />
    </x-form.section>
    <x-form.section title="Contact" description="Shown to guests where relevant (receipts, website).">
        <x-form.text name="contact[phone]" label="Phone" :value="old('contact.phone', $contact['phone'] ?? '')" inputmode="tel" :disabled="! $canGeneral" />
        <x-form.text name="contact[email]" label="Email" type="email" :value="old('contact.email', $contact['email'] ?? '')" :disabled="! $canGeneral" />
        <x-form.text name="contact[address]" label="Address" :value="old('contact.address', $contact['address'] ?? '')" :disabled="! $canGeneral" />
        <x-form.text name="contact[managerName]" label="Manager" :value="old('contact.managerName', $contact['managerName'] ?? '')" :disabled="! $canGeneral" />
    </x-form.section>
    <x-form.section title="Opening hours" description="Property time. Add a split shift with 'Add interval'; use special days for holidays and events." stacked>
        <x-form.weekly-hours name="hours" label="Opening hours" error-key="openingHours" :value="$hours" :disabled="! $canGeneral" />
    </x-form.section>
    @if ($canGeneral)<x-form.actions submit="Save facility" />
    @else<x-pending-api title="Read-only" :items="['You need the facility.manage permission to edit a facility.']" />@endif
</form>
@if ($canEdit)
    <x-form.section class="mt-8" title="Where it sits" description="A facility can sit under another one (for example a court under the Sports Arena). Reports and rules roll up along this tree." >
        <form method="POST" action="{{ route('setup.facilities.move', $id) }}" class="grid gap-3" data-testid="move-form">@csrf<input type="hidden" name="etag" value="{{ $etag }}">
            <x-form.select name="parentId" label="Parent facility" :options="collect($flat)->reject(fn ($p) => ($p['id'] ?? '') === $id)->map(fn ($p) => ['value' => $p['id'], 'label' => $p['name'] ?? $p['code']])->values()->all()" placeholder="(top level: not inside another facility)" :clearable="true" :value="$f['parentId'] ?? ''" />
            <div><x-btn variant="secondary">Move facility</x-btn></div></form>
    </x-form.section>
@endif
