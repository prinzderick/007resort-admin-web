{{-- Preview of the next five dates an event will happen (Lagos time), updating as the schedule fields change. --}}
<div class="mt-5 rounded-lg border border-stone-200 bg-stone-50 p-4" x-data="cmsRecurrence()" x-effect="start = String(f.startsAt || '').slice(0, 10); time = String(f.startsAt || '').slice(11, 16); endTime = String(f.endsAt || '').slice(11, 16); recurrence = String(f.recurrence || 'NONE').toLowerCase(); until = f.recurrenceUntil || ''" data-testid="occurrences">
    <div class="t-label mb-2">Next dates</div>
    <template x-if="!start"><p class="text-sm text-stone-500">Choose the start date to see when this event will happen.</p></template>
    <template x-if="start && !list.length"><p class="text-sm text-stone-500">No upcoming dates: the first date is in the past or the repeat ends before today.</p></template>
    <ol class="space-y-1 text-sm" x-show="list.length"><template x-for="(d, i) in list" :key="d"><li class="flex items-center gap-2"><span class="flex size-5 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-800" x-text="i + 1"></span><span x-text="fmt(d)"></span><span class="text-stone-500" x-show="time" x-text="'at ' + t12(time) + (endTime ? ' to ' + t12(endTime) : '')"></span></li></template></ol>
    <p class="mt-2 text-xs text-stone-500" x-show="recurrence === 'weekly' && !until">It repeats every week with no end date. Set "Repeat until" to stop it.</p>
</div>
