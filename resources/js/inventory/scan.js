import { matchItem, firstEmptyRow } from './lib.js';

/*
 * Storekeeper stock forms (receive / transfer / count / adjust / wastage) at resources/views/pages/inventory/form.blade.php.
 * A USB barcode scanner behaves like a very fast keyboard: it types the code then sends Enter. We keep the scan box a
 * plain text input -- never the async searchable combobox, which would fire a fetch on every keystroke and can eat the
 * Enter itself -- so a full burst of digits always lands as one value and Enter is the only thing that resolves it.
 */
export function register(Alpine) {
    // <div x-data="barcodeScan(itemsJson)"><input x-model="code" @keydown.enter.prevent="resolve()"></div>
    Alpine.data('barcodeScan', (items) => ({
        items: items || [],
        code: '',
        notFound: false,
        lastCode: '',
        resolve() {
            const raw = this.code;
            const hit = matchItem(this.items, raw);
            if (!hit) {
                if (raw.trim() !== '') {
                    this.notFound = true;
                    this.lastCode = raw.trim();
                }
                return;
            }
            this.notFound = false;
            this.code = '';
            this.$dispatch('inventory-scan', hit);
        },
    }));

    // Wraps the <form> on every stock-entry screen. Listens for `inventory-scan` (dispatched by the box above) and
    // fills the first empty item row, growing the row list (receive/transfer/count only) when every visible row is
    // already taken, then moves focus to that row's quantity input so the next scan can go straight to typing a qty.
    Alpine.data('lineRows', (start, max = 12) => ({
        rows: start,
        scanMsg: null,
        onScan(item) {
            this.place(item, 0, max);
        },
        place(item, attempt, max) {
            if (attempt > max) return;
            const at = firstEmptyRow(this.rows, (i) => this.rowValue(i));
            if (at === null) {
                if (this.rows < max) {
                    this.rows++;
                    this.$nextTick(() => this.place(item, attempt + 1, max));
                } else if (max === 1) {
                    this.scanMsg = 'The item field is already set. Change it below, or submit this one first.';
                } else {
                    this.scanMsg = `All ${max} lines are full — submit this batch first.`;
                }
                return;
            }
            this.setRowValue(at, item.value);
            this.scanMsg = `Added ${item.label} to line ${at + 1}.`;
            this.$nextTick(() => this.qtyEl(at)?.querySelector('input')?.focus());
        },
        // Plain DOM lookups (not $refs): each row's x-form.select/text control owns its OWN x-data scope, and
        // Alpine's $refs never crosses into a nested component -- a ref placed on that element is invisible to
        // the surrounding <form>'s x-data. A CSS query has no such boundary, so we tag rows with data-line-item /
        // data-line-qty instead and read the control's Alpine state off the element directly.
        itemEl(i) {
            return this.$el.querySelector(`[data-line-item="${i}"]`);
        },
        qtyEl(i) {
            return this.$el.querySelector(`[data-line-qty="${i}"]`);
        },
        rowValue(i) {
            const el = this.itemEl(i);
            return el ? window.Alpine.$data(el).value : undefined;
        },
        setRowValue(i, value) {
            const el = this.itemEl(i);
            if (el) window.Alpine.$data(el).value = value;
        },
    }));
}
