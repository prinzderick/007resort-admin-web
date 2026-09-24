#!/usr/bin/env node
/*
 * Full-page screenshots of the portal through headless Google Chrome (DevTools protocol; no npm dependencies, Node >= 22).
 *
 *   node scripts/screenshots.mjs --base http://127.0.0.1:8071 --user owner1 --password '...' --out docs/screenshots [--width 1440] [--only dashboard,devices] [--paths scripts/screenshot-pages.json]
 *
 * Logs in through the real sign-in form (so it exercises the real session), visits every page in the list, waits for the page
 * (and Livewire/charts) to settle, and writes <name>.png.
 */
import { spawn } from 'node:child_process';
import { readFileSync, mkdirSync, writeFileSync } from 'node:fs';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const args = Object.fromEntries(process.argv.slice(2).reduce((a, v, i, all) => (v.startsWith('--') ? [...a, [v.slice(2), all[i + 1]?.startsWith('--') || all[i + 1] === undefined ? 'true' : all[i + 1]]] : a), []));
const base = (args.base || 'http://127.0.0.1:8071').replace(/\/$/, '');
const out = args.out || 'docs/screenshots';
const width = Number(args.width || 1440);
const pages = JSON.parse(readFileSync(args.paths || new URL('./screenshot-pages.json', import.meta.url), 'utf8'));
const only = args.only ? new Set(args.only.split(',')) : null;
const chrome = args.chrome || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
mkdirSync(out, { recursive: true });

const port = 9300 + Math.floor(Math.random() * 500);
const proc = spawn(chrome, [`--remote-debugging-port=${port}`, '--headless=new', '--disable-gpu', '--hide-scrollbars', `--user-data-dir=${join(tmpdir(), 'r007-shots-' + port)}`, `--window-size=${width},900`, 'about:blank'], { stdio: 'ignore' });
process.on('exit', () => proc.kill());

let target;
for (let i = 0; i < 50; i++) {
    try { target = (await (await fetch(`http://127.0.0.1:${port}/json`)).json()).find((t) => t.type === 'page'); if (target) break; } catch {}
    await sleep(200);
}
if (!target) throw new Error('Chrome did not start');
const ws = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((r) => (ws.onopen = r));
let id = 0;
const waiting = new Map();
const events = [];
ws.onmessage = (m) => { const d = JSON.parse(m.data); if (d.id && waiting.has(d.id)) { waiting.get(d.id)(d); waiting.delete(d.id); } else events.push(d); };
const send = (method, params = {}) => new Promise((res) => { const i = ++id; waiting.set(i, res); ws.send(JSON.stringify({ id: i, method, params })); });
const evalJs = async (expression) => (await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true })).result?.result?.value;
async function go(url, settleMs = 1500) {
    events.length = 0;
    await send('Page.navigate', { url });
    for (let i = 0; i < 100; i++) { if (events.some((e) => e.method === 'Page.loadEventFired')) break; await sleep(100); }
    await sleep(settleMs);
}

await send('Page.enable');
await send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });

// sign in through the real form
await go(`${base}/login`, 300);
await evalJs(`document.querySelector('[name=identifier]').value = ${JSON.stringify(args.user || 'owner1')}; document.querySelector('[name=password]').value = ${JSON.stringify(args.password || '')}; document.querySelector('form').submit();`);
await sleep(2500);
if ((await evalJs('location.pathname')) === '/login') throw new Error('Sign-in failed');

const manifest = [];
for (const p of pages) {
    if (only && !only.has(p.name)) continue;
    await go(base + p.path, p.settle || 2200);
    if (p.eval) { await evalJs(p.eval); await sleep(800); }
    const h = Math.min(6000, Math.max(900, Number(await evalJs('Math.ceil(document.documentElement.scrollHeight)'))));
    await send('Emulation.setDeviceMetricsOverride', { width, height: h, deviceScaleFactor: 1, mobile: false });
    await sleep(300);
    const shot = await send('Page.captureScreenshot', { format: 'png' });
    writeFileSync(join(out, `${p.name}.png`), Buffer.from(shot.result.data, 'base64'));
    const title = await evalJs('document.title');
    // Layout audit: page-level sideways scroll and text that is cut off (an element narrower than its own content that hides the rest).
    const audit = await evalJs(`(() => { const w = document.documentElement.clientWidth; const over = document.documentElement.scrollWidth - w;
        const clipped = [...document.querySelectorAll('main *')].filter((e) => e.children.length === 0 && e.textContent.trim().length > 3 && e.scrollWidth > e.clientWidth + 2 && ['hidden', 'clip'].includes(getComputedStyle(e).overflowX) && getComputedStyle(e).textOverflow !== 'ellipsis' && !e.closest('.table-scroll, [x-cloak], .sr-only') && e.offsetParent !== null).slice(0, 3).map((e) => e.textContent.trim().slice(0, 30));
        const bad = [...document.querySelectorAll('[data-state="error"], [data-state="missing"], [data-state="unreachable"]')].map((e) => e.getAttribute('data-state')); const crashed = /Undefined (array key|variable|property)|Whoops|ErrorException|TypeError/.test(document.body.innerText); return JSON.stringify({ over, clipped, bad, crashed }); })()`);
    const a = JSON.parse(audit || '{"over":0,"clipped":[],"bad":[],"crashed":false}');
    manifest.push({ name: p.name, path: p.path, title, height: h, overflowX: a.over, clipped: a.clipped, failedReads: a.bad, crashed: a.crashed });
    console.log(a.over > 0 || a.clipped.length || a.bad.length || a.crashed ? 'WARN' : 'ok', p.name, h, a.over > 0 ? 'overflowX=' + a.over : '', a.clipped.length ? 'clipped=' + JSON.stringify(a.clipped) : '', a.bad.length ? 'failedReads=' + a.bad.join(',') : '', a.crashed ? 'CRASHED' : '');
    await send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });
}
writeFileSync(join(out, 'manifest.json'), JSON.stringify(manifest, null, 2));
ws.close();
proc.kill();
process.exit(0);
