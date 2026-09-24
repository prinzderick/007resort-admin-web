import { define } from './core.js';
import * as L from './lib.js';

const num = (v, d = null) => (v === null || v === undefined || v === '' || Number.isNaN(Number(v)) ? d : Number(v));

/** Shared value formatting for slider bubbles, range ends and tick labels. */
function formatter(cfg) {
    const d = cfg.decimals ?? L.decimals(cfg.step ?? 1);
    return (v) => {
        if (v === null || v === undefined || v === '') return '';
        let s;
        if (cfg.format === 'clock') s = L.minutesToTime(Number(v) * 60);
        else if (cfg.format === 'thousands') s = Number(v).toLocaleString('en-US', { maximumFractionDigits: d });
        else s = String(Number(Number(v).toFixed(d)));
        return cfg.bare ? s : `${cfg.prefix || ''}${s}${cfg.unit ? (cfg.unitGlue ?? '') + cfg.unit : ''}`;
    };
}

/** Track dragging shared by slider and range: pointer capture on window so the drag survives leaving the track. */
function drag(onMove, onEnd) {
    const move = (ev) => onMove(ev);
    const up = () => {
        window.removeEventListener('pointermove', move);
        window.removeEventListener('pointerup', up);
        window.removeEventListener('pointercancel', up);
        onEnd();
    };
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
    window.addEventListener('pointercancel', up);
}

export function register(Alpine) {
    // ------------------------------------------------------------------------------------------------ toggle
    define(Alpine, 'fToggle', (cfg) => ({
        value: !!cfg.value,
        initial: !!cfg.value,
        defaultValue: cfg.default === null || cfg.default === undefined ? null : !!cfg.default,
        toggle() {
            if (this.canEdit) this.value = !this.value;
        },
    }));

    // ------------------------------------------------------------------------------------------------ stepper
    define(Alpine, 'fStepper', (cfg) => {
        const min = num(cfg.min, -Infinity);
        const max = num(cfg.max, Infinity);
        const step = num(cfg.step, 1);
        const anchor = Number.isFinite(min) ? min : 0;
        return {
            min, max, step, text: '', holdTimer: null, holdInt: null,
            init() {
                this.text = this.show(this.value);
                this.$watch('value', (v) => {
                    if (this.text !== this.show(v)) this.text = this.show(v);
                });
            },
            show(v) {
                return v === null || v === undefined || v === '' ? '' : String(v);
            },
            nudge(dir) {
                if (!this.canEdit) return;
                const cur = num(this.value, cfg.emptyAs ?? anchor);
                this.value = L.clamp(L.roundToStep(cur + dir * step, step, anchor), min, max);
            },
            hold(dir) {
                if (!this.canEdit) return;
                this.nudge(dir);
                this.holdTimer = setTimeout(() => (this.holdInt = setInterval(() => this.nudge(dir), 70)), 420);
            },
            release() {
                clearTimeout(this.holdTimer);
                clearInterval(this.holdInt);
            },
            commit() {
                if (String(this.text).trim() === '') {
                    if (cfg.nullable) this.value = null;
                    else this.text = this.show(this.value);
                    return;
                }
                const n = Number(String(this.text).replace(/,/g, ''));
                if (Number.isNaN(n)) {
                    this.localError = 'Enter a number.';
                    this.text = this.show(this.value);
                    return;
                }
                this.localError = n < min || n > max ? `Between ${min} and ${max}.` : null;
                this.value = L.clamp(L.roundToStep(n, step, anchor), min, max);
                this.text = this.show(this.value);
            },
            key(e) {
                if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.nudge(e.key === 'ArrowUp' ? 1 : -1);
                }
            },
            get atMin() { return num(this.value, anchor) <= min; },
            get atMax() { return num(this.value, anchor) >= max; },
        };
    });

    // ------------------------------------------------------------------------------------------------ slider
    define(Alpine, 'fSlider', (cfg) => {
        const min = num(cfg.min, 0);
        const max = num(cfg.max, 100);
        const step = num(cfg.step, 1);
        const points = cfg.snap || [];
        const fmt = formatter(cfg);
        const tickLabel = formatter({ ...cfg, bare: true });
        return {
            min, max, step, fmt, tickLabel, points, active: false, text: '', typing: false,
            ticksList: L.ticks(min, max, cfg.ticks || 0),
            init() {
                const start = num(this.value, num(cfg.default, min));
                this.value = L.clamp(L.roundToStep(start, step, min), min, max);
                this.text = String(this.value);
                this.$watch('value', (v) => {
                    if (!this.typing) this.text = String(v);
                });
            },
            get pos() { return L.pct(num(this.value, min), min, max); },
            get label() { return fmt(num(this.value, min)); },
            snapTo(raw) {
                return L.snap(raw, { min, max, step, points, tolerance: cfg.snapTolerance ?? null });
            },
            fromEvent(e) {
                const r = this.$refs.track.getBoundingClientRect();
                return this.snapTo(L.valueAt((e.clientX - r.left) / r.width, min, max));
            },
            down(e) {
                if (!this.canEdit || (e.button !== undefined && e.button !== 0)) return;
                e.preventDefault();
                this.active = true;
                this.value = this.fromEvent(e);
                this.$refs.thumb.focus({ preventScroll: true });
                drag((ev) => (this.value = this.fromEvent(ev)), () => (this.active = false));
            },
            key(e) {
                if (!this.canEdit) return;
                const v = L.keyValue(e.key, num(this.value, min), { min, max, step, shift: e.shiftKey });
                if (v !== null) {
                    e.preventDefault();
                    this.value = v;
                }
            },
            typeIn() {
                this.typing = true;
                const n = Number(String(this.text).replace(/[^0-9.\-]/g, ''));
                if (this.text !== '' && !Number.isNaN(n) && n >= min && n <= max) this.value = L.roundToStep(n, step, min);
            },
            commitText() {
                this.typing = false;
                const n = Number(String(this.text).replace(/[^0-9.\-]/g, ''));
                if (this.text === '' || Number.isNaN(n)) this.localError = 'Enter a number.';
                else {
                    this.localError = n < min || n > max ? `Between ${fmt(min)} and ${fmt(max)}.` : null;
                    this.value = L.clamp(L.roundToStep(n, step, min), min, max);
                }
                this.text = String(this.value);
            },
        };
    });

    // ------------------------------------------------------------------------------------------------ range (dual handle)
    define(Alpine, 'fRange', (cfg) => {
        const min = num(cfg.min, 0);
        const max = num(cfg.max, 100);
        const step = num(cfg.step, 1);
        const minGap = num(cfg.minGap, 0);
        const maxGap = cfg.maxGap === null || cfg.maxGap === undefined ? Infinity : Number(cfg.maxGap);
        const points = cfg.snap || [];
        const fmt = formatter(cfg);
        const tickLabel = formatter({ ...cfg, bare: true });
        const opts = (moved, push = false) => ({ min, max, step, minGap, maxGap, moved, push });
        return {
            min, max, step, fmt, tickLabel, points, minGap, active: null, texts: { from: '', to: '' }, top: 'to',
            ticksList: L.ticks(min, max, cfg.ticks || 0),
            init() {
                const v = this.value || {};
                const d = cfg.default || {};
                const r = L.constrainRange(num(v.from, num(d.from, min)), num(v.to, num(d.to, max)), opts('from'));
                this.value = { from: r.from, to: r.to };
                this.sync();
                this.$watch('value', () => this.sync());
            },
            sync() {
                if (!this.value) return;
                this.texts = { from: String(this.value.from), to: String(this.value.to) };
                this.localError = L.constrainRange(this.value.from, this.value.to, opts('from')).error;
            },
            pos(which) { return L.pct(this.value[which], min, max); },
            get fillStyle() {
                const a = this.pos('from');
                return `left:${a}%;width:${this.pos('to') - a}%`;
            },
            apply(which, raw) {
                const snapped = L.snap(raw, { min, max, step, points });
                const from = which === 'from' ? snapped : this.value.from;
                const to = which === 'to' ? snapped : this.value.to;
                const r = L.constrainRange(from, to, opts(which));
                this.value = { from: r.from, to: r.to };
                this.top = which;
            },
            fromEvent(e) {
                const rect = this.$refs.track.getBoundingClientRect();
                return L.valueAt((e.clientX - rect.left) / rect.width, min, max);
            },
            down(e, which = null) {
                if (!this.canEdit || (e.button !== undefined && e.button !== 0)) return;
                e.preventDefault();
                const raw = this.fromEvent(e);
                if (!which) {
                    if (this.value.from === this.value.to) which = raw < this.value.from ? 'from' : 'to';
                    else which = Math.abs(raw - this.value.from) <= Math.abs(raw - this.value.to) ? 'from' : 'to';
                }
                this.active = which;
                this.apply(which, raw);
                this.$refs[which].focus({ preventScroll: true });
                drag((ev) => this.apply(which, this.fromEvent(ev)), () => (this.active = null));
            },
            key(e, which) {
                if (!this.canEdit) return;
                const v = L.keyValue(e.key, this.value[which], { min, max, step, shift: e.shiftKey });
                if (v !== null) {
                    e.preventDefault();
                    this.apply(which, v);
                }
            },
            commitText(which) {
                const n = Number(String(this.texts[which]).replace(/[^0-9.\-]/g, ''));
                if (this.texts[which] !== '' && !Number.isNaN(n)) this.apply(which, L.clamp(n, min, max));
                this.sync();
            },
            get summary() { return `${fmt(this.value.from)} to ${fmt(this.value.to)}`; },
        };
    });

    // ------------------------------------------------------------------------------------------------ money (decimal strings only)
    define(Alpine, 'fMoney', (cfg) => {
        const scale = cfg.scale ?? 2;
        const show = (v) => L.formatMoney(v, Math.min(2, scale));
        const norm = (v) => L.normalizeMoney(v, scale, { allowNegative: true }) ?? '';
        return {
            scale, text: '', typing: false,
            init() {
                if (this.value !== null && this.value !== '') this.value = L.normalizeMoney(this.value, scale, { allowNegative: !!cfg.negative }) ?? this.value;
                this.text = show(this.value);
                this.$watch('value', (v) => {
                    if (!this.typing) this.text = show(v);
                });
            },
            get dirty() { return norm(this.value) !== norm(this.initial); },
            get atDefault() { return !this.hasDefault || norm(this.value) === norm(this.defaultValue); },
            onInput(e) {
                this.typing = true;
                const el = e.target;
                const caret = el.selectionStart ?? el.value.length;
                const before = el.value.slice(0, caret).replace(/[^0-9.]/g, '').length;
                const next = L.formatMoneyTyping(el.value, scale);
                this.text = next;
                el.value = next;
                let seen = 0;
                let pos = 0;
                while (pos < next.length && seen < before) {
                    if (/[0-9.]/.test(next[pos])) seen++;
                    pos++;
                }
                el.setSelectionRange(pos, pos);
                this.validate(next);
            },
            validate(raw) {
                const n = L.normalizeMoney(raw, scale, { allowNegative: !!cfg.negative });
                if (n === null) {
                    this.localError = 'Enter a valid amount.';
                    return null;
                }
                if (n === '') {
                    this.localError = cfg.required ? 'Enter an amount.' : null;
                    this.value = '';
                    return '';
                }
                if (cfg.min !== null && cfg.min !== undefined && L.compareMoney(n, cfg.min) < 0) this.localError = `Must be at least ${cfg.currency || ''}${L.formatMoney(cfg.min)}.`;
                else if (cfg.max !== null && cfg.max !== undefined && L.compareMoney(n, cfg.max) > 0) this.localError = `Must be at most ${cfg.currency || ''}${L.formatMoney(cfg.max)}.`;
                else this.localError = null;
                this.value = n; // always the decimal string, e.g. "1234500.00": never a number
                return n;
            },
            commit() {
                this.typing = false;
                const n = this.validate(this.text);
                this.text = show(n === null ? this.value : n);
            },
            key(e) {
                if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;
                e.preventDefault();
                if (!this.canEdit) return;
                this.typing = false;
                this.value = L.stepMoney(this.value, cfg.step ?? '1', e.key === 'ArrowUp' ? 1 : -1, scale, !!cfg.negative);
                this.text = show(this.value);
                this.validate(this.text);
            },
            quick(amount) {
                if (!this.canEdit) return;
                this.typing = false;
                this.value = L.normalizeMoney(amount, scale);
                this.text = show(this.value);
                this.localError = null;
            },
            get quickList() { return (cfg.quick || []).map((a) => ({ raw: a, label: L.formatMoney(a, 0) })); },
        };
    });

    // ------------------------------------------------------------------------------------------------ duration
    define(Alpine, 'fDuration', (cfg) => {
        const base = cfg.unit || 'seconds';
        const units = cfg.units || ['minutes', 'hours', 'days'];
        const fromBase = (v, u) => L.convertDuration(Number(v), base, u);
        const empty = (v) => v === null || v === undefined || v === '';
        return {
            base, units, u: units[0], amount: '', typing: false,
            // Plain property (a getter would see the nested slider's own `value` through the merged scope); kept in step by watchers.
            sliderModel: 0,
            init() {
                if (!empty(this.value)) this.u = L.bestUnit(L.toSeconds(Number(this.value), base), units).unit;
                this.amount = empty(this.value) ? '' : String(fromBase(this.value, this.u));
                this.syncSlider();
                this.$watch('value', (v) => {
                    this.syncSlider();
                    if (!this.typing) this.amount = empty(v) ? '' : String(Number(fromBase(v, this.u).toFixed(4)));
                });
                this.$watch('u', () => this.syncSlider());
                this.$watch('sliderModel', (n) => {
                    if (Number(n) !== this.sliderFromValue()) this.fromSlider(n);
                });
            },
            sliderFromValue() {
                return empty(this.value) ? this.sliderMin : Math.round(fromBase(this.value, this.u));
            },
            syncSlider() {
                this.sliderModel = this.sliderFromValue();
            },
            get human() { return empty(this.value) ? '' : L.humanDuration(L.toSeconds(Number(this.value), base)); },
            get sliderMin() { return Math.ceil(fromBase(cfg.min ?? 0, this.u)); },
            get sliderMax() { return Math.floor(fromBase(cfg.max ?? 0, this.u)); },
            get showSlider() {
                if (!cfg.slider || cfg.max === null || cfg.max === undefined) return false;
                const span = this.sliderMax - this.sliderMin;
                return span >= 2 && span <= 2000;
            },
            /** Convert a displayed amount into the base unit, honouring integer-only and min/max. */
            toBase(amount) {
                let v = L.convertDuration(amount, this.u, base);
                if (cfg.integer) v = Math.round(v);
                return v;
            },
            check(v) {
                if (cfg.min !== null && cfg.min !== undefined && v < cfg.min) return `At least ${L.humanDuration(L.toSeconds(cfg.min, base))}.`;
                if (cfg.max !== null && cfg.max !== undefined && v > cfg.max) return `At most ${L.humanDuration(L.toSeconds(cfg.max, base))}.`;
                return null;
            },
            typeIn() {
                this.typing = true;
                if (this.amount === '') return;
                const n = Number(this.amount);
                if (Number.isNaN(n)) return;
                const v = this.toBase(n);
                this.localError = this.check(v);
                if (!this.localError) this.value = v;
            },
            commit() {
                this.typing = false;
                const n = Number(this.amount);
                if (this.amount === '' || Number.isNaN(n)) {
                    if (cfg.nullable && this.amount === '') this.value = null;
                    this.amount = empty(this.value) ? '' : String(Number(fromBase(this.value, this.u).toFixed(4)));
                    return;
                }
                let v = this.toBase(n);
                v = Math.min(Math.max(v, cfg.min ?? -Infinity), cfg.max ?? Infinity);
                this.localError = null;
                this.value = v;
                this.amount = String(Number(fromBase(v, this.u).toFixed(4)));
            },
            switchUnit(next) {
                if (!this.canEdit) return;
                this.u = next;
                this.amount = empty(this.value) ? '' : String(Number(fromBase(this.value, next).toFixed(4)));
            },
            fromSlider(n) {
                if (!this.canEdit) return;
                this.typing = false;
                this.value = Math.min(Math.max(this.toBase(Number(n)), cfg.min ?? -Infinity), cfg.max ?? Infinity);
                this.localError = null;
            },
            key(e) {
                if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;
                e.preventDefault();
                if (!this.canEdit) return;
                this.typing = false;
                const cur = Number(this.amount || 0);
                const v = Math.min(Math.max(this.toBase(cur + (e.key === 'ArrowUp' ? 1 : -1)), cfg.min ?? -Infinity), cfg.max ?? Infinity);
                this.value = v;
                this.amount = String(Number(fromBase(v, this.u).toFixed(4)));
            },
        };
    });

    // ------------------------------------------------------------------------------------------------ choice controls (segmented, radio cards, checkbox group)
    define(Alpine, 'fChoice', (cfg) => {
        const start = cfg.multiple ? (Array.isArray(cfg.value) ? [...cfg.value] : []) : cfg.value ?? null;
        return {
            options: cfg.options || [],
            multiple: !!cfg.multiple,
            value: start,
            initial: cfg.multiple ? [...start] : start,
            isOn(v) {
                return this.multiple ? (this.value || []).some((x) => String(x) === String(v)) : this.value !== null && this.value !== undefined && String(this.value) === String(v);
            },
            pick(i) {
                const o = this.options[i];
                if (!this.canEdit || !o || o.disabled) return;
                if (!this.multiple) {
                    this.value = o.value;
                    return;
                }
                const cur = [...(this.value || [])];
                const at = cur.findIndex((x) => String(x) === String(o.value));
                if (at >= 0) {
                    if (cfg.min && cur.length <= cfg.min) return void (this.localError = `Keep at least ${cfg.min} selected.`);
                    cur.splice(at, 1);
                } else {
                    if (cfg.max && cur.length >= cfg.max) return void (this.localError = `Choose at most ${cfg.max}.`);
                    cur.push(o.value);
                }
                this.localError = null;
                this.value = this.options.map((x) => x.value).filter((v) => cur.some((c) => String(c) === String(v)));
            },
            get allOn() { return this.options.filter((o) => !o.disabled).every((o) => this.isOn(o.value)); },
            toggleAll() {
                if (!this.canEdit) return;
                this.value = this.allOn ? [] : this.options.filter((o) => !o.disabled).map((o) => o.value);
            },
            // Arrow keys move + select like a native radio group.
            arrow(e, i) {
                if (this.multiple || !this.canEdit) return;
                const dir = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[e.key];
                if (!dir) return;
                e.preventDefault();
                let n = i;
                do n = (n + dir + this.options.length) % this.options.length;
                while (this.options[n].disabled && n !== i);
                this.pick(n);
                this.$nextTick(() => this.$refs['opt' + n]?.focus());
            },
        };
    });
}
