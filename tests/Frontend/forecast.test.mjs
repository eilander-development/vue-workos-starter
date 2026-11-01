import assert from "node:assert/strict";
import { test } from "node:test";
import { buildSync } from "esbuild";

const bundled = buildSync({ entryPoints: [new URL("../../resources/js/sparen/forecast.ts", import.meta.url).pathname], bundle: true, write: false, platform: "node", format: "esm" });
const { calculateForecast, addMonths, groupForecastItems, forecastMonthComparison, forecastPostContext, forecastComparisonReference, forecastTableBreakdown, forecastKpiRuleEffects, forecastBeforeRules, forecastYearOverview } = await import(`data:text/javascript;base64,${Buffer.from(bundled.outputFiles[0].text).toString("base64")}`);
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

test('each KPI explains each applicable rule; lower savings increases free space', () => {
  const data = source();
  const rules = [rule({ value: 2 }), rule({ id: 'save', itemId: 'savings', operation: 'delta', value: -100 }), rule({ id: 'disabled', itemId: 'insurance', enabled: false, value: 10 }), rule({ id: 'later', effectiveMonth: '2027-06', operation: 'delta', value: -50 })];
  const result = calculateForecast(data, rules);
  assert.deepEqual(forecastKpiRuleEffects(result, result.months[0], rules, 'income').map(row => row.effect), [60]);
  assert.deepEqual(forecastKpiRuleEffects(result, result.months[0], rules, 'savings').map(row => row.effect), [-100]);
  assert.deepEqual(forecastKpiRuleEffects(result, result.months[0], rules, 'free').map(row => row.effect), [60, 100]);
  assert.deepEqual(forecastKpiRuleEffects(result, result.months[1], rules, 'free').map(row => row.effect), [60, 100, -50]);
  const baseline = forecastBeforeRules(result, data);
  for (const month of result.months) for (const key of ['income', 'expenses', 'savings', 'free']) {
    const effects = forecastKpiRuleEffects(result, month, rules, key);
    assert.equal(effects.reduce((sum, row) => sum + Math.round(row.effect * 100), 0), Math.round((month.after[key] - baseline.months.find(row => row.month === month.month).after[key]) * 100));
  }
});

test('rule effects explain a quarterly gap and an omitted post instead of claiming money changes', () => {
  const data = source();
  const rules = [rule({ itemId: 'insurance', operation: 'delta', value: 10 }), rule({ id: 'save', itemId: 'savings', operation: 'delta', value: -100 })];
  const result = calculateForecast(data, rules, [{ itemId: 'savings', basis: 'profile', included: false, excludedMonths: [] }]);
  const first = forecastKpiRuleEffects(result, result.months[0], rules, 'free');
  assert.equal(first[0].effect, 0);
  assert.equal(first[0].note, 'Deze maand geen betaling');
  assert.equal(first[1].effect, 0);
  assert.equal(first[1].note, 'Post niet meegeteld in deze KPI');
  assert.equal(forecastKpiRuleEffects(result, result.months[2], rules, 'free')[0].effect, -10);
});

test('year overview reports rule impact deficits and lowest month without rolling balances', () => {
  const data = source();
  const result = calculateForecast(data, [rule({ operation: 'set', value: 0 })]);
  const overview = forecastYearOverview(result, forecastBeforeRules(result, data));
  assert.equal(overview.ruleEffect, -36000);
  assert.equal(overview.deficitMonths, 12);
  assert.equal(overview.lowest.month, '2027-07');
  assert.equal(overview.lowest.after.free, -500);
  assert.equal(overview.averageFree, -300);
});

test('comparison captions describe dated, undated, mixed and unavailable reference sources', () => {
  const month = { beforeItems: { a: { amount: 36, scheduled: true, sourceMonth: '2026-05' }, b: { amount: 0, scheduled: false, sourceMonth: '2025-05' } } };
  assert.equal(forecastComparisonReference(month).label, 'mei 2026');
  month.beforeItems.b.amount = 50;
  month.beforeItems.b.sourceMonth = '2027-05';
  assert.equal(forecastComparisonReference(month).label, 'mei 2026 / mei 2027');
  assert.match(forecastComparisonReference(month).description, /mei 2026, mei 2027/);
  month.beforeItems.a.sourceMonth = null;
  assert.match(forecastComparisonReference(month).description, /Ongedateerd basisbedrag/);
  delete month.beforeItems.a.sourceMonth;
  assert.match(forecastComparisonReference(month).description, /Bronperiode niet vastgelegd/);
  month.beforeItems.a.amount = 0;
  assert.equal(forecastComparisonReference(month).label, 'mei 2027');
  month.beforeItems.b.amount = 0;
  assert.equal(forecastComparisonReference(month).label, 'Geen begrote betalingen');
});

test('table separates historical changes and scenario choices from amount rules in cents', () => {
  const data = source();
  data.version = 7;
  data.items[0].latestFixed = { amount: 3200, sourceMonth: '2027-04' };
  data.items[2].latestFixed = { amount: 220, sourceMonth: '2027-04' };
  for (const month of data.months) for (const cell of Object.values(month.items)) cell.sourceMonth = addMonths(month.month, -12);
  const settings = [{ itemId: 'savings', basis: 'latest', included: true, excludedMonths: ['2027-05'] }];
  const snapshot = calculateForecast(data, [rule({ value: 2.01 })], settings);
  const rows = forecastTableBreakdown(snapshot, snapshot.months[0], data);
  assert.equal(rows.income.referenceLabel, 'mei 2026');
  assert.equal(rows.income.basisLabel, 'april 2027');
  assert.equal(rows.income.basis, 3200);
  assert.equal(rows.income.basisDifference, 200);
  assert.equal(rows.income.ruleEffect, 64.32);
  assert.equal(rows.income.totalDifference, 264.32);
  assert.equal(rows.savings.basis, 0);
  assert.equal(rows.savings.basisDifference, -200);
  assert.equal(rows.savings.ruleEffect, 0);
  for (const item of snapshot.items) assert.equal(Math.round((rows[item.id].basisDifference + rows[item.id].ruleEffect) * 100), Math.round(rows[item.id].totalDifference * 100));
  const next = forecastTableBreakdown(snapshot, snapshot.months[1], data);
  assert.equal(next.savings.basis, 220);
  assert.equal(next.savings.basisDifference, 20);
  assert.equal(next.insurance.ruleEffect, 0);
});

test('saved table explanations retain frozen sources instead of current data and sum multiple rules', () => {
  const data = source();
  data.version = 7;
  data.items[0].latestFixed = { amount: 3200, sourceMonth: '2027-04' };
  const snapshot = calculateForecast(data, [rule({ operation: 'delta', value: 80 }), rule({ id: 'r2', effectiveMonth: '2027-06', operation: 'delta', value: -30 })]);
  snapshot.source = structuredClone(data);
  data.items[0].latestFixed.amount = 9999;
  const rows = forecastTableBreakdown(snapshot, snapshot.months[1], data);
  assert.equal(rows.income.basis, 3200);
  assert.equal(rows.income.ruleEffect, 50);
  assert.equal(rows.income.totalDifference, 250);
});

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

test('new forecasts automatically use latest fixed amounts and preserve quarterly timing', () => {
  const data = source();
  data.version = 4;
  data.items[0].latestFixed = { amount: 3200, sourceMonth: '2027-04' };
  data.items[1].latestFixed = { amount: 350, sourceMonth: '2027-04' };
  const result = calculateForecast(data, []);
  assert.equal(result.months[0].after.income, 3200);
  assert.equal(result.months[0].after.expenses, 0);
  assert.equal(result.months[2].after.expenses, 350);
  data.items[0].forecastBasis = 'profile';
  assert.equal(calculateForecast(data, []).months[0].after.income, 3000);
});

test('monthly historical gaps are only filled by an explicit monthly setting', () => {
  const data = source();
  data.version = 4;
  data.items[0].latestFixed = { amount: 3000, sourceMonth: '2027-04' };
  data.months[1].items.income = { amount: 0, scheduled: false, sourceMonth: '2026-06' };
  assert.equal(calculateForecast(data, []).months[1].after.income, 0);
  const settings = [{ itemId: 'income', basis: 'latest', everyMonth: true, included: true, excludedMonths: [] }];
  const result = calculateForecast(data, [], settings);
  assert.equal(result.months[1].after.income, 3000);
  assert.ok(result.specs[0].scheduledMonths.includes(6));
  data.months[1].items.income.sourceMonth = '2027-06';
  assert.equal(calculateForecast(data, [], settings).months[1].after.income, 0);
});

test('month comparison corrects for fixed budget differences and reconciles deviations in cents', () => {
  const data = source();
  data.months[1].items.income.amount = 3061;
  data.months[1].items.savings.amount = 157.23;
  const result = calculateForecast(data, [rule({ effectiveMonth: '2027-06', value: 2 })]);
  const comparison = forecastMonthComparison(result, 1);
  assert.equal(forecastMonthComparison(result, 0), null);
  assert.equal(forecastMonthComparison(result, 12), null);
  assert.equal(comparison.rows.reduce((sum, row) => sum + Math.round(row.effect * 100), 0), Math.round(comparison.difference * 100));
  assert.equal(comparison.difference, 61.22);
  assert.ok(comparison.rows.some(row => row.itemId === 'income' && row.reason.includes('wijziging')));
  assert.equal(forecastMonthComparison(calculateForecast(source(), []), 1), null);
});

test('month comparison keeps analysis exclusions but hides normal quarterly timing', () => {
  const data = source();
  const result = calculateForecast(data, [], [{ itemId: 'income', basis: 'profile', included: true, excludedMonths: ['2027-06'] }]);
  const omitted = forecastMonthComparison(result, 1);
  assert.equal(omitted.difference, -3000);
  assert.equal(omitted.rows[0].reason, 'Anders meegenomen in de berekening');
  const quarterly = forecastMonthComparison(calculateForecast(data, []), 2);
  assert.equal(quarterly, null);
});

test('quarterly benefits never produce a repeated deviation after an ongoing amount adjustment', () => {
  const data = source();
  data.items[1].type = 'inkomsten';
  data.items[1].name = 'Kinderbijslag';
  data.items[1].latestFixed = { amount: 362.35, sourceMonth: '2027-04' };
  data.version = 4;
  const normal = calculateForecast(data, []);
  for (let index = 1; index < 12; index++) assert.equal(forecastMonthComparison(normal, index), null);
  const adjusted = calculateForecast(data, [rule({ itemId: 'insurance', effectiveMonth: '2027-05', value: 2 })]);
  for (let index = 1; index < 12; index++) assert.equal(forecastMonthComparison(adjusted, index), null);
});

test('only the deviation is shown when a new rule coincides with a regular quarterly payment', () => {
  const data = source();
  const result = calculateForecast(data, [rule({ effectiveMonth: '2027-07', operation: 'delta', value: 61 })]);
  const comparison = forecastMonthComparison(result, 2);
  assert.equal(result.months[2].after.free - result.months[1].after.free, -239);
  assert.equal(comparison.difference, 61);
  assert.deepEqual(comparison.rows.map(row => row.itemId), ['income']);
  assert.equal(comparison.rows[0].expectedBefore, 3000);
  assert.equal(comparison.rows[0].expectedAfter, 3000);
});

test('known monthly budget changes are normal and a completely excluded post does not generate rhythm alerts', () => {
  const data = source();
  data.months[1].items.income.amount = 3100;
  data.months[1].items.savings.amount = 150;
  assert.equal(forecastMonthComparison(calculateForecast(data, []), 1), null);
  const result = calculateForecast(data, [], [{ itemId: 'insurance', basis: 'profile', included: false, excludedMonths: [] }]);
  assert.equal(forecastMonthComparison(result, 2), null);
});

test('monthly cadence exceptions remain visible and opposing deviations do not hide each other', () => {
  const data = source();
  data.items[1].latestFixed = { amount: 300, sourceMonth: '2027-04' };
  const settings = [{ itemId: 'insurance', basis: 'latest', everyMonth: true, included: true, excludedMonths: [] }];
  assert.equal(forecastMonthComparison(calculateForecast(data, [], settings), 2).difference, 300);
  const opposing = calculateForecast(data, [rule({ effectiveMonth: '2027-06', operation: 'delta', value: 100 }), rule({ id: 's1', itemId: 'savings', effectiveMonth: '2027-06', operation: 'delta', value: 100 })]);
  const comparison = forecastMonthComparison(opposing, 1);
  assert.equal(comparison.difference, 0);
  assert.equal(comparison.rows.length, 2);
});

test('lower savings contributions compensate income changes and reject negative contributions', () => {
  const data = source();
  const result = calculateForecast(data, [rule({ operation: 'delta', value: -100 }), rule({ id: 'buffer', itemId: 'savings', operation: 'delta', value: -100 })]);
  assert.equal(result.months[0].after.income, 2900);
  assert.equal(result.months[0].after.savings, 100);
  assert.equal(result.months[0].after.free, result.months[0].before.free);
  assert.equal(result.specs.find(spec => spec.itemId === 'savings').amounts[5], 100);
  assert.equal(calculateForecast(data, [rule({ itemId: 'savings', operation: 'percent', value: -25 })]).months[0].after.savings, 150);
  assert.equal(calculateForecast(data, [rule({ itemId: 'savings', operation: 'set', value: 0 })]).months[0].after.savings, 0);
  assert.throws(() => calculateForecast(data, [rule({ itemId: 'savings', operation: 'delta', value: -201 })]));
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
  assert.equal(result.version, 7);
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
  assert.equal(groups[1].label, 'Uitgaven');
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


test('post context explains the last known base separately from the historical same-month amount', () => {
  const data = source();
  data.version = 5;
  data.items[0].latestFixed = { amount: 40, sourceMonth: '2026-12' };
  data.items[0].references = { 5: { month: '2026-05', amount: 36, sourceMonth: '2026-05' } };
  for (const month of data.months) { month.items.income.amount = 36; month.items.income.sourceMonth = '2026-05'; }
  const result = calculateForecast(data, []);
  const context = forecastPostContext(result, '2027-05', 'income');
  assert.equal(context.basisPeriod, '2027-04');
  assert.equal(context.basisMonth, '2026-12');
  assert.equal(context.basisAmount, 40);
  assert.equal(context.referenceAmount, 36);
  assert.equal(context.amount, 40);
  data.months[0].items.income = { amount: 50, sourceMonth: '2027-05', scheduled: true };
  const explicit = forecastPostContext(calculateForecast(data, []), '2027-05', 'income');
  assert.equal(explicit.explicit, true);
  assert.equal(explicit.basisAmount, 50);
  assert.equal(explicit.referenceAmount, 36);
  assert.equal(forecastPostContext(result, '2027-05', 'missing'), undefined);
});


test('legacy automatic false cadence does not block a newly recognized monthly series; deliberate choices stay intact', () => {
 const data = source(); data.version = 5;
 data.items[1].forecastMonthly = true; data.items[1].cadenceReason = 'monthly-start'; data.items[1].latestFixed = { amount: 300, sourceMonth: '2027-04' };
 const legacy = { itemId: 'insurance', basis: 'latest', included: true, excludedMonths: [], everyMonth: false, rememberBasis: false };
 const original = structuredClone(legacy);
 assert.equal(calculateForecast(data, [], [legacy]).months[0].after.expenses, 300);
 assert.equal(calculateForecast(data, [], [{...legacy, cadenceOverride:true}]).months[0].after.expenses, 0);
 assert.equal(calculateForecast(data, [], [{...legacy, rememberBasis:true}]).months[0].after.expenses, 0);
 assert.deepEqual(legacy, original);
});


test('fixed and extra classifications are isolated to a forecast, preserve raw amounts and do not mutate the source', () => {
 const data = source(); data.version=6;
 data.items.push({id:'card',name:'Creditcard',type:'uitgaven',latestUnfiltered:{amount:150,sourceMonth:'2027-04'}});
 for(const month of data.months) month.items.card={amount:0,scheduled:false,rawAmount:150,rawScheduled:true,sourceMonth:'2027-04',excludedCost:{kind:'historical',amount:150,sourceMonth:'2027-04',reason:'deferred'}};
 const original=structuredClone(data);
 const fixed=calculateForecast(data,[rule({itemId:'card',operation:'delta',value:10})],[{itemId:'card',basis:'latest',included:true,excludedMonths:[],costMode:'fixed'}]);
 assert.equal(fixed.months[0].after.expenses,160);
 assert.equal(fixed.months[0].warnings.length,0);
 assert.equal(fixed.specs.find(spec=>spec.itemId==='card').beforeAmounts[5],150);
 const extra=calculateForecast(data,[],[{itemId:'savings',basis:'latest',included:true,excludedMonths:[],costMode:'extra'}]);
 assert.equal(extra.months[0].after.savings,200);
 const normalExpense=calculateForecast(data,[rule({itemId:'insurance',effectiveMonth:'2027-07',operation:'delta',value:10})],[{itemId:'insurance',basis:'profile',included:true,excludedMonths:[],costMode:'extra'}]);
 assert.equal(normalExpense.months[2].after.expenses,0);
 assert.equal(normalExpense.months[2].warnings.find(row=>row.itemId==='insurance').amount,310);
 assert.equal(normalExpense.specs.find(spec=>spec.itemId==='insurance').amounts[7],310);
 assert.deepEqual(data,original);
 assert.equal(calculateForecast(data,[]).months[0].after.expenses,0);
});
