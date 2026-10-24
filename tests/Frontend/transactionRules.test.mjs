import assert from "node:assert/strict";
import { test } from "node:test";
import { buildSync } from "esbuild";

async function bundle(file) {
  const result = buildSync({
    entryPoints: [new URL(`../../resources/js/sparen/${file}`, import.meta.url).pathname],
    bundle: true, write: false, platform: "node", format: "esm",
  });
  return import(`data:text/javascript;base64,${Buffer.from(result.outputFiles[0].text).toString("base64")}`);
}
const { applyTransactionRules } = await bundle("transactionRules.ts");
const { computeMonthKpi } = await bundle("monthKpi.ts");
const goal = { id: "goal", name: "spaarrekening Z98765432", accountIban: "", kind: "goal" };
const tx = { id: "tx", description: "Naar Oranje spaarrekening Z98765432", amount: -825, type: "Sparen", categoryGroup: "Spaargeld" };
const rule = { id: "specific", keyword: "Naar Oranje spaarrekening Z98765432", matchField: "description", isActive: true, targetType: "sparen", targetGroup: "Spaargeld", targetBudgetItemId: "savings" };
const generic = { ...rule, id: "generic", keyword: "Oranje spaarrekening", targetBudgetItemId: "wrong" };

test("a specific rule repairs an unlinked deposit and removes its duplicate forecast deduction", () => {
  const [linked] = applyTransactionRules([tx], [generic, rule], [goal]);
  assert.equal(linked.budgetItemId, "savings");
  assert.equal(linked.matchedRuleId, "specific");
  assert.equal(linked.assignedSavingsGoalId, goal.id);
  const item = { id: "savings", actual: 825, paidOrReceived: 0 };
  const before = computeMonthKpi({ incomeItems: [], expenseItems: [], savingsItems: [item], bankBalance: 1000 });
  const after = computeMonthKpi({ incomeItems: [], expenseItems: [], savingsItems: [{ ...item, paidOrReceived: Math.abs(linked.amount) }], bankBalance: 1000 });
  assert.equal(before.totalSavingsRemaining, 825);
  assert.equal(after.totalSavingsRemaining, 0);
  assert.equal(after.expectedEndOfMonth - before.expectedEndOfMonth, 825);
});

test("an explicit goal mapping takes precedence over a rule", () => {
  const [linked] = applyTransactionRules([tx], [rule], [{ ...goal, categoryBudgetItemId: "explicit" }]);
  assert.equal(linked.budgetItemId, "explicit");
  assert.equal(linked.matchedRuleId, undefined);
});

test("a generic rule cannot capture a recognized goal without its own mapping", () => {
  const [linked] = applyTransactionRules([tx], [generic], [goal]);
  assert.equal(linked.budgetItemId, undefined);
});

test("existing direct links survive reconciliation", () => {
  const [linked] = applyTransactionRules([{ ...tx, budgetItemId: "manual" }], [rule], [goal]);
  assert.equal(linked.budgetItemId, "manual");
});

test("withdrawals and pot deposits remain excluded from budget linking", () => {
  const [withdrawal] = applyTransactionRules([{ ...tx, description: "Van Oranje spaarrekening Z98765432", amount: 825 }], [rule], [goal]);
  const [pot] = applyTransactionRules([tx], [rule], [{ ...goal, kind: "pot" }]);
  assert.equal(withdrawal.linkExcluded, true);
  assert.equal(withdrawal.budgetItemId, undefined);
  assert.equal(pot.linkExcluded, true);
  assert.equal(pot.budgetItemId, undefined);
});
