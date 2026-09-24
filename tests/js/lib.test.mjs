import test from 'node:test';
import assert from 'node:assert/strict';
import * as L from '../../resources/js/form/lib.js';

test('roundToStep has no float dust', () => {
    assert.equal(L.roundToStep(0.30000000000000004, 0.1), 0.3);
    assert.equal(L.roundToStep(7, 5, 0), 5);
    assert.equal(L.roundToStep(8, 5, 0), 10);
    assert.equal(L.roundToStep(33, 5, 3), 33); // grid anchored at min
});

test('snap: clamps, snaps to points within tolerance, else to the step grid', () => {
    const o = { min: 0, max: 100, step: 5, points: [0, 25, 50, 75, 100] };
    assert.equal(L.snap(-20, o), 0);
    assert.equal(L.snap(140, o), 100);
    assert.equal(L.snap(26, o), 25);
    assert.equal(L.snap(48, o), 50);
    assert.equal(L.snap(37, { ...o, points: [] }), 35);
    assert.equal(L.snap(33, { min: 0, max: 100, step: 1, points: [33.3], tolerance: 1 }), 33.3, 'off-grid snap points stay reachable');
});

test('pct and valueAt', () => {
    assert.equal(L.pct(50, 0, 100), 50);
    assert.equal(L.pct(5, 5, 240), 0);
    assert.equal(L.pct(999, 0, 10), 100);
    assert.equal(L.pct(1, 1, 1), 0);
    assert.equal(L.valueAt(0.5, 10, 20), 15);
    assert.equal(L.valueAt(2, 10, 20), 20);
});

test('keyValue: arrows, page, home/end, clamps', () => {
    const o = { min: 0, max: 100, step: 5 };
    assert.equal(L.keyValue('ArrowRight', 50, o), 55);
    assert.equal(L.keyValue('ArrowLeft', 50, o), 45);
    assert.equal(L.keyValue('ArrowUp', 100, o), 100);
    assert.equal(L.keyValue('PageUp', 50, o), 60);
    assert.equal(L.keyValue('Home', 50, o), 0);
    assert.equal(L.keyValue('End', 50, o), 100);
    assert.equal(L.keyValue('ArrowRight', 50, { ...o, shift: true }), 100);
    assert.equal(L.keyValue('a', 50, o), null);
});

test('ticks', () => {
    assert.deepEqual(L.ticks(0, 100, 5), [0, 25, 50, 75, 100]);
    assert.deepEqual(L.ticks(0, 100, [10, 500, 90]), [10, 90]);
    assert.deepEqual(L.ticks(0, 100, 1), []);
});

test('constrainRange enforces the gap in both directions', () => {
    const o = { min: 0, max: 100, step: 1, minGap: 10 };
    assert.deepEqual(L.constrainRange(45, 50, { ...o, moved: 'from' }), { from: 40, to: 50, error: null });
    assert.deepEqual(L.constrainRange(45, 50, { ...o, moved: 'to' }), { from: 45, to: 55, error: null });
    assert.deepEqual(L.constrainRange(45, 50, { ...o, moved: 'from', push: true }), { from: 45, to: 55, error: null });
    assert.deepEqual(L.constrainRange(0, 100, { ...o, maxGap: 30, moved: 'from' }), { from: 70, to: 100, error: null });
    assert.equal(L.constrainRange(95, 100, { ...o, moved: 'to' }).from >= 0, true);
});

test('normalizeMoney: strings in, strings out, exact', () => {
    assert.equal(L.normalizeMoney('₦1,234.5'), '1234.50');
    assert.equal(L.normalizeMoney('0.1'), '0.10');
    assert.equal(L.normalizeMoney('1234567890123456789.99'), '1234567890123456789.99', 'beyond 2^53 stays exact');
    assert.equal(L.normalizeMoney('19.999'), '20.00', 'half-up on digits');
    assert.equal(L.normalizeMoney('0.004'), '0.00');
    assert.equal(L.normalizeMoney('0.005'), '0.01');
    assert.equal(L.normalizeMoney('12', 4), '12.0000');
    assert.equal(L.normalizeMoney('12.5', 0), '13');
    assert.equal(L.normalizeMoney('.5'), '0.50');
    assert.equal(L.normalizeMoney('007'), '7.00');
    assert.equal(L.normalizeMoney(''), '');
    assert.equal(L.normalizeMoney('abc'), null);
    assert.equal(L.normalizeMoney('1.2.3'), null);
    assert.equal(L.normalizeMoney('-5'), null);
    assert.equal(L.normalizeMoney('-5', 2, { allowNegative: true }), '-5.00');
    assert.equal(L.normalizeMoney('-0.001', 2, { allowNegative: true }), '0.00');
    assert.equal(L.normalizeMoney(0.1 + 0.2), '0.30');
});

test('compareMoney handles scales and signs', () => {
    assert.equal(L.compareMoney('10.00', '10'), 0);
    assert.equal(L.compareMoney('0.0000', '0.00'), 0);
    assert.equal(L.compareMoney('9.99', '10'), -1);
    assert.equal(L.compareMoney('-1', '0.5'), -1);
    assert.equal(L.compareMoney('12345678901234567890.01', '12345678901234567890.00'), 1);
});

test('formatMoney and typing formatter', () => {
    assert.equal(L.formatMoney('1234567.5'), '1,234,567.50');
    assert.equal(L.formatMoney('0.0000'), '0.00');
    assert.equal(L.formatMoney('1000.1250'), '1,000.125');
    assert.equal(L.formatMoney('-1234.00'), '-1,234.00');
    assert.equal(L.formatMoney(''), '');
    assert.equal(L.formatMoneyTyping('1234'), '1,234');
    assert.equal(L.formatMoneyTyping('1234.'), '1,234.');
    assert.equal(L.formatMoneyTyping('₦1234567.891'), '1,234,567.89');
    assert.equal(L.formatMoneyTyping('.5'), '0.5');
});

test('duration conversion and best unit', () => {
    assert.equal(L.convertDuration(600, 'seconds', 'minutes'), 10);
    assert.equal(L.convertDuration(1.5, 'hours', 'minutes'), 90);
    assert.deepEqual(L.bestUnit(600), { value: 10, unit: 'minutes' });
    assert.deepEqual(L.bestUnit(86400), { value: 1, unit: 'days' });
    assert.deepEqual(L.bestUnit(90), { value: 90, unit: 'seconds' });
    assert.deepEqual(L.bestUnit(5400, ['minutes', 'hours']), { value: 90, unit: 'minutes' });
    assert.equal(L.humanDuration(93784), '1 day 2 hours');
    assert.equal(L.humanDuration(600), '10 minutes');
    assert.equal(L.humanDuration(1), '1 second');
});

test('parseTime accepts the ways people type', () => {
    assert.equal(L.parseTime('9'), '09:00');
    assert.equal(L.parseTime('9am'), '09:00');
    assert.equal(L.parseTime('9:30 pm'), '21:30');
    assert.equal(L.parseTime('12am'), '00:00');
    assert.equal(L.parseTime('12pm'), '12:00');
    assert.equal(L.parseTime('0930'), '09:30');
    assert.equal(L.parseTime('21:05'), '21:05');
    assert.equal(L.parseTime('24:00'), '24:00');
    assert.equal(L.parseTime('25:00'), null);
    assert.equal(L.parseTime('13pm'), null);
    assert.equal(L.parseTime('9:75'), null);
    assert.equal(L.parseTime('nope'), null);
    assert.equal(L.formatTime12('13:05'), '1:05 PM');
    assert.equal(L.formatTime12('00:00'), '12:00 AM');
    assert.equal(L.spanMinutes('22:00', '02:00', true), 240);
    assert.equal(L.spanMinutes('22:00', '02:00', false), -1200);
});

test('validateIntervals: order, overlap, overnight', () => {
    assert.deepEqual(L.validateIntervals([{ from: '08:00', to: '12:00' }, { from: '14:00', to: '22:00' }]), []);
    assert.equal(L.validateIntervals([{ from: '08:00', to: '12:00' }, { from: '11:00', to: '22:00' }]).length, 1);
    assert.equal(L.validateIntervals([{ from: '12:00', to: '08:00' }]).length, 1);
    assert.deepEqual(L.validateIntervals([{ from: '20:00', to: '02:00' }], { overnight: true }), []);
    assert.equal(L.validateIntervals([{ from: '', to: '08:00' }]).length, 1);
});

test('copyDay and summariseWeek', () => {
    const wk = Object.fromEntries(L.DAYS.map((d) => [d, { open: false, intervals: [] }]));
    wk.mon = { open: true, intervals: [{ from: '08:00', to: '22:00' }] };
    const out = L.copyDay(wk, 'mon', ['tue', 'wed', 'thu', 'fri']);
    assert.equal(out.fri.open, true);
    out.fri.intervals[0].from = '09:00';
    assert.equal(out.mon.intervals[0].from, '08:00', 'deep copy');
    assert.equal(L.summariseWeek({ ...out, fri: out.thu }), 'Mon-Fri 08:00-22:00, Sat-Sun closed');
});

test('presetRange is deterministic with an injected today', () => {
    const today = new Date(2026, 8, 24); // Thu 24 Sep 2026
    assert.deepEqual(L.presetRange('today', today), { from: '2026-09-24', to: '2026-09-24' });
    assert.deepEqual(L.presetRange('last7', today), { from: '2026-09-18', to: '2026-09-24' });
    assert.deepEqual(L.presetRange('thisWeek', today), { from: '2026-09-21', to: '2026-09-27' });
    assert.deepEqual(L.presetRange('thisMonth', today), { from: '2026-09-01', to: '2026-09-30' });
    assert.deepEqual(L.presetRange('lastMonth', today), { from: '2026-08-01', to: '2026-08-31' });
    assert.equal(L.presetRange('nope', today), null);
});

test('validateDateRange', () => {
    assert.equal(L.validateDateRange('2026-09-01', '2026-09-30'), null);
    assert.match(L.validateDateRange('2026-09-30', '2026-09-01'), /on or after/);
    assert.match(L.validateDateRange('2026-02-31', '2026-03-01'), /not valid/);
    assert.match(L.validateDateRange('2026-09-01', '2026-12-30', { maxDays: 31 }), /at most 31/);
    assert.match(L.validateDateRange('2026-01-01', '2026-01-05', { min: '2026-06-01' }), /before/);
});

test('validateFile checks size and accept list', () => {
    assert.equal(L.validateFile({ name: 'a.png', size: 100, type: 'image/png' }, { maxBytes: 1000, accept: 'image/*' }), null);
    assert.match(L.validateFile({ name: 'a.png', size: 5000, type: 'image/png' }, { maxBytes: 1000 }), /limit/);
    assert.match(L.validateFile({ name: 'a.exe', size: 1, type: 'application/x-msdownload' }, { accept: '.png,.jpg,image/*' }), /not an accepted/);
    assert.equal(L.validateFile({ name: 'A.PNG', size: 1, type: '' }, { accept: '.png' }), null);
    assert.equal(L.humanBytes(2048), '2.0 KB');
});

test('colours', () => {
    assert.equal(L.normalizeHex('0F7'), '#00ff77');
    assert.equal(L.normalizeHex('#0f7d4f'), '#0f7d4f');
    assert.equal(L.normalizeHex('zz'), null);
    assert.ok(L.contrastRatio('#000000', '#ffffff') > 20.9);
});

test('confirmMatches, key/values, same()', () => {
    assert.equal(L.confirmMatches(' DELETE ', 'DELETE'), true);
    assert.equal(L.confirmMatches('delete', 'DELETE'), false);
    assert.equal(L.confirmMatches('delete', 'DELETE', { caseSensitive: false }), true);
    assert.equal(L.confirmMatches('', ''), false);
    assert.equal(L.validateKeyValues([{ key: 'a', value: '1' }, { key: 'a', value: '2' }]).length, 1);
    assert.equal(L.validateKeyValues([{ key: '', value: 'x' }]).length, 1);
    assert.ok(L.same('0.00', '0.0000'));
    assert.ok(L.same(5, '5'));
    assert.ok(L.same(null, ''));
    assert.ok(!L.same(true, false));
    assert.ok(L.same({ a: 1, b: [1, 2] }, { b: [1, 2], a: '1' }));
});

test('monthGrid is a 6x7 Monday-first grid', () => {
    const g = L.monthGrid(2026, 8); // September 2026 starts on a Tuesday
    assert.equal(g.length, 42);
    assert.equal(g[0].iso, '2026-08-31');
    assert.equal(g[0].out, true);
    assert.equal(g[1].iso, '2026-09-01');
    assert.equal(g[1].out, false);
    assert.equal(L.monthGrid(2026, 8, 0)[0].iso, '2026-08-30');
});

test('parseDateInput is day-first and forgiving', () => {
    assert.equal(L.parseDateInput('2026-09-24'), '2026-09-24');
    assert.equal(L.parseDateInput('24/09/2026'), '2026-09-24');
    assert.equal(L.parseDateInput('4-9-26'), '2026-09-04');
    assert.equal(L.parseDateInput('24 Sep 2026'), '2026-09-24');
    assert.equal(L.parseDateInput('Sept 24, 2026'), '2026-09-24');
    assert.equal(L.parseDateInput('31/02/2026'), null);
    assert.equal(L.parseDateInput('nonsense'), null);
    assert.equal(L.formatDate('2026-09-24'), '24 Sep 2026');
});

test('stepMoney is exact and never negative by default', () => {
    assert.equal(L.stepMoney('0.10', '0.10', 1), '0.20');
    assert.equal(L.stepMoney('0.30', '0.10', -1), '0.20');
    assert.equal(L.stepMoney('0.00', '1', -1), '0.00');
    assert.equal(L.stepMoney('', '500', 1), '500.00');
    assert.equal(L.stepMoney('9999999999999999.99', '0.01', 1), '10000000000000000.00');
    assert.equal(L.stepMoney('0.00', '1', -1, 2, true), '-1.00');
});
