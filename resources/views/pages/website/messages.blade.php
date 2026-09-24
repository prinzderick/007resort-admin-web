@php
    use App\Support\Cms\Cms;
    $brand = config('app.name');
    $mailto = fn (array $m) => 'mailto:'.rawurlencode((string) ($m['email'] ?? '')).'?subject='.rawurlencode('Re: your message to 007 Resort & Spa').'&body='.rawurlencode("\n\n---\n".($m['name'] ?? '')." wrote:\n".($m['message'] ?? ''));
    $rows = array_map(fn ($m) => ['id' => $m['id'], 'name' => $m['name'] ?? '', 'email' => $m['email'] ?? '', 'phone' => $m['phone'] ?? '', 'topic' => $topics[$m['topic'] ?? ''] ?? ($m['topic'] ?? ''), 'message' => $m['message'] ?? '', 'status' => $m['status'] ?? 'NEW', 'note' => $m['internalNote'] ?? '', 'handledBy' => $m['handledBy'] ?? '', 'received' => Cms::when($m['createdAt'] ?? null), 'mailto' => $mailto($m)], $items);
@endphp
<x-cms.layout title="Messages">
    <x-page-header title="Messages" subtitle="Messages sent through the contact form on the website. Open one to read it, reply by email and keep a private note." :crumbs="['Website' => route('website.index'), 'Messages' => null]" />
    <div class="mb-4"><x-cms.chips :options="$chips" :current="request('status', '')" :counts="array_filter($chipCounts, fn ($v) => $v !== null)" /></div>
    <x-filter-form :reset="route('website.messages')">
        <x-filter-text name="q" label="Search" :value="request('q')" placeholder="Name, email or words in the message" width="18rem" />
        <x-filter-select name="topic" label="Topic" :options="$topics" :value="request('topic')" all="All topics" width="12rem" />
        @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    </x-filter-form>

    <div x-data="{ m: null, sel: [], ids: @js(array_column($rows, 'id')), get all() { return this.ids.length > 0 && this.sel.length === this.ids.length }, toggleAll(on) { this.sel = on ? [...this.ids] : [] }, open(r) { this.m = r }, close() { this.m = null } }" @keydown.escape.window="close()">
        <x-card flush x-data="tableTools">
            <x-fetch :of="$list" what="Messages" />
            @if ($list->ok())
                @if ($items === [] && ! request()->hasAny(['q', 'status', 'topic']))
                    <x-empty title="No messages yet" text="Messages from the website contact form land here. Nothing has been sent so far." icon="mail" />
                @else
                    <form method="POST" action="{{ route('website.messages.bulk') }}" x-show="sel.length" x-cloak class="flex flex-wrap items-center gap-3 border-b border-brand-200 bg-brand-50 px-4 py-2 text-sm" data-testid="bulk-bar" role="status">@csrf
                        <template x-for="id in sel" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                        <b x-text="sel.length + (sel.length === 1 ? ' message selected' : ' messages selected')"></b>
                        <button name="action" value="READ" class="f-btn" data-size="sm" data-testid="bulk-read">Mark as read</button>
                        <button name="action" value="REPLIED" class="f-btn" data-size="sm">Mark as replied</button>
                        <button name="action" value="SPAM" class="f-btn" data-size="sm">Mark as spam</button>
                        <input type="hidden" name="action" value="DELETE" disabled x-ref="del"><button type="button" class="f-btn" data-size="sm" data-variant="danger" @click="r007Confirm('Erase the selected messages for good? This cannot be undone.', 'Erase').then(ok => { if (ok) { $refs.del.disabled = false; $el.form.submit(); } })" data-testid="bulk-erase">Erase</button>
                        <button type="button" class="ml-auto text-stone-600 underline" @click="sel = []">Clear selection</button>
                    </form>
                    <div class="overflow-x-auto"><table class="data-table" data-testid="messages-table">
                        <thead><tr><th class="w-10"><input type="checkbox" class="size-4 accent-brand-600" aria-label="Select all messages" :checked="all" @change="toggleAll($event.target.checked)"></th><th data-sort>From</th><th data-sort>Topic</th><th>Message</th><th data-sort>Status</th><th data-sort>Received</th><th class="text-right">Actions</th></tr></thead>
                        <tbody>
                        @forelse ($rows as $r)
                            <tr data-row data-testid="row" data-status="{{ $r['status'] }}" :class="sel.includes('{{ $r['id'] }}') && 'bg-brand-50/60'">
                                <td><input type="checkbox" class="size-4 accent-brand-600" value="{{ $r['id'] }}" x-model="sel" aria-label="Select message from {{ $r['name'] }}"></td>
                                <td data-sort="{{ $r['name'] }}"><button type="button" class="text-left" @click="open(@js($r))"><span class="{{ $r['status'] === 'NEW' ? 'font-semibold' : 'font-medium' }} text-stone-900">{{ $r['name'] }}</span><span class="block text-xs text-stone-500">{{ $r['email'] }}</span></button></td>
                                <td class="text-sm" data-sort="{{ $r['topic'] }}">{{ $r['topic'] }}</td>
                                <td class="max-w-md"><button type="button" class="block max-w-md truncate text-left text-sm {{ $r['status'] === 'NEW' ? 'font-medium text-stone-900' : 'text-stone-600' }}" @click="open(@js($r))">{{ \Illuminate\Support\Str::limit($r['message'], 90) }}</button></td>
                                <td data-sort="{{ $r['status'] }}"><x-status-pill :status="$r['status']">{{ \App\Http\Controllers\Website\MessagesController::STATUS[$r['status']] ?? $r['status'] }}</x-status-pill></td>
                                <td class="whitespace-nowrap text-sm text-stone-600" data-sort="{{ $r['received'] }}">{{ $r['received'] }}</td>
                                <td class="whitespace-nowrap text-right"><div class="inline-flex items-center gap-x-3 text-sm font-medium"><button type="button" class="text-brand-700 underline" @click="open(@js($r))" data-testid="open-message">Open</button><a class="text-stone-700 underline" href="{{ $r['mailto'] }}" data-testid="reply-mailto">Reply by email</a></div></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-empty title="No messages match" text="Try a different search or filter." icon="search"><x-btn class="mt-3" variant="secondary" :href="route('website.messages')">Clear filters</x-btn></x-empty></td></tr>
                        @endforelse
                        </tbody>
                    </table></div>
                    <x-pagination :count="count($items)" :next="$next" noun="messages" />
                @endif
            @endif
        </x-card>

        {{-- Detail drawer --}}
        <div x-cloak x-show="m" class="fixed inset-0 z-50 bg-stone-900/30" @click="close()"></div>
        <aside x-cloak x-show="m" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" class="fixed inset-y-0 right-0 z-50 flex w-full max-w-lg flex-col bg-white shadow-2xl" role="dialog" aria-modal="true" aria-label="Message" data-testid="message-drawer">
            <template x-if="m">
                <div class="flex min-h-0 flex-1 flex-col">
                    <div class="flex items-start justify-between gap-3 border-b border-stone-100 px-5 py-4"><div><h2 class="text-base font-semibold" x-text="m.name"></h2><p class="text-xs text-stone-500"><span x-text="m.topic"></span> &middot; <span x-text="m.received"></span></p></div><button type="button" @click="close()" class="flex size-9 items-center justify-center rounded-full hover:bg-stone-100" aria-label="Close"><x-icon name="x" /></button></div>
                    <div class="flex-1 space-y-5 overflow-y-auto px-5 py-4 text-sm">
                        <dl class="grid grid-cols-[6rem_1fr] gap-y-1.5"><dt class="text-stone-500">Email</dt><dd class="break-all"><a class="text-brand-700 underline" :href="'mailto:' + m.email" x-text="m.email"></a></dd><dt class="text-stone-500">Phone</dt><dd x-text="m.phone || '-'"></dd><dt class="text-stone-500">Status</dt><dd><span class="font-medium" x-text="({NEW:'New',READ:'Read',REPLIED:'Replied',SPAM:'Spam'})[m.status]"></span><span class="text-stone-500" x-show="m.handledBy" x-text="' (handled by ' + m.handledBy + ')'"></span></dd></dl>
                        <div><div class="t-label mb-1">Message</div><p class="whitespace-pre-wrap rounded-lg border border-stone-200 bg-stone-50 p-3 leading-6" x-text="m.message" data-testid="message-body"></p></div>
                        <a :href="m.mailto" class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-lg bg-brand-600 px-4 text-sm font-medium text-white hover:bg-brand-700" data-testid="drawer-reply"><x-icon name="mail" class="size-4" /> Reply by email</a>
                        <form method="POST" :action="'{{ url('/website/messages') }}/' + m.id" class="space-y-2">@csrf @method('PATCH')
                            <label class="t-label block" for="note">Private note (only staff see this)</label>
                            <textarea id="note" name="internalNote" rows="3" maxlength="2000" x-model="m.note" class="w-full rounded-lg border border-stone-300 p-2.5 text-sm focus:border-brand-500 focus:outline-none" placeholder="e.g. Called back, sent the quote."></textarea>
                            <button class="f-btn" data-size="sm" data-testid="save-note">Save note</button>
                        </form>
                    </div>
                    <div class="grid gap-2 border-t border-stone-100 p-4">
                        <div class="flex flex-wrap gap-2">
                            <template x-for="s in [['READ','Mark as read'],['REPLIED','Mark as replied'],['SPAM','Mark as spam'],['NEW','Mark as new']]" :key="s[0]">
                                <form method="POST" :action="'{{ url('/website/messages') }}/' + m.id" x-show="m.status !== s[0]">@csrf @method('PATCH')<input type="hidden" name="status" :value="s[0]"><button class="f-btn" data-size="sm" x-text="s[1]" :data-testid="'mark-' + s[0].toLowerCase()"></button></form>
                            </template>
                        </div>
                        <form method="POST" :action="'{{ url('/website/messages') }}/' + m.id" x-data="confirmSubmit('Erase this message for good? This cannot be undone.', 'Erase')" @submit="ask($event)">@csrf @method('DELETE')<button class="text-sm font-medium text-red-700 underline" data-testid="erase">Erase this message</button></form>
                    </div>
                </div>
            </template>
        </aside>
    </div>
</x-cms.layout>
