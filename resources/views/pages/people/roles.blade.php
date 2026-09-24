<x-layouts.app title="Roles & permissions">
    <x-page-header title="Roles & permissions" subtitle="What each role may do. Roles are assigned to a person at a scope (whole organization, the site, or one facility) on the staff page. The API decides every action from these permissions, never from the role name." />
    <x-fetch :of="$roles" what="Roles" />
    @if ($roles->ok())
        @php $rs = collect($roles->items()); @endphp
        <x-card title="Permission matrix" subtitle="Number of permissions each role holds in each area" flush>
            <div class="table-scroll"><table class="data-table" data-testid="role-matrix">
                <thead><tr><th>Area</th>@foreach ($rs as $r)<th class="text-center" title="{{ $r['description'] ?? '' }}">{{ $r['name'] ?? $r['code'] ?? '' }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach ($groups as $area => $codes)
                    <tr><td class="font-medium">{{ $area }} <span class="text-xs font-normal text-stone-400">({{ count($codes) }})</span></td>
                        @foreach ($rs as $r)@php $n = count(array_intersect($codes, (array) ($r['permissions'] ?? []))); @endphp<td class="text-center tabular-nums {{ $n === 0 ? 'text-stone-300' : ($n === count($codes) ? 'font-semibold text-brand-700' : '') }}">{{ $n === 0 ? '-' : $n }}</td>@endforeach</tr>
                @endforeach
                </tbody></table></div>
        </x-card>
        @foreach ($rs as $r)
            <x-card :title="($r['name'] ?? $r['code'] ?? '').' ('.count($r['permissions'] ?? []).' permissions)'" :subtitle="$r['description'] ?? null" x-data="{ open: false }">
                <button type="button" class="text-sm font-medium text-brand-700 underline" @click="open = !open" x-text="open ? 'Hide permissions' : 'Show permissions'"></button>
                @if (! empty($r['approvalRequired']))<p class="mt-2 text-xs text-amber-800">Needs a second person to approve: {{ implode(', ', $r['approvalRequired']) }}</p>@endif
                <div x-cloak x-show="open" class="mt-3 grid gap-x-6 gap-y-1 text-sm md:grid-cols-2">
                    @foreach ($r['permissions'] ?? [] as $code)
                        <div class="flex justify-between gap-3 border-b border-stone-100 py-1"><code class="text-xs">{{ $code }}</code><span class="text-xs text-stone-500">{{ $desc[$code] ?? '' }}</span></div>
                    @endforeach
                </div>
            </x-card>
        @endforeach
    @endif
    <x-pending-api :items="['Creating custom roles or editing a role\'s permissions: the API has no role write endpoints yet (roles are fixed by the platform)']" />
</x-layouts.app>
