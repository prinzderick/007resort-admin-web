import test from 'node:test';
import assert from 'node:assert/strict';
import * as L from '../../resources/js/cms/lib.js';

test('markdown renders the supported subset and escapes HTML', () => {
    assert.equal(L.renderMarkdown('# Title'), '<h1>Title</h1>');
    assert.equal(L.renderMarkdown('Hello **bold** and *it*'), '<p>Hello <strong>bold</strong> and <em>it</em></p>');
    assert.equal(L.renderMarkdown('- a\n- b'), '<ul><li>a</li><li>b</li></ul>');
    assert.equal(L.renderMarkdown('1. a\n2. b'), '<ol><li>a</li><li>b</li></ol>');
    assert.equal(L.renderMarkdown('> quote'), '<blockquote>quote</blockquote>');
    assert.equal(L.renderMarkdown('one\ntwo'), '<p>one<br>two</p>');
    assert.match(L.renderMarkdown('<script>alert(1)</script>'), /&lt;script&gt;/);
    assert.doesNotMatch(L.renderMarkdown('<b onclick=x>'), /<b /);
});

test('markdown links and images only allow safe URLs', () => {
    assert.match(L.renderMarkdown('[a](https://x.com/?a=1&b=2)'), /href="https:\/\/x\.com\/\?a=1&amp;b=2"/);
    assert.match(L.renderMarkdown('[a](javascript:alert(1))'), /href="#"/);
    assert.match(L.renderMarkdown('![pool](/media/p.jpg)'), /<img src="\/media\/p\.jpg" alt="pool"/);
    assert.match(L.renderMarkdown('![x](data:text/html;base64,AAA)'), /src="#"/);
    assert.doesNotMatch(L.renderMarkdown('[a](https://x.com" onmouseover="y)'), /<a /);
});

test('code spans and fences are not formatted inside', () => {
    assert.equal(L.renderMarkdown('use `**x**` here'), '<p>use <code>**x**</code> here</p>');
    assert.equal(L.renderMarkdown('```\n<b>\n```'), '<pre><code>&lt;b&gt;</code></pre>');
});

test('toolbar: bold wraps the selection or a placeholder', () => {
    let r = L.applyFormat({ value: 'hello world', start: 6, end: 11 }, 'bold');
    assert.equal(r.value, 'hello **world**');
    assert.equal(r.value.slice(r.start, r.end), 'world');
    r = L.applyFormat({ value: '', start: 0, end: 0 }, 'italic');
    assert.equal(r.value, '*italic text*');
});

test('toolbar: headings and lists prefix lines and toggle off again', () => {
    let r = L.applyFormat({ value: 'a\nb', start: 0, end: 3 }, 'ul');
    assert.equal(r.value, '- a\n- b');
    r = L.applyFormat({ value: r.value, start: 0, end: r.value.length }, 'ul');
    assert.equal(r.value, 'a\nb');
    r = L.applyFormat({ value: 'a\nb', start: 0, end: 3 }, 'ol');
    assert.equal(r.value, '1. a\n2. b');
    r = L.applyFormat({ value: 'Title', start: 2, end: 2 }, 'h2');
    assert.equal(r.value, '## Title');
    r = L.applyFormat({ value: '# Old', start: 0, end: 0 }, 'h3');
    assert.equal(r.value, '### Old');
});

test('toolbar: link and image insert markdown', () => {
    let r = L.applyFormat({ value: 'see menu', start: 4, end: 8 }, 'link', { url: '/menu' });
    assert.equal(r.value, 'see [menu](/menu)');
    r = L.applyFormat({ value: 'x ', start: 2, end: 2 }, 'image', { url: '/m/1.jpg', alt: 'Pool' });
    assert.equal(r.value, 'x ![Pool](/m/1.jpg)');
});

test('slugify', () => {
    assert.equal(L.slugify('Spa & Wellness 2026!'), 'spa-and-wellness-2026');
    assert.equal(L.slugify('  Crème Brûlée  '), 'creme-brulee');
    assert.equal(L.slugify('---'), '');
    assert.equal(L.slugify('a'.repeat(200), 10), 'aaaaaaaaaa');
});

test('move reorders and clamps', () => {
    assert.deepEqual(L.move([1, 2, 3], 0, 2), [2, 3, 1]);
    assert.deepEqual(L.move([1, 2, 3], 2, 0), [3, 1, 2]);
    assert.deepEqual(L.move([1, 2, 3], 1, 9), [1, 3, 2]);
    assert.deepEqual(L.move([1, 2, 3], 7, 0), [1, 2, 3]);
});

test('occurrences: weekly until a date, never before today', () => {
    assert.deepEqual(L.occurrences({ start: '2026-10-02', recurrence: 'weekly', until: '2026-10-23', today: '2026-09-24' }), ['2026-10-02', '2026-10-09', '2026-10-16', '2026-10-23']);
    assert.deepEqual(L.occurrences({ start: '2026-09-01', recurrence: 'weekly', until: '2026-12-31', today: '2026-09-24', n: 2 }), ['2026-09-29', '2026-10-06']);
    assert.deepEqual(L.occurrences({ start: '2026-10-02', recurrence: 'none' , today: '2026-09-24'}), ['2026-10-02']);
    assert.deepEqual(L.occurrences({ start: '2026-08-02', recurrence: 'none', today: '2026-09-24' }), []);
    assert.deepEqual(L.occurrences({ start: 'bad' }), []);
    assert.equal(L.occurrences({ start: '2026-10-02', recurrence: 'weekly', today: '2026-09-24', n: 5 }).length, 5);
});

test('bytes', () => {
    assert.equal(L.bytes(500), '500 B');
    assert.equal(L.bytes(2048), '2 KB');
    assert.equal(L.bytes(5 * 1048576), '5.0 MB');
});
