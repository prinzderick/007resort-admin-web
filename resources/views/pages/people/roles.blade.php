<x-layouts.app title="Roles & permissions">
    <x-page-header title="Roles & permissions" subtitle="What each role may do. A role is given to a person at a place (the whole organization, the site or one facility) on their staff page. The system decides every action from these permissions, never from the role's name." :crumbs="['People' => route('staff.index'), 'Roles & permissions' => null]">
        <x-slot:actions>@if ($canEdit)<x-btn type="button" icon="plus" @click="$dispatch('open-modal', 'add-role')" data-testid="add-role">New role</x-btn>@endif</x-slot:actions>
    </x-page-header>
    <x-tabs :tabs="['edit' => 'Roles', 'matrix' => 'Compare roles']" :current="$tab" />
    <x-fetch :of="$roles" what="Roles" />
    @if ($roles->ok())
        @php $rs = collect($roles->items()); @endphp
        @if ($tab === 'matrix')
            <x-card title="Permission matrix" subtitle="How many permissions each role holds in each area" flush>
                <div class="table-scroll"><table class="data-table" data-testid="role-matrix">
                    <thead><tr><th>Area</th>@foreach ($rs as $r)<th class="text-center" title="{{ $r['description'] ?? '' }}">{{ $r['name'] ?? $r['code'] ?? '' }}</th>@endforeach</tr></thead>
                    <tbody>
                    @foreach ($groups as $area => $codes)
                        <tr><td class="font-medium">{{ ucfirst(str_replace('_', ' ', $area)) }} <span class="text-xs font-normal text-stone-400">({{ count($codes) }})</span></td>
                            @foreach ($rs as $r)@php $n = count(array_intersect($codes, (array) ($r['permissions'] ?? []))); @endphp<td class="text-center tabular-nums {{ $n === 0 ? 'text-stone-300' : ($n === count($codes) ? 'font-semibold text-brand-700' : '') }}">{{ $n === 0 ? '-' : $n }}</td>@endforeach</tr>
                    @endforeach
                    </tbody></table></div>
            </x-card>
        @else
            <div class="grid gap-5 lg:grid-cols-[17rem_minmax(0,1fr)]">
                <nav aria-label="Roles" class="rounded-xl border border-stone-200 bg-white p-2 shadow-sm lg:sticky lg:top-20 lg:self-start" data-testid="role-list">
                    @foreach ($rs as $r)
                        <a href="{{ route('people.roles', ['role' => $r['id']]) }}" class="flex items-center justify-between gap-2 rounded-lg px-3 py-2.5 text-sm {{ $selected === $r['id'] ? 'bg-brand-50 font-semibold text-brand-800' : 'text-stone-700 hover:bg-stone-50' }}" @if ($selected === $r['id']) aria-current="page" @endif>
                            <span class="min-w-0 truncate">{{ $r['name'] ?? $r['code'] }}</span>
                            <span class="flex shrink-0 items-center gap-1.5">@if (! ($r['system'] ?? true))<x-badge tone="info" :dot="false">Custom</x-badge>@endif<span class="text-xs tabular-nums text-stone-400">{{ count($r['permissions'] ?? []) }}</span></span>
                        </a>
                    @endforeach
                </nav>
                <div class="min-w-0">
                    @if ($detail->ok())
                        @php
                            $role = (array) ($detail->data['role'] ?? []);
                            $locked = ! ($role['editable'] ?? true) || $role['code'] === 'OWNER' || ! $canEdit;
                            $groups = (array) ($detail->data['groups'] ?? []);
                            $granted = collect($groups)->flatMap(fn ($g) => collect($g['items'] ?? [])->where('granted', true)->pluck('code'))->values()->all();
                            $needs = collect($groups)->flatMap(fn ($g) => collect($g['items'] ?? [])->where('requiresApproval', true)->pluck('code'))->values()->all();
                            $total = collect($groups)->sum(fn ($g) => count($g['items'] ?? []));
                        @endphp
                        <form method="POST" action="{{ route('people.roles.permissions', $role['id']) }}" x-data="{ q: '', on: @js($granted), get count() { return this.on.length } }" novalidate data-testid="role-form">@csrf @method('PUT')
                            <input type="hidden" name="etag" value="{{ $etag }}">
                            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                                <div><h2 class="text-lg font-semibold tracking-tight">{{ $role['name'] ?? '' }}</h2><p class="text-sm text-stone-600">{{ $role['description'] ?? '' }}</p>
                                    <p class="mt-1 text-xs text-stone-500">{{ ($role['assignments'] ?? 0) }} {{ ($role['assignments'] ?? 0) == 1 ? 'assignment' : 'assignments' }} &middot; <b x-text="count"></b> of {{ $total }} permissions</p></div>
                                @if ($canEdit && ! ($role['system'] ?? true))<div class="flex gap-2"><x-btn type="button" variant="secondary" @click="$dispatch('open-modal', 'rename-role')">Rename</x-btn>
                                    <button type="button" class="f-btn" data-variant="danger" x-on:click="$dispatch('open-modal', 'delete-role')">Delete role</button></div>@endif
                            </div>
                            @if ($locked)<x-alert tone="info">{{ ($role['code'] ?? '') === 'OWNER' ? 'The Owner role always has every permission and cannot be changed.' : (! $canEdit ? 'You need the role.manage permission to change roles. You can look, not edit.' : 'This role cannot be edited.') }}</x-alert>@endif
                            <div class="mb-4 max-w-sm"><x-form.text name="q" label="Find a permission" placeholder="e.g. refund, stock, price" x-model="q" :clearable="true" /></div>
                            @foreach ($groups as $g)
                                <section class="mb-4 rounded-xl border border-stone-200 bg-white shadow-sm" x-show="[...$el.querySelectorAll('[data-perm]')].some(e => !e.hidden)">
                                    <div class="flex items-center justify-between border-b border-stone-100 px-5 py-3"><h3 class="text-sm font-semibold">{{ $g['group'] }}</h3>
                                        <span class="text-xs text-stone-500">{{ count($g['items'] ?? []) }} permissions</span></div>
                                    <ul class="divide-y divide-stone-100">
                                    @foreach ($g['items'] ?? [] as $it)
                                        @php $can = ($it['grantable'] ?? true) || ! empty($it['granted']); @endphp
                                        <li data-perm class="flex items-start gap-3 px-5 py-3" :hidden="q.trim() !== '' && ! {{ \Illuminate\Support\Js::from(mb_strtolower($it['code'].' '.($it['description'] ?? ''))) }}.includes(q.trim().toLowerCase())">
                                            <input type="checkbox" id="p-{{ $it['code'] }}" name="permissions[]" value="{{ $it['code'] }}" x-model="on" class="mt-0.5 size-5 shrink-0 accent-brand-600" @disabled($locked || ! $can) @if (! $can) title="You do not hold this permission yourself, so you cannot give it to a role." @endif>
                                            <label for="p-{{ $it['code'] }}" class="min-w-0 flex-1 cursor-pointer"><span class="block text-sm font-medium">{{ $it['description'] ?? $it['code'] }}</span><code class="text-xs text-stone-500">{{ $it['code'] }}</code>@if (! $can)<span class="ml-2 text-xs text-amber-800">You cannot grant this</span>@endif</label>
                                            <label class="flex shrink-0 items-center gap-1.5 text-xs text-stone-600" x-show="on.includes('{{ $it['code'] }}')" x-cloak title="A second person must approve each time this is used"><input type="checkbox" name="approval[]" value="{{ $it['code'] }}" class="size-4 accent-amber-600" @checked(in_array($it['code'], $needs, true)) @disabled($locked)> needs approval</label>
                                        </li>
                                    @endforeach
                                    </ul>
                                </section>
                            @endforeach
                            @if (! $locked)<x-form.actions submit="Save permissions" />@endif
                        </form>
                        @if ($canEdit && ! ($role['system'] ?? true))
                            <x-dialog name="rename-role" title="Rename role"><form method="POST" action="{{ route('people.roles.update', $role['id']) }}" class="grid gap-4" novalidate>@csrf @method('PATCH')
                                <x-form.text name="name" label="Name" required :value="$role['name']" :maxlength="80" /><x-form.text name="description" label="Description" :value="$role['description'] ?? ''" :maxlength="255" />
                                <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Save</x-btn></div></form></x-dialog>
                            <x-dialog name="delete-role" title="Delete {{ $role['name'] }}?" subtitle="Only a role nobody holds can be deleted."><form method="POST" action="{{ route('people.roles.destroy', $role['id']) }}" class="grid gap-4" novalidate>@csrf @method('DELETE')
                                <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Keep it</x-btn><x-btn variant="danger">Delete role</x-btn></div></form></x-dialog>
                        @endif
                    @else
                        <x-fetch :of="$detail" what="The role's permissions" />
                    @endif
                </div>
            </div>
            @if ($canEdit)
                <x-dialog name="add-role" title="New role" subtitle="A custom role for people whose job does not fit the standard ones.">
                    <form method="POST" action="{{ route('people.roles.store') }}" class="grid gap-4" novalidate>@csrf
                        <x-form.text name="name" label="Name" required :maxlength="80" placeholder="e.g. Head barista" />
                        <x-form.text name="description" label="What is it for?" :maxlength="255" />
                        <x-form.select name="copyFrom" label="Start from" :options="$rs->pluck('name', 'id')->all()" :clearable="true" placeholder="Nothing (empty role)" hint="Copies that role's permissions so you only change what differs." />
                        <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Create role</x-btn></div>
                    </form>
                </x-dialog>
            @endif
        @endif
    @endif
</x-layouts.app>
