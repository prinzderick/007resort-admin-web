import { same } from './lib.js';

export const clone = (v) => (v === undefined ? null : JSON.parse(JSON.stringify(v)));

/**
 * State every control shares: `value` (the model, bound out through x-modelable so wire:model / x-model work), the baseline it
 * is compared with (`initial`), the dirty flag, reset-to-default and a client-side error line.
 */
export function core(cfg = {}) {
    return {
        cfg,
        id: cfg.id,
        name: cfg.name,
        value: clone(cfg.value ?? null),
        initial: clone(cfg.value ?? null),
        // disabled / readonly live on the shell's data-* attributes (not in x-data), so a server re-render can flip them without
        // changing the x-data expression: Alpine re-initialises a component whose x-data text changed, wiping its state.
        rev: 0,
        get disabled() {
            this.rev;
            return !!cfg.disabled || this.$el?.getAttribute?.('data-disabled') === 'true';
        },
        get readonly() {
            this.rev;
            return !!cfg.readonly || this.$el?.getAttribute?.('data-readonly') === 'true';
        },
        hasDefault: !!cfg.hasDefault,
        defaultValue: clone(cfg.default ?? null),
        localError: null,
        ready: false,

        get dirty() {
            return !same(this.value, this.initial);
        },
        get atDefault() {
            return !this.hasDefault || same(this.value, this.defaultValue);
        },
        get canEdit() {
            return !this.disabled && !this.readonly;
        },

        baseline() {
            this.initial = clone(this.value);
        },
        revert() {
            this.value = clone(this.initial);
        },
        resetDefault() {
            if (!this.canEdit) return;
            this.value = clone(this.defaultValue);
            this.afterReset?.();
        },

        /** Derived state (unit, clamped start, normalised structure) from the current value; controls override. Must be idempotent. */
        hydrateOnce() {
            if (this._hv === this.value && this._hv !== undefined) return;
            this.hydrate?.();
            this._hv = this.value;
        },

        init() {
            this._mo = new MutationObserver(() => this.rev++);
            this._mo.observe(this.$el, { attributes: true, attributeFilter: ['data-disabled', 'data-readonly'] });
            this.hydrateOnce();
            // The outer binding (wire:model / x-model) pushes its value in right after init (a microtask), so the baseline is taken one
            // macrotask later. With wire:model the value is NOT in the x-data text (it would change on every render and reset us).
            this._boot = setTimeout(() => {
                this.hydrateOnce();
                this.baseline();
                this.ready = true;
            }, 0);
            this.$watch('dirty', (dirty) => this.$dispatch('f-dirty', { id: this.id, name: this.name, dirty, danger: cfg.danger || null }));
            // Tell classic listeners (dirtyGuard, wire) that the control changed.
            this.$watch('value', () => {
                if (this.ready) this.$el.dispatchEvent(new CustomEvent('f-change', { bubbles: true, detail: { id: this.id, name: this.name, value: this.value } }));
            });
            this._saved = () => this.baseline();
            window.addEventListener('form-saved', this._saved);
        },
        destroy() {
            clearTimeout(this._boot);
            this._mo?.disconnect();
            window.removeEventListener('form-saved', this._saved);
            this._cleanup?.();
        },
    };
}

/** Merge property descriptors (keeps getters live) and chain init/destroy so a control extends core without replacing it. */
export function mix(base, ext) {
    const chained = {};
    for (const k of ['init', 'destroy']) {
        if (base[k] && ext[k]) {
            const a = base[k];
            const b = ext[k];
            chained[k] = function () {
                a.call(this);
                b.call(this);
            };
        }
    }
    const out = Object.defineProperties({}, Object.getOwnPropertyDescriptors(base));
    Object.defineProperties(out, Object.getOwnPropertyDescriptors(ext));
    Object.assign(out, chained);
    return out;
}

export function define(Alpine, name, factory) {
    Alpine.data(name, (cfg = {}) => mix(core(cfg), factory(cfg)));
}

/** Debounce helper for async option loading and live validation. */
export function debounce(fn, ms) {
    let t;
    return (...a) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...a), ms);
    };
}

let uid = 0;
export const nextId = (p = 'f') => `${p}-${++uid}`;
