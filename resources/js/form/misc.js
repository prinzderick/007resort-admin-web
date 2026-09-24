import { define, clone } from './core.js';
import * as L from './lib.js';

export function register(Alpine) {
    // ------------------------------------------------------------------------------------------------ text / textarea
    define(Alpine, 'fText', (cfg) => ({
        value: cfg.value ?? '',
        initial: cfg.value ?? '',
        focused: false,
        get count() { return String(this.value ?? '').length; },
        get over() { return !!cfg.maxlength && this.count > cfg.maxlength; },
        get near() { return !!cfg.maxlength && this.count >= cfg.maxlength * 0.9; },
        clear() {
            if (!this.canEdit) return;
            this.value = '';
            this.$nextTick(() => this.$refs.input?.focus());
        },
        get hasValue() { return String(this.value ?? '') !== ''; },
        grow() {
            const el = this.$refs.input;
            if (!cfg.autosize || !el) return;
            el.style.height = 'auto';
            el.style.height = `${el.scrollHeight + 2}px`;
        },
        init() {
            if (cfg.autosize) this.$nextTick(() => this.grow());
        },
    }));

    // ------------------------------------------------------------------------------------------------ password
    define(Alpine, 'fPassword', (cfg) => ({
        value: cfg.value ?? '',
        initial: cfg.value ?? '',
        show: false,
        caps: false,
        get strength() {
            const v = String(this.value || '');
            if (!v) return 0;
            let s = 0;
            if (v.length >= 8) s++;
            if (v.length >= 12) s++;
            if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++;
            if (/\d/.test(v) && /[^A-Za-z0-9]/.test(v)) s++;
            return Math.max(1, Math.min(4, s));
        },
        get strengthLabel() { return ['', 'Weak', 'Fair', 'Good', 'Strong'][this.strength]; },
        keyCheck(e) {
            if (e.getModifierState) this.caps = e.getModifierState('CapsLock');
        },
    }));

    // ------------------------------------------------------------------------------------------------ PIN (boxes + optional keypad)
    define(Alpine, 'fPin', (cfg) => {
        const len = cfg.length || 4;
        return {
            len,
            value: String(cfg.value ?? ''),
            initial: String(cfg.value ?? ''),
            mask: cfg.mask !== false,
            get digits() { return Array.from({ length: len }, (_, i) => String(this.value)[i] || ''); },
            set(i, ch) {
                const d = this.digits;
                d[i] = ch;
                this.value = d.join('').replace(/\s/g, '');
                if (this.value.length === len) this.$dispatch('f-complete', { name: this.name, value: this.value });
            },
            onInput(e, i) {
                if (!this.canEdit) return;
                const raw = e.target.value.replace(/\D/g, '');
                if (raw.length > 1) return this.fill(raw, i);
                e.target.value = raw;
                this.set(i, raw);
                if (raw && i < len - 1) this.$refs['d' + (i + 1)]?.focus();
            },
            fill(text, from = 0) {
                const clean = text.replace(/\D/g, '');
                const cur = this.digits;
                for (let k = 0; k < clean.length && from + k < len; k++) cur[from + k] = clean[k];
                this.value = cur.join('').replace(/\s/g, '');
                const at = Math.min(len - 1, from + clean.length);
                this.$refs['d' + at]?.focus();
                if (this.value.length === len) this.$dispatch('f-complete', { name: this.name, value: this.value });
            },
            onKey(e, i) {
                if (e.key === 'Backspace' && !this.digits[i] && i > 0) {
                    e.preventDefault();
                    this.set(i - 1, '');
                    this.$refs['d' + (i - 1)]?.focus();
                } else if (e.key === 'ArrowLeft' && i > 0) {
                    e.preventDefault();
                    this.$refs['d' + (i - 1)]?.focus();
                } else if (e.key === 'ArrowRight' && i < len - 1) {
                    e.preventDefault();
                    this.$refs['d' + (i + 1)]?.focus();
                }
            },
            onPaste(e, i) {
                e.preventDefault();
                if (this.canEdit) this.fill(e.clipboardData?.getData('text') || '', i);
            },
            press(k) {
                if (!this.canEdit) return;
                if (k === 'back') this.value = String(this.value).slice(0, -1);
                else if (k === 'clear') this.value = '';
                else if (String(this.value).length < len) {
                    this.value = String(this.value) + k;
                    if (this.value.length === len) this.$dispatch('f-complete', { name: this.name, value: this.value });
                }
            },
            afterReset() {
                this.value = String(this.value ?? '');
            },
        };
    });

    // ------------------------------------------------------------------------------------------------ colour
    define(Alpine, 'fColor', (cfg) => ({
        value: cfg.value || '',
        initial: cfg.value || '',
        text: cfg.value || '',
        init() {
            this.$watch('value', (v) => (this.text = v || ''));
        },
        get valid() { return L.normalizeHex(this.value) !== null; },
        get ratio() {
            const hex = L.normalizeHex(this.value);
            return hex ? L.contrastRatio(hex, cfg.on || '#ffffff') : null;
        },
        get ratioText() { return this.ratio ? `${this.ratio.toFixed(1)}:1 on ${cfg.on ? 'the chosen background' : 'white'}` : ''; },
        get readable() { return this.ratio === null || this.ratio >= 4.5; },
        commit() {
            if (this.text.trim() === '') {
                this.value = '';
                this.localError = null;
                return;
            }
            const hex = L.normalizeHex(this.text);
            if (!hex) return void (this.localError = 'Enter a colour like #0f7d4f.');
            this.localError = null;
            this.value = hex;
            this.text = hex;
        },
        pick(hex) {
            if (!this.canEdit) return;
            this.value = hex;
            this.localError = null;
        },
    }));

    // ------------------------------------------------------------------------------------------------ file / image
    define(Alpine, 'fFile', (cfg) => ({
        files: [],
        existing: clone(cfg.existing || []),
        removedExisting: false,
        over: false,
        progress: null,
        value: null,
        initial: null,
        get dirty() { return this.files.some((f) => !f.error) || this.removedExisting; },
        get atDefault() { return true; },
        get input() { return this.$refs.input; },
        pickFiles(list) {
            if (!this.canEdit) return;
            const incoming = Array.from(list || []);
            const keep = [];
            const rows = [];
            for (const f of incoming) {
                const error = L.validateFile(f, { maxBytes: cfg.maxBytes, accept: cfg.accept });
                rows.push({ name: f.name, size: f.size, type: f.type, error, url: !error && f.type.startsWith('image/') ? URL.createObjectURL(f) : null });
                if (!error) keep.push(f);
            }
            let next = cfg.multiple ? [...this.files.filter((r) => !r.error).map((r) => r._file).filter(Boolean), ...keep] : keep.slice(0, 1);
            if (cfg.maxFiles && next.length > cfg.maxFiles) {
                next = next.slice(0, cfg.maxFiles);
                this.localError = `You can add at most ${cfg.maxFiles} files.`;
            } else this.localError = rows.some((r) => r.error) ? null : this.localError;
            this.releaseUrls();
            const dt = new DataTransfer();
            next.forEach((f) => dt.items.add(f));
            this.input.files = dt.files;
            const kept = rows.filter((r) => !r.error);
            this.files = [...next.map((f) => ({ name: f.name, size: f.size, type: f.type, error: null, url: kept.find((r) => r.name === f.name)?.url || (f.type.startsWith('image/') ? URL.createObjectURL(f) : null), _file: f })), ...rows.filter((r) => r.error)];
            this.value = next.map((f) => f.name);
            this.emit();
        },
        emit() {
            const ev = new Event('change', { bubbles: true });
            ev._fchecked = true; // already validated: let it through to Livewire's upload handler
            this.input.dispatchEvent(ev);
        },
        /** Capture-phase guard: stops Livewire seeing (and uploading) files that fail validation. */
        onChange(e) {
            if (e.target !== this.input || e._fchecked) return;
            e.stopPropagation();
            e.stopImmediatePropagation();
            this.pickFiles(this.input.files);
        },
        drop(e) {
            this.over = false;
            if (!this.canEdit) return;
            this.pickFiles(e.dataTransfer?.files);
        },
        remove(i) {
            const left = this.files.filter((_, k) => k !== i).filter((r) => !r.error).map((r) => r._file).filter(Boolean);
            const dt = new DataTransfer();
            left.forEach((f) => dt.items.add(f));
            this.releaseUrls();
            this.files = this.files.filter((_, k) => k !== i);
            this.input.files = dt.files;
            this.value = left.map((f) => f.name);
            this.emit();
        },
        dismiss(i) {
            this.files = this.files.filter((_, k) => k !== i);
        },
        removeExisting(i) {
            this.existing = this.existing.filter((_, k) => k !== i);
            this.removedExisting = true;
        },
        releaseUrls() {
            this.files.forEach((f) => f.url && URL.revokeObjectURL(f.url));
        },
        size(n) { return L.humanBytes(n); },
        _cleanup() { this.releaseUrls(); },
    }));

    // ------------------------------------------------------------------------------------------------ actions (sticky save bar)
    Alpine.data('fActions', (cfg = {}) => ({
        names: {},
        dirtyCount: 0,
        busy: !!cfg.loading,
        justSaved: false,
        form: null,
        get dirty() { return this.dirtyCount > 0; },
        init() {
            this.form = this.$el.closest('form');
            const scope = this.form || document;
            this._dirty = (e) => {
                if (!e.detail?.id) return;
                if (e.detail.dirty) this.names[e.detail.id] = e.detail.name || e.detail.id;
                else delete this.names[e.detail.id];
                this.dirtyCount = Object.keys(this.names).length;
                if (this.dirty) this.justSaved = false;
            };
            scope.addEventListener('f-dirty', this._dirty);
            // Native fields (plain <input>s from the older components) count too.
            this._native = (e) => {
                if (e.target?.closest?.('[data-f]') || !e.target?.name) return;
                this.names['native:' + e.target.name] = e.target.name;
                this.dirtyCount = Object.keys(this.names).length;
            };
            scope.addEventListener('input', this._native);
            scope.addEventListener('change', this._native);
            this._submit = (e) => {
                if (this.busy) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    return;
                }
                if (e.defaultPrevented) return;
                this.busy = true;
                clearTimeout(this._fail);
                this._fail = setTimeout(() => (this.busy = false), cfg.timeout ?? 20000);
            };
            this.form?.addEventListener('submit', this._submit, true);
            this._show = () => (this.busy = !!cfg.loading);
            window.addEventListener('pageshow', this._show);
            this._saved = () => {
                this.busy = false;
                clearTimeout(this._fail);
                this.names = {};
                this.dirtyCount = 0;
                this.justSaved = true;
                setTimeout(() => (this.justSaved = false), 4000);
            };
            window.addEventListener('form-saved', this._saved);
            // Livewire: a finished or failed round trip frees the button (validation errors keep the edits; the component
            // dispatches `form-saved` when it really saved, which re-baselines every control).
            const hook = () =>
                window.Livewire?.hook?.('commit', ({ succeed, fail }) => {
                    if (!this.busy) return;
                    succeed(() => {
                        this.busy = false;
                        clearTimeout(this._fail);
                    });
                    fail(() => (this.busy = false));
                });
            if (window.Livewire?.hook) hook();
            else document.addEventListener('livewire:init', hook, { once: true });
        },
        destroy() {
            const scope = this.form || document;
            scope.removeEventListener('f-dirty', this._dirty);
            scope.removeEventListener('input', this._native);
            scope.removeEventListener('change', this._native);
            this.form?.removeEventListener('submit', this._submit, true);
            window.removeEventListener('pageshow', this._show);
            window.removeEventListener('form-saved', this._saved);
        },
        cancel() {
            const scope = this.form || document;
            scope.querySelectorAll('[data-f]').forEach((el) => window.Alpine.$data(el)?.revert?.());
            this.names = {};
            this.dirtyCount = 0;
            this.$dispatch('f-cancel');
        },
    }));

    // ------------------------------------------------------------------------------------------------ typed confirmation
    Alpine.data('fConfirm', (cfg = {}) => ({
        open: false,
        typed: '',
        get matches() {
            return cfg.phrase ? L.confirmMatches(this.typed, cfg.phrase, { caseSensitive: cfg.caseSensitive !== false }) : true;
        },
        show() {
            this.typed = '';
            this.open = true;
            this.$nextTick(() => (this.$refs.typed || this.$refs.ok)?.focus());
        },
        hide() {
            this.open = false;
            this.$nextTick(() => this.$refs.trigger?.querySelector('button, a, input')?.focus());
        },
        ok() {
            if (!this.matches) return;
            this.$dispatch('f-confirmed', { id: cfg.id });
            if (cfg.wire && this.$wire && typeof this.$wire[cfg.wire] === 'function') this.$wire[cfg.wire](...(cfg.args || []));
            if (cfg.form) document.getElementById(cfg.form)?.requestSubmit();
            this.open = false;
        },
    }));

    // ------------------------------------------------------------------------------------------------ schema: high-impact acknowledgement
    Alpine.data('fSchema', () => ({
        danger: {},
        ack: false,
        q: '',
        /** Rule rows call match(searchText) to hide themselves while the settings search box has text. */
        match(text) {
            const q = this.q.trim().toLowerCase();
            return q === '' || text.includes(q);
        },
        get highCount() { return Object.keys(this.danger).length; },
        init() {
            this.$el.addEventListener('f-dirty', (e) => {
                const d = e.detail;
                if (!d?.id || d.danger !== 'high') return;
                if (d.dirty) this.danger[d.id] = d.name || d.id;
                else delete this.danger[d.id];
                if (!this.highCount) this.ack = false;
            });
            const form = this.$el.closest('form');
            form?.addEventListener(
                'submit',
                (e) => {
                    if (this.highCount && !this.ack) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        this.$refs.ack?.focus();
                    }
                },
                true,
            );
        },
    }));
}
