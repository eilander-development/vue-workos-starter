import assert from 'node:assert/strict';
import { test } from 'node:test';
import { buildSync } from 'esbuild';

const bundled = buildSync({ entryPoints: [new URL('../../resources/js/sparen/budgetRhythm.ts', import.meta.url).pathname], bundle: true, write: false, platform: 'node', format: 'esm' });
const { budgetRhythmRows, budgetRhythmTotals, rhythmMonthsInScope } = await import(`data:text/javascript;base64,${Buffer.from(bundled.outputFiles[0].text).toString('base64')}`);
const months = [
    { month: '2025-12', year: 2025, amount: 100, periodStart: '2025-12-15', through: '2026-01-14', complete: true },
    { month: '2026-01', year: 2026, amount: 100, periodStart: '2026-01-15', through: '2026-02-01', complete: false },
];
const item = { id: 'insurance', type: 'uitgaven' };
const transaction = (id, date, overrides = {}) => ({ id, date, type: 'Uitgave', budgetItemId: item.id, amount: -100, ...overrides });

test('rhythm respects reporting periods, split allocations, type and the current cut-off', () => {
    const rows = budgetRhythmRows(item, [
        transaction('old', '2025-12-14'),
        transaction('dec', '2026-01-14'),
        transaction('jan', '2026-01-15', { amount: -300, allocations: [{ budgetItemId: item.id, amount: 80 }, { budgetItemId: 'other', amount: 220 }] }),
        transaction('wrong-type', '2026-01-20', { type: 'Inkomsten' }),
        transaction('other', '2026-01-20', { budgetItemId: 'other' }),
        transaction('pending', '2026-02-01', { isPending: true }),
        transaction('future', '2026-02-02'),
    ], months);
    assert.deepEqual(rows.map(row => row.tx.id), ['pending', 'jan', 'dec']);
    assert.equal(rows.find(row => row.tx.id === 'jan').amount, 80);
    const totals = budgetRhythmTotals(rows, months);
    assert.deepEqual(totals.map(month => [month.month, month.actual, month.count]), [['2025-12', 100, 1], ['2026-01', 80, 1]]);
});

test('clicking a year or month filters by the budget month across a calendar year boundary', () => {
    assert.deepEqual(rhythmMonthsInScope(months, { year: 2025 }).map(month => month.month), ['2025-12']);
    assert.deepEqual(rhythmMonthsInScope(months, { month: '2026-01' }).map(month => month.month), ['2026-01']);
    assert.equal(rhythmMonthsInScope(months, null).length, 2);
    assert.equal(rhythmMonthsInScope(months, { year: 2024 }).length, 0);
});
