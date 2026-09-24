import Chart from 'chart.js/auto';

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
    window.Alpine.data('confirmSubmit', (message) => ({
        ask(e) {
            if (!window.confirm(message)) {
                e.preventDefault();
            }
        },
    }));

    // Client-side sort + filter for small tables (<table x-data="sortableTable">).
    window.Alpine.data('tableTools', () => ({
        q: '',
        sortCol: null,
        sortDir: 1,
        rows() {
            return Array.from(this.$refs.body.querySelectorAll('tr[data-row]'));
        },
        filter() {
            const q = this.q.trim().toLowerCase();
            this.rows().forEach((r) => (r.hidden = q !== '' && !r.textContent.toLowerCase().includes(q)));
        },
        sort(i, numeric) {
            this.sortDir = this.sortCol === i ? -this.sortDir : 1;
            this.sortCol = i;
            const rows = this.rows();
            const val = (r) => {
                const t = (r.children[i]?.dataset.sort ?? r.children[i]?.textContent ?? '').trim();
                return numeric ? parseFloat(t.replace(/[^0-9.\-]/g, '')) || 0 : t.toLowerCase();
            };
            rows.sort((a, b) => (val(a) > val(b) ? 1 : val(a) < val(b) ? -1 : 0) * this.sortDir);
            rows.forEach((r) => this.$refs.body.appendChild(r));
        },
    }));
});
