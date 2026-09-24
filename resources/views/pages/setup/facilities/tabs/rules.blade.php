@if ($eff->ok())
    <x-fetch :of="$defs" what="The rule definitions" />
    @if ($defs->ok() && $defs->items() !== [])
        <p class="mb-4 max-w-3xl text-sm text-stone-600">These are the rules this facility runs by. Only rules for the features switched on under Capabilities are listed. Use "Reset to default" on any control to go back to the standard value. Changes apply to new activity straight away.</p>
        <form method="POST" action="{{ route('setup.facilities.rules', $id) }}" data-testid="rules-form" novalidate>
            @csrf @method('PUT')
            <input type="hidden" name="_rules_present" value="1">
            <x-form.schema :definitions="$defs->items()" :values="$ruleValues" name="rules" :capabilities="$ruleCapabilities" :options="$ruleOptions" :search="true" :disabled="! $canRules" />
            @if ($notApplicable !== [])<p class="f-hint mt-2">Hidden because this facility does not have the feature they need: {{ implode(', ', array_map(fn ($k) => $defMap[$k]['label'] ?? $k, array_slice($notApplicable, 0, 12))) }}{{ count($notApplicable) > 12 ? ' and '.(count($notApplicable) - 12).' more' : '' }}. Turn it on under Capabilities to see them.</p>@endif
            @if ($canRules)
                <x-form.actions submit="Save rules" />
            @else
                <x-pending-api :items="['You need the config.manage.rules permission to change operating rules.']" />
            @endif
        </form>
    @endif
@else
    <x-fetch :of="$eff" what="Operating rules" />
@endif
