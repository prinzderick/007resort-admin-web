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
        disabled: !!cfg.disabled,
        readonly: !!cfg.readonly,
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

        init() {
            // The outer binding (wire:model / x-model) has pushed its value in by the next tick: that is the baseline.
            this.$nextTick(() => {
                this.baseline();
                this.ready = true;
            });
            this.$watch('dirty', (dirty) => this.$dispatch('f-dirty', { id: this.id, name: this.name, dirty, danger: cfg.danger || null }));
            // Tell classic listeners (dirtyGuard, wire) that the control changed.
            this.$watch('value', () => {
                if (this.ready) this.$el.dispatchEvent(new CustomEvent('f-change', { bubbles: true, detail: { id: this.id, name: this.name, value: this.value } }));
            });
            this._saved = () => this.baseline();
            window.addEventListener('form-saved', this._saved);
        },
        destroy() {
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
