@php
    use App\Support\Cms\Cms;
    $dialogToReopen = old('_dialog');
@endphp
<x-cms.layout title="Homepage">
    <x-page-header title="Homepage" subtitle="Build the home page from blocks. Drag blocks (or use the arrow buttons) to change their order, and switch each one on or off. New blocks start switched off." :crumbs="['Website' => route('website.index'), 'Homepage' => null]">
        <x-slot:actions>@if (config('r007.site_url'))<x-btn variant="secondary" :href="Cms::siteUrl('/')" target="_blank" rel="noopener" icon="external">Preview on site</x-btn>@endif</x-slot:actions>
    </x-page-header>
    <x-fetch :of="$loaded" what="The homepage blocks" />
    @unless ($canManage)<x-alert tone="info">You can look at the homepage but not change it. Editing needs the "cms.manage" permission.</x-alert>@endunless

    @if ($loaded->ok())
        <nav class="mb-5 flex flex-wrap gap-2 text-sm" aria-label="Jump to a block type">
            @foreach ($types as $type => $t)<a href="#{{ strtolower($type) }}" class="rounded-full border border-stone-300 bg-white px-3 py-1.5 font-medium text-stone-700 hover:bg-stone-50">{{ $t['label'] }} <span class="text-stone-400">{{ count($by[$type] ?? []) }}</span></a>@endforeach
        </nav>

        @foreach ($types as $type => $t)
            @php $rows = $by[$type] ?? []; $on = count(array_filter($rows, fn ($r) => $r['enabled'] ?? false)); @endphp
            <section id="{{ strtolower($type) }}" class="scroll-mt-24" data-testid="block-{{ $type }}">
                <x-card :title="$t['label']" :subtitle="$t['blurb']" flush>
                    <x-slot:aside>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs text-stone-500">{{ $on }} of {{ count($rows) }} on</span>
                            @if (config('r007.site_url'))<a class="inline-flex items-center gap-1 text-xs font-medium text-stone-600 underline" href="{{ Cms::siteUrl('/'.$t['anchor']) }}" target="_blank" rel="noopener">Preview on site <x-icon name="external" class="size-3.5" /></a>@endif
                            @if ($canManage && $rows !== [])<x-btn type="button" variant="secondary" icon="plus" @click="$dispatch('open-modal', 'add-{{ $type }}')" data-testid="add-{{ $type }}">Add {{ $t['one'] }}</x-btn>@endif
                        </div>
                    </x-slot:aside>
                    @if ($rows === [])
                        <x-empty :title="'No '.strtolower($t['label']).' yet'" text="Nothing from this section is shown on the website until you add one and switch it on." icon="layers">
                            @if ($canManage)<x-btn class="mt-4" type="button" icon="plus" @click="$dispatch('open-modal', 'add-{{ $type }}')" data-testid="add-first-{{ $type }}">{{ $t['add'] }}</x-btn>@endif
                        </x-empty>
                    @else
                        <form method="POST" action="{{ route('website.homepage.reorder') }}" x-data="{ changed: false }" @cms-reordered="changed = true">@csrf
                            <input type="hidden" name="type" value="{{ $type }}">
                            <ol class="divide-y divide-stone-100" x-data="cmsSortable" aria-label="{{ $t['label'] }}, in display order">
                                @foreach ($rows as $i => $r)
                                    @php
                                        $p = (array) ($r['payload'] ?? []);
                                        $m = $t['image'] ? Cms::media($p[$t['image']] ?? null, $media) : null;
                                        $title = (string) ($p[$t['title']] ?? '(untitled)');
                                        $sub = (string) ($p[$t['sub']] ?? '');
                                    @endphp
                                    <li class="flex flex-wrap items-center gap-3 px-4 py-3 sm:flex-nowrap" data-sort-item data-sort-id="{{ $r['id'] }}" data-sort-label="{{ $title }}" @if ($canManage) draggable="true" @dragstart="start($event, $el)" @dragover="over($event, $el)" @drop.prevent @dragend="end()" @endif data-testid="block-row">
                                        <input type="hidden" name="ids[]" value="{{ $r['id'] }}">
                                        @if ($canManage)<span class="cursor-grab text-stone-400" title="Drag to reorder" aria-hidden="true"><x-icon name="grip" class="size-5" /></span>@endif
                                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-stone-100 text-xs font-semibold text-stone-600" data-sort-pos>{{ $i + 1 }}</span>
                                        @if ($t['image'])<span class="hidden h-11 w-16 shrink-0 overflow-hidden rounded-md bg-stone-100 sm:block">@if ($m)<img src="{{ Cms::thumb($m, 240) }}" alt="" class="size-full object-cover" loading="lazy">@endif</span>@endif
                                        <div class="min-w-0 flex-1"><div class="truncate text-sm font-medium text-stone-900">{{ $title }}</div>@if ($sub !== '')<div class="truncate text-xs text-stone-500">{{ \Illuminate\Support\Str::limit($sub, 110) }}</div>@endif</div>
                                        <x-status-pill :status="($r['enabled'] ?? false) ? 'PUBLISHED' : 'DRAFT'">{{ ($r['enabled'] ?? false) ? 'On the website' : 'Switched off' }}</x-status-pill>
                                        <div class="flex shrink-0 items-center gap-1 text-sm font-medium">
                                            @if ($canManage)
                                                <button type="button" class="flex size-9 items-center justify-center rounded-lg border border-stone-200 hover:bg-stone-50 disabled:opacity-40" data-move="up" @click="up($el.closest('[data-sort-item]'))" aria-label="Move {{ $title }} up" @if ($i === 0) data-first @endif><x-icon name="up" class="size-4" /></button>
                                                <button type="button" class="flex size-9 items-center justify-center rounded-lg border border-stone-200 hover:bg-stone-50" data-move="down" @click="down($el.closest('[data-sort-item]'))" aria-label="Move {{ $title }} down"><x-icon name="down" class="size-4" /></button>
                                            @endif
                                            @if ($canPublish)<button type="submit" form="toggle-{{ $r['id'] }}" class="ml-1 rounded-lg border px-3 py-1.5 text-sm {{ ($r['enabled'] ?? false) ? 'border-stone-300 text-stone-700 hover:bg-stone-50' : 'border-brand-600 bg-brand-600 text-white hover:bg-brand-700' }}" data-testid="toggle">{{ ($r['enabled'] ?? false) ? 'Switch off' : 'Switch on' }}</button>@endif
                                            <button type="button" class="ml-1 px-2 text-brand-700 underline" @click="$dispatch('open-modal', 'edit-{{ $r['id'] }}')" data-testid="edit-block">{{ $canManage ? 'Edit' : 'View' }}</button>
                                            @if ($canManage)<button type="submit" form="del-{{ $r['id'] }}" class="px-2 text-red-700 underline" data-testid="delete-block">Remove</button>@endif
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                            <div class="flex items-center justify-between gap-3 border-t border-stone-100 bg-stone-50 px-4 py-2.5 text-sm" x-show="changed" x-cloak role="status"><span class="text-stone-700">The order changed. Save it to update the website.</span><button class="f-btn" data-variant="primary" data-size="sm" data-testid="save-order">Save order</button></div>
                        </form>
                        @foreach ($rows as $r)
                            @if ($canPublish)<form id="toggle-{{ $r['id'] }}" method="POST" action="{{ route('website.homepage.toggle', [$r['id'], ($r['enabled'] ?? false) ? 'disable' : 'enable']) }}">@csrf</form>@endif
                            @if ($canManage)<form id="del-{{ $r['id'] }}" method="POST" action="{{ route('website.homepage.destroy', $r['id']) }}" x-data="confirmSubmit('Remove this {{ $t['one'] }} from the homepage? This cannot be undone.', 'Remove')" @submit="ask($event)">@csrf @method('DELETE')</form>@endif
                            <x-dialog name="edit-{{ $r['id'] }}" :title="($canManage ? 'Edit ' : '').$t['one']" maxWidth="max-w-4xl">
                                @include('pages.website.partials.home-form', ['type' => $type, 't' => $t, 'item' => $r, 'media' => $media, 'dialog' => 'edit-'.$r['id']])
                            </x-dialog>
                        @endforeach
                    @endif
                    @if ($canManage)
                        <x-dialog name="add-{{ $type }}" :title="'Add '.$t['one']" subtitle="It starts switched off. Turn it on from the list when it is ready." maxWidth="max-w-4xl">
                            @include('pages.website.partials.home-form', ['type' => $type, 't' => $t, 'item' => null, 'media' => $media, 'dialog' => 'add-'.$type])
                        </x-dialog>
                    @endif
                </x-card>
            </section>
        @endforeach
    @endif
    @if ($dialogToReopen && $errors->any())<div x-data x-init="$nextTick(() => $dispatch('open-modal', @js($dialogToReopen)))"></div>@endif
</x-cms.layout>
