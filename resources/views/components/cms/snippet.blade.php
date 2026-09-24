@props(['titleKey' => 'f.seoTitle', 'fallbackTitle' => 'f.title', 'descKey' => 'f.seoDescription', 'fallbackDesc' => "f.excerpt || f.summary || ''", 'path' => '/'])
{{-- Search-result preview (what Google will roughly show). Reads the surrounding form's live values (x-data f). --}}
<div class="rounded-lg border border-stone-200 bg-white p-4" data-testid="seo-snippet" aria-label="Search result preview">
    <div class="text-xs text-stone-500">{{ preg_replace('#^https?://#', '', rtrim(\App\Support\Cms\Cms::siteUrl(''), '/')) ?: 'yoursite.example' }}<span x-text="'{{ $path }}' + (f.slug || '')"></span></div>
    <div class="mt-0.5 truncate text-lg leading-snug text-[#1a0dab]" x-text="(({{ $titleKey }} || {{ $fallbackTitle }}) || 'Page title').slice(0, 70)"></div>
    <div class="mt-0.5 line-clamp-2 text-sm text-stone-600" x-text="(({{ $descKey }} || {{ $fallbackDesc }}) || 'Add a short description so visitors know what to expect.').slice(0, 200)"></div>
    <div class="mt-2 text-xs" :class="(({{ $titleKey }} || '').length > 60 || ({{ $descKey }} || '').length > 160) ? 'text-amber-700' : 'text-stone-400'">Titles over about 60 letters and descriptions over about 160 get cut off in search results.</div>
</div>
