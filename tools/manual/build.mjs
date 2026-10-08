// Builds resources/manual/SERI-Resort-Staff-Manual.pdf from resources/manual/staff-manual.md.
// Needs Google Chrome (headless print) and pdftotext (poppler) to put real page numbers in the contents.
//   cd tools/manual && npm install && npm run build
import { readFileSync, writeFileSync, mkdtempSync, existsSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import MarkdownIt from 'markdown-it';

const here = dirname(fileURLToPath(import.meta.url));
const root = join(here, '..', '..');
const source = join(root, 'resources', 'manual', 'staff-manual.md');
const output = join(root, 'resources', 'manual', 'SERI-Resort-Staff-Manual.pdf');
const css = readFileSync(join(here, 'manual.css'), 'utf8');
const chrome = process.env.CHROME_BIN ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
if (!existsSync(chrome)) throw new Error(`Chrome not found at ${chrome} (set CHROME_BIN)`);

const EDITION = process.env.MANUAL_EDITION ?? 'Edition 1  ·  8 October 2026';
const PARTS = [
  { from: 1, to: 4, name: 'Start here' },
  { from: 5, to: 10, name: 'On the floor' },
  { from: 11, to: 15, name: 'Back office' },
  { from: 16, to: 19, name: 'Reference' },
];
const partOf = (n) => PARTS.find((p) => n >= p.from && n <= p.to).name;

const md = new MarkdownIt({ html: false, linkify: false, typographer: false });
const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

// split into chapters on "## N. Title"
const chapters = [];
for (const block of readFileSync(source, 'utf8').split(/^## /m).slice(1)) {
  const nl = block.indexOf('\n');
  const heading = block.slice(0, nl).trim();
  const m = heading.match(/^(\d+)\.\s+(.*)$/);
  if (!m) throw new Error(`Chapter heading not numbered: ${heading}`);
  chapters.push({ n: Number(m[1]), title: m[2], body: block.slice(nl + 1) });
}

function decorate(html) {
  // group each <h3> with what follows until the next <h3>
  const parts = html.split(/(?=<h3>)/);
  return parts.map((chunk, i) => {
    if (i === 0 && !chunk.startsWith('<h3>')) {
      return chunk
        .replace(/<p>(<strong>Your job:<\/strong>[\s\S]*?)<\/p>/, '<p class="p-job">$1</p>')
        .replace(/<p>(<strong>Your device:<\/strong>[\s\S]*?)<\/p>/, '<p class="p-device">$1</p>')
        .replace(/<p>(<strong>Check with IT:<\/strong>[\s\S]*?)<\/p>/g, '<p class="note-it">$1</p>');
    }
    const heading = (chunk.match(/^<h3>(.*?)<\/h3>/) ?? [])[1] ?? '';
    const cls = heading === 'Never do this' ? 'sec sec-never' : heading === 'If something goes wrong' ? 'sec sec-wrong' : 'sec';
    return `<div class="${cls}">${chunk.replace(/<p>(<strong>Check with IT:<\/strong>[\s\S]*?)<\/p>/g, '<p class="note-it">$1</p>')}</div>`;
  }).join('');
}

function page(tocPages) {
  const toc = PARTS.map((part) => {
    const items = chapters.filter((c) => c.n >= part.from && c.n <= part.to)
      .map((c) => `<li><span class="n">${c.n}</span><span class="t">${esc(c.title)}</span><span class="p">${tocPages[c.n] ?? '0'}</span></li>`).join('');
    return `<div class="part">${part.name}</div><ol>${items}</ol>`;
  }).join('');

  const body = chapters.map((c) => `
    <section class="chapter" id="ch${c.n}">
      <div class="chapter-head"><div class="num">${c.n}</div><div><div class="part">${partOf(c.n)}</div><h2>${esc(c.title)}</h2></div></div>
      ${decorate(md.render(c.body))}
    </section>`).join('');

  return `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>SERI Resort - Staff Operations Manual</title><style>${css}</style></head><body>
  <section class="cover"><div class="inner">
    <div class="brand">SERI Resort</div>
    <h1>Staff Operations<br>Manual</h1><div class="rule"></div>
    <div class="sub">How to do your job on the system, step by step: waiters, cashiers, supervisors, kitchen, stores, accounts, managers, IT and the website.</div>
  </div><div class="foot"><span>${EDITION}</span><span>Internal use  ·  SERI Resort staff only</span></div></section>
  <section class="toc"><h2>Contents</h2><p class="lead">Read chapters 2 and 17 first, then your own chapter. Chapter 1 shows which chapters are yours.</p>${toc}</section>
  ${body}</body></html>`;
}

function print(html, pdfPath) {
  const dir = mkdtempSync(join(tmpdir(), 'manual-'));
  const file = join(dir, 'manual.html');
  writeFileSync(file, html);
  execFileSync(chrome, ['--headless=new', '--disable-gpu', '--no-pdf-header-footer', `--print-to-pdf=${pdfPath}`, '--virtual-time-budget=4000', `file://${file}`], { stdio: 'ignore' });
}

function findPages(pdfPath) {
  const pages = {};
  const total = Number(/Pages:\s+(\d+)/.exec(execFileSync('pdfinfo', [pdfPath]).toString())[1]);
  const flat = (s) => s.replace(/\s+/g, ' ').trim();
  for (let p = 3; p <= total; p++) { // 1 = cover, 2 = contents
    const head = execFileSync('pdftotext', ['-f', String(p), '-l', String(p), '-layout', pdfPath, '-']).toString()
      .split('\n').filter((l) => l.trim()).slice(0, 4).map(flat).join(' ');
    for (const c of chapters) {
      if (!pages[c.n] && head.includes(`${c.n} ${flat(c.title)}`)) pages[c.n] = p;
    }
  }
  return { pages, total };
}

const pass1 = join(tmpdir(), `manual-pass1-${process.pid}.pdf`);
print(page({}), pass1);
const { pages } = findPages(pass1);
const missing = chapters.filter((c) => !pages[c.n]).map((c) => c.n);
if (missing.length) throw new Error(`Could not find the page of chapter(s): ${missing.join(', ')}`);
print(page(pages), output);
const { total } = findPages(output);
console.log(`Wrote ${output} (${total} pages)`);
console.log('Chapter pages:', JSON.stringify(pages));
