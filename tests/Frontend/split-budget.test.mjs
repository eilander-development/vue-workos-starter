import assert from 'node:assert/strict';
import { test } from 'node:test';
import { buildSync } from 'esbuild';

const bundled = buildSync({ entryPoints: [new URL('../../resources/js/sparen/splitStatus.ts', import.meta.url).pathname], bundle: true, write: false, platform: 'node', format: 'esm' });
const { reviewSplit, splitAllocationsFromBudget } = await import(`data:text/javascript;base64,${Buffer.from(bundled.outputFiles[0].text).toString('base64')}`);
const month = { monthId: 'okt', year: 2026 };
const rule = { id: 'inshared', name: 'InShared', keyword: 'inshared', matchField: 'description', targetType: 'uitgaven', isActive: true, allocations: [{ budgetItemId: 'car', amount: 60 }, { budgetItemId: 'home', amount: 40 }] };
const budgets = [{ id: 'car', name: 'Auto', actual: 70 }, { id: 'home', name: 'Woning', actual: 30 }];
const tx = { id: 'payment', description: 'InShared', date: '2026-10-20', amount: -100, type: 'Uitgave', matchedRuleId: rule.id, budgetItemId: 'car', allocations: rule.allocations };

test('split differences are visible per budget item even when the total matches the bank payment', () => {
    const review = reviewSplit(rule, budgets, [tx], month);
    assert.equal(review.bankVariance.kind, 'ok');
    assert.equal(review.budgetedTotal, 100);
    assert.equal(review.hasBudgetIssue, true);
    assert.equal(review.hasIssue, true);
    assert.equal(review.canAdoptBudget, true);
    assert.deepEqual(review.envelopes.map(row => [row.planned, row.budgeted, row.budgetDifference, row.filledBudgetVariance.amount]), [[60, 70, -10, -10], [40, 30, 10, 10]]);
});

test('budget adoption builds a new allocation draft without modifying rules, budgets or bank data', () => {
    const original = structuredClone({ rule, budgets, tx });
    const next = splitAllocationsFromBudget(rule.allocations, budgets);
    assert.deepEqual(next, [{ budgetItemId: 'car', amount: 70 }, { budgetItemId: 'home', amount: 30 }]);
    assert.deepEqual({ rule, budgets, tx }, original);
    const review = reviewSplit({ ...rule, allocations: next }, budgets, [{ ...tx, allocations: next }], month);
    assert.equal(review.hasBudgetIssue, false);
    assert.equal(review.hasIssue, false);
    assert.equal(review.canAdoptBudget, false);
});

test('missing, zero and duplicate budget items cannot silently create an incomplete split', () => {
    assert.equal(splitAllocationsFromBudget(rule.allocations, budgets.slice(0, 1)), null);
    assert.equal(splitAllocationsFromBudget(rule.allocations, [{ ...budgets[0], actual: 0 }, budgets[1]]), null);
    assert.equal(splitAllocationsFromBudget([rule.allocations[0], rule.allocations[0]], budgets), null);
    const review = reviewSplit(rule, budgets.slice(0, 1), [tx], month);
    assert.equal(review.budgetedTotal, null);
    assert.equal(review.envelopes[1].budgeted, null);
    assert.equal(review.hasBudgetIssue, true);
    assert.equal(review.canAdoptBudget, false);
});

test('adopting budget proportions does not conceal a bank amount different from the budget', () => {
    const next = splitAllocationsFromBudget(rule.allocations, budgets);
    const review = reviewSplit({ ...rule, allocations: next }, budgets, [{ ...tx, amount: -110, allocations: [{ budgetItemId: 'car', amount: 77 }, { budgetItemId: 'home', amount: 33 }] }], month);
    assert.equal(review.hasBudgetIssue, false);
    assert.equal(review.bankDifference, 10);
    assert.equal(review.hasIssue, true);
    assert.equal(review.envelopes[0].filledBudgetVariance.amount, 7);
});
