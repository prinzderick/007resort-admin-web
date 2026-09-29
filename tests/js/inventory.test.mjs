import test from 'node:test';
import assert from 'node:assert/strict';
import { matchItem, firstEmptyRow } from '../../resources/js/inventory/lib.js';

const ITEMS = [
    { value: 'id-1', label: 'Star Lager (bottle)', sku: 'B1' },
    { value: 'id-2', label: 'Guinness (bottle)', sku: 'B2' },
    { value: 'id-3', label: 'Heineken (bottle)', sku: 'HK-99' },
];

test('matchItem resolves an exact SKU, case-insensitively', () => {
    assert.equal(matchItem(ITEMS, 'B1').value, 'id-1');
    assert.equal(matchItem(ITEMS, 'b1').value, 'id-1');
    assert.equal(matchItem(ITEMS, 'hk-99').value, 'id-3');
});

test('matchItem falls back to an exact label match', () => {
    assert.equal(matchItem(ITEMS, 'Guinness (bottle)').value, 'id-2');
    assert.equal(matchItem(ITEMS, 'GUINNESS (BOTTLE)').value, 'id-2');
});

test('matchItem never partial-matches: a half-typed code must not resolve', () => {
    // A "fast burst of digits then Enter" is simulated by calling matchItem once with the full string --
    // the scan box never resolves mid-keystroke, so a prefix of a real SKU must stay unmatched.
    assert.equal(matchItem(ITEMS, 'B'), null);
    assert.equal(matchItem(ITEMS, 'HK-9'), null);
    assert.equal(matchItem(ITEMS, 'Guinness'), null);
});

test('matchItem ignores blank input and non-array item lists', () => {
    assert.equal(matchItem(ITEMS, ''), null);
    assert.equal(matchItem(ITEMS, '   '), null);
    assert.equal(matchItem(null, 'B1'), null);
});

test('firstEmptyRow finds the first unset row and returns null once every row is taken', () => {
    const values = ['id-1', '', 'id-3'];
    assert.equal(firstEmptyRow(3, (i) => values[i]), 1);
    assert.equal(firstEmptyRow(1, (i) => values[i]), null);
    assert.equal(firstEmptyRow(3, (i) => 'id-'.concat(i)), null);
    assert.equal(firstEmptyRow(1, (i) => undefined), 0);
});
