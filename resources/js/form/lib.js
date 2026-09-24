/*
 * Pure helpers behind the form controls. No DOM, no Alpine: unit-tested with `node --test` (tests/js/*.test.mjs).
 *
 * Money rule: amounts are DECIMAL STRINGS end to end. Nothing in here ever parses money into a JS number.
 */

// ---------------------------------------------------------------------------------------------- numbers

/** Number of decimal places in a step such as 0.25 -> 2, 5 -> 0, 1e-7 -> 7. */
export function decimals(n) {
    if (!Number.isFinite(n)) return 0;
    const s = String(n);
    if (s.includes('e-')) return parseInt(s.split('e-')[1], 10) + (s.split('e-')[0].split('.')[1] || '').length;
    return (s.split('.')[1] || '').length;
}

export function clamp(v, min, max) {
    if (Number.isFinite(min) && v < min) v = min;
    if (Number.isFinite(max) && v > max) v = max;
    return v;
}

/** Round to a step grid anchored at `min`, without float dust (0.1 + 0.2 style). */
export function roundToStep(v, step = 1, min = 0) {
    if (!(step > 0)) return v;
    const d = Math.max(decimals(step), decimals(min));
    const snapped = min + Math.round((v - min) / step) * step;
    return Number(snapped.toFixed(d));
}

/**
 * Snap a raw slider value: clamp, then pull to the nearest snap point when within `tolerance` (in value units),
 * otherwise round to the step grid. Snap points are always reachable even when they are off the step grid.
 */
export function snap(v, { min = 0, max = 100, step = 1, points = [], tolerance = null } = {}) {
    v = clamp(v, min, max);
    const tol = tolerance ?? (step > 0 ? step * 1.5 : (max - min) * 0.02);
    let best = null;
    for (const p of points) {
        const d = Math.abs(p - v);
        if (d <= tol && (best === null || d < Math.abs(best - v))) best = p;
    }
    if (best !== null) return clamp(best, min, max);
    return clamp(roundToStep(v, step, min), min, max);
}

/** 0..100 position of a value on a track. */
export function pct(v, min, max) {
    if (!(max > min)) return 0;
    return ((clamp(v, min, max) - min) / (max - min)) * 100;
}

/** Inverse of pct(): raw (unsnapped) value at a 0..1 position. */
export function valueAt(fraction, min, max) {
    return min + Math.min(1, Math.max(0, fraction)) * (max - min);
}

/** Keyboard step for a slider: arrows = step, PageUp/PageDown = 10 steps (or 10%), Home/End = ends. Returns the new value or null. */
export function keyValue(key, v, { min, max, step = 1, shift = false }) {
    const big = Math.max(step, (max - min) / 10);
    const table = {
        ArrowRight: v + step, ArrowUp: v + step, ArrowLeft: v - step, ArrowDown: v - step,
        PageUp: v + big, PageDown: v - big, Home: min, End: max,
    };
    if (!(key in table)) return null;
    let n = table[key];
    if (shift && key.startsWith('Arrow')) n = v + (n > v ? step * 10 : -step * 10);
    return clamp(roundToStep(n, step, min), min, max);
}

/** Tick marks for a track. `ticks` is a count (evenly spread), or an explicit array of values. */
export function ticks(min, max, spec) {
    if (Array.isArray(spec)) return spec.filter((t) => t >= min && t <= max);
    const n = Number(spec) || 0;
    if (n < 2) return [];
    return Array.from({ length: n }, (_, i) => min + ((max - min) * i) / (n - 1));
}

/**
 * Keep a from/to pair valid after one end moved. `moved` = 'from' | 'to'. The other end is pushed (not the mover blocked)
 * only when `push` is true; otherwise the mover stops at the gap. Returns {from,to,error}.
 */
export function constrainRange(from, to, { min, max, step = 1, minGap = 0, maxGap = Infinity, moved = 'from', push = false }) {
    from = clamp(roundToStep(from, step, min), min, max);
    to = clamp(roundToStep(to, step, min), min, max);
    if (moved === 'from') {
        if (to - from < minGap) {
            if (push && from + minGap <= max) to = from + minGap;
            else from = to - minGap;
        }
        if (to - from > maxGap) push ? (to = from + maxGap) : (from = to - maxGap);
    } else {
        if (to - from < minGap) {
            if (push && to - minGap >= min) from = to - minGap;
            else to = from + minGap;
        }
        if (to - from > maxGap) push ? (from = to - maxGap) : (to = from + maxGap);
    }
    from = clamp(from, min, max);
    to = clamp(to, min, max);
    let error = null;
    if (to < from) error = 'The end must be after the start.';
    else if (to - from < minGap) error = `Must span at least ${minGap}.`;
    else if (to - from > maxGap) error = `Must span at most ${maxGap}.`;
    return { from, to, error };
}

// ---------------------------------------------------------------------------------------------- money (strings only)

/**
 * Normalise whatever a person typed ("₦1,234.5", "1 234,5" is NOT supported: comma = thousands) into a plain decimal string
 * with exactly `scale` decimals, or '' when empty, or null when unparseable. Excess decimals are rounded half-up on the
 * string digits (BigInt), never with floats.
 */
export function normalizeMoney(input, scale = 2, { allowNegative = false } = {}) {
    if (input === null || input === undefined) return '';
    let s = String(input).trim().replace(/[₦\s,]/g, '').replace(/^NGN/i, '');
    if (s === '') return '';
    let neg = false;
    if (s.startsWith('-')) {
        if (!allowNegative) return null;
        neg = true;
        s = s.slice(1);
    }
    if (!/^\d*\.?\d*$/.test(s) || s === '.') return null;
    const [rawInt = '', rawFrac = ''] = s.split('.');
    const roundUp = rawFrac.length > scale && rawFrac.charCodeAt(scale) - 48 >= 5;
    // Work on the digits as a BigInt: exact, no float anywhere.
    let units = BigInt((rawInt || '0') + rawFrac.slice(0, scale).padEnd(scale, '0'));
    if (roundUp) units += 1n;
    const digits = units.toString().padStart(scale + 1, '0');
    const out = scale > 0 ? `${digits.slice(0, -scale)}.${digits.slice(-scale)}` : digits;
    return neg && units !== 0n ? `-${out}` : out;
}

/** Add two non-negative decimal strings with BigInt scaling (used for rounding and totals). */
export function addDecimalStrings(a, b, scale = 2) {
    const toBig = (x) => {
        const [i, f = ''] = String(x).split('.');
        return BigInt(i + f.padEnd(scale, '0').slice(0, scale));
    };
    const sum = (toBig(a) + toBig(b)).toString().padStart(scale + 1, '0');
    return scale > 0 ? `${sum.slice(0, -scale)}.${sum.slice(-scale)}` : sum;
}

/** value +/- step (dir = 1 | -1) on decimal strings, exact; never below zero unless `allowNegative`. */
export function stepMoney(value, step = '1', dir = 1, scale = 2, allowNegative = false) {
    const cur = normalizeMoney(value === '' || value === null || value === undefined ? '0' : value, scale, { allowNegative: true }) ?? normalizeMoney('0', scale);
    const stp = normalizeMoney(step, scale) ?? normalizeMoney('1', scale);
    const toUnits = (s) => BigInt(s.replace('.', ''));
    let units = toUnits(cur) + BigInt(dir) * toUnits(stp);
    if (units < 0n && !allowNegative) units = 0n;
    const neg = units < 0n;
    const digits = (neg ? -units : units).toString().padStart(scale + 1, '0');
    const out = scale > 0 ? `${digits.slice(0, -scale)}.${digits.slice(-scale)}` : digits;
    return neg ? `-${out}` : out;
}

/** Compare two decimal strings: -1, 0, 1. Handles signs and differing scales. */
export function compareMoney(a, b) {
    const parse = (x) => {
        const s = String(x);
        const neg = s.startsWith('-');
        const [i, f = ''] = s.replace('-', '').split('.');
        return { neg, i: BigInt(i || '0'), f };
    };
    const A = parse(a);
    const B = parse(b);
    const scale = Math.max(A.f.length, B.f.length);
    const big = (p) => (p.neg ? -1n : 1n) * BigInt(p.i.toString() + p.f.padEnd(scale, '0'));
    const x = big(A);
    const y = big(B);
    return x < y ? -1 : x > y ? 1 : 0;
}

/** "1234567.5" -> "1,234,567.50". Trailing zeros beyond `minDecimals` are trimmed, so 4-dp API strings read naturally. */
export function formatMoney(value, minDecimals = 2) {
    if (value === '' || value === null || value === undefined) return '';
    const s = String(value);
    const neg = s.startsWith('-');
    let [int, frac = ''] = s.replace('-', '').split('.');
    frac = frac.replace(/0+$/, '');
    frac = frac.padEnd(minDecimals, '0');
    int = int.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return (neg ? '-' : '') + int + (frac ? `.${frac}` : '');
}

/** Live-typing formatter: keeps the caret-friendly partial input ("1,234.") while inserting thousands separators. */
export function formatMoneyTyping(raw, scale = 2) {
    let s = String(raw).replace(/[₦\s,]/g, '').replace(/[^\d.]/g, '');
    const dot = s.indexOf('.');
    let int = dot === -1 ? s : s.slice(0, dot);
    let frac = dot === -1 ? null : s.slice(dot + 1).replace(/\./g, '').slice(0, scale);
    int = int.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    if (int === '' && frac !== null) int = '0';
    return frac === null ? int : `${int}.${frac}`;
}

// ---------------------------------------------------------------------------------------------- duration

export const UNIT_SECONDS = { seconds: 1, minutes: 60, hours: 3600, days: 86400 };
const UNIT_ORDER = ['days', 'hours', 'minutes', 'seconds'];

export function toSeconds(value, unit) {
    return value * (UNIT_SECONDS[unit] ?? 1);
}

/** Convert between units; result is rounded to 6 dp to avoid float dust. */
export function convertDuration(value, from, to) {
    return Number(((value * UNIT_SECONDS[from]) / UNIT_SECONDS[to]).toFixed(6));
}

/** Largest unit (from `allowed`) that shows the value as a whole number: 600 s -> {10, 'minutes'}. */
export function bestUnit(seconds, allowed = UNIT_ORDER) {
    if (!seconds) return { value: 0, unit: allowed[allowed.length - 1] };
    for (const u of UNIT_ORDER.filter((x) => allowed.includes(x))) {
        const v = seconds / UNIT_SECONDS[u];
        if (Number.isInteger(v)) return { value: v, unit: u };
    }
    const u = allowed[allowed.length - 1];
    return { value: Number((seconds / UNIT_SECONDS[u]).toFixed(4)), unit: u };
}

/** "1 day 2 hours", "10 minutes", "45 seconds". */
export function humanDuration(seconds) {
    if (seconds === null || seconds === undefined || seconds === '') return '';
    let s = Math.round(seconds);
    if (s === 0) return '0 minutes';
    const parts = [];
    for (const u of UNIT_ORDER) {
        const n = Math.floor(s / UNIT_SECONDS[u]);
        if (n > 0) {
            parts.push(`${n} ${u.slice(0, -1)}${n === 1 ? '' : 's'}`);
            s -= n * UNIT_SECONDS[u];
        }
    }
    return parts.slice(0, 2).join(' ');
}

// ---------------------------------------------------------------------------------------------- time of day

/** "9", "9am", "9:30 pm", "0930", "21:05" -> "HH:MM" (24h) or null. */
export function parseTime(input) {
    if (input === null || input === undefined) return null;
    const s = String(input).trim().toLowerCase().replace(/\./g, '');
    if (s === '') return null;
    const m = s.match(/^(\d{1,2})(?::?(\d{2}))?\s*(am|pm|a|p)?$/);
    if (!m) return null;
    let h = parseInt(m[1], 10);
    const min = m[2] ? parseInt(m[2], 10) : 0;
    const mer = m[3];
    if (min > 59) return null;
    if (mer) {
        if (h < 1 || h > 12) return null;
        if (mer.startsWith('p') && h < 12) h += 12;
        if (mer.startsWith('a') && h === 12) h = 0;
    } else if (h > 24 || (h === 24 && min > 0)) return null;
    if (h === 24) return '24:00';
    return `${String(h).padStart(2, '0')}:${String(min).padStart(2, '0')}`;
}

export function timeToMinutes(t) {
    if (!t) return null;
    const [h, m] = t.split(':').map(Number);
    return h * 60 + m;
}

export function minutesToTime(total) {
    const t = Math.max(0, Math.min(1440, Math.round(total)));
    return `${String(Math.floor(t / 60)).padStart(2, '0')}:${String(t % 60).padStart(2, '0')}`;
}

/** "13:05" -> "1:05 PM". "24:00" -> "12:00 AM (midnight)". */
export function formatTime12(t) {
    if (!t) return '';
    const [h, m] = t.split(':').map(Number);
    if (h === 24) return '12:00 AM';
    const suffix = h >= 12 ? 'PM' : 'AM';
    return `${h % 12 === 0 ? 12 : h % 12}:${String(m).padStart(2, '0')} ${suffix}`;
}

/** Minutes between two times; an end at or before the start means "next day" when `overnight` is allowed. */
export function spanMinutes(from, to, overnight = false) {
    const a = timeToMinutes(from);
    const b = timeToMinutes(to);
    if (a === null || b === null) return null;
    if (b > a) return b - a;
    return overnight ? 1440 - a + b : b - a;
}

// ---------------------------------------------------------------------------------------------- weekly hours

export const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
export const DAY_LABELS = { mon: 'Monday', tue: 'Tuesday', wed: 'Wednesday', thu: 'Thursday', fri: 'Friday', sat: 'Saturday', sun: 'Sunday' };

/** Validate one day's intervals [{from,to}]: order, min length, overlaps. Returns a list of messages (empty = valid). */
export function validateIntervals(intervals, { overnight = false } = {}) {
    const errors = [];
    const spans = [];
    intervals.forEach((iv, i) => {
        const a = timeToMinutes(iv.from);
        const b = timeToMinutes(iv.to);
        if (a === null || b === null) return errors.push(`Interval ${i + 1}: enter both times.`);
        if (b <= a && !overnight) return errors.push(`Interval ${i + 1}: closing time must be after opening time.`);
        spans.push([a, b <= a ? b + 1440 : b, i + 1]);
    });
    spans.sort((x, y) => x[0] - y[0]);
    for (let i = 1; i < spans.length; i++) {
        if (spans[i][0] < spans[i - 1][1]) errors.push(`Intervals ${spans[i - 1][2]} and ${spans[i][2]} overlap.`);
    }
    return errors;
}

/** Deep clone of a day's intervals onto other days. `schedule` = {mon:{open,intervals}}. Returns a new schedule. */
export function copyDay(schedule, fromDay, toDays) {
    const next = JSON.parse(JSON.stringify(schedule));
    const src = schedule[fromDay];
    for (const d of toDays) if (d !== fromDay) next[d] = JSON.parse(JSON.stringify(src));
    return next;
}

/** Human summary "Mon-Fri 08:00-22:00, Sat 09:00-23:00, Sun closed". */
export function summariseWeek(schedule) {
    const key = (d) => (schedule[d]?.open ? schedule[d].intervals.map((i) => `${i.from}-${i.to}`).join(', ') : 'closed');
    const out = [];
    let start = 0;
    for (let i = 1; i <= DAYS.length; i++) {
        if (i === DAYS.length || key(DAYS[i]) !== key(DAYS[start])) {
            const a = DAYS[start];
            const b = DAYS[i - 1];
            out.push(`${cap(a)}${a === b ? '' : '-' + cap(b)} ${key(a)}`);
            start = i;
        }
    }
    return out.join(', ');
}
const cap = (s) => s.charAt(0).toUpperCase() + s.slice(1);

// ---------------------------------------------------------------------------------------------- dates

export const pad2 = (n) => String(n).padStart(2, '0');
export const isoDate = (d) => `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
export const parseIso = (s) => {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
    if (!m) return null;
    const d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    return d.getMonth() === Number(m[2]) - 1 ? d : null;
};
export const addDays = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);

export const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const MON3 = MONTHS.map((m) => m.slice(0, 3));

/** 6 x 7 grid of days for a month view. weekStart: 1 = Monday (default), 0 = Sunday. */
export function monthGrid(year, month, weekStart = 1) {
    const first = new Date(year, month, 1);
    const offset = (first.getDay() - weekStart + 7) % 7;
    const start = addDays(first, -offset);
    return Array.from({ length: 42 }, (_, i) => {
        const d = addDays(start, i);
        return { iso: isoDate(d), day: d.getDate(), out: d.getMonth() !== month };
    });
}

/** "2026-09-24" -> "24 Sep 2026". */
export function formatDate(iso) {
    const d = parseIso(iso);
    return d ? `${d.getDate()} ${MON3[d.getMonth()]} ${d.getFullYear()}` : '';
}

/** Accepts "2026-09-24", "24/09/2026", "24-9-26", "24 Sep 2026", "Sep 24 2026". Day-first, as in Nigeria. Returns ISO or null. */
export function parseDateInput(text) {
    const s = String(text ?? '').trim();
    if (s === '') return null;
    if (parseIso(s)) return s;
    let m = /^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{2}|\d{4})$/.exec(s);
    let d;
    let mo;
    let y;
    if (m) [d, mo, y] = [Number(m[1]), Number(m[2]), Number(m[3])];
    else if ((m = /^(\d{1,2})\s+([A-Za-z]{3,9})\.?,?\s+(\d{2}|\d{4})$/.exec(s))) [d, mo, y] = [Number(m[1]), MON3.findIndex((x) => x.toLowerCase() === m[2].slice(0, 3).toLowerCase()) + 1, Number(m[3])];
    else if ((m = /^([A-Za-z]{3,9})\.?\s+(\d{1,2}),?\s+(\d{2}|\d{4})$/.exec(s))) [d, mo, y] = [Number(m[2]), MON3.findIndex((x) => x.toLowerCase() === m[1].slice(0, 3).toLowerCase()) + 1, Number(m[3])];
    else return null;
    if (y < 100) y += 2000;
    const iso = `${y}-${pad2(mo)}-${pad2(d)}`;
    return parseIso(iso) ? iso : null;
}

/** Presets for date-range: today, yesterday, last7, last30, thisWeek (Mon start), thisMonth, lastMonth. `today` is injectable for tests. */
export function presetRange(name, today = new Date()) {
    const t = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    const monday = addDays(t, -((t.getDay() + 6) % 7));
    const map = {
        today: [t, t],
        yesterday: [addDays(t, -1), addDays(t, -1)],
        last7: [addDays(t, -6), t],
        last30: [addDays(t, -29), t],
        thisWeek: [monday, addDays(monday, 6)],
        thisMonth: [new Date(t.getFullYear(), t.getMonth(), 1), new Date(t.getFullYear(), t.getMonth() + 1, 0)],
        lastMonth: [new Date(t.getFullYear(), t.getMonth() - 1, 1), new Date(t.getFullYear(), t.getMonth(), 0)],
    };
    const r = map[name];
    return r ? { from: isoDate(r[0]), to: isoDate(r[1]) } : null;
}

export function validateDateRange(from, to, { min = null, max = null, maxDays = null } = {}) {
    const a = parseIso(from);
    const b = parseIso(to);
    if (from && !a) return 'Start date is not valid.';
    if (to && !b) return 'End date is not valid.';
    if (a && b && b < a) return 'The end date must be on or after the start date.';
    if (a && min && from < min) return `Start date cannot be before ${min}.`;
    if (b && max && to > max) return `End date cannot be after ${max}.`;
    if (a && b && maxDays && Math.round((b - a) / 86400000) + 1 > maxDays) return `Pick at most ${maxDays} days.`;
    return null;
}

// ---------------------------------------------------------------------------------------------- files, colours, misc

export function humanBytes(n) {
    if (n < 1024) return `${n} B`;
    if (n < 1024 * 1024) return `${(n / 1024).toFixed(n < 10240 ? 1 : 0)} KB`;
    return `${(n / 1024 / 1024).toFixed(1)} MB`;
}

/** accept = ".png,.jpg,image/*,application/pdf". Returns an error string or null. */
export function validateFile(file, { maxBytes = null, accept = '' } = {}) {
    if (maxBytes && file.size > maxBytes) return `${file.name} is ${humanBytes(file.size)}; the limit is ${humanBytes(maxBytes)}.`;
    const rules = String(accept).split(',').map((s) => s.trim().toLowerCase()).filter(Boolean);
    if (rules.length) {
        const name = String(file.name).toLowerCase();
        const type = String(file.type || '').toLowerCase();
        const ok = rules.some((r) => (r.startsWith('.') ? name.endsWith(r) : r.endsWith('/*') ? type.startsWith(r.slice(0, -1)) : type === r));
        if (!ok) return `${file.name} is not an accepted file type (${accept}).`;
    }
    return null;
}

/** "#0F7" | "0f7d4f" -> "#0f7d4f" or null. */
export function normalizeHex(input) {
    let s = String(input ?? '').trim().replace(/^#/, '').toLowerCase();
    if (/^[0-9a-f]{3}$/.test(s)) s = s.split('').map((c) => c + c).join('');
    return /^[0-9a-f]{6}$/.test(s) ? `#${s}` : null;
}

/** WCAG contrast ratio of two hex colours, to warn about unreadable label colours. */
export function contrastRatio(a, b) {
    const lum = (hex) => {
        const [r, g, bl] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255).map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));
        return 0.2126 * r + 0.7152 * g + 0.0722 * bl;
    };
    const [hi, lo] = [lum(a), lum(b)].sort((x, y) => y - x);
    return (hi + 0.05) / (lo + 0.05);
}

/** Typed confirmation: case-sensitive by default, whitespace-trimmed. */
export function confirmMatches(typed, phrase, { caseSensitive = true } = {}) {
    const a = String(typed ?? '').trim();
    const b = String(phrase ?? '').trim();
    return b !== '' && (caseSensitive ? a === b : a.toLowerCase() === b.toLowerCase());
}

/** Key/value rows: returns messages for empty and duplicate keys. */
export function validateKeyValues(rows, { keyPattern = null } = {}) {
    const errors = [];
    const seen = new Set();
    rows.forEach((r, i) => {
        const k = String(r.key ?? '').trim();
        if (k === '' && String(r.value ?? '').trim() !== '') errors.push(`Row ${i + 1}: add a key.`);
        else if (k !== '') {
            if (seen.has(k)) errors.push(`Row ${i + 1}: "${k}" is already used.`);
            if (keyPattern && !new RegExp(keyPattern).test(k)) errors.push(`Row ${i + 1}: "${k}" has invalid characters.`);
            seen.add(k);
        }
    });
    return errors;
}

/** Deep, order-insensitive-for-objects equality used by the dirty indicator (numbers compare as numbers, "" == null). */
export function same(a, b) {
    if (a === b) return true;
    const blank = (x) => x === '' || x === null || x === undefined;
    if (blank(a) && blank(b)) return true;
    if (typeof a === 'object' && a && typeof b === 'object' && b) {
        const ka = Object.keys(a);
        const kb = Object.keys(b);
        if (ka.length !== kb.length) return false;
        return ka.every((k) => same(a[k], b[k]));
    }
    if (typeof a === 'boolean' || typeof b === 'boolean') return String(a) === String(b);
    if (!blank(a) && !blank(b) && !Number.isNaN(Number(a)) && !Number.isNaN(Number(b))) return Number(a) === Number(b);
    return String(a) === String(b);
}
