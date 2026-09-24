@php
    use App\Support\Cms\SettingsGroups;
    $keys = array_keys($defs);
    $tabErr = fn ($g) => collect($errors->keys())->contains(fn ($k) => str_starts_with($k, $g.'.'));
    // Live values for the previews (announcement bar, search snippet, map).
    $f = [];
    foreach ($defs as $g => $d) {
        foreach ($d['fields'] as $fld) {
            if (! in_array($fld['type'], ['image'], true)) {
                $v = old("{$g}.{$fld['key']}", $values[$g][$fld['key']] ?? ($fld['default'] ?? ($fld['type'] === 'toggle' ? false : '')));
                $f[$fld['name']] = is_bool($v) ? $v : (string) ($v ?? '');
            }
        }
    }
    $hoursState = old('hours.weekly') ? ['weekly' => collect(old('hours.weekly'))->map(fn ($r) => ['open' => $r['open'] ?? '', 'close' => $r['close'] ?? '', 'closed' => filter_var($r['closed'] ?? false, FILTER_VALIDATE_BOOLEAN)])->all(), 'holidays' => array_values((array) old('hours.holidays', []))] : $hours;
@endphp
<x-cms.layout title="Site settings">
    <x-page-header title="Site settings" subtitle="The details that appear on every page of the website. Change what you need, then press Save at the bottom." :crumbs="['Website' => route('website.index'), 'Site settings' => null]" />
    <x-fetch :of="$loaded" what="Site settings" />
    @unless ($canManage)<x-alert tone="info">You can view the settings but not change them. Editing needs the "cms.manage" permission.</x-alert>@endunless

    @if ($loaded->ok())
    <div x-data="cmsTabs('brand', @js($keys))" data-testid="settings">
        <div role="tablist" aria-label="Settings sections" class="mb-5 flex flex-wrap gap-1 border-b border-stone-200 text-sm" @keydown="key($event)">
            @foreach ($defs as $g => $d)
                <button type="button" role="tab" id="tab-{{ $g }}" x-ref="tab-{{ $g }}" :aria-selected="tab === '{{ $g }}' ? 'true' : 'false'" :tabindex="tab === '{{ $g }}' ? 0 : -1" aria-controls="panel-{{ $g }}" @click="go('{{ $g }}')"
                        class="-mb-px flex min-h-11 items-center gap-1.5 border-b-2 px-3 py-3" :class="tab === '{{ $g }}' ? 'border-brand-600 font-semibold text-brand-700' : 'border-transparent text-stone-600 hover:text-stone-900'" data-testid="tab-{{ $g }}">
                    {{ $d['label'] }}@if ($tabErr($g))<span class="size-2 rounded-full bg-red-600" title="This tab has a problem"></span><span class="sr-only">(has errors)</span>@endif
                </button>
            @endforeach
        </div>

        <div x-data="{ f: @js($f) }">
        <form method="POST" action="{{ route('website.settings.update') }}" x-data="cmsForm" novalidate data-testid="settings-form">
            @csrf @method('PUT')
            @foreach ($versions as $g => $v)<input type="hidden" name="versions[{{ $g }}]" value="{{ $v }}">@endforeach

            @foreach ($defs as $g => $d)
                <section id="panel-{{ $g }}" role="tabpanel" aria-labelledby="tab-{{ $g }}" x-show="tab === '{{ $g }}'" @if ($g !== 'brand') x-cloak @endif data-tab-panel="{{ $g }}">
                    <x-card :title="$d['label']" :subtitle="$d['blurb']">
                        @if ($g === 'hours')
                            @include('pages.website.partials.settings-hours')
                        @else
                            <div class="grid gap-5 {{ in_array($g, ['contact', 'social', 'booking']) ? 'md:grid-cols-2' : '' }}">
                                @foreach ($d['fields'] as $spec)
                                    @php
                                        $spec['name'] = $spec['name'];
                                        $vals = [$spec['name'] => $values[$g][$spec['key']] ?? null];
                                        $mediaFor = [$spec['name'] => \App\Support\Cms\Cms::media($values[$g][$spec['key']] ?? null, $media)];
                                    @endphp
                                    <div class="{{ in_array($spec['type'], ['textarea', 'image']) && in_array($g, ['contact']) ? 'md:col-span-2' : '' }}"><x-cms.field :spec="$spec" :values="$vals" :media="$mediaFor" :can-edit="$canManage" /></div>
                                @endforeach
                            </div>
                            @if ($g === 'contact')
                                <div class="mt-5" x-show="/^https:\/\//.test(f['contact[mapEmbedUrl]'] || '')" x-cloak><div class="t-label mb-1">Map preview</div><iframe :src="/^https:\/\//.test(f['contact[mapEmbedUrl]'] || '') ? f['contact[mapEmbedUrl]'] : 'about:blank'" class="h-56 w-full rounded-lg border border-stone-200" loading="lazy" referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin" title="Map preview"></iframe></div>
                            @endif
                            @if ($g === 'seo')
                                <div class="mt-5"><div class="t-label mb-1">Home page in search results</div><x-cms.snippet title-key="f['seo[defaultTitle]']" fallback-title="f['brand[name]']" desc-key="f['seo[defaultDescription]']" fallback-desc="''" path="" /></div>
                            @endif
                            @if ($g === 'announcement')
                                <div class="mt-5" data-testid="announcement-preview">
                                    <div class="t-label mb-1">Preview</div>
                                    <div x-show="f['announcement[enabled]']" class="flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-center text-sm font-medium"
                                         :class="{ 'bg-sky-100 text-sky-900': f['announcement[tone]'] === 'INFO', 'bg-brand-600 text-white': f['announcement[tone]'] === 'PROMO', 'bg-amber-100 text-amber-950': f['announcement[tone]'] === 'WARNING' }">
                                        <span x-text="f['announcement[text]'] || 'Your message appears here'"></span>
                                        <span class="underline" x-show="f['announcement[link]']" x-text="'Learn more'"></span>
                                    </div>
                                    <p class="rounded-lg border border-dashed border-stone-300 px-4 py-2.5 text-center text-sm text-stone-500" x-show="!f['announcement[enabled]']" x-cloak>The bar is switched off, so visitors see nothing at the top of the page.</p>
                                </div>
                            @endif
                        @endif
                    </x-card>
                </section>
            @endforeach

            @if ($canManage)<x-form.actions submit="Save settings" cancel="Discard changes" />@endif
        </form>
        </div>
    </div>
    @endif
</x-cms.layout>
