# Form controls (`x-form.*`)

Blade + Alpine controls, one look, one contract. Live gallery (dev only, no sign-in): `/styleguide/forms`. Screenshots: `docs/screenshots/forms/`.

Files: `resources/views/components/form/*` (Blade), `resources/js/form/*` (Alpine; pure logic in `lib.js`), `resources/css/form.css`, `app/Support/Form/*` (`FormField`, `RuleControl`, `Format`), fixtures `resources/fixtures/rule-definitions.json`.

## The contract every control follows

| Prop | Meaning |
|---|---|
| `name` | Posted input name (`rules[hold_ttl_seconds]`). Errors are looked up under the dotted key (`rules.hold_ttl_seconds`), i.e. where the API's 422 `errors` land. |
| `wire:model[.live]` / `x-model` | Bind the value. Works through `x-modelable`, so it goes on the control like on any input. |
| `label`, `hint`, `required`, `optional`, `disabled`, `readonly`, `loading`, `error`, `error-key`, `id`, `bare` | Shell: label, help text, required marker, states, explicit error text, no chrome (nested use). |
| `meta` | `['description'=>, 'badges'=>[['text','tone']], 'default'=>['text','value']]`, used by the rule renderer. |

Also built in: "Edited" dirty dot, reset-to-default, 44px targets, focus ring, `aria-*`, arrow-key support.

## Values (what a control emits)

| Control | Model / posted value |
|---|---|
| toggle | boolean; posts `1`/`0` |
| slider, percent, stepper | number |
| range, time-range, date-range | `{from,to}`; posts `name[from]`, `name[to]` |
| money | **decimal string** (`"1234.50"`), never a number; `:scale="4"` for API strings |
| duration | integer in `unit` (API unit, e.g. seconds); edited in min/h/d |
| time / date | `"HH:MM"` (24h) / `"YYYY-MM-DD"` |
| select, checkbox-group, tags | scalar or array; arrays post `name[]` |
| key-value | `[{key,value}]`; posts `name[<key>]` |
| weekly-hours | `{weekly:{mon:{open,intervals:[{from,to}]}...}, exceptions:[{date,label,open,from,to}]}`; posts one JSON input |
| password | never echoed back |

## Copy-paste

```blade
<x-form.toggle name="allow_open_tabs" label="Allow open tabs" description="Customers can pay later." :value="true" />
<x-form.slider wire:model="holdMinutes" label="Hold time" :min="5" :max="120" :step="5" unit=" min" :ticks="5" :snap="[15,30,60]" />
<x-form.range wire:model="band" label="Price band" :min="0" :max="100000" :step="1000" prefix="₦" format="thousands" :min-gap="5000" />
<x-form.money name="limit" label="Waiter cash limit" value="50000.00" :quick="['20000','50000']" />
<x-form.duration name="ttl" label="Hold time" unit="seconds" :value="600" :min="30" :max="86400" :slider="true" />
<x-form.radio-cards name="strategy" label="Offline booking" :options="[['value'=>'A','label'=>'Reserved pool','description'=>'…','icon'=>'pie']]" value="A" />
<x-form.segmented name="slot" label="Slot" :options="['30'=>'30 min','60'=>'60 min']" value="60" />
<x-form.select wire:model="city" label="City" :options="$cities" :multiple="true" :creatable="true" loader="cities" />
<x-form.weekly-hours wire:model="hours" label="Opening hours" />
<x-form.file wire:model="invoice" label="Invoice" accept=".pdf,image/*" :max-size="5" />
```

Async select: `window.R007Forms.loaders.cities = async (q, {signal}) => [{value, label}]`, or `endpoint="/lookup?"` (GET `?q=`). Create-new fires a bubbling `f-create` event (`detail.resolve(id, label)`).

### Save bar, dirty tracking, danger

```blade
<form wire:submit="save"> … controls … <x-form.actions submit="Save rules" /> </form>
```
`x-form.actions` shows "N unsaved changes", swallows a second submit while saving, Discard reverts every control. **Livewire: `$this->dispatch('form-saved')` after a successful save** (re-baselines the controls). Typed confirmation: `<x-form.confirm phrase="DISABLE PAYMENTS" wire="disable"><x-slot:trigger>…</x-slot:trigger></x-form.confirm>`.

## Schema renderer

```blade
<x-form.schema :definitions="$defs" :values="$values" name="rules" :options="['payment_facility_unit_id'=>$facilities]" :capabilities="$enabled" :search="true" />
{{-- Livewire: wire="rules" (:live="true" for wire:model.live); high-impact changes bind `confirm` --}}
```
Definitions are the `GET /organization/rule-definitions` items. Rules are grouped into sections by `group`; only rules of enabled `capabilities` show. Changing a **high** danger rule reveals an acknowledgement (posts `confirm=1`) and blocks submit until ticked (API: `danger_confirmation_required`). Optional backend hints: `control`, `step`, `snap[]`, `quick[]`, `multiline`, `allowed[].description|icon`.

| Definition | Control |
|---|---|
| `bool` | toggle |
| `enum`, ≤4 options, any with description, or >2 with long labels | radio-cards |
| `enum`, ≤4 short options | segmented |
| `enum`, >4 options | select (searchable when >10) |
| `multi_enum` ≤8 / more | checkbox-group / multi select |
| `number` with `allowed` | segmented (≤7) or select |
| `number` unit `percent`, or type `percent` | percent (slider + number) |
| `number`, span ≤10 | stepper |
| `number`, span 11–1000 | slider + number box |
| `number`, span >1000 or unbounded | stepper (editable) |
| `duration` | duration (unit switcher, slider when small enough) |
| `money` | money (₦, 4 dp on the wire) |
| `time` / `string` / `facility` | time / text or textarea (>120 chars) / select |

Each rule shows label, plain-language description, danger badge (medium/high), "Not enforced yet" for `enforcement: planned`, `Default: …` with **Reset to default**, and its server error.

## Gotchas (learned the hard way)

- **Keep the x-data text stable.** Alpine wipes a component whose x-data changed on a Livewire re-render. `FormField::cfg()` omits `value` when a model owns it and moves disabled/readonly to `data-*`. Do not add volatile values to it.
- Nested controls (`x-model` on a child) are evaluated in the child's scope, where `value` is the child's: bridge with plain properties + watchers, not getters.
- Money in JS is string/BigInt only (`lib.js`); never `parseFloat` an amount.
- Tests: `php artisan test` (render, Livewire, renderer) and `npm run test:js` (slider math, money, time, dates).
