<?php

namespace App\Support\Form;

use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\ComponentAttributeBag;

/**
 * Everything the x-form.* components share: a stable DOM id, the validation messages for the field (Laravel's error bag, which is
 * where the API's 422 `errors` land), the accessibility wiring (aria-describedby) and the JSON config handed to the Alpine component.
 * Built once per control by FormField::resolve(); the Blade shell (x-form.field) only reads it.
 */
final class FormField
{
    /** @var list<string> */
    public array $messages;

    private function __construct(
        public readonly string $id,
        public readonly ?string $name,
        public readonly ?string $key,
        public readonly ?string $label,
        public readonly ?string $hint,
        public readonly bool $required,
        public readonly bool $optional,
        public readonly bool $disabled,
        public readonly bool $readonly,
        public readonly bool $bare,
        public readonly bool $loading,
        public readonly bool $modelBound,
        public readonly mixed $value,
        /** @var array<string, mixed> */
        public readonly array $meta,
        ?string $error,
    ) {
        $this->messages = $error !== null && $error !== '' ? [$error] : $this->lookup();
    }

    /** @param  array<string, mixed>  $v  get_defined_vars() of the component view */
    public static function resolve(ComponentAttributeBag $attributes, array $v): self
    {
        $name = self::str($v['name'] ?? null);
        $wire = $attributes->whereStartsWith('wire:model')->first();
        $wire = is_string($wire) && $wire !== '' ? $wire : null;
        $key = self::str($v['errorKey'] ?? null) ?? ($name !== null ? self::dot($name) : $wire);
        $id = self::str($v['id'] ?? null) ?? 'f-'.Str::slug(str_replace(['[', ']', '.'], '-', $name ?? $wire ?? ''), '-').($name === null && $wire === null ? Str::random(6) : '');

        return new self(
            id: $id, name: $name, key: $key, label: self::str($v['label'] ?? null), hint: self::str($v['hint'] ?? null),
            required: (bool) ($v['required'] ?? false), optional: (bool) ($v['optional'] ?? false),
            disabled: (bool) ($v['disabled'] ?? false), readonly: (bool) ($v['readonly'] ?? false),
            bare: (bool) ($v['bare'] ?? false), loading: (bool) ($v['loading'] ?? false),
            modelBound: $attributes->whereStartsWith(['wire:model', 'x-model'])->isNotEmpty(),
            value: $v['value'] ?? null, meta: is_array($v['meta'] ?? null) ? $v['meta'] : [],
            error: self::str($v['error'] ?? null),
        );
    }

    /** contact[phone] -> contact.phone (the dotted key Laravel and the API use for field errors). */
    public static function dot(string $name): string
    {
        return trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    }

    private static function str(mixed $v): ?string
    {
        return is_string($v) && $v !== '' ? $v : (is_int($v) || is_float($v) ? (string) $v : null);
    }

    /** @return list<string> */
    private function lookup(): array
    {
        if ($this->key === null) {
            return [];
        }
        $bag = view()->shared('errors');
        if (! $bag instanceof ViewErrorBag) {
            return [];
        }
        $out = [];
        foreach ($bag->getBag('default')->messages() as $field => $messages) {
            if ($field === $this->key || str_starts_with($field, $this->key.'.')) {
                array_push($out, ...$messages);
            }
        }

        return array_values(array_unique($out));
    }

    public function invalid(): bool
    {
        return $this->messages !== [];
    }

    public function message(): ?string
    {
        return $this->messages === [] ? null : implode(' ', $this->messages);
    }

    /** ids for aria-describedby: description, hint and error text, when present. */
    public function describedBy(): string
    {
        return trim(
            (! empty($this->meta['description']) ? $this->id.'-desc ' : '')
            .($this->hint ? $this->id.'-hint ' : '')
            .($this->invalid() ? $this->id.'-error' : '')
        );
    }

    /**
     * Normalise options given as ['value' => 'Label'], ['a', 'b'] or [['value'=>..,'label'=>..,'description'=>..,'icon'=>..,'disabled'=>..,'badge'=>..,'group'=>..]]
     * into the list shape every choice control uses. Values keep their type (int stays int) so the model round-trips.
     *
     * @param  iterable<mixed>|null  $options
     * @return list<array{value: mixed, label: string, description: ?string, icon: ?string, disabled: bool, badge: ?string, group: ?string}>
     */
    public static function options(?iterable $options): array
    {
        $out = [];
        $options = is_array($options) ? $options : ($options === null ? [] : iterator_to_array($options));
        $isList = array_is_list($options);
        foreach ($options as $k => $o) {
            if (is_array($o)) {
                $value = $o['value'] ?? $k;
                $out[] = [
                    'value' => $value, 'label' => (string) ($o['label'] ?? $value), 'description' => $o['description'] ?? null,
                    'icon' => $o['icon'] ?? null, 'disabled' => (bool) ($o['disabled'] ?? false), 'badge' => $o['badge'] ?? null, 'group' => $o['group'] ?? null,
                ];
            } else {
                $out[] = ['value' => $isList ? $o : $k, 'label' => (string) $o, 'description' => null, 'icon' => null, 'disabled' => false, 'badge' => null, 'group' => null];
            }
        }

        return $out;
    }

    /**
     * JSON config for the Alpine component: control-specific keys plus the shared ones (name, initial value, default, danger).
     *
     * The x-data text must be STABLE across server re-renders: Alpine re-initialises a component whose x-data expression changed
     * (Livewire morph), wiping its state. So nothing that changes while a person types goes in here: `value` is left out when a
     * wire:model / x-model owns it (it arrives through x-modelable), and disabled / readonly / invalid travel as data-* attributes.
     *
     * @param  array<string, mixed>  $specific
     */
    public function cfg(array $specific = []): array
    {
        return array_replace([
            'id' => $this->id,
            'name' => $this->name,
            'value' => $this->modelBound ? null : $this->value,
            'default' => $this->meta['default']['value'] ?? null,
            'hasDefault' => array_key_exists('default', $this->meta),
            'danger' => $this->meta['danger'] ?? null,
        ], $specific);
    }

    /** `fSlider({...})` as an x-data string. */
    public function xData(string $component, array $specific = []): string
    {
        return $component.'('.Js::from($this->cfg($specific)).')';
    }
}
