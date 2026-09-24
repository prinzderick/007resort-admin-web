@props(['f'])
{{-- Badges (danger level, "not enforced yet") and the "Edited" dot: shown in the label row, or inline for controls that draw their own title. --}}
@foreach ($f->meta['badges'] ?? [] as $b)<span class="f-badge" data-tone="{{ $b['tone'] ?? 'neutral' }}" @if (! empty($b['title'])) title="{{ $b['title'] }}" @endif>{{ $b['text'] }}</span>@endforeach
<span class="f-dirty" x-show="$data.dirty" x-cloak>Edited</span>
