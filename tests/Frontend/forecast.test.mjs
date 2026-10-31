import assert from "node:assert/strict";
import { test } from "node:test";
import { buildSync } from "esbuild";

const bundled = buildSync({ entryPoints: [new URL("../../resources/js/sparen/forecast.ts", import.meta.url).pathname], bundle: true, write: false, platform: "node", format: "esm" });
const { calculateForecast, addMonths, groupForecastItems } = await import(`data:text/javascript;base64,${Buffer.from(bundled.outputFiles[0].text).toString("base64")}`);
const rule = (overrides = {}) => ({ id: "r1", itemId: "income", effectiveMonth: "2027-05", operation: "percent", value: 3, enabled: true, ...overrides });
function source() {
  return {
    startMonth: "2027-05", sourceHash: "test", minMonth: "2026-11", boundaries: {},
    items: [{ id: "income", name: "Salaris", type: "inkomsten" }, { id: "insurance", name: "Verzekering", type: "uitgaven" }, { id: "savings", name: "Sparen", type: "sparen" }],
    months: Array.from({ length: 24 }, (_, i) => {
      const month = addMonths("2027-05", i);
      const quarterly = [1, 4, 7, 10].includes(Number(month.slice(5)));
      return { month, items: { income: { amount: 3000, scheduled: true }, insurance: { amount: quarterly ? 300 : 0, scheduled: quarterly }, savings: { amount: 200, scheduled: true } } };
    }),
  };
}

test("twelve independent months, with no mutation or rolling balance", () => {
  const data = source();
  const original = structuredClone(data);
  const snapshot = calculateForecast(data, [rule()]);
  assert.equal(snapshot.months.length, 12);
  assert.equal(snapshot.months[11].month, "2028-04");
  assert.equal(snapshot.months[0].after.free, 2890);
  assert.equal(snapshot.months[1].after.free, 2890);
  assert.deepEqual(data, original);
});

test("quarterly adjustment begins on the next scheduled payment", () => {
  const snapshot = calculateForecast(source(), [rule({ itemId: "insurance", value: 10 })]);
  assert.equal(snapshot.specs[0].firstPayment, "2027-07");
  assert.equal(snapshot.months[0].after.expenses, 0);
  assert.equal(snapshot.months[2].after.expenses, 330);
  assert.equal(snapshot.months[5].after.expenses, 330);
});

test("chronological rules compound once per change, not per month", () => {
  const snapshot = calculateForecast(source(), [rule({ id: "r2", effectiveMonth: "2027-09", value: 2 }), rule()]);
  assert.equal(snapshot.months[0].after.income, 3090);
  assert.equal(snapshot.months[4].after.income, 3151.8);
  assert.equal(snapshot.months[5].after.income, 3151.8);
});

test("existing later changes delimit the new rule", () => {
  const data = source();
  data.boundaries.income = ["2027-09"];
  for (const month of data.months.filter(month => month.month >= "2027-09")) month.items.income.amount = 3500;
  const snapshot = calculateForecast(data, [rule()]);
  assert.equal(snapshot.specs[0].endMonth, "2027-09");
  assert.equal(snapshot.months[3].after.income, 3090);
  assert.equal(snapshot.months[4].after.income, 3500);
});

test("disabled copied rules do not apply twice", () => {
  const snapshot = calculateForecast(source(), [rule({ enabled: false })]);
  assert.equal(snapshot.specs.length, 0);
  assert.equal(snapshot.months[0].after.income, 3000);
});

test("invalid and ambiguous adjustments are rejected", () => {
  assert.throws(() => calculateForecast(source(), [rule({ operation: "set", value: -1 })]));
  assert.throws(() => calculateForecast(source(), [rule(), rule({ id: "r2" })]));
  assert.throws(() => calculateForecast(source(), [rule({ effectiveMonth: "2028-05" })]));
});

test("last-month rules still get a complete fixed annual pattern", () => {
  const snapshot = calculateForecast(source(), [rule({ effectiveMonth: "2028-04", value: 10 })]);
  assert.equal(Object.keys(snapshot.specs[0].amounts).length, 12);
  assert.equal(snapshot.months[10].after.income, 3000);
  assert.equal(snapshot.months[11].after.income, 3300);
});

test('historical references are preserved as warnings and never counted as future expenses', () => {
  const data = source();
  data.items.push({ id: 'extra', name: 'Klarna', group: 'Overig', type: 'uitgaven' });
  for (const month of data.months) month.items.extra = { amount: 0, scheduled: false, excludedCost: {
    kind: 'historical', amount: 250, sourceMonth: '2026-05', reason: 'deferred', entries: [],
  } };
  const result = calculateForecast(data, [rule()]);
  assert.equal(result.version, 3);
  assert.equal(result.months[0].after.free, 2890);
  assert.equal(result.months[0].warnings[0].sourceMonth, '2026-05');
  assert.equal(result.months[0].warnings[0].kind, 'historical');
  assert.equal(result.months[0].warnings[0].amount, 250);
});

test('explicitly planned extras are separate from the structural result', () => {
  const data = source();
  data.items.push({ id: 'extra', name: 'Aankoop', type: 'uitgaven', group: 'Overig' });
  for (const month of data.months) month.items.extra = { amount: 0, scheduled: false };
  data.months[0].items.extra.excludedCost = { kind: 'planned', amount: 400, sourceMonth: '2027-05', reason: 'month_entries' };
  const result = calculateForecast(data, [rule()]);
  assert.equal(result.months[0].after.free, 2890);
  assert.equal(result.months[0].warnings[0].kind, 'planned');
  assert.equal(result.months[1].warnings.length, 0);
});

test('post groups keep income, fixed expenses and savings separate and reconcile subtotals', () => {
  const result = calculateForecast(source(), [rule()]);
  const groups = groupForecastItems(result, result.months[2]);
  assert.deepEqual(groups.map(group => group.type), ['inkomsten', 'uitgaven', 'sparen']);
  assert.deepEqual(groups.map(group => group.after), [3090, 300, 200]);
  assert.equal(groups[0].after - groups[1].after - groups[2].after, result.months[2].after.free);
  assert.equal(groups[1].label, 'Uitgaven (vast)');
  result.version = 1;
  assert.equal(groupForecastItems(result, result.months[2])[1].label, 'Uitgaven');
});

test('latest fixed amount preserves quarterly timing and composes with a rule', () => {
  const data = source();
  data.items.find(item => item.id === 'insurance').latestFixed = { amount: 330, sourceMonth: '2026-12' };
  const settings = [{ itemId: 'insurance', basis: 'latest', included: true, excludedMonths: [] }];
  const result = calculateForecast(data, [rule({ itemId: 'insurance', value: 10 })], settings);
  assert.equal(result.months[0].after.expenses, 0);
  assert.equal(result.months[2].after.expenses, 363);
  assert.equal(result.specs.length, 2);
  assert.equal(result.months[2].afterItems.insurance.sourceMonth, '2026-12');
});

test('exclusions affect only the forecast while keeping the baseline and source immutable', () => {
  const data = source();
  const original = structuredClone(data);
  const result = calculateForecast(data, [], [{ itemId: 'income', basis: 'profile', included: true, excludedMonths: ['2027-05'] }]);
  assert.equal(result.months[0].before.income, 3000);
  assert.equal(result.months[0].after.income, 0);
  assert.equal(result.months[1].after.income, 3000);
  assert.equal(result.months[0].afterItems.income.omitted, true);
  assert.deepEqual(data, original);
  assert.equal(result.specs.length, 0);
});

test('explicit future amounts are kept when latest basis is selected', () => {
  const data = source();
  data.items[0].latestFixed = { amount: 3200, sourceMonth: '2026-12' };
  data.months[1].items.income = { amount: 3500, scheduled: true, sourceMonth: '2027-06' };
  const result = calculateForecast(data, [], [{ itemId: 'income', basis: 'latest', included: true, excludedMonths: [] }]);
  assert.equal(result.months[0].after.income, 3200);
  assert.equal(result.months[1].after.income, 3500);
});

test('an analysis omission does not cancel or alter an enabled amount-change rule', () => {
  const result = calculateForecast(source(), [rule()], [{ itemId: 'income', basis: 'profile', included: true, excludedMonths: ['2027-05'] }]);
  assert.equal(result.months[0].after.income, 0);
  assert.equal(result.months[1].after.income, 3090);
  assert.equal(result.specs[0].amounts[5], 3090);
  assert.ok(result.specs[0].scheduledMonths.includes(5));
});
