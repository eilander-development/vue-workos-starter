import assert from 'node:assert/strict';
import { test } from 'node:test';
import { buildSync } from 'esbuild';

async function loadModule(name) {
  const result = buildSync({ entryPoints: [new URL(`../../resources/js/sparen/${name}.ts`, import.meta.url).pathname], bundle: true, write: false, platform: 'node', format: 'esm' });
  return import(`data:text/javascript;base64,${Buffer.from(result.outputFiles[0].text).toString('base64')}`);
}
const { transactionStatusTotals, isBookedTransaction, transactionStatusLabel } = await loadModule('transactionStatus');
const { sumRawBankTotals } = await loadModule('monthKpi');
const { computePeriodCashflow } = await loadModule('cashflow');
const { computePotSettlement } = await loadModule('potSettlement');
const expense = { id: 'expense', date: '2026-09-25', amount: -100.01, type: 'Uitgave', description: 'Groceries', budgetItemId: 'groceries', categoryGroup: 'Dagelijks Leven', accountIban: '', source: 'EnableBanking' };

test('status policy handles old records and keeps history excluded even without the pending flag', () => {
  assert.equal(isBookedTransaction(expense), true);
  assert.equal(isBookedTransaction({ ...expense, status: 'reserved' }), false);
  assert.equal(isBookedTransaction({ ...expense, isPending: true }), false);
  assert.equal(isBookedTransaction({ ...expense, status: 'history' }), false);
  assert.equal(transactionStatusLabel({ status: 'history', isPending: true }), 'Historie');
  assert.equal(transactionStatusLabel({ status: 'reserved' }), 'Reservering');
});

test('booked and reserved totals stay separate and never include archived copies', () => {
  const result = transactionStatusTotals([expense, { ...expense, isPending: true }, { ...expense, status: 'history', amount: -999 }, { ...expense, amount: 20.02 }, { ...expense, status: 'reserved', amount: 5.01 }]);
  assert.deepEqual(result, { bookedIn: 20.02, bookedOut: 100.01, reservedIn: 5.01, reservedOut: 100.01, reservedCount: 2 });
});

test('actual bank KPIs and period cash flow count one booked row, not its reservation or history', () => {
  const rows = [expense, { ...expense, status: 'reserved' }, { ...expense, status: 'history', isPending: false, amount: -999 }];
  assert.deepEqual(sumRawBankTotals(rows), { totalIncomeBank: 0, totalExpenseBank: 100.01, totalSavingsBank: 0 });
  const flow = computePeriodCashflow(rows, { monthId: 'sep', year: 2026 });
  assert.equal(flow.spent, 100.01);
  assert.equal(flow.transactions.spent.length, 1);
});

test('a reservation cannot create pot compensation and history cannot recreate spending', () => {
  const goal = { id: 'groceries', name: 'spaarrekening H13134210', kind: 'pot', initialAmount: 0, monthlyContribution: 500, categoryBudgetItemId: 'groceries' };
  const month = { monthId: 'sep', year: 2026, items: [{ id: 'groceries', actual: 500 }] };
  const rows = [{ ...expense, status: 'reserved' }, { ...expense, status: 'history' }];
  assert.equal(computePotSettlement(goal, month, rows).toTransfer, 0);
  assert.equal(computePotSettlement(goal, month, [...rows, expense]).toTransfer, 100.01);
});
