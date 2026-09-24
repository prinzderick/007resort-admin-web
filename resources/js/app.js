import Chart from 'chart.js/auto';
import './form/index.js';
import './cms/index.js';

window.Chart = Chart;

const BRAND = ['#0f7d4f', '#2b6cde', '#e2a72e', '#c9503c', '#7b5cd6', '#1f9db5', '#8a8f98'];

/*
 * Alpine building blocks used by the Blade components. Livewire ships Alpine, so we only register data here.
 */
document.addEventListener('alpine:init', () => {
    // <div x-data="chart({...})"><canvas x-ref="c"></canvas></div>
    window.Alpine.data('chart', (config) => ({
        instance: null,
        init() {
            const cfg = JSON.parse(JSON.stringify(config));
            (cfg.data.datasets || []).forEach((d, i) => {
                const c = BRAND[i % BRAND.length];
                if (cfg.type === 'doughnut') {
                    d.backgroundColor = d.backgroundColor || (cfg.data.labels || []).map((_, j) => BRAND[j % BRAND.length]);
                } else if (cfg.type === 'line') {
                    d.borderColor = d.borderColor || c;
                    d.backgroundColor = d.backgroundColor || c;
                    d.tension = 0.35;
                    d.pointRadius = 3;
                    d.borderWidth = 2;
                }
            });
            cfg.options = Object.assign({ responsive: true, maintainAspectRatio: false, animation: false }, cfg.options || {});
            // Money axes: 12,000 -> N12k, 2,500,000 -> N2.5m; tooltips keep the exact figure. Legend swatches are round, not blocks.
            const fmt = (v) => (Math.abs(v) >= 1e6 ? '\u20a6' + +(v / 1e6).toFixed(1) + 'm' : Math.abs(v) >= 1e3 ? '\u20a6' + +(v / 1e3).toFixed(1) + 'k' : '\u20a6' + v);
            if (cfg.money && cfg.options.scales?.y) {
                cfg.options.scales.y.ticks = Object.assign({ callback: fmt, maxTicksLimit: 6 }, cfg.options.scales.y.ticks || {});
                cfg.options.plugins = cfg.options.plugins || {};
                cfg.options.plugins.tooltip = { callbacks: { label: (c) => ' ' + c.dataset.label + ': \u20a6' + Number(c.parsed.y).toLocaleString('en-NG', { minimumFractionDigits: 2 }) } };
            }
            cfg.options.plugins = cfg.options.plugins || {};
            cfg.options.plugins.legend = Object.assign({ labels: { usePointStyle: true, boxWidth: 8, boxHeight: 8 } }, cfg.options.plugins.legend || {});
            this.instance = new Chart(this.$refs.canvas, cfg);
        },
        destroy() {
            this.instance?.destroy();
        },
    }));

    // Warn before leaving a form with unsaved edits (settings screens).
    window.Alpine.data('dirtyGuard', () => ({
        dirty: false,
        init() {
            const form = this.$el;
            form.addEventListener('input', () => (this.dirty = true));
            form.addEventListener('change', () => (this.dirty = true));
            form.addEventListener('submit', () => (this.dirty = false));
            window.addEventListener('beforeunload', (e) => {
                if (this.dirty) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        },
    }));

    // Click-to-confirm for risky actions: <form x-data="confirmSubmit('Message')" @submit="ask($event)">
    window.Alpine.data('confirmSubmit', (message, confirmLabel = 'Yes, continue') => ({
        ask(e) {
            // Styled confirmation instead of the browser's: same look as every other dialog, keyboard friendly.
            e.preventDefault();
            const form = e.target.closest('form') || e.target;
            window.r007Confirm(message, confirmLabel).then((ok) => { if (ok) { form.submit(); } });
        },
    }));

    window.r007Confirm = (message, confirmLabel = 'Yes, continue') => new Promise((resolve) => {
        const back = document.createElement('div');
        back.className = 'f-modal-back';
        back.innerHTML = '<div class="f-modal" role="alertdialog" aria-modal="true" aria-labelledby="r7c-t"><h2 id="r7c-t">Please confirm</h2><p></p><div class="f-modal-actions"><button type="button" class="f-btn" data-act="no">Cancel</button><button type="button" class="f-btn" data-variant="primary" data-act="yes"></button></div></div>';
        back.querySelector('p').textContent = message;
        back.querySelector('[data-act=yes]').textContent = confirmLabel;
        const done = (v) => { document.removeEventListener('keydown', onKey); back.remove(); resolve(v); };
        const onKey = (ev) => { if (ev.key === 'Escape') { done(false); } };
        back.addEventListener('click', (ev) => { if (ev.target === back || ev.target.dataset.act === 'no') { done(false); } else if (ev.target.dataset.act === 'yes') { done(true); } });
        document.addEventListener('keydown', onKey);
        document.body.appendChild(back);
        back.querySelector('[data-act=yes]').focus();
    });

    // Tables: quick filter, sort with indicators, density, column visibility, row selection + export (x-data="tableTools").
    window.Alpine.data('tableTools', () => ({
        q: '',
        sortCol: null,
        sortDir: 1,
        density: 'comfortable',
        columns: [],
        selected: 0,
        offset: 0,
        init() {
            this.density = localStorage.getItem('r007.density') || 'comfortable';
            document.documentElement.dataset.density = this.density;
            this.$nextTick(() => this.setup());
        },
        table() {
            return this.$el.querySelector('table.data-table');
        },
        setup() {
            const t = this.table();
            if (!t || t.dataset.ready) return;
            t.dataset.ready = '1';
            const rows = Array.from(t.querySelectorAll('tbody tr[data-row]'));
            if (rows.length && this.$el.querySelector('[data-component=table-tools]')) {
                // selection column
                this.offset = 1;
                const hr = t.querySelector('thead tr');
                const th = document.createElement('th');
                th.className = 'w-10';
                th.innerHTML = '<input type="checkbox" class="size-4 accent-brand-600" aria-label="Select all rows">';
                th.firstChild.addEventListener('change', (e) => rows.forEach((r) => this.mark(r, e.target.checked)));
                hr.insertBefore(th, hr.firstChild);
                rows.forEach((r) => {
                    const td = document.createElement('td');
                    td.innerHTML = '<input type="checkbox" class="size-4 accent-brand-600" aria-label="Select row">';
                    td.firstChild.addEventListener('change', (e) => this.mark(r, e.target.checked));
                    r.insertBefore(td, r.firstChild);
                });
                t.querySelectorAll('tbody tr:not([data-row]) td[colspan], tfoot td[colspan]').forEach((td) => td.setAttribute('colspan', Number(td.getAttribute('colspan')) + 1));
            }
            this.columns = Array.from(t.querySelectorAll('thead th')).map((h, i) => ({ i, label: h.textContent.trim(), hidden: false })).filter((c) => c.label !== '' && !(this.offset && c.i === 0));
            // sort headers
            t.querySelectorAll('thead th[data-sort]').forEach((h) => {
                h.tabIndex = 0;
                const idx = Array.from(h.parentNode.children).indexOf(h);
                const numeric = h.classList.contains('num') || h.classList.contains('text-right');
                h.addEventListener('click', () => this.sortBy(idx, numeric));
                h.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.sortBy(idx, numeric); } });
            });
        },
        rows() {
            return Array.from((this.$refs.body || this.table()?.tBodies[0] || document.body).querySelectorAll('tr[data-row]'));
        },
        mark(r, on) {
            r.dataset.selected = on ? 'true' : 'false';
            const c = r.querySelector('td input[type=checkbox]');
            if (c) c.checked = on;
            this.selected = this.rows().filter((x) => x.dataset.selected === 'true').length;
        },
        clearSelection() {
            this.rows().forEach((r) => this.mark(r, false));
            const all = this.table()?.querySelector('thead input[type=checkbox]');
            if (all) all.checked = false;
        },
        exportSelected() {
            const t = this.table();
            const head = Array.from(t.querySelectorAll('thead th')).map((h) => h.textContent.trim());
            const keep = head.map((h, i) => (h !== '' && !(this.offset && i === 0) ? i : -1)).filter((i) => i >= 0);
            const esc = (v) => '"' + String(v).replace(/\s+/g, ' ').trim().replace(/"/g, '""') + '"';
            const lines = [keep.map((i) => esc(head[i])).join(',')];
            this.rows().filter((r) => r.dataset.selected === 'true').forEach((r) => lines.push(keep.map((i) => esc(r.children[i].textContent)).join(',')));
            const a = document.createElement('a');
            a.href = URL.createObjectURL(new Blob(['\ufeff' + lines.join('\n')], { type: 'text/csv' }));
            a.download = 'selected-rows.csv';
            a.click();
        },
        filter() {
            const q = this.q.trim().toLowerCase();
            this.rows().forEach((r) => (r.hidden = q !== '' && !r.textContent.toLowerCase().includes(q)));
        },
        sortBy(i, numeric) {
            this.sortDir = this.sortCol === i ? -this.sortDir : 1;
            this.sortCol = i;
            const t = this.table();
            t.querySelectorAll('thead th').forEach((h, k) => (k === i ? h.setAttribute('aria-sort', this.sortDir === 1 ? 'ascending' : 'descending') : h.removeAttribute('aria-sort')));
            const val = (r) => {
                const c = r.children[i];
                const raw = (c?.dataset.sort ?? c?.textContent ?? '').trim();
                return numeric ? parseFloat(raw.replace(/[^0-9.\-]/g, '')) || 0 : raw.toLowerCase();
            };
            const body = this.rows()[0]?.parentNode;
            this.rows().sort((a, b) => (val(a) > val(b) ? 1 : val(a) < val(b) ? -1 : 0) * this.sortDir).forEach((r) => body.appendChild(r));
        },
        // legacy call sites pass a body-relative index
        sort(i, numeric) {
            this.sortBy(i + this.offset, !!numeric);
        },
        setDensity(d) {
            this.density = d;
            localStorage.setItem('r007.density', d);
            document.documentElement.dataset.density = d;
        },
        toggleCol(i) {
            const c = this.columns.find((x) => x.i === i);
            c.hidden = !c.hidden;
            this.table().querySelectorAll('tr').forEach((r) => { if (r.children[i] && !r.children[i].hasAttribute('colspan')) r.children[i].style.display = c.hidden ? 'none' : ''; });
        },
    }));
});

// No double submits: a form (or a wire:click button) that is in flight shows a spinner and ignores further clicks.
document.addEventListener('submit', (e) => {
    const f = e.target;
    if (!(f instanceof HTMLFormElement) || e.defaultPrevented) return;
    setTimeout(() => {
        if (e.defaultPrevented) return;
        f.querySelectorAll('button:not([type=button])').forEach((b) => { if (!b.dataset.keep) { b.dataset.loading = 'true'; b.setAttribute('aria-busy', 'true'); } });
        f.addEventListener('submit', (ev) => ev.preventDefault(), { once: false });
    }, 0);
});
window.addEventListener('pageshow', () => document.querySelectorAll('[data-loading]').forEach((b) => { delete b.dataset.loading; b.removeAttribute('aria-busy'); }));
