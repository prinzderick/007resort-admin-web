/*
 * Pure helpers for the website (CMS) screens: Markdown, slugs, list reordering, recurrence.
 * No DOM access here so everything is unit-tested with `node --test` (tests/js/cms.test.mjs).
 */

export function escapeHtml(s) {
    return String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/** Only http(s), mailto, tel, site-relative and #anchor links survive; anything else (javascript:, data:) becomes "#". */
export function safeUrl(u) {
    const url = String(u ?? '').trim().replace(/&amp;/g, '&');
    if (/^(https?:\/\/|mailto:|tel:|\/(?!\/)|#)/i.test(url)) {
        return escapeHtml(url);
    }
    return '#';
}

function inline(raw) {
    let s = escapeHtml(raw);
    const codes = [];
    s = s.replace(/`([^`]+)`/g, (_, c) => `\u0000${codes.push(`<code>${c}</code>`) - 1}\u0000`);
    s = s.replace(/!\[([^\]]*)\]\(([^)\s]+)\)/g, (_, alt, url) => `<img src="${safeUrl(url)}" alt="${alt}" loading="lazy">`);
    s = s.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (_, text, url) => `<a href="${safeUrl(url)}" rel="noopener">${text}</a>`);
    s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>').replace(/__([^_]+)__/g, '<strong>$1</strong>');
    s = s.replace(/(^|[^*\w])\*([^*\s][^*]*)\*(?!\*)/g, '$1<em>$2</em>').replace(/(^|[^_\w])_([^_\s][^_]*)_(?!\w)/g, '$1<em>$2</em>');
    return s.replace(/\u0000(\d+)\u0000/g, (_, i) => codes[Number(i)]);
}

/** A small, safe Markdown subset: headings, paragraphs, bold/italic, code, links, images, quotes, rules, bullet and numbered lists. */
export function renderMarkdown(src) {
    const lines = String(src ?? '').replace(/\r\n?/g, '\n').split('\n');
    const out = [];
    let para = [];
    let list = null; // {tag, items}
    const flushPara = () => { if (para.length) { out.push(`<p>${para.map(inline).join('<br>')}</p>`); para = []; } };
    const flushList = () => { if (list) { out.push(`<${list.tag}>${list.items.map((i) => `<li>${inline(i)}</li>`).join('')}</${list.tag}>`); list = null; } };
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        let m;
        if ((m = line.match(/^```/))) {
            flushPara(); flushList();
            const code = [];
            i++;
            while (i < lines.length && !lines[i].startsWith('```')) { code.push(lines[i]); i++; }
            out.push(`<pre><code>${escapeHtml(code.join('\n'))}</code></pre>`);
        } else if (line.trim() === '') {
            flushPara(); flushList();
        } else if ((m = line.match(/^(#{1,6})\s+(.*)$/))) {
            flushPara(); flushList();
            out.push(`<h${m[1].length}>${inline(m[2].trim())}</h${m[1].length}>`);
        } else if (/^\s*([-*_])(\s*\1){2,}\s*$/.test(line)) {
            flushPara(); flushList();
            out.push('<hr>');
        } else if ((m = line.match(/^>\s?(.*)$/))) {
            flushPara(); flushList();
            out.push(`<blockquote>${inline(m[1])}</blockquote>`);
        } else if ((m = line.match(/^\s*[-*+]\s+(.*)$/))) {
            flushPara();
            if (!list || list.tag !== 'ul') { flushList(); list = { tag: 'ul', items: [] }; }
            list.items.push(m[1]);
        } else if ((m = line.match(/^\s*\d+[.)]\s+(.*)$/))) {
            flushPara();
            if (!list || list.tag !== 'ol') { flushList(); list = { tag: 'ol', items: [] }; }
            list.items.push(m[1]);
        } else {
            flushList();
            para.push(line.trim());
        }
    }
    flushPara(); flushList();
    return out.join('\n');
}

/**
 * Toolbar actions on a text selection. state = {value, start, end}; returns the new state (same shape).
 * Actions: bold, italic, h2, h3, ul, ol, quote, link (opts.url), image (opts.url, opts.alt).
 */
export function applyFormat(state, action, opts = {}) {
    const { value } = state;
    let { start, end } = state;
    if (start > end) { [start, end] = [end, start]; }
    const sel = value.slice(start, end);
    const wrap = (l, r, placeholder) => {
        const text = sel || placeholder;
        const v = value.slice(0, start) + l + text + r + value.slice(end);
        return { value: v, start: start + l.length, end: start + l.length + text.length };
    };
    const lineStart = value.lastIndexOf('\n', start - 1) + 1;
    let lineEnd = value.indexOf('\n', end);
    if (lineEnd === -1) { lineEnd = value.length; }
    const prefixLines = (fn) => {
        const block = value.slice(lineStart, lineEnd).split('\n');
        const allOn = block.every((l, i) => fn(i).test.test(l));
        const stripped = block.map((l) => l.replace(/^(#{1,6}\s+|[-*+]\s+|\d+[.)]\s+|>\s?)/, ''));
        const next = allOn ? stripped : block.map((l, i) => fn(i).make(stripped[i]));
        const text = next.join('\n');
        return { value: value.slice(0, lineStart) + text + value.slice(lineEnd), start: lineStart, end: lineStart + text.length };
    };
    switch (action) {
        case 'bold': return wrap('**', '**', 'bold text');
        case 'italic': return wrap('*', '*', 'italic text');
        case 'h2': return prefixLines(() => ({ test: /^##\s/, make: (l) => `## ${l}` }));
        case 'h3': return prefixLines(() => ({ test: /^###\s/, make: (l) => `### ${l}` }));
        case 'ul': return prefixLines(() => ({ test: /^[-*+]\s/, make: (l) => `- ${l}` }));
        case 'ol': return prefixLines((i) => ({ test: /^\d+[.)]\s/, make: (l) => `${i + 1}. ${l}` }));
        case 'quote': return prefixLines(() => ({ test: /^>\s?/, make: (l) => `> ${l}` }));
        case 'link': {
            const text = sel || 'link text';
            const md = `[${text}](${opts.url || 'https://'})`;
            return { value: value.slice(0, start) + md + value.slice(end), start: start + 1, end: start + 1 + text.length };
        }
        case 'image': {
            const md = `![${opts.alt || sel || 'Describe the image'}](${opts.url || ''})`;
            return { value: value.slice(0, start) + md + value.slice(end), start: start + md.length, end: start + md.length };
        }
        default: return state;
    }
}

/** "Spa & Wellness 2026!" -> "spa-wellness-2026" */
export function slugify(s, max = 80) {
    return String(s ?? '')
        .normalize('NFKD').replace(/[̀-ͯ]/g, '')
        .toLowerCase().replace(/&/g, ' and ').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')
        .slice(0, max).replace(/-+$/g, '');
}

/** Move one item of an array (returns a new array; out-of-range targets clamp). */
export function move(list, from, to) {
    const a = list.slice();
    if (from < 0 || from >= a.length) { return a; }
    const t = Math.max(0, Math.min(a.length - 1, to));
    const [item] = a.splice(from, 1);
    a.splice(t, 0, item);
    return a;
}

const pad = (n) => String(n).padStart(2, '0');
const iso = (d) => `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())}`;
const parse = (s) => { const [y, m, d] = String(s).split('-').map(Number); return new Date(Date.UTC(y, m - 1, d)); };

/**
 * Next occurrences of an event. recurrence: 'none' | 'weekly'; dates are plain Lagos calendar dates (YYYY-MM-DD, Lagos has no DST).
 * Returns up to `n` dates on/after `today` (never before the first date), stopping at `until` (inclusive).
 */
export function occurrences({ start, recurrence = 'none', until = null, today = null, n = 5 }) {
    if (!start || !/^\d{4}-\d{2}-\d{2}$/.test(start)) { return []; }
    const floor = today && today > start ? today : start;
    const out = [];
    if (recurrence !== 'weekly') {
        return start >= floor ? [start] : [];
    }
    const cur = parse(start);
    const stop = until && /^\d{4}-\d{2}-\d{2}$/.test(until) ? until : null;
    for (let guard = 0; guard < 2000 && out.length < n; guard++) {
        const d = iso(cur);
        if (stop && d > stop) { break; }
        if (d >= floor) { out.push(d); }
        cur.setUTCDate(cur.getUTCDate() + 7);
    }
    return out;
}

/** Human file size. */
export function bytes(n) {
    n = Number(n) || 0;
    if (n < 1024) { return `${n} B`; }
    if (n < 1048576) { return `${(n / 1024).toFixed(0)} KB`; }
    return `${(n / 1048576).toFixed(1)} MB`;
}
