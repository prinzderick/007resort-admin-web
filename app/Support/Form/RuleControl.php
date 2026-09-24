<?php

namespace App\Support\Form;

/**
 * Maps one backend rule definition (GET /api/v1/organization/rule-definitions item) to the right x-form.* control.
 *
 * Definition shape (see 007resort-api docs/CONFIG_ADMIN_API.md):
 *   key, label, description, group, type (bool|enum|multi_enum|number|duration|money|percent|time|string|facility), capability, capabilities[],
 *   default, unit, allowed[{value,label,description?,icon?}], min, max, step?, integer, dangerLevel (low|medium|high), enforcement (server|client|planned).
 * Optional UI hints the backend MAY add (all ignored when absent): control (force a control), step, snap[], quick[] (money shortcuts), multiline, ui.control.
 *
 * Mapping table (first match wins):
 *   bool                                   -> toggle
 *   enum      <= 4 options, any has description, or > 2 options with long labels -> radio-cards
 *   enum      <= 4 short options            -> segmented
 *   enum      >  4 options                  -> select (searchable when > 10)
 *   multi_enum <= 8 options                 -> checkbox-group;  more -> select (multiple)
 *   number with `allowed` list              -> segmented (<= 7) | select
 *   number unit "percent"/"%" or type percent -> percent (slider 0-100 + number)
 *   number, span (max-min) <= 10            -> stepper
 *   number, 10 < span <= 1000               -> slider + number box (ticks, unit)
 *   number, span > 1000 or unbounded        -> stepper (editable number)
 *   duration                                -> duration (number + unit switcher, slider when the range is small enough)
 *   money                                   -> money (₦, decimal string, 4 dp on the wire)
 *   time                                    -> time
 *   string                                  -> text (textarea when max length > 120 or multiline)
 *   facility                                -> select (options supplied by the page)
 */
final class RuleControl
{
    public const CONTROLS = ['toggle', 'radio-cards', 'segmented', 'select', 'checkbox-group', 'percent', 'stepper', 'slider', 'duration', 'money', 'time', 'text', 'textarea'];

    private const ICONS = [
        'PAY_FIRST' => 'bolt', 'PAY_AFTER_SERVICE' => 'clipboard', 'OPEN_TAB' => 'layers', 'PAY_BEFORE_LEAVING' => 'shield', 'PAY_ON_EXIT' => 'store', 'PAY_AT_RECEPTION' => 'card',
        'A_OFFLINE_ALLOCATION' => 'pie', 'B_ONLINE_AUTHORITY_REQUIRED' => 'lock', 'C_DISABLE_ONLINE' => 'ban',
        'SEND' => 'bolt', 'SETTLE' => 'cash', 'NONE' => 'ban', 'CASH_ONLY' => 'cash', 'ALL' => 'card',
        'ENTRY' => 'check', 'ENTRY_EXIT' => 'refresh', 'RELEASE_RETURN' => 'layers',
    ];

    private const UNIT_SECONDS = ['seconds' => 1, 'minutes' => 60, 'hours' => 3600, 'days' => 86400];

    /**
     * @param  array<string, mixed>  $def
     * @return array{control: string, props: array<string, mixed>, default: mixed, defaultText: string, badges: list<array<string, string>>, options: list<array<string, mixed>>}
     */
    public static function describe(array $def, ?string $force = null): array
    {
        $type = strtolower((string) ($def['type'] ?? 'string'));
        $unit = isset($def['unit']) && $def['unit'] !== '' ? (string) $def['unit'] : null;
        $min = self::num($def['min'] ?? null);
        $max = self::num($def['max'] ?? null);
        $hasDefault = array_key_exists('default', $def) && $def['default'] !== null;
        $default = $def['default'] ?? null;
        $options = self::options($def['allowed'] ?? null);
        $force ??= $def['control'] ?? ($def['ui']['control'] ?? null);

        if ($type === 'number' && in_array($unit, ['percent', '%'], true)) {
            $type = 'percent';
        }

        $control = 'text';
        $props = [];
        switch (true) {
            case $type === 'bool':
                $control = 'toggle';
                $default = $hasDefault ? filter_var($default, FILTER_VALIDATE_BOOLEAN) : null;
                break;

            case $type === 'enum':
                $n = count($options);
                $long = collect($options)->contains(fn ($o) => mb_strlen($o['label']) > 16);
                $described = collect($options)->contains(fn ($o) => ! empty($o['description']));
                $control = $n > 6 ? 'select' : ($described || ($n > 2 && $long) ? 'radio-cards' : ($n > 4 ? 'select' : 'segmented'));
                $props = ['options' => $options] + ($control === 'select' ? ['searchable' => $n > 10] : []);
                break;

            case $type === 'multi_enum':
                $control = count($options) <= 8 ? 'checkbox-group' : 'select';
                $props = ['options' => $options] + ($control === 'select' ? ['multiple' => true] : []);
                $default = is_array($default) ? array_values($default) : [];
                break;

            case $type === 'facility':
                $control = 'select';
                $props = ['options' => $options, 'placeholder' => 'Choose a facility'];
                break;

            case $type === 'number' && $options !== []:
                $control = count($options) <= 7 ? 'segmented' : 'select';
                $props = ['options' => $options];
                break;

            case $type === 'percent':
                $control = 'percent';
                $props = ['min' => $min ?? 0, 'max' => $max ?? 100, 'step' => $def['step'] ?? (! empty($def['integer']) ? 1 : 0.5)];
                break;

            case $type === 'number':
                $step = $def['step'] ?? 1;
                $span = $min !== null && $max !== null ? $max - $min : null;
                if ($span !== null && $span > 10 && $span <= 1000) {
                    $control = 'slider';
                    $props = ['min' => $min, 'max' => $max, 'step' => $step, 'unit' => $unit, 'ticks' => 5, 'snap' => $def['snap'] ?? []];
                } else {
                    $control = 'stepper';
                    $props = ['min' => $min, 'max' => $max, 'step' => $step, 'unit' => $unit, 'nullable' => ! $hasDefault];
                }
                break;

            case $type === 'duration':
                $control = 'duration';
                $base = isset(self::UNIT_SECONDS[$unit]) ? $unit : 'seconds';
                $props = [
                    'unit' => $base, 'units' => self::durationUnits($base, $min, $max, $default), 'min' => $min, 'max' => $max,
                    'integer' => $def['integer'] ?? true, 'slider' => $min !== null && $max !== null, 'nullable' => ! $hasDefault,
                ];
                break;

            case $type === 'money':
                $control = 'money';
                $scale = is_string($default) && str_contains($default, '.') ? max(2, strlen(explode('.', $default)[1])) : 4;
                $props = ['scale' => $scale, 'currency' => $unit === 'NGN' || $unit === null ? '₦' : $unit.' ', 'quick' => $def['quick'] ?? [],
                    'min' => isset($def['min']) ? (string) $def['min'] : null, 'max' => isset($def['max']) ? (string) $def['max'] : null];
                $default = $hasDefault ? (string) $default : null;
                break;

            case $type === 'time':
                $control = 'time';
                $props = ['allow24' => true];
                break;

            default: // string
                $long = ! empty($def['multiline']) || ($max !== null && $max > 120);
                $control = $long ? 'textarea' : 'text';
                $props = ['maxlength' => $max !== null ? (int) $max : null] + ($long ? ['rows' => 3, 'counter' => $max !== null] : []);
        }

        if ($force !== null && in_array($force, self::CONTROLS, true) && $force !== $control) {
            $control = $force;
            $props = self::propsFor($force, $props, $options, $min, $max, $unit, $def);
        }

        return [
            'control' => $control,
            'props' => $props,
            'default' => $default,
            'defaultText' => 'Default: '.self::defaultText($type, $control, $default, $options, $unit, $hasDefault),
            'badges' => self::badges($def),
            'options' => $options,
        ];
    }

    /** Re-derive props when a caller forces a control. */
    private static function propsFor(string $control, array $old, array $options, ?float $min, ?float $max, ?string $unit, array $def): array
    {
        return match ($control) {
            'slider' => ['min' => $min ?? 0, 'max' => $max ?? 100, 'step' => $def['step'] ?? 1, 'unit' => $unit, 'ticks' => 5],
            'stepper' => ['min' => $min, 'max' => $max, 'step' => $def['step'] ?? 1, 'unit' => $unit],
            'percent' => ['min' => $min ?? 0, 'max' => $max ?? 100],
            'segmented', 'radio-cards', 'select', 'checkbox-group' => ['options' => $options],
            default => $old,
        };
    }

    /** @return list<string> */
    private static function durationUnits(string $base, ?float $min, ?float $max, mixed $default): array
    {
        $baseSize = self::UNIT_SECONDS[$base];
        $maxSeconds = $max !== null ? $max * $baseSize : null;
        $out = [];
        foreach (self::UNIT_SECONDS as $u => $size) {
            if ($size < $baseSize || ($maxSeconds !== null && $size > $maxSeconds)) {
                continue;
            }
            if ($u === 'seconds' && $base === 'seconds') {
                $fine = ($min !== null && fmod($min, 60) !== 0.0) || (is_numeric($default) && fmod((float) $default, 60) !== 0.0) || ($maxSeconds !== null && $maxSeconds < 600);
                if (! $fine) {
                    continue;
                }
            }
            $out[] = $u;
        }

        return $out ?: [$base];
    }

    /** @return list<array<string, string>> */
    private static function badges(array $def): array
    {
        $out = [];
        $level = $def['dangerLevel'] ?? 'low';
        if ($level === 'high') {
            $out[] = ['text' => 'High impact', 'tone' => 'high', 'title' => 'Changes money, stock or security controls. You will be asked to confirm.'];
        } elseif ($level === 'medium') {
            $out[] = ['text' => 'Medium impact', 'tone' => 'medium', 'title' => 'Changes how staff or customers work. Review before saving.'];
        }
        if (($def['enforcement'] ?? null) === 'planned') {
            $out[] = ['text' => 'Not enforced yet', 'tone' => 'info', 'title' => 'Saved and synced, but no app reads this yet.'];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public static function options(mixed $allowed): array
    {
        $out = [];
        $allowed = is_array($allowed) ? $allowed : [];
        $isList = array_is_list($allowed);
        foreach ($allowed as $k => $a) {
            $a = is_array($a) ? $a : ['value' => $isList ? $a : $k, 'label' => $a];
            $value = $a['value'] ?? $k;
            $out[] = [
                'value' => $value, 'label' => (string) ($a['label'] ?? $value), 'description' => $a['description'] ?? null,
                'icon' => $a['icon'] ?? (is_string($value) ? (self::ICONS[$value] ?? null) : null), 'badge' => $a['badge'] ?? null, 'disabled' => (bool) ($a['disabled'] ?? false),
            ];
        }

        return $out;
    }

    private static function num(mixed $v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }

    private static function defaultText(string $type, string $control, mixed $default, array $options, ?string $unit, bool $hasDefault): string
    {
        if (! $hasDefault && $type !== 'bool') {
            return 'not set';
        }

        return match (true) {
            $type === 'bool' => $default ? 'On' : 'Off',
            in_array($type, ['enum', 'number', 'facility'], true) && $options !== [] => collect($options)->first(fn ($o) => (string) $o['value'] === (string) $default)['label'] ?? (string) $default,
            $type === 'multi_enum' => $default === [] || $default === null ? 'none' : collect($options)->filter(fn ($o) => in_array($o['value'], (array) $default, true))->pluck('label')->implode(', '),
            $type === 'money' => '₦'.Format::money((string) $default, 2),
            $type === 'duration' => Format::duration((float) $default * (self::UNIT_SECONDS[$unit] ?? 1)),
            $type === 'percent' => (str_contains((string) $default, '.') ? rtrim(rtrim((string) $default, '0'), '.') : (string) $default).'%',
            $type === 'time' => Format::time12((string) $default),
            $type === 'number' => $default.($unit ? ' '.$unit : ''),
            default => $default === '' ? 'empty' : (string) $default,
        };
    }
}
