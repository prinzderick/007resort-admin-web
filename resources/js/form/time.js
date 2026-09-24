import { define, clone, mix } from './core.js';
import * as L from './lib.js';

const outsideClose = (self) => {
    self.open = false;
};

export function register(Alpine) {
    // ------------------------------------------------------------------------------------------------ time of day
    define(Alpine, 'fTime', (cfg) => {
        const step = cfg.step || 30;
        const lo = L.timeToMinutes(cfg.min || '00:00');
        const hi = L.timeToMinutes(cfg.max || (cfg.allow24 ? '24:00' : '23:59'));
        const twelve = cfg.format !== '24';
        const show = (v) => (!v ? '' : twelve ? L.formatTime12(v) : v === '24:00' ? '24:00' : v);
        return {
            step, open: false, text: '', active: -1,
            get times() {
                const out = [];
                for (let m = Math.ceil(lo / step) * step; m <= hi; m += step) out.push(L.minutesToTime(m));
                return out;
            },
            show,
            hydrate() {
                this.text = show(this.value);
            },
            init() {
                this.$watch('value', (v) => (this.text = show(v)));
            },
            commit() {
                if (this.text.trim() === '') {
                    this.value = cfg.nullable === false ? this.value : null;
                    this.text = show(this.value);
                    return;
                }
                const t = L.parseTime(this.text);
                if (t === null || (t === '24:00' && !cfg.allow24)) {
                    this.localError = 'Enter a time like 9:30 AM or 21:30.';
                    return;
                }
                const m = L.timeToMinutes(t);
                if (m < lo || m > hi) {
                    this.localError = `Between ${show(L.minutesToTime(lo))} and ${show(L.minutesToTime(hi))}.`;
                    return;
                }
                this.localError = null;
                this.value = t;
                this.text = show(t);
            },
            openList() {
                if (!this.canEdit) return;
                this.open = true;
                const i = this.times.indexOf(this.value);
                this.active = i >= 0 ? i : Math.max(0, this.times.findIndex((t) => L.timeToMinutes(t) >= (L.timeToMinutes(this.value) ?? 540)));
                this.$nextTick(() => this.scrollActive());
            },
            close() {
                this.open = false;
            },
            choose(t) {
                this.value = t;
                this.text = show(t);
                this.localError = null;
                this.open = false;
            },
            scrollActive() {
                this.$refs.list?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' });
            },
            key(e) {
                if (!this.canEdit) return;
                const n = this.times.length;
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (!this.open) return this.openList();
                    this.active = (this.active + (e.key === 'ArrowDown' ? 1 : -1) + n) % n;
                    this.$nextTick(() => this.scrollActive());
                } else if (e.key === 'Enter' && this.open && this.active >= 0) {
                    e.preventDefault();
                    this.choose(this.times[this.active]);
                } else if (e.key === 'Escape' && this.open) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.close();
                } else if (e.key === 'Enter' && !this.open) this.commit();
                else if (e.key === 'Tab') this.close();
            },
        };
    });

    // ------------------------------------------------------------------------------------------------ time range (open - close)
    define(Alpine, 'fTimeRange', (cfg) => ({
        // Plain properties (not getters): nested <x-form.time x-model="from"> is evaluated in its OWN scope, where a getter's `this.value`
        // would resolve to the child's value. Watchers keep from/to and value in step.
        from: null,
        to: null,
        value: cfg.value ?? { from: null, to: null },
        initial: cfg.value ?? { from: null, to: null },
        get span() {
            const m = L.spanMinutes(this.from, this.to, !!cfg.overnight);
            return m === null ? '' : m < 0 ? '' : L.humanDuration(m * 60);
        },
        get overnight() {
            return !!cfg.overnight && this.from && this.to && L.timeToMinutes(this.to) <= L.timeToMinutes(this.from);
        },
        hydrate() {
            if (!this.value || typeof this.value !== 'object') this.value = { from: null, to: null };
            this.from = this.value.from ?? null;
            this.to = this.value.to ?? null;
            this.check();
        },
        init() {
            this.$watch('value', (v) => {
                if ((v?.from ?? null) !== this.from) this.from = v?.from ?? null;
                if ((v?.to ?? null) !== this.to) this.to = v?.to ?? null;
                this.check();
            });
            this.$watch('from', (f) => {
                if ((this.value?.from ?? null) !== f) this.value = { from: f, to: this.value?.to ?? null };
            });
            this.$watch('to', (t) => {
                if ((this.value?.to ?? null) !== t) this.value = { from: this.value?.from ?? null, to: t };
            });
        },
        check() {
            const { from, to } = this.value || {};
            if (!from || !to) return void (this.localError = null);
            const m = L.spanMinutes(from, to, !!cfg.overnight);
            if (m <= 0) this.localError = 'Closing time must be after opening time.';
            else if (cfg.minSpan && m < cfg.minSpan) this.localError = `Must be at least ${L.humanDuration(cfg.minSpan * 60)} long.`;
            else this.localError = null;
        },
    }));

    // ------------------------------------------------------------------------------------------------ weekly hours
    define(Alpine, 'fWeek', (cfg) => {
        const blankDay = () => ({ open: false, intervals: [] });
        const dflt = () => clone(cfg.defaultInterval || { from: '09:00', to: '17:00' });
        return {
            days: L.DAYS, labels: L.DAY_LABELS, copyOpen: null, notice: '',
            // Never null: templates read value.weekly[d] before an outer wire:model has pushed its value in.
            value: cfg.value ?? { weekly: Object.fromEntries(L.DAYS.map((d) => [d, blankDay()])), exceptions: [] },
            /** Alias of `value` for x-model paths on NESTED controls (their own scope has a `value` too, which would shadow ours). */
            week: null,
            hydrate() {
                const v = this.value && typeof this.value === 'object' ? this.value : {};
                const weekly = {};
                for (const d of L.DAYS) {
                    const src = v.weekly?.[d] || {};
                    weekly[d] = { open: !!src.open, intervals: Array.isArray(src.intervals) ? src.intervals.map((i) => ({ from: i.from ?? null, to: i.to ?? null })) : [] };
                    if (weekly[d].open && weekly[d].intervals.length === 0) weekly[d].intervals.push(dflt());
                }
                this.value = { weekly, exceptions: Array.isArray(v.exceptions) ? v.exceptions.map((e) => ({ date: e.date ?? null, label: e.label ?? '', open: !!e.open, from: e.from ?? null, to: e.to ?? null })) : [] };
                this.week = this.value;
                this.validate();
            },
            init() {
                this.$watch('value', (v) => {
                    this.week = v;
                    this.validate();
                });
            },
            /** Reassign so x-modelable / wire:model see a new reference after in-place edits. */
            touch() {
                this.value = { weekly: this.value.weekly, exceptions: this.value.exceptions };
            },
            toggleDay(d) {
                if (!this.canEdit) return;
                const day = this.value.weekly[d];
                day.open = !day.open;
                if (day.open && day.intervals.length === 0) day.intervals.push(dflt());
                this.touch();
            },
            addInterval(d) {
                if (!this.canEdit) return;
                const list = this.value.weekly[d].intervals;
                const last = list[list.length - 1];
                const start = last?.to && last.to !== '24:00' ? last.to : '18:00';
                const end = L.minutesToTime(Math.min(1439, L.timeToMinutes(start) + 180));
                list.push({ from: start, to: end });
                this.touch();
            },
            removeInterval(d, i) {
                if (!this.canEdit) return;
                const day = this.value.weekly[d];
                day.intervals.splice(i, 1);
                if (day.intervals.length === 0) day.open = false;
                this.touch();
            },
            copy(from, scope) {
                if (!this.canEdit) return;
                const targets = { weekdays: ['mon', 'tue', 'wed', 'thu', 'fri'], weekend: ['sat', 'sun'], all: L.DAYS }[scope] || [];
                const next = L.copyDay(this.value.weekly, from, targets);
                // In place, so the nested controls' `week.weekly[d]` paths keep pointing at the live objects.
                for (const d of targets) {
                    this.value.weekly[d].open = next[d].open;
                    this.value.weekly[d].intervals = next[d].intervals;
                }
                this.touch();
                this.copyOpen = null;
                this.notice = `Copied ${this.labels[from]}'s hours to ${scope === 'all' ? 'every day' : scope === 'weekdays' ? 'all weekdays' : 'the weekend'}.`;
            },
            errors: {},
            validate() {
                const out = {};
                for (const d of L.DAYS) {
                    const day = this.value.weekly[d];
                    if (day.open) {
                        const e = L.validateIntervals(day.intervals, { overnight: !!cfg.overnight });
                        if (e.length) out[d] = e;
                    }
                }
                this.errors = out;
                const ex = this.value.exceptions.some((e) => !e.date);
                this.localError = Object.keys(out).length ? 'Fix the highlighted days before saving.' : ex ? 'Every exception needs a date.' : null;
            },
            addException() {
                if (!this.canEdit) return;
                this.value.exceptions.push({ date: null, label: '', open: false, from: '10:00', to: '16:00' });
                this.touch();
            },
            removeException(i) {
                if (!this.canEdit) return;
                this.value.exceptions.splice(i, 1);
                this.touch();
            },
            get summary() { return L.summariseWeek(this.value.weekly); },
        };
    });

    // ------------------------------------------------------------------------------------------------ calendar (date + date-range)
    const calendar = (cfg) => {
        const today = () => L.isoDate(new Date());
        return {
            open: false, view: { y: 0, m: 0 }, focusIso: null, today: today(), weekStart: 1,
            dows: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            get tabIso() {
                const sel = typeof this.value === 'string' ? this.value : this.value?.from;
                const prefix = `${this.view.y}-${L.pad2(this.view.m + 1)}`;
                return [this.focusIso, sel, this.today].find((i) => i && i.startsWith(prefix)) || `${prefix}-01`;
            },
            get monthLabel() { return `${L.MONTHS[this.view.m]} ${this.view.y}`; },
            get cells() { return L.monthGrid(this.view.y, this.view.m, this.weekStart); },
            setView(iso) {
                const d = L.parseIso(iso) || new Date();
                this.view = { y: d.getFullYear(), m: d.getMonth() };
            },
            shift(n) {
                const d = new Date(this.view.y, this.view.m + n, 1);
                this.view = { y: d.getFullYear(), m: d.getMonth() };
            },
            disabledDay(iso) {
                return (cfg.min && iso < cfg.min) || (cfg.max && iso > cfg.max) || false;
            },
            moveFocus(e, iso) {
                const d = L.parseIso(iso);
                const delta = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[e.key];
                let next = null;
                if (delta) next = L.addDays(d, delta);
                else if (e.key === 'PageDown') next = new Date(d.getFullYear(), d.getMonth() + 1, d.getDate());
                else if (e.key === 'PageUp') next = new Date(d.getFullYear(), d.getMonth() - 1, d.getDate());
                else if (e.key === 'Home') next = L.addDays(d, -((d.getDay() + 7 - this.weekStart) % 7));
                else if (e.key === 'End') next = L.addDays(d, 6 - ((d.getDay() + 7 - this.weekStart) % 7));
                if (!next) return;
                e.preventDefault();
                this.focusIso = L.isoDate(next);
                if (next.getMonth() !== this.view.m || next.getFullYear() !== this.view.y) this.view = { y: next.getFullYear(), m: next.getMonth() };
                this.$nextTick(() => this.$refs.cal?.querySelector(`[data-iso="${this.focusIso}"]`)?.focus());
            },
            focusStart(iso) {
                this.$nextTick(() => (this.$refs.cal?.querySelector(`[data-iso="${iso}"]`) || this.$refs.cal?.querySelector('[data-iso]:not([disabled])'))?.focus());
            },
        };
    };

    define(Alpine, 'fDate', (cfg) => mix(calendar(cfg), {
        text: '',
        hydrate() {
            this.text = L.formatDate(this.value);
            this.setView(this.value || this.today);
        },
        init() {
            this.$watch('value', (v) => (this.text = L.formatDate(v)));
        },
        openCal() {
            if (!this.canEdit) return;
            this.setView(this.value || this.today);
            this.open = true;
            this.focusStart(this.value || this.today);
        },
        closeCal() {
            this.open = false;
        },
        pick(iso) {
            if (this.disabledDay(iso)) return;
            this.value = iso;
            this.localError = null;
            this.open = false;
            this.$nextTick(() => this.$refs.input?.focus());
        },
        clear() {
            if (!this.canEdit) return;
            this.value = null;
            this.text = '';
            this.localError = null;
        },
        commit() {
            if (this.text.trim() === '') {
                this.value = null;
                this.localError = cfg.required ? 'Choose a date.' : null;
                return;
            }
            const iso = L.parseDateInput(this.text);
            if (!iso) return void (this.localError = 'Enter a date like 24 Sep 2026 or 24/09/2026.');
            if (this.disabledDay(iso)) return void (this.localError = `Choose a date${cfg.min ? ' from ' + L.formatDate(cfg.min) : ''}${cfg.max ? ' up to ' + L.formatDate(cfg.max) : ''}.`);
            this.localError = null;
            this.value = iso;
            this.text = L.formatDate(iso);
        },
        cellState(c) {
            return { selected: c.iso === this.value, today: c.iso === this.today, disabled: this.disabledDay(c.iso) };
        },
        key(e) {
            if (e.key === 'ArrowDown' && !this.open) {
                e.preventDefault();
                this.openCal();
            } else if (e.key === 'Escape' && this.open) {
                e.stopPropagation();
                this.closeCal();
            }
        },
    }));

    define(Alpine, 'fDateRange', (cfg) => {
        const presets = (cfg.presets || []).map((p) => ({ key: p.key, label: p.label }));
        return mix(calendar(cfg), {
            presets,
            hover: null,
            value: cfg.value ?? { from: null, to: null },
            initial: cfg.value ?? { from: null, to: null },
            hydrate() {
                if (!this.value || typeof this.value !== 'object') this.value = { from: null, to: null };
                this.setView(this.value.from || this.today);
                this.check();
            },
            init() {
                this.$watch('value', () => this.check());
            },
            get label() {
                const { from, to } = this.value || {};
                if (!from && !to) return '';
                if (from && to && from === to) return L.formatDate(from);
                return `${L.formatDate(from)} - ${to ? L.formatDate(to) : '...'}`;
            },
            get anchor() { return this.value?.from && !this.value?.to ? this.value.from : null; },
            check() {
                this.localError = L.validateDateRange(this.value?.from, this.value?.to, { min: cfg.min, max: cfg.max, maxDays: cfg.maxDays });
            },
            openCal() {
                if (!this.canEdit) return;
                this.setView(this.value?.from || this.today);
                this.open = true;
                this.focusStart(this.value?.from || this.today);
            },
            closeCal() {
                this.open = false;
                this.hover = null;
            },
            pick(iso) {
                if (this.disabledDay(iso)) return;
                if (!this.anchor) {
                    this.value = { from: iso, to: null };
                    return;
                }
                const [a, b] = iso < this.anchor ? [iso, this.anchor] : [this.anchor, iso];
                this.value = { from: a, to: b };
                this.hover = null;
                if (!L.validateDateRange(a, b, { min: cfg.min, max: cfg.max, maxDays: cfg.maxDays })) this.open = false;
            },
            preset(key) {
                if (!this.canEdit) return;
                const r = L.presetRange(key);
                if (!r) return;
                this.value = r;
                this.setView(r.from);
                this.open = false;
            },
            activePreset() {
                return this.presets.find((p) => {
                    const r = L.presetRange(p.key);
                    return r && r.from === this.value?.from && r.to === this.value?.to;
                })?.key;
            },
            clear() {
                if (this.canEdit) this.value = { from: null, to: null };
            },
            inRange(iso) {
                const from = this.value?.from;
                const to = this.value?.to || (this.anchor && this.hover ? this.hover : null);
                if (!from || !to) return false;
                const [a, b] = from <= to ? [from, to] : [to, from];
                return iso >= a && iso <= b;
            },
            isEdge(iso) {
                return iso === this.value?.from || iso === this.value?.to;
            },
            key(e) {
                if (e.key === 'Escape' && this.open) {
                    e.stopPropagation();
                    this.closeCal();
                }
            },
        });
    });
}

export { outsideClose };
