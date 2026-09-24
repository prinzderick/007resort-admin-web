@php
    use App\Support\Cms\Cms;
    use App\Support\Form\FormField;
    $key = $def['key'];
    $isNew = $id === null;
    $canEdit = $canManage;
    $title = $isNew ? 'New '.strtolower($def['label']) : ($item['title'] ?? $item['name'] ?? 'Edit '.strtolower($def['label']));
    $sections = fn ($list) => $list;
    $fVals = [];
    foreach (\App\Http\Controllers\Website\ContentController::fields($def) as $s) {
        if (! in_array($s['type'] ?? 'text', ['markdown', 'image'], true)) {
            $fVals[$s['name']] = old(FormField::dot($s['name']), $values[$s['name']] ?? ($s['default'] ?? (($s['type'] ?? '') === 'tags' ? [] : '')));
        }
    }
    $fVals['ticketMode'] = old('ticketMode', $values['ticketMode'] ?? 'none');
    $fVals['excerpt'] = $fVals['excerpt'] ?? '';
    $fVals['summary'] = $fVals['summary'] ?? '';
    $seoPrefix = isset($def['sitePath']) ? ($def['sitePath'])(['slug' => '']) : '/';
    $action = $isNew ? route('website.'.$key.'.store') : route('website.'.$key.'.update', $id);
    $publishable = $def['publish'] && $canPublish;
    $live = in_array($status, ['PUBLISHED', 'SCHEDULED'], true);
@endphp
<x-cms.layout :title="$title">
    <x-page-header :title="$title" :subtitle="$isNew ? 'Fill in the details, then save it as a draft or publish it.' : null" :crumbs="['Website' => route('website.index'), $def['plural'] => route('website.'.$key.'.index'), ($isNew ? 'New' : 'Edit') => null]">
        <x-slot:actions>
            @if (! $isNew && $status === 'PUBLISHED' && isset($def['sitePath']) && config('r007.site_url'))<x-btn variant="secondary" :href="Cms::siteUrl(($def['sitePath'])($item))" target="_blank" rel="noopener" icon="external">View on site</x-btn>@endif
            <x-btn variant="secondary" :href="route('website.'.$key.'.index')" data-allow-leave>Back to {{ strtolower($def['plural']) }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    @unless ($canEdit)<x-alert tone="info">You can look at this {{ strtolower($def['label']) }} but not change it. Editing needs the "cms.manage" permission.</x-alert>@endunless

    <div x-data="{ f: @js($fVals) }">
        <form method="POST" action="{{ $action }}" x-data="cmsForm" novalidate data-testid="content-form" id="content-form">
            @csrf
            @unless ($isNew)@method('PUT')<input type="hidden" name="rowVersion" value="{{ $item['rowVersion'] ?? '' }}">@endunless
            <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="min-w-0">
                    @foreach ($def['main'] as $s)
                        <x-card :title="$s['title']" :subtitle="$s['subtitle'] ?? null">
                            @isset ($s['view'])
                                @include($s['view'], ['section' => $s, 'facilities' => $facilities, 'products' => $products, 'values' => $values, 'canEdit' => $canEdit])
                            @else
                                <div class="grid gap-5 {{ ($s['cols'] ?? 1) === 2 ? 'sm:grid-cols-2' : '' }}">
                                    @foreach ($s['fields'] as $spec)
                                        <div class="{{ in_array($spec['type'], ['markdown', 'segmented'], true) && ($s['cols'] ?? 1) === 2 && $spec['type'] === 'markdown' ? 'sm:col-span-2' : '' }}"><x-cms.field :spec="$spec" :values="$values" :media="$media" :options="$options" :can-edit="$canEdit" /></div>
                                    @endforeach
                                </div>
                                @if (($s['kind'] ?? null) === 'seo')<div class="mt-5"><x-cms.snippet :path="$seoPrefix" /></div>@endif
                                @isset ($s['after'])@include($s['after'])@endisset
                            @endisset
                        </x-card>
                    @endforeach
                </div>

                <aside class="min-w-0 xl:sticky xl:top-20">
                    @if ($def['publish'])
                        <x-card title="Status" data-testid="status-card">
                            <div class="mb-3 flex items-center gap-2"><x-cms.status :status="$status === 'NEW' ? 'DRAFT' : $status" /><span class="text-sm text-stone-600" data-testid="status-note">
                                @switch($status)
                                    @case('NEW')Not saved yet.@break
                                    @case('DRAFT')Only staff can see this.@break
                                    @case('PUBLISHED')Live on the website since {{ Cms::when($item['publishedAt'] ?? null) }}.@break
                                    @case('SCHEDULED')Goes live on {{ Cms::when($item['publishedAt'] ?? null) }}.@break
                                    @case('ARCHIVED')Hidden from the website.@break
                                @endswitch
                            </span></div>
                            @if ($publishable && $canEdit)
                                @if (! $live && ($def['schedule'] ?? false))
                                    <div class="mb-3" x-data="{ at: '' }"><x-cms.datetime name="publishAt" label="Publish date and time" hint="Leave the date blank to publish right away, or pick a future date to schedule it." /></div>
                                @endif
                                <div class="grid gap-2">
                                    @if ($live)
                                        <button type="submit" class="f-btn" data-variant="primary" name="intent" value="save">Save changes</button>
                                        <button type="submit" class="f-btn" form="unpublish-form" data-testid="unpublish">Unpublish</button>
                                    @else
                                        <button type="submit" class="f-btn" data-variant="primary" name="intent" value="publish" data-testid="save-publish">{{ $status === 'ARCHIVED' ? 'Save and restore as published' : 'Save and publish' }}</button>
                                        <button type="submit" class="f-btn" name="intent" value="save" data-testid="save-draft">{{ $status === 'ARCHIVED' ? 'Save (stay archived)' : 'Save as draft' }}</button>
                                    @endif
                                    @if (! $isNew && $status !== 'ARCHIVED')<button type="submit" class="f-btn" form="archive-form" data-testid="archive">Archive</button>@endif
                                </div>
                            @elseif ($def['publish'])
                                <p class="text-xs text-stone-500">You can save changes but not publish or unpublish. Publishing needs the "cms.publish" permission.</p>
                            @endif
                            @if (! $isNew && $canManage)<div class="mt-4 border-t border-stone-100 pt-3"><button type="submit" form="delete-form" class="text-sm font-medium text-red-700 underline" data-testid="delete">Delete this {{ strtolower($def['label']) }}</button>@if ($live)<p class="mt-1 text-xs text-stone-500">Published items must be archived before they can be deleted.</p>@endif</div>@endif
                        </x-card>
                    @endif
                    @foreach ($def['side'] as $s)
                        <x-card :title="$s['title']">
                            <div class="grid gap-5">
                                @foreach ($s['fields'] as $spec)<x-cms.field :spec="$spec" :values="$values" :media="$media" :options="$options" :can-edit="$canEdit" />@endforeach
                            </div>
                        </x-card>
                    @endforeach
                    @if (! $isNew)
                        <p class="px-1 text-xs text-stone-500">Last changed {{ Cms::when($item['updatedAt'] ?? null) }}.</p>
                    @endif
                </aside>
            </div>
            @if ($canEdit)
                <x-form.actions :submit="$def['publish'] ? ($live ? 'Save changes' : 'Save as draft') : 'Save '.strtolower($def['label'])" cancel="Discard changes" class="mt-2" />
            @endif
        </form>
        @if ($def['publish'] && ! $isNew && $publishable)
            <form id="unpublish-form" method="POST" action="{{ route('website.'.$key.'.transition', [$id, 'unpublish']) }}" x-data="confirmSubmit('Unpublish this {{ strtolower($def['label']) }}? It disappears from the website but you keep it as a draft.', 'Unpublish')" @submit="ask($event)">@csrf</form>
            <form id="archive-form" method="POST" action="{{ route('website.'.$key.'.transition', [$id, 'archive']) }}" x-data="confirmSubmit('Archive this {{ strtolower($def['label']) }}? It is hidden from the website and moved out of the way.', 'Archive')" @submit="ask($event)">@csrf</form>
        @endif
        @if (! $isNew && $canManage)
            <form id="delete-form" method="POST" action="{{ route('website.'.$key.'.destroy', $id) }}" x-data="confirmSubmit('Delete this {{ strtolower($def['label']) }} for good? This cannot be undone.', 'Delete')" @submit="ask($event)">@csrf @method('DELETE')</form>
        @endif
    </div>
</x-cms.layout>
