<x-layouts.app title="Staff manual">
    <x-page-header title="Staff operations manual" subtitle="How to do your job on the system, step by step. Pick your role below, or search for a word.">
        <x-slot:actions>
            @if ($hasPdf)
                <x-btn variant="secondary" icon="download" :href="route('manual.pdf').'?download=1'" target="_blank" rel="noopener" data-testid="manual-pdf">Download the PDF</x-btn>
            @endif
            <x-btn variant="ghost" icon="file" type="button" onclick="window.print()">Print this page</x-btn>
        </x-slot:actions>
    </x-page-header>

    <style>
        .manual .p-job, .manual .p-device { border-radius: .6rem; padding: .7rem 1rem; margin: 0 0 .8rem; }
        .manual .p-job { background: #eef5f2; border-left: 4px solid #0e5a4b; font-size: 1.02rem; }
        .manual .p-device { background: #f1f4f8; border-left: 4px solid #6b7f95; }
        .manual .prose-manual h3 { font-size: 1.05rem; font-weight: 600; color: #0e5a4b; margin: 1.6rem 0 .6rem; padding-bottom: .3rem; border-bottom: 2px solid #c9973f; }
        .manual .prose-manual p { margin: 0 0 .7rem; line-height: 1.6; }
        .manual .prose-manual ul, .manual .prose-manual ol { margin: 0 0 .9rem 1.3rem; }
        .manual .prose-manual ul { list-style: disc; } .manual .prose-manual ol { list-style: decimal; }
        .manual .prose-manual li { margin: 0 0 .35rem; padding-left: .2rem; line-height: 1.55; }
        .manual .prose-manual li::marker { color: #0e5a4b; font-weight: 600; }
        .manual .prose-manual code { background: #eef5f2; padding: .05rem .35rem; border-radius: .3rem; font-size: .88em; }
        .manual .prose-manual table { width: 100%; border-collapse: collapse; margin: .4rem 0 1.1rem; font-size: .88rem; display: block; overflow-x: auto; }
        .manual .prose-manual th { background: #0e5a4b; color: #fff; text-align: left; font-weight: 600; padding: .5rem .7rem; white-space: nowrap; }
        .manual .prose-manual td { padding: .5rem .7rem; border-bottom: 1px solid #d9e2de; vertical-align: top; min-width: 7rem; }
        .manual .prose-manual tbody tr:nth-child(even) td { background: #f6faf8; }
        .manual .prose-manual td:first-child { font-weight: 600; color: #0a3f35; }
        .manual .sec-never { background: #fbedea; border-left: 4px solid #9b2c1f; border-radius: .6rem; padding: .1rem 1rem .5rem; margin: 1.3rem 0; }
        .manual .sec-never h3 { color: #9b2c1f; border-bottom-color: #e8c3bd; margin-top: .8rem; }
        .manual .sec-wrong { background: #fdf3e3; border-left: 4px solid #8a4a0b; border-radius: .6rem; padding: .1rem 1rem .5rem; margin: 1.3rem 0; }
        .manual .sec-wrong h3 { color: #8a4a0b; border-bottom-color: #ecd3ab; margin-top: .8rem; }
        .manual .note-it { border: 1px dashed #c9973f; background: #fffdf7; border-radius: .5rem; padding: .55rem .8rem; }
        @media print {
            aside, header, nav, .no-print { display: none !important; }
            .manual .chapter { break-before: page; }
            .manual .chapter:first-of-type { break-before: auto; }
        }
    </style>

    <div class="manual" x-data="{ q: '' }">
        <div class="no-print mb-6 grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-stone-200 bg-white p-4 lg:col-span-2">
                <p class="mb-2 text-sm font-semibold text-stone-800">Find your role</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($roles as $role)
                        <a href="#chapter-{{ $role['target'] }}"
                           class="rounded-full border border-stone-300 bg-white px-3 py-1.5 text-sm text-stone-800 hover:border-brand-600 hover:bg-brand-50"
                           title="Read chapters {{ implode(', ', $role['chapters']) }}"
                           data-testid="manual-role">{{ $role['label'] }}</a>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-stone-500">A role takes you to its own chapter. Everyone should also read chapters 2 and 17.</p>
            </div>
            <div class="rounded-xl border border-stone-200 bg-white p-4">
                <label class="mb-2 block text-sm font-semibold text-stone-800" for="manual-search">Search the manual</label>
                <input id="manual-search" x-model="q" type="search" placeholder="for example: void, cash session, offline"
                       class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20">
                <p class="mt-2 text-xs text-stone-500">Chapters that do not mention it are hidden.</p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[15rem_minmax(0,1fr)]">
            <nav class="no-print lg:sticky lg:top-4 lg:self-start" aria-label="Chapters">
                <ol class="space-y-0.5 rounded-xl border border-stone-200 bg-white p-2 text-sm">
                    @foreach ($chapters as $c)
                        <li x-show="q.trim().length < 2 || document.getElementById('chapter-{{ $c['n'] }}')?.textContent.toLowerCase().includes(q.trim().toLowerCase())">
                            <a class="flex gap-2 rounded-lg px-2 py-1.5 text-stone-700 hover:bg-brand-50 hover:text-brand-800" href="#chapter-{{ $c['n'] }}">
                                <span class="w-5 shrink-0 font-semibold text-brand-700">{{ $c['n'] }}</span><span>{{ $c['title'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            </nav>

            <div class="space-y-6">
                @foreach ($chapters as $c)
                    <section id="chapter-{{ $c['n'] }}" class="chapter scroll-mt-20 rounded-xl border border-stone-200 bg-white"
                             x-show="q.trim().length < 2 || $el.textContent.toLowerCase().includes(q.trim().toLowerCase())">
                        <div class="flex items-center gap-4 rounded-t-xl bg-brand-900 px-5 py-4 text-white" style="background:#0a3f35">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full text-lg font-extrabold" style="background:#c9973f;color:#0a3f35">{{ $c['n'] }}</span>
                            <h2 class="text-xl font-semibold leading-tight">{{ $c['title'] }}</h2>
                        </div>
                        <div class="prose-manual px-5 py-4 text-[0.95rem] text-stone-800">{!! $c['html'] !!}</div>
                    </section>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.app>
