@php use App\Support\Cms\SettingsGroups; @endphp
{{-- Weekly opening hours: a closed switch and open/close times for each day, plus dated exceptions (holidays). Posts hours[weekly][DAY][open|close|closed] and hours[holidays][n][...]. --}}
<div x-data="{ h: @js($hoursState), copyAll(from) { const src = this.h.weekly[from]; Object.keys(this.h.weekly).forEach(d => { this.h.weekly[d] = { ...src }; }); $dispatch('cms-change'); },
        addHoliday() { this.h.holidays.push({ date: '', label: '', open: '10:00', close: '18:00', closed: false }); $dispatch('cms-change'); }, removeHoliday(i) { this.h.holidays.splice(i, 1); $dispatch('cms-change'); } }" data-testid="hours-editor">
    <div class="overflow-x-auto"><table class="w-full text-sm">
        <thead><tr class="text-left text-xs font-semibold uppercase tracking-wide text-stone-500"><th class="py-2 pr-3">Day</th><th class="px-3 py-2">Closed all day</th><th class="px-3 py-2">Opens</th><th class="px-3 py-2">Closes</th><th class="py-2 pl-3"><span class="sr-only">Shortcut</span></th></tr></thead>
        <tbody class="divide-y divide-stone-100">
            @foreach (SettingsGroups::DAYS as $d => $name)
                <tr data-testid="day-{{ $d }}">
                    <td class="py-2 pr-3 font-medium">{{ $name }}</td>
                    <td class="px-3 py-2"><button type="button" role="switch" class="f-toggle" :aria-checked="h.weekly.{{ $d }}.closed ? 'true' : 'false'" aria-label="{{ $name }} closed all day" @click="h.weekly.{{ $d }}.closed = !h.weekly.{{ $d }}.closed; $dispatch('cms-change')" @if (! $canManage) disabled @endif><span class="f-switch"></span></button></td>
                    <td class="px-3 py-2"><div class="w-36" x-show="!h.weekly.{{ $d }}.closed"><x-form.time bare x-model="h.weekly.{{ $d }}.open" label="{{ $name }} opens" :step="15" :disabled="! $canManage" /></div><span class="text-stone-400" x-show="h.weekly.{{ $d }}.closed" x-cloak>Closed</span></td>
                    <td class="px-3 py-2"><div class="w-36" x-show="!h.weekly.{{ $d }}.closed"><x-form.time bare x-model="h.weekly.{{ $d }}.close" label="{{ $name }} closes" :step="15" :allow24="true" :disabled="! $canManage" /></div></td>
                    <td class="py-2 pl-3 text-right">@if ($canManage && $d === 'MON')<button type="button" class="f-link" @click="copyAll('MON')">Use Monday for every day</button>@endif</td>
                    <input type="hidden" name="hours[weekly][{{ $d }}][open]" :value="h.weekly.{{ $d }}.open"><input type="hidden" name="hours[weekly][{{ $d }}][close]" :value="h.weekly.{{ $d }}.close"><input type="hidden" name="hours[weekly][{{ $d }}][closed]" :value="h.weekly.{{ $d }}.closed ? 1 : 0">
                </tr>
            @endforeach
        </tbody>
    </table></div>
    @php $err = collect($errors->keys())->filter(fn ($k) => str_starts_with($k, 'hours.'))->map(fn ($k) => $errors->first($k))->unique()->values(); @endphp
    @foreach ($err as $m)<p class="f-error mt-2" role="alert">{{ $m }}</p>@endforeach

    <div class="mt-6 border-t border-stone-100 pt-5">
        <div class="flex flex-wrap items-center justify-between gap-2"><div><h3 class="text-sm font-semibold">Holidays and special days</h3><p class="text-xs text-stone-500">Days that differ from the weekly hours, such as Christmas. They replace the normal hours on that date.</p></div>@if ($canManage)<button type="button" class="f-btn" data-size="sm" @click="addHoliday()" data-testid="add-holiday"><x-icon name="plus" class="size-4" /> Add a special day</button>@endif</div>
        <p class="mt-3 rounded-lg border border-dashed border-stone-300 px-4 py-3 text-sm text-stone-500" x-show="!h.holidays.length">No special days yet.</p>
        <ul class="mt-3 space-y-3">
            <template x-for="(hol, i) in h.holidays" :key="i">
                <li class="grid items-end gap-3 rounded-lg border border-stone-200 p-3 md:grid-cols-[10rem_minmax(0,1fr)_8rem_8rem_auto_auto]" data-testid="holiday-row">
                    <div><label class="f-label mb-1 block">Date</label><input type="date" x-model="hol.date" :name="'hours[holidays][' + i + '][date]'" class="min-h-10 w-full rounded-lg border border-stone-300 px-2 text-sm" :disabled="{{ $canManage ? 'false' : 'true' }}"></div>
                    <div><label class="f-label mb-1 block">Name</label><input type="text" x-model="hol.label" :name="'hours[holidays][' + i + '][label]'" maxlength="80" placeholder="Christmas Day" class="min-h-10 w-full rounded-lg border border-stone-300 px-2 text-sm"></div>
                    <div><label class="f-label mb-1 block">Opens</label><input type="time" x-model="hol.open" :name="'hours[holidays][' + i + '][open]'" :disabled="hol.closed" class="min-h-10 w-full rounded-lg border border-stone-300 px-2 text-sm disabled:bg-stone-100"></div>
                    <div><label class="f-label mb-1 block">Closes</label><input type="time" x-model="hol.close" :name="'hours[holidays][' + i + '][close]'" :disabled="hol.closed" class="min-h-10 w-full rounded-lg border border-stone-300 px-2 text-sm disabled:bg-stone-100"></div>
                    <label class="flex min-h-10 items-center gap-2 text-sm"><input type="checkbox" x-model="hol.closed" class="size-4 accent-brand-600"> Closed</label>
                    <input type="hidden" :name="'hours[holidays][' + i + '][closed]'" :value="hol.closed ? 1 : 0">
                    @if ($canManage)<button type="button" class="f-link" @click="removeHoliday(i)" :aria-label="'Remove special day ' + (i + 1)">Remove</button>@endif
                </li>
            </template>
        </ul>
    </div>

    <div class="mt-6 border-t border-stone-100 pt-5"><x-cms.field :spec="\App\Support\Cms\SettingsGroups::all()['hours']['fields'][0]" :values="['hours[notes]' => $values['hours']['notes'] ?? '']" :can-edit="$canManage" /></div>
</div>
