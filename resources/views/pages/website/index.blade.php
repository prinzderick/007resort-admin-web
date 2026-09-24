@php
    $staff = auth_staff();
    $n = fn ($path, $default = 0) => (int) data_get($s, $path, $default);
    $quick = array_values(array_filter([
        ['Write a blog post', route('website.posts.create'), 'edit', 'cms.manage'],
        ['Add an event', route('website.events.create'), 'calendar', 'cms.manage'],
        ['Change the announcement bar', route('website.settings').'#announcement', 'bell', 'cms.view'],
        ['Edit the homepage', route('website.homepage'), 'home', 'cms.view'],
        ['Upload pictures', route('website.media'), 'upload', 'cms.view'],
        ['Update opening hours', route('website.settings').'#hours', 'clock', 'cms.view'],
        ['Read new messages', route('website.messages', ['status' => 'NEW']), 'mail', 'cms.messages.manage'],
        ['See subscribers', route('website.subscribers'), 'users', 'cms.subscribers.view'],
    ], fn ($q) => $staff->can($q[3])));
@endphp
<x-cms.layout title="Website">
    <x-page-header title="Website" subtitle="Everything visitors see on the public website is managed here. Changes go live as soon as you publish." :crumbs="['Website' => null]" />
    @if ($summary)<x-fetch :of="$summary" what="The website summary" />@endif

    @if ($s)
        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4" data-testid="website-stats">
            <x-stat label="Published posts" :value="$n('posts.published')" :hint="$n('posts.draft').' draft'.($n('posts.draft') === 1 ? '' : 's')" />
            <x-stat label="Published events" :value="$n('events.published')" :hint="$n('events.draft').' draft'.($n('events.draft') === 1 ? '' : 's')" />
            <x-stat label="Subscribers" :value="$n('subscribers.confirmed')" :hint="$n('subscribers.pending').' waiting to confirm'" />
            <x-stat label="New messages" :value="$n('messages.new')" :tone="$n('messages.new') > 0 ? 'warn' : 'default'" :hint="$n('messages.new') > 0 ? 'Waiting for a reply' : 'Inbox is clear'" />
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <x-card title="Quick actions" subtitle="The things editors do most">
            <ul class="grid gap-2 sm:grid-cols-2">
                @foreach ($quick as [$label, $href, $icon])
                    <li><a href="{{ $href }}" class="flex min-h-12 items-center gap-3 rounded-lg border border-stone-200 px-4 py-2 text-sm font-medium text-stone-800 hover:border-brand-300 hover:bg-brand-50"><span class="flex size-8 items-center justify-center rounded-full bg-brand-50 text-brand-700"><x-icon :name="$icon" class="size-4" /></span>{{ $label }}</a></li>
                @endforeach
            </ul>
        </x-card>
        <x-card title="Content at a glance">
            @if ($s)
                <dl class="divide-y divide-stone-100 text-sm">
                    @foreach ([['Pages', 'pages', route('website.pages.index')], ['Blog posts', 'posts', route('website.posts.index')], ['Events', 'events', route('website.events.index')], ['Gallery albums', 'albums', route('website.gallery')]] as [$label, $k, $href])
                        <div class="flex items-center justify-between py-2"><dt><a class="text-brand-700 underline" href="{{ $href }}">{{ $label }}</a></dt><dd class="text-stone-600">{{ $n($k.'.published') }} live @if ($n($k.'.draft') > 0)&middot; <span class="text-amber-700">{{ $n($k.'.draft') }} draft</span>@endif</dd></div>
                    @endforeach
                    <div class="flex items-center justify-between py-2"><dt><a class="text-brand-700 underline" href="{{ route('website.media') }}">Pictures</a></dt><dd class="text-stone-600">{{ $n('media.count') }}</dd></div>
                </dl>
            @else
                <p class="text-sm text-stone-500">Numbers appear here when the website module is reachable.</p>
            @endif
        </x-card>
    </div>
</x-cms.layout>
