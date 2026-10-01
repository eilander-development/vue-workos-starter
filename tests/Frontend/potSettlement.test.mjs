import assert from "node:assert/strict";
import { test } from "node:test";
import { buildSync } from "esbuild";

const bundled = buildSync({
  entryPoints: [new URL("../../resources/js/sparen/potSettlement.ts", import.meta.url).pathname],
  bundle: true,
  write: false,
  platform: "node",
  format: "esm",
});
const { computePotSettlement, potCompensationStatus } = await import(
  `data:text/javascript;base64,${Buffer.from(bundled.outputFiles[0].text).toString("base64")}`
);
const goal = { id: "groceries", name: "spaarrekening H13134210", kind: "pot", initialAmount: 0, monthlyContribution: 500, categoryBudgetItemId: "groceries" };
const month = { monthId: "sep", year: 2026, items: [{ id: "groceries", actual: 500 }] };
const deposit = { id: "deposit", date: "2026-09-24", description: "Naar Oranje spaarrekeningH13134210", amount: -1000, type: "Sparen" };
const spending = { id: "expense", date: "2026-09-25", description: "Groceries", amount: -577.71, type: "Uitgave", budgetItemId: "groceries" };
const withdrawal = { id: "withdrawal", date: "2026-09-26", description: "Van Oranje spaarrekeningH13134210", amount: 200, type: "Sparen" };

test("funding alone does not compensate spending", () => {
  const result = computePotSettlement(goal, month, [deposit, spending]);
  assert.equal(result.compensated, 0);
  assert.equal(result.available, 1000);
  assert.equal(result.toTransfer, 577.71);
  assert.equal(potCompensationStatus(result).sufficient, false);
});

test("partial compensation uses actual withdrawals", () => {
  const result = computePotSettlement(goal, month, [deposit, spending, withdrawal]);
  assert.equal(result.compensated, 200);
  assert.equal(result.available, 800);
  assert.equal(result.toTransfer, 377.71);
});

test("full compensation matches the displayed transaction total", () => {
  const result = computePotSettlement(goal, month, [deposit, spending, { ...withdrawal, amount: 577.71 }]);
  assert.equal(result.compensated, 577.71);
  assert.equal(result.available, 422.29);
  assert.equal(result.toTransfer, 0);
  assert.equal(potCompensationStatus(result).sufficient, true);
});

test("pending, other-pot and out-of-period withdrawals are excluded", () => {
  const result = computePotSettlement(goal, month, [deposit, spending,
    { ...withdrawal, isPending: true },
    { ...withdrawal, date: "2026-09-14" },
    { ...withdrawal, date: "2026-10-15" },
    { ...withdrawal, description: "Van Oranje spaarrekeningX99999999" },
  ]);
  assert.equal(result.compensated, 0);
  assert.equal(result.compensationTransactions.length, 0);
});

test("surplus transfers remain visible instead of being capped at spending", () => {
  const result = computePotSettlement(goal, month, [deposit, spending, { ...withdrawal, amount: 600 }]);
  assert.equal(result.compensated, 600);
  assert.equal(result.available, 400);
  assert.equal(result.toTransfer, 0);
});

test("funding total is explained by the exact listed deposits", () => {
  const first = { ...deposit, id: "4220", amount: -500 };
  const second = { ...deposit, id: "4224", amount: -500 };
  const result = computePotSettlement(goal, month, [first, second, withdrawal,
    { ...deposit, id: "pending", isPending: true },
    { ...deposit, id: "earlier", date: "2026-09-14" },
    { ...deposit, id: "other", description: "Naar Oranje spaarrekeningX99999999" },
  ]);
  assert.deepEqual(result.depositTransactions.map(tx => tx.id), ["4220", "4224"]);
  assert.equal(result.deposited, 1000);
  assert.equal(result.deposited, result.depositTransactions.reduce((sum, tx) => sum - tx.amount, 0));
});
