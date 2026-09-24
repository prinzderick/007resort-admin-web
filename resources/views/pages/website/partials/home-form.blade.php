{{-- Add / edit form for one homepage block. $type, $t (definition), $item (or null), $media map, $canManage, $dialog (dialog name). --}}
@php
    use App\Support\Cms\Cms;
    use App\Support\Form\FormField;
    $payload = (array) ($item['payload'] ?? []);
    $vals = [];
    $mediaFor = [];
    $fInit = [];
    foreach ($t['fields'] as $spec) {
        $vals[$spec['name']] = $payload[$spec['key']] ?? null;
        $mediaFor[$spec['name']] = $spec['type'] === 'image' ? Cms::media($payload[$spec['key']] ?? null, $media) : null;
        $fInit[$spec['name']] = $spec['type'] === 'image' ? ($payload[$spec['key']] ?? '') : (string) ($payload[$spec['key']] ?? ($spec['default'] ?? ''));
        $fInit[$spec['name'].':url'] = '';
    }
    $isEdit = $item !== null;
@endphp
<form method="POST" action="{{ $isEdit ? route('website.homepage.update', $item['id']) : route('website.homepage.store') }}" novalidate x-data="{ f: @js($fInit) }" data-testid="{{ $isEdit ? 'edit-form' : 'add-form' }}">
    @csrf @if ($isEdit)@method('PATCH')<input type="hidden" name="rowVersion" value="{{ $item['rowVersion'] ?? '' }}">@endif
    <input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="_dialog" value="{{ $dialog }}">
    <div class="grid gap-5 {{ ($t['preview'] ?? false) ? 'lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]' : '' }}">
        <div class="grid content-start gap-4">
            @foreach ($t['fields'] as $spec)
                <x-cms.field :spec="$spec" :values="$vals" :media="$mediaFor" :can-edit="$canManage" />
            @endforeach
        </div>
        @if ($t['preview'] ?? false)
            <div class="lg:sticky lg:top-0" data-testid="hero-preview">
                <div class="t-label mb-1">Live preview</div>
                <div class="relative aspect-video overflow-hidden rounded-xl bg-stone-700 bg-cover bg-center" :style="f['payload[mediaId]:url'] ? 'background-image:url(\'' + f['payload[mediaId]:url'] + '\')' : ''">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/25 to-black/10"></div>
                    <div class="absolute inset-0 flex flex-col justify-end gap-2 p-5 text-white" :class="{ 'items-start text-left': (f['payload[alignment]'] || 'LEFT') === 'LEFT', 'items-center text-center': f['payload[alignment]'] === 'CENTER', 'items-end text-right': f['payload[alignment]'] === 'RIGHT' }">
                        <div class="text-xl font-semibold leading-tight drop-shadow" x-text="f['payload[headline]'] || 'Your headline'"></div>
                        <div class="max-w-[85%] text-xs text-white/90" x-show="f['payload[subheadline]']" x-text="f['payload[subheadline]']"></div>
                        <span class="mt-1 inline-flex rounded-full bg-brand-600 px-3.5 py-1.5 text-xs font-semibold" x-show="f['payload[ctaLabel]']" x-text="f['payload[ctaLabel]']"></span>
                    </div>
                </div>
                <p class="mt-2 text-xs text-stone-500">Roughly how it looks on a computer. On phones the text is smaller.</p>
            </div>
        @endif
    </div>
    <div class="mt-5 flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn>@if ($canManage)<x-btn data-testid="save-block">{{ $isEdit ? 'Save changes' : 'Add '.$t['one'] }}</x-btn>@endif</div>
</form>
