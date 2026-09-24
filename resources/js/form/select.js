import { define, debounce, clone } from './core.js';
import * as L from './lib.js';

/**
 * Async option hook. Register a loader anywhere on the page and reference it by name from the component:
 *   window.R007Forms.loaders.facilities = async (query, { signal }) => [{ value: 'id', label: 'Main Bar' }];
 *   <x-form.select loader="facilities" ...>          (or endpoint="/lookup/facilities" for a plain GET ?q=)
 */
window.R007Forms = window.R007Forms || { loaders: {} };
window.R007Forms.loaders = window.R007Forms.loaders || {};

const asOption = (o) => (o && typeof o === 'object' ? { value: o.value ?? o.id, label: String(o.label ?? o.name ?? o.value ?? o.id), description: o.description ?? null, disabled: !!o.disabled, group: o.group ?? null } : { value: o, label: String(o), description: null, disabled: false, group: null });

export function register(Alpine) {
    // ------------------------------------------------------------------------------------------------ select (single / multi, searchable, async, create-new)
    define(Alpine, 'fSelect', (cfg) => {
        const multiple = !!cfg.multiple;
        const start = multiple ? (Array.isArray(cfg.value) ? [...cfg.value] : []) : cfg.value ?? null;
        return {
            multiple,
            value: start,
            initial: clone(start),
            options: (cfg.options || []).map(asOption),
            remote: [],
            made: [],
            open: false,
            query: '',
            active: 0,
            loading: false,
            loadError: null,
            loaded: false,
            get all() {
                const seen = new Set();
                return [...this.options, ...this.remote, ...this.made].filter((o) => (seen.has(String(o.value)) ? false : seen.add(String(o.value))));
            },
            get hasRemote() { return !!(cfg.endpoint || cfg.loader); },
            get list() {
                const q = this.query.trim().toLowerCase();
                const base = this.hasRemote ? [...this.remote, ...this.made, ...this.options] : this.all;
                const seen = new Set();
                const uniq = base.filter((o) => (seen.has(String(o.value)) ? false : seen.add(String(o.value))));
                const rows = q === '' || this.hasRemote ? uniq : uniq.filter((o) => `${o.label} ${o.description ?? ''}`.toLowerCase().includes(q));
                const out = rows.map((o) => ({ kind: 'option', ...o }));
                if (cfg.creatable && this.query.trim() !== '' && !this.all.some((o) => o.label.toLowerCase() === q)) out.push({ kind: 'create', label: this.query.trim(), value: null });
                return out;
            },
            find(v) {
                return this.all.find((o) => String(o.value) === String(v)) || { value: v, label: String(v), description: null };
            },
            get chosen() {
                if (multiple) return (this.value || []).map((v) => this.find(v));
                return this.value === null || this.value === '' ? [] : [this.find(this.value)];
            },
            get summary() { return this.chosen.length ? this.chosen.map((o) => o.label).join(', ') : ''; },
            isOn(v) { return multiple ? (this.value || []).some((x) => String(x) === String(v)) : this.value !== null && String(this.value) === String(v); },
            init() {
                if (cfg.preload && this.hasRemote) this.load('');
                this._search = debounce((q) => this.load(q), cfg.debounce ?? 250);
                this.$watch('query', (q) => {
                    this.active = 0;
                    if (this.hasRemote) this._search(q);
                });
            },
            openList() {
                if (!this.canEdit) return;
                this.open = true;
                this.active = Math.max(0, this.list.findIndex((o) => o.kind === 'option' && this.isOn(o.value)));
                if (this.hasRemote && !this.loaded) this.load(this.query);
                this.$nextTick(() => {
                    this.$refs.search?.focus({ preventScroll: true });
                    this.scrollActive();
                });
            },
            close(focusTrigger = false) {
                this.open = false;
                this.query = '';
                if (focusTrigger) this.$nextTick(() => this.$refs.trigger?.focus());
            },
            toggleList() { this.open ? this.close(true) : this.openList(); },
            scrollActive() { this.$refs.listbox?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' }); },
            async load(q) {
                this.loadError = null;
                this._abort?.abort();
                this._abort = new AbortController();
                this.loading = true;
                try {
                    let rows;
                    const loader = cfg.loader && window.R007Forms.loaders[cfg.loader];
                    if (loader) rows = await loader(q, { signal: this._abort.signal });
                    else {
                        const url = new URL(cfg.endpoint, window.location.origin);
                        url.searchParams.set(cfg.queryParam || 'q', q);
                        const res = await fetch(url, { headers: { Accept: 'application/json' }, signal: this._abort.signal, credentials: 'same-origin' });
                        if (!res.ok) throw new Error(`HTTP ${res.status}`);
                        const body = await res.json();
                        rows = Array.isArray(body) ? body : body.items || body.data || [];
                    }
                    this.remote = (rows || []).map(asOption);
                    this.loaded = true;
                } catch (e) {
                    if (e.name === 'AbortError') return;
                    this.loadError = 'Could not load options.';
                } finally {
                    this.loading = false;
                }
            },
            choose(row) {
                if (!this.canEdit) return;
                if (row.kind === 'create') return this.create(row.label);
                if (row.disabled) return;
                if (multiple) {
                    const cur = [...(this.value || [])];
                    const at = cur.findIndex((x) => String(x) === String(row.value));
                    if (at >= 0) cur.splice(at, 1);
                    else if (cfg.max && cur.length >= cfg.max) return void (this.localError = `Choose at most ${cfg.max}.`);
                    else cur.push(row.value);
                    this.localError = null;
                    this.value = cur;
                    this.query = '';
                } else {
                    this.value = row.value;
                    this.close(true);
                }
            },
            create(label) {
                const opt = { value: label, label, description: null, disabled: false, group: null, created: true };
                // A page can persist the new item and swap in its real id: @f-create="e => save(e.detail)" -> detail.resolve(id, label).
                this.$dispatch('f-create', {
                    name: this.name, label,
                    resolve: (value, text = label) => {
                        this.made = this.made.filter((m) => m !== opt);
                        const real = { ...opt, value, label: text };
                        this.made.push(real);
                        this.value = multiple ? (this.value || []).map((v) => (v === opt.value ? value : v)) : value;
                    },
                });
                this.made.push(opt);
                if (multiple) {
                    this.value = [...(this.value || []), opt.value];
                    this.query = '';
                } else {
                    this.value = opt.value;
                    this.close(true);
                }
            },
            remove(v) {
                if (!this.canEdit) return;
                this.value = (this.value || []).filter((x) => String(x) !== String(v));
            },
            clear() {
                if (!this.canEdit) return;
                this.value = multiple ? [] : null;
            },
            key(e) {
                if (!this.canEdit) return;
                const rows = this.list;
                const n = rows.length;
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (!this.open) return this.openList();
                    if (!n) return;
                    let i = this.active;
                    do i = (i + (e.key === 'ArrowDown' ? 1 : -1) + n) % n;
                    while (rows[i].disabled && i !== this.active);
                    this.active = i;
                    this.$nextTick(() => this.scrollActive());
                } else if (e.key === 'Home' && this.open) {
                    e.preventDefault();
                    this.active = 0;
                    this.$nextTick(() => this.scrollActive());
                } else if (e.key === 'End' && this.open) {
                    e.preventDefault();
                    this.active = n - 1;
                    this.$nextTick(() => this.scrollActive());
                } else if (e.key === 'Enter') {
                    if (this.open && rows[this.active]) {
                        e.preventDefault();
                        this.choose(rows[this.active]);
                    }
                } else if (e.key === 'Escape' && this.open) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.close(true);
                } else if (e.key === 'Backspace' && multiple && this.query === '' && (this.value || []).length) {
                    this.value = this.value.slice(0, -1);
                } else if (e.key === 'Tab') this.close();
            },
        };
    });

    // ------------------------------------------------------------------------------------------------ tags (free text chips)
    define(Alpine, 'fTags', (cfg) => ({
        value: Array.isArray(cfg.value) ? [...cfg.value] : [],
        initial: Array.isArray(cfg.value) ? [...cfg.value] : [],
        draft: '',
        max: cfg.max || null,
        norm(t) {
            let s = String(t).trim();
            if (cfg.lowercase) s = s.toLowerCase();
            return s;
        },
        add(raw) {
            if (!this.canEdit) return;
            const parts = String(raw).split(cfg.separators ? new RegExp(`[${cfg.separators}]`) : /[,\n;]/).map((t) => this.norm(t)).filter(Boolean);
            if (!parts.length) return;
            const next = [...(this.value || [])];
            let err = null;
            for (const p of parts) {
                if (cfg.maxLength && p.length > cfg.maxLength) err = `Tags can be at most ${cfg.maxLength} characters.`;
                else if (cfg.pattern && !new RegExp(cfg.pattern).test(p)) err = cfg.patternMessage || `"${p}" is not a valid tag.`;
                else if (next.some((x) => x.toLowerCase() === p.toLowerCase())) err = `"${p}" is already added.`;
                else if (this.max && next.length >= this.max) err = `At most ${this.max} tags.`;
                else next.push(p);
            }
            this.localError = err;
            this.value = next;
            if (!err) this.draft = '';
        },
        remove(i) {
            if (!this.canEdit) return;
            this.value = this.value.filter((_, k) => k !== i);
            this.localError = null;
        },
        key(e) {
            if ((e.key === 'Enter' || e.key === ',' || e.key === ';') && this.draft.trim() !== '') {
                e.preventDefault();
                this.add(this.draft);
            } else if (e.key === 'Backspace' && this.draft === '' && this.value.length) {
                this.remove(this.value.length - 1);
            }
        },
        paste(e) {
            const text = e.clipboardData?.getData('text') || '';
            if (/[,\n;]/.test(text)) {
                e.preventDefault();
                this.add(text);
            }
        },
        blur() {
            if (this.draft.trim() !== '') this.add(this.draft);
        },
    }));

    // ------------------------------------------------------------------------------------------------ key / value rows
    define(Alpine, 'fKeyValue', (cfg) => ({
        value: Array.isArray(cfg.value) ? cfg.value.map((r) => ({ key: r.key ?? '', value: r.value ?? '' })) : Object.entries(cfg.value || {}).map(([key, value]) => ({ key, value: String(value) })),
        initial: null,
        errs: [],
        init() {
            this.initial = clone(this.value);
            this.$watch('value', () => this.validate());
        },
        add() {
            if (!this.canEdit || (cfg.max && this.value.length >= cfg.max)) return;
            this.value = [...this.value, { key: '', value: '' }];
            this.$nextTick(() => this.$refs.rows?.querySelector('.f-kv-row:last-child input')?.focus());
        },
        remove(i) {
            if (this.canEdit) this.value = this.value.filter((_, k) => k !== i);
        },
        validate() {
            this.errs = L.validateKeyValues(this.value, { keyPattern: cfg.keyPattern });
            this.localError = this.errs[0] || null;
        },
        rowInvalid(i) {
            return this.errs.some((e) => e.startsWith(`Row ${i + 1}:`));
        },
        get atMax() { return !!cfg.max && this.value.length >= cfg.max; },
    }));
}
