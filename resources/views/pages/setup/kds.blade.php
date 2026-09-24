@php $routeList = $routes->items(); $kindNames = ['KITCHEN' => 'Kitchen', 'BAR' => 'Bar', 'NONE' => 'Not prepared']; @endphp
<x-layouts.app title="Kitchen & bar routing">
    <x-page-header title="Kitchen & bar routing" subtitle="Which kitchen or bar screen prepares each kind of order. A restaurant can send its drinks to a bar in another building." :crumbs="['Setup' => route('setup.index'), 'Kitchen & bar routing' => null]" />
    <x-filter-form>
        <x-filter-select name="facility" label="Facility" :options="collect($facilities)->pluck('name', 'id')->all()" :value="$facilityId" all="Choose a facility" width="16rem" />
    </x-filter-form>
    <x-fetch :of="$routes" what="Routes" />
    <x-fetch :of="$stations" what="Kitchen and bar screens" />
    @if ($routes->ok() && $facilityId !== '')
        <form action="{{ route('setup.kds.save') }}" method="POST" novalidate data-testid="routing-form">@csrf @method('PUT')
            <input type="hidden" name="facilityId" value="{{ $facilityId }}">
            <x-form.section title="Screens for {{ $facilityNames[$facilityId] ?? 'this facility' }}" description="For each kind of order, pick the screen that prepares it. Empty means the facility's own default screen.">
                @foreach ($routeList as $rt)
                    @continue(($rt['kind'] ?? '') === 'NONE')
                    <x-form.select :name="'stations['.$rt['id'].']'" :label="($rt['name'] ?? '').' orders go to'" :options="$stationOptions" :value="$map[$rt['id']] ?? null" :clearable="true" placeholder="This facility's default screen" :disabled="! $canManage"
                        :hint="($kindNames[$rt['kind'] ?? ''] ?? '').' route. Products set to this route appear on the chosen screen.'" />
                @endforeach
            </x-form.section>
            @if ($canManage)<x-form.actions submit="Save routing" />@endif
        </form>
    @endif

    <x-card title="Routes" subtitle="The kinds of preparation a product can have. Set a product's route on its catalog page." flush class="mt-8">
        <x-slot:aside>@if ($canManage)<x-btn type="button" variant="secondary" icon="plus" @click="$dispatch('open-modal', 'add-route')">Add route</x-btn>@endif</x-slot:aside>
        @if ($routes->ok())
            <div class="table-scroll"><table class="data-table"><thead><tr><th>Route</th><th>Code</th><th>Type</th><th class="w-12"></th></tr></thead><tbody>
            @foreach ($routeList as $rt)<tr><td class="font-medium">{{ $rt['name'] }}</td><td class="font-mono text-xs">{{ $rt['code'] }}</td><td>{{ $kindNames[$rt['kind'] ?? ''] ?? $rt['kind'] }}</td><td class="text-right">@if ($canManage)<button type="button" class="text-sm font-medium text-brand-700 underline" @click="$dispatch('open-modal', 'route-{{ $rt['id'] }}')">Edit</button>@endif</td></tr>@endforeach
            </tbody></table></div>
        @endif
    </x-card>
    <x-card title="Kitchen and bar screens" subtitle="The screens that exist. Add one as a 'Kitchen / bar screen' operating point on the facility's Operating points tab." flush>
        @if ($stations->ok())
            <div class="table-scroll"><table class="data-table"><thead><tr><th>Screen</th><th>Facility</th><th>Type</th><th>Status</th></tr></thead><tbody>
            @forelse ($stations->items() as $s)<tr><td class="font-medium">{{ $s['name'] }}</td><td>{{ $facilityNames[$s['facilityId'] ?? ''] ?? '' }}</td><td>{{ ucfirst(strtolower($s['kdsStation']['kind'] ?? '')) }}</td><td><x-badge :tone="($s['active'] ?? true) ? 'good' : 'default'">{{ ($s['active'] ?? true) ? 'Active' : 'Off' }}</x-badge></td></tr>
            @empty<tr><td colspan="4"><x-empty title="No screens yet" icon="flame" /></td></tr>@endforelse
            </tbody></table></div>
        @endif
    </x-card>
    @if ($canManage)
        @foreach (array_merge([null], $routeList) as $rt)
            @php $isNew = $rt === null; @endphp
            <x-dialog :name="$isNew ? 'add-route' : 'route-'.$rt['id']" :title="$isNew ? 'Add a route' : 'Edit '.$rt['name']">
                <form method="POST" action="{{ $isNew ? route('setup.kds.route.store') : route('setup.kds.route.update', $rt['id']) }}" class="grid gap-4" novalidate>@csrf @if (! $isNew) @method('PATCH') @endif
                    <x-form.text name="name" label="Name" required :value="$rt['name'] ?? ''" :maxlength="80" placeholder="e.g. Pastry" />
                    @if ($isNew)<x-form.text name="code" label="Code" required :maxlength="32" hint="Letters, numbers and underscores. Cannot be changed later." />@endif
                    <x-form.segmented name="kind" label="Type" :options="$kindNames" :value="$rt['kind'] ?? 'KITCHEN'" />
                    <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Save route</x-btn></div>
                </form>
            </x-dialog>
        @endforeach
    @endif
</x-layouts.app>
