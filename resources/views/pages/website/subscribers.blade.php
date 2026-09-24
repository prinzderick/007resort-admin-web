@php use App\Support\Cms\Cms; $c = fn ($k) => (int) ($counts[$k] ?? 0); $total = $c('confirmed') + $c('pending') + $c('unsubscribed'); @endphp
<x-cms.layout title="Subscribers">
    <x-page-header title="Subscribers" subtitle="People who signed up for news on the website. Only people who confirmed by email should be sent mail." :crumbs="['Website' => route('website.index'), 'Subscribers' => null]">
        <x-slot:actions>
            @if ($canExport)<x-btn variant="secondary" icon="download" :href="route('website.subscribers.export', array_filter(['status' => request('status')]))" data-testid="export-csv" data-allow-leave>Export CSV{{ request('status') ? ' ('.strtolower(\App\Http\Controllers\Website\SubscribersController::STATUS[request('status')] ?? '').')' : '' }}</x-btn>
            @else<span class="text-xs text-stone-500" title="Needs the cms.subscribers.export permission">Export is not available for your account.</span>@endif
        </x-slot:actions>
    </x-page-header>

    @if ($list->ok())
        <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3 2xl:grid-cols-5" data-testid="sub-counts">
            <x-stat label="Total" :value="number_format($total)" />
            <x-stat label="Confirmed" :value="number_format($c('confirmed'))" tone="good" hint="Can be emailed" />
            <x-stat label="Waiting to confirm" :value="number_format($c('pending'))" :tone="$c('pending') ? 'warn' : 'default'" hint="Have not clicked the email link" />
            <x-stat label="Unsubscribed" :value="number_format($c('unsubscribed'))" hint="Do not email" />
            <x-stat label="New in 30 days" :value="'+'.number_format($growth['added'])" :spark="$growth['spark']" :hint="$growth['capped'] ? 'From the latest 1,000 sign-ups' : 'Daily running total'" />
        </div>
    @endif

    <div class="mb-4"><x-cms.chips :options="$chips" :current="request('status', '')" /></div>
    <x-filter-form :reset="route('website.subscribers')">
        <x-filter-text name="q" label="Search" :value="request('q')" placeholder="Email or name" width="16rem" />
        <x-filter-select name="source" label="Signed up from" :options="$sources" :value="request('source')" all="Anywhere" width="13rem" />
        @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    </x-filter-form>

    <x-card flush x-data="tableTools">
        <x-fetch :of="$list" what="Subscribers" />
        @if ($list->ok())
            @if ($items === [] && ! request()->hasAny(['q', 'status', 'source']))
                <x-empty title="No subscribers yet" text="When visitors sign up for news in the website footer, blog or events pages, they appear here." icon="mail" />
            @else
                <x-table-tools placeholder="Filter these subscribers..." :csv="false" :selectable="false" />
                <div class="overflow-x-auto"><table class="data-table" data-testid="subscribers-table">
                    <thead><tr><th data-sort>Email</th><th data-sort>Name</th><th data-sort>Signed up from</th><th data-sort>Status</th><th data-sort>Date</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                    @forelse ($items as $s)
                        <tr data-row data-testid="row">
                            <td class="font-medium" data-sort="{{ $s['email'] ?? '' }}">{{ $s['email'] ?? '' }}</td>
                            <td class="text-sm text-stone-600">{{ $s['name'] ?? '-' }}</td>
                            <td class="text-sm">{{ $sources[$s['source'] ?? ''] ?? ucfirst((string) ($s['source'] ?? '-')) }}</td>
                            <td data-sort="{{ $s['status'] ?? '' }}"><x-status-pill :status="$s['status'] ?? ''">{{ ['CONFIRMED' => 'Confirmed', 'PENDING' => 'Waiting to confirm', 'UNSUBSCRIBED' => 'Unsubscribed'][$s['status'] ?? ''] ?? ($s['status'] ?? '') }}</x-status-pill></td>
                            <td class="whitespace-nowrap text-sm text-stone-600" data-sort="{{ $s['createdAt'] ?? '' }}">{{ Cms::day($s['createdAt'] ?? null) }}</td>
                            <td class="whitespace-nowrap text-right"><div class="inline-flex items-center gap-x-3 text-sm font-medium">
                                @if ($canManage)
                                    @if (($s['status'] ?? '') !== 'UNSUBSCRIBED')<form method="POST" action="{{ route('website.subscribers.unsubscribe', $s['id']) }}" class="inline" x-data="confirmSubmit('Unsubscribe {{ e($s['email'] ?? 'this person') }}? They will stop receiving website emails.', 'Unsubscribe')" @submit="ask($event)">@csrf<button class="text-stone-700 underline" data-testid="unsubscribe">Unsubscribe</button></form>@endif
                                    <form method="POST" action="{{ route('website.subscribers.destroy', $s['id']) }}" class="inline" x-data="confirmSubmit('Erase {{ e($s['email'] ?? 'this person') }} permanently? Their address and name are deleted for good and cannot be recovered. Use this for data-deletion requests.', 'Erase for good')" @submit="ask($event)">@csrf @method('DELETE')<button class="text-red-700 underline" data-testid="erase">Erase</button></form>
                                @else<span class="text-xs text-stone-400">View only</span>@endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty title="Nobody matches" text="Try a different search or filter." icon="search"><x-btn class="mt-3" variant="secondary" :href="route('website.subscribers')">Clear filters</x-btn></x-empty></td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                <x-pagination :count="count($items)" :next="$next" noun="subscribers" />
            @endif
        @endif
    </x-card>
</x-cms.layout>
