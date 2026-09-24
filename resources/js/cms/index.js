/*
 * Alpine components for the Website (CMS) screens. Pure logic lives in ./lib.js (unit-tested).
 * Every component posts through ordinary form inputs (hidden fields), so every screen still works as a plain HTML form.
 */
import * as L from './lib.js';

const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';
const fire = (el) => el?.dispatchEvent(new Event('change', { bubbles: true }));

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    /* ------------------------------------------------------------------ unsaved-changes guard for a whole form */
    Alpine.data('cmsForm', () => ({
        dirty: false,
        saving: false,
        init() {
            const form = this.$el;
            const mark = (e) => { if (!this.saving && e.target?.closest?.('[data-no-dirty]') == null) this.dirty = true; };
            form.addEventListener('input', mark);
            form.addEventListener('change', mark);
            form.addEventListener('cms-change', mark);
            form.addEventListener('submit', () => { this.saving = true; this.dirty = false; });
            window.addEventListener('beforeunload', (e) => { if (this.dirty && !this.saving) { e.preventDefault(); e.returnValue = ''; } });
            // In-app links: ask with the styled dialog instead of silently dropping edits.
            document.addEventListener('click', async (e) => {
                const a = e.target.closest?.('a[href]');
                if (!this.dirty || !a || a.target === '_blank' || a.hasAttribute('data-allow-leave') || a.getAttribute('href').startsWith('#') || e.defaultPrevented) return;
                if (a.closest('form') === form && a.hasAttribute('data-in-form')) return;
                e.preventDefault();
                if (await window.r007Confirm('You have unsaved changes on this page. Leave without saving?', 'Leave page')) { this.dirty = false; window.location.href = a.href; }
            });
            window.addEventListener('pageshow', () => (this.saving = false));
        },
    }));

    /* ------------------------------------------------------------------ tabs inside one form (settings shell) */
    Alpine.data('cmsTabs', (initial, keys = []) => ({
        tab: initial,
        init() {
            const h = decodeURIComponent(location.hash.replace('#', ''));
            if (keys.includes(h)) this.tab = h;
            // A tab holding a validation error opens first.
            const bad = this.$el.querySelector('[data-tab-panel] [data-invalid="true"]')?.closest('[data-tab-panel]')?.dataset.tabPanel;
            if (bad) this.tab = bad;
            this.$watch('tab', (t) => history.replaceState(null, '', '#' + t));
        },
        go(t) { this.tab = t; },
        key(e) {
            const i = keys.indexOf(this.tab);
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { this.tab = keys[(i + 1) % keys.length]; e.preventDefault(); this.$nextTick(() => this.$refs['tab-' + this.tab]?.focus()); }
            if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { this.tab = keys[(i - 1 + keys.length) % keys.length]; e.preventDefault(); this.$nextTick(() => this.$refs['tab-' + this.tab]?.focus()); }
        },
    }));

    /* ------------------------------------------------------------------ Markdown editor with toolbar and live preview */
    Alpine.data('cmsMarkdown', (cfg = {}) => ({
        value: cfg.value || '',
        mode: 'write', // small screens: write | preview (side by side from lg up)
        get html() { return L.renderMarkdown(this.value); },
        get words() { return (this.value.trim().match(/\S+/g) || []).length; },
        get readMinutes() { return Math.max(1, Math.round(this.words / 200)); },
        apply(action, opts = {}) {
            const ta = this.$refs.ta;
            const r = L.applyFormat({ value: this.value, start: ta.selectionStart, end: ta.selectionEnd }, action, opts);
            this.value = r.value;
            this.$nextTick(() => { ta.focus(); ta.setSelectionRange(r.start, r.end); fire(ta); });
        },
        link() {
            const url = window.prompt('Link address (https://... or /page)', 'https://');
            if (url) this.apply('link', { url });
        },
        image() {
            window.dispatchEvent(new CustomEvent('cms-pick-image', { detail: { resolve: (m) => m && this.apply('image', { url: m.url, alt: m.alt || m.title || '' }) } }));
        },
        key(e) {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'b') { e.preventDefault(); this.apply('bold'); }
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'i') { e.preventDefault(); this.apply('italic'); }
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); this.link(); }
        },
    }));

    /* ------------------------------------------------------------------ slug that follows the title until edited by hand */
    Alpine.data('cmsSlug', (cfg = {}) => ({
        slug: cfg.value || '',
        touched: !!cfg.value,
        init() {
            const src = document.getElementById(cfg.source);
            if (!src) return;
            src.addEventListener('input', () => { if (!this.touched) { this.slug = L.slugify(src.value); } });
        },
        edit() { this.touched = true; },
    }));

    /* ------------------------------------------------------------------ image field (opens the shared picker) */
    Alpine.data('cmsImage', (cfg = {}) => ({
        id: cfg.id || '',
        url: cfg.url || '',
        alt: cfg.alt || '',
        choose() {
            window.dispatchEvent(new CustomEvent('cms-pick-image', { detail: { current: { id: this.id }, resolve: (m) => {
                if (!m) return;
                this.id = m.id; this.url = m.url; this.alt = m.alt || '';
                this.$nextTick(() => { this.$root.querySelectorAll('input[type=hidden]').forEach(fire); });
            } } }));
        },
        clear() { this.id = ''; this.url = ''; this.$nextTick(() => this.$root.querySelectorAll('input[type=hidden]').forEach(fire)); },
    }));

    /* ------------------------------------------------------------------ the shared image picker modal (one per page) */
    Alpine.data('cmsMediaPicker', (cfg = {}) => ({
        open: false,
        q: '', tag: '',
        items: [], tags: [], next: null,
        loading: false, error: '', selected: null,
        resolver: null,
        uploads: [], dropping: false,
        listen() {
            window.addEventListener('cms-pick-image', (e) => {
                this.resolver = e.detail?.resolve || null;
                this.selected = null; this.q = ''; this.tag = ''; this.uploads = [];
                this.open = true;
                this.load(true);
                this.pendingId = e.detail?.current?.id || null;
            });
        },
        async load(reset = false) {
            this.loading = true; this.error = '';
            try {
                const p = new URLSearchParams({ q: this.q, tag: this.tag });
                if (!reset && this.next) p.set('cursor', this.next);
                const r = await fetch(`${cfg.listUrl}?${p}`, { headers: { Accept: 'application/json' } });
                if (!r.ok) throw new Error((await r.json().catch(() => ({}))).message || `Could not load the library (${r.status}).`);
                const j = await r.json();
                this.items = reset ? j.items : this.items.concat(j.items);
                if (!this.q && !this.tag && j.tags) this.tags = j.tags;
                this.next = j.nextCursor || null;
                if (reset && this.pendingId) { this.selected = this.items.find((i) => i.id === this.pendingId) || null; this.pendingId = null; }
            } catch (e) { this.error = e.message; } finally { this.loading = false; }
        },
        search() { clearTimeout(this._t); this._t = setTimeout(() => this.load(true), 250); },
        pick(m) { this.selected = m; },
        confirm() { if (this.selected && this.resolver) this.resolver({ ...this.selected }); this.close(); },
        close() { this.open = false; this.resolver = null; },
        async upload(files) {
            for (const f of Array.from(files)) {
                const u = { name: f.name, progress: 0, error: '', done: false };
                this.uploads.push(u);
                try {
                    const j = await window.cmsUploadFile(cfg.uploadUrl, f, (p) => (u.progress = p));
                    u.done = true; u.progress = 100;
                    if (j.item) { this.items.unshift(j.item); this.pick(j.item); }
                } catch (e) { u.error = e.message; }
            }
        },
        bytes: L.bytes,
    }));

    /* ------------------------------------------------------------------ drag-to-reorder with accessible up/down buttons */
    Alpine.data('cmsSortable', (cfg = {}) => ({
        message: '',
        dragging: null,
        items() { return Array.from(this.$root.querySelectorAll(':scope > [data-sort-item]')); },
        label(el) { return el.dataset.sortLabel || 'Item'; },
        announce(el) {
            const list = this.items();
            this.message = `${this.label(el)} moved to position ${list.indexOf(el) + 1} of ${list.length}.`;
            list.forEach((it, i) => { const n = it.querySelector('[data-sort-pos]'); if (n) n.textContent = i + 1; });
            const first = this.$root.querySelector('input'); fire(first);
            this.$root.dispatchEvent(new CustomEvent('cms-reordered', { bubbles: true, detail: { ids: list.map((i) => i.dataset.sortId) } }));
        },
        up(el) { const p = el.previousElementSibling; if (p?.hasAttribute('data-sort-item')) { p.before(el); this.announce(el); el.querySelector('[data-move=up]')?.focus(); } },
        down(el) { const n = el.nextElementSibling; if (n?.hasAttribute('data-sort-item')) { n.after(el); this.announce(el); el.querySelector('[data-move=down]')?.focus(); } },
        start(e, el) { this.dragging = el; e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', el.dataset.sortId || ''); el.dataset.dragging = 'true'; },
        over(e, el) {
            if (!this.dragging || this.dragging === el) return;
            e.preventDefault();
            const r = el.getBoundingClientRect();
            const after = cfg.grid ? e.clientX > r.left + r.width / 2 : e.clientY > r.top + r.height / 2;
            after ? el.after(this.dragging) : el.before(this.dragging);
        },
        end() { if (this.dragging) { delete this.dragging.dataset.dragging; this.announce(this.dragging); } this.dragging = null; },
    }));

    /* ------------------------------------------------------------------ multi-file drag-and-drop uploader with progress */
    Alpine.data('cmsUploader', (cfg = {}) => ({
        queue: [], dropping: false, busy: false,
        pick(e) { this.add(e.target.files); e.target.value = ''; },
        drop(e) { this.dropping = false; this.add(e.dataTransfer.files); },
        add(files) {
            const ok = /^image\/(jpeg|png|webp|gif|avif)$/;
            Array.from(files).forEach((f) => {
                const q = { name: f.name, size: L.bytes(f.size), progress: 0, state: 'waiting', error: '', file: f };
                if (!ok.test(f.type)) { q.state = 'failed'; q.error = 'Only JPEG, PNG, WebP, GIF or AVIF images.'; }
                else if (f.size > (cfg.maxMb || 10) * 1048576) { q.state = 'failed'; q.error = `Larger than ${cfg.maxMb || 10} MB.`; }
                this.queue.push(q);
            });
            this.run();
        },
        async run() {
            if (this.busy) return;
            this.busy = true;
            for (const q of this.queue) {
                if (q.state !== 'waiting') continue;
                q.state = 'uploading';
                try { await window.cmsUploadFile(cfg.url, q.file, (p) => (q.progress = p), cfg.fields || {}); q.state = 'done'; q.progress = 100; }
                catch (e) { q.state = 'failed'; q.error = e.message; }
            }
            this.busy = false;
            if (this.queue.some((q) => q.state === 'done') && !this.queue.some((q) => q.state === 'waiting')) this.$dispatch('cms-uploaded');
            if (cfg.reload && this.queue.some((q) => q.state === 'done')) setTimeout(() => location.reload(), 900);
        },
        retry(q) { q.state = 'waiting'; q.error = ''; q.progress = 0; this.run(); },
        get counts() { const c = { done: 0, failed: 0, total: this.queue.length }; this.queue.forEach((q) => { if (q.state === 'done') c.done++; if (q.state === 'failed') c.failed++; }); return c; },
    }));

    /* ------------------------------------------------------------------ event recurrence preview */
    Alpine.data('cmsRecurrence', (cfg = {}) => ({
        start: cfg.start || '', recurrence: cfg.recurrence || 'none', until: cfg.until || '', time: cfg.time || '', endTime: '',
        get list() {
            const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Lagos' }).format(new Date());
            return L.occurrences({ start: this.start, recurrence: this.recurrence, until: this.until, today, n: 5 });
        },
        t12(hhmm) { const [h, m] = String(hhmm).split(':').map(Number); if (Number.isNaN(h)) return ''; return `${((h + 11) % 12) + 1}:${String(m).padStart(2, '0')} ${h < 12 ? 'AM' : 'PM'}`; },
        fmt(d) { return new Date(d + 'T12:00:00Z').toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' }); },
    }));

    /* ------------------------------------------------------------------ live announcement-bar / hero previews are plain x-data in Blade */
});

/** Upload one file to a same-origin endpoint with progress (XHR: fetch cannot report upload progress). Resolves with the JSON body. */
window.cmsUploadFile = (url, file, onProgress = () => {}, fields = {}) => new Promise((resolve, reject) => {
    const fd = new FormData();
    fd.append('file', file);
    Object.entries(fields).forEach(([k, v]) => fd.append(k, v));
    const x = new XMLHttpRequest();
    x.open('POST', url);
    x.setRequestHeader('X-CSRF-TOKEN', csrf());
    x.setRequestHeader('Accept', 'application/json');
    x.upload.onprogress = (e) => { if (e.lengthComputable) onProgress(Math.round((e.loaded / e.total) * 100)); };
    x.onerror = () => reject(new Error('Network error: the upload did not reach the server.'));
    x.onload = () => {
        let j = {};
        try { j = JSON.parse(x.responseText); } catch { /* not JSON */ }
        if (x.status >= 200 && x.status < 300) resolve(j);
        else reject(new Error(j.message || (j.errors && Object.values(j.errors)[0]?.[0]) || `Upload failed (${x.status}).`));
    };
    x.send(fd);
});

window.R007Cms = { lib: L };
