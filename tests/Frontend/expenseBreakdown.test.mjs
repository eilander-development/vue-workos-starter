import assert from "node:assert/strict";
import { test } from "node:test";
import { buildSync } from "esbuild";

const bundled = buildSync({
  entryPoints: [new URL("../../resources/js/sparen/monthKpi.ts", import.meta.url).pathname],
  bundle: true,
  write: false,
  platform: "node",
  format: "esm",
});
const {
  buildExpenseModalBreakdown,
  buildBudgetExpenseModalBreakdown,
  buildDeferredPaymentModalBreakdown,
  computeMonthKpi,
} = await import(
  `data:text/javascript;base64,${Buffer.from(bundled.outputFiles[0].text).toString("base64")}`
);

function expense(overrides) {
  return {
    id: "expense",
    name: "Uitgave",
    group: "Overige Kosten",
    type: "uitgaven",
    estimated: 0,
    actual: 0,
    paidOrReceived: 0,
    ...overrides,
  };
}

test("unbudgeted spending remains visible in all expenses and overspending", () => {
  const spending = expense({ id: "fees", name: "Bankkosten", paidOrReceived: 7.20 });
  const items = [spending, expense({ id: "unused" })];

  const all = buildExpenseModalBreakdown(items, "all");
  const over = buildExpenseModalBreakdown(items, "over");

  assert.deepEqual(all.rows.map(row => row.id), ["fees"]);
  assert.equal(all.rows[0].budgetItem, spending);
  assert.deepEqual(all.totals, { budget: 0, paid: 7.20, remaining: 0, over: 7.20 });
  assert.equal(all.totalValue, 0);
  assert.equal(all.totalLabel, "Begroot");
  assert.deepEqual(over.rows.map(row => row.id), ["fees"]);
  assert.equal(over.totalValue, 7.20);
  assert.equal(over.totalLabel, "Overschrijding");
});

test("bank payments above budget remain uncapped and expose the excess", () => {
  const item = expense({ actual: 100, paidOrReceived: 120 });

  const result = buildExpenseModalBreakdown([item], "all");

  assert.deepEqual(result.rows[0].cells, {
    name: "Uitgave",
    group: "Overige Kosten",
    budget: 100,
    paid: 120,
    remaining: 0,
    over: 20,
  });
  assert.deepEqual(result.totals, { budget: 100, paid: 120, remaining: 0, over: 20 });
  assert.equal(result.totalValue, 100);
});

test("column totals reconcile in cents after adding fractional amounts", () => {
  const items = [
    expense({ id: "first", actual: 0.10, paidOrReceived: 0.30 }),
    expense({ id: "second", actual: 0.20, paidOrReceived: 0.40 }),
  ];

  const result = buildExpenseModalBreakdown(items, "over");

  assert.deepEqual(result.totals, { budget: 0.30, paid: 0.70, remaining: 0, over: 0.40 });
  assert.equal(result.totalValue, 0.40);
});

test("fixed, monthly and deferred expenses contribute once to matching column totals", () => {
  const rent = expense({ id: "rent", name: "Huur", actual: 1000, paidOrReceived: 1000 });
  const monthly = expense({ id: "monthly", actual: 150, paidOrReceived: 50,
    monthEntries: [{ id: "entry", description: "Deze maand", amount: 150 }] });
  const klarna = expense({ id: "klarna", name: "Klarna", actual: 200, paidOrReceived: 70,
    monthEntries: [{ id: "klarna-entry", description: "Aflossing", amount: 200 }] });
  const creditcard = expense({ id: "creditcard", name: "Creditcard", actual: 300, paidOrReceived: 350 });
  const items = [rent, monthly, klarna, creditcard];

  const all = buildExpenseModalBreakdown(items, "all");
  const deferred = buildExpenseModalBreakdown(items, "deferred");
  const over = buildExpenseModalBreakdown(items, "over");
  const kpi = computeMonthKpi({ incomeItems: [], expenseItems: items, savingsItems: [], bankBalance: 0 });

  assert.deepEqual(all.rows.map(row => row.id), ["rent", "monthly", "klarna", "creditcard"]);
  assert.deepEqual(all.totals, { budget: 1650, paid: 1470, remaining: 230, over: 50 });
  assert.equal(all.totalValue, 1650);
  assert.equal(all.rows[1].budgetItem, monthly);
  assert.equal(kpi.totalExpenseFixedBudget, 1300);
  assert.equal(kpi.totalExpenseRulesBudget, 350);
  assert.equal(kpi.expenseFixedCount, 2);
  assert.equal(kpi.expenseBudgetedCount, 4);
  assert.equal(kpi.totalExpenseBudget, all.totalValue);
  assert.equal(kpi.totalExpenseOver, all.totals.over);
  assert.deepEqual(deferred.rows.map(row => row.id), ["klarna", "creditcard"]);
  assert.deepEqual(deferred.totals, { budget: 500, paid: 420, remaining: 130, over: 50 });
  assert.equal(deferred.totalValue, 130);
  assert.equal(deferred.totalLabel, "Nog open");
  assert.deepEqual(over.rows.map(row => row.id), ["creditcard"]);
  assert.equal(over.totalValue, 50);
});

test("existing expense and deferred entry points also expose unbudgeted bank payments", () => {
  const items = [expense({ name: "Klarna", paidOrReceived: 7.20 })];
  const kpi = computeMonthKpi({ incomeItems: [], expenseItems: items, savingsItems: [], bankBalance: 0 });

  const all = buildBudgetExpenseModalBreakdown(items, kpi);
  const deferred = buildDeferredPaymentModalBreakdown(items);

  assert.equal(all.rows[0].cells.paid, 7.20);
  assert.equal(all.rows[0].cells.over, 7.20);
  assert.equal(deferred.rows[0].cells.paid, 7.20);
  assert.equal(deferred.rows[0].cells.over, 7.20);
});

for (const view of ["all", "deferred", "over"]) {
  test(`${view} rows open the original expense only after clicking`, () => {
    const item = expense({ name: "Klarna", actual: 100, paidOrReceived: 120 });
    const opened = [];

    const result = buildExpenseModalBreakdown([item], view, selected => opened.push(selected));

    assert.deepEqual(opened, []);
    result.rows[0].onClick();
    assert.deepEqual(opened, [item]);
    assert.equal(opened[0], item);
  });
}

test("an empty expense selection produces zero totals without clickable rows", () => {
  const result = buildExpenseModalBreakdown([], "over");

  assert.deepEqual(result.rows, []);
  assert.deepEqual(result.totals, { budget: 0, paid: 0, remaining: 0, over: 0 });
  assert.equal(result.totalValue, 0);
});
