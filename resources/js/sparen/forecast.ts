export type ForecastRule = {
    id: string;
    itemId: string;
    effectiveMonth: string;
    operation: 'delta' | 'percent' | 'set';
    value: number;
    enabled: boolean;
};

export type ForecastItem = {
    id: string;
    name: string;
    group: string;
    type: 'inkomsten' | 'uitgaven' | 'sparen';
    forecastMode?: 'auto' | 'fixed' | 'extra';
    latestFixed?: { amount: number; sourceMonth: string | null } | null;
    latestUnfiltered?: { amount: number; sourceMonth: string | null } | null;
    amountVariants?: number[];
    forecastBasis?: 'latest' | 'profile';
    forecastMonthly?: boolean;
    cadenceReason?:
        | 'stored-monthly'
        | 'stored-pattern'
        | 'monthly-start'
        | 'monthly-stop'
        | 'monthly-continuation'
        | 'existing';
    references?: Record<
        string,
        { month: string; amount: number; sourceMonth: string | null }
    >;
};
export type ForecastSetting = {
    itemId: string;
    basis: 'profile' | 'latest';
    included: boolean;
    excludedMonths: string[];
    rememberBasis?: boolean;
    everyMonth?: boolean;
    cadenceOverride?: boolean;
    costMode?: 'auto' | 'fixed' | 'extra';
};
export type ForecastSettingChange = {
    itemId: string;
    basis?: 'profile' | 'latest';
    included?: boolean;
    month?: string;
    monthIncluded?: boolean;
    rememberBasis?: boolean;
    everyMonth?: boolean;
    costMode?: 'auto' | 'fixed' | 'extra';
    automaticCadence?: boolean;
    reset?: boolean;
};
export type ForecastExcludedCost = {
    kind: 'historical' | 'planned';
    amount: number;
    sourceMonth: string | null;
    reason: 'marked_extra' | 'deferred' | 'month_entries';
    entries?: { id: string; description: string; amount: number }[];
};
export type ForecastWarning = ForecastExcludedCost & {
    itemId: string;
    name: string;
    group: string;
};
export type ForecastCell = {
    amount: number;
    scheduled: boolean;
    excludedCost?: ForecastExcludedCost;
    sourceMonth?: string | null;
    datedChange?: boolean;
    basis?: 'latest';
    omitted?: boolean;
    adjusted?: boolean;
    rawAmount?: number;
    rawScheduled?: boolean;
    analysisExtra?: boolean;
};
export type ForecastTotals = {
    income: number;
    expenses: number;
    savings: number;
    free: number;
};
export type ForecastSource = {
    version?: number;
    startMonth: string;
    basisMonth?: string;
    minMonth: string;
    sourceHash: string;
    items: ForecastItem[];
    months: { month: string; items: Record<string, ForecastCell> }[];
    boundaries: Record<string, string[]>;
};
export type ForecastPostContext = {
    month: string;
    startMonth: string;
    basisPeriod: string;
    basisMonth: string | null;
    basisAmount: number;
    referenceMonth: string | null;
    referenceAmount: number;
    amount: number;
    included: boolean;
    scheduled: boolean;
    basis: 'latest' | 'profile';
    monthly: boolean;
    cadenceReason?: ForecastItem['cadenceReason'];
    explicit: boolean;
    costMode: 'auto' | 'fixed' | 'extra';
};
export type ForecastSpec = {
    label?: string;
    ruleId: string;
    itemId: string;
    name: string;
    effectiveMonth: string;
    endMonth: string | null;
    beforeAmounts: Record<string, number>;
    amounts: Record<string, number>;
    scheduledMonths: number[];
    firstPayment: string | null;
};
export type ForecastSnapshot = {
    version: number;
    source?: ForecastSource;
    settings?: ForecastSetting[];
    items: ForecastItem[];
    specs: ForecastSpec[];
    months: {
        month: string;
        before: ForecastTotals;
        after: ForecastTotals;
        beforeItems: Record<string, ForecastCell>;
        afterItems: Record<string, ForecastCell>;
        warnings?: ForecastWarning[];
    }[];
};
export type SavedForecast = {
    id: string;
    name: string;
    notes: string | null;
    startMonth: string;
    createdAt: string;
    updatedAt: string;
    revision: string;
    sourceHash: string;
    createdBy: string | null;
    copiedFrom: string | null;
    appliedAt: string | null;
    revertedAt: string | null;
    revertedFrom: string | null;
    appliedRuleIds: string[];
    rules?: ForecastRule[];
    snapshot?: ForecastSnapshot;
};
export type BudgetHistoryEntry = {
    description?: string | null;
    id: string;
    forecastId: string;
    forecastName: string;
    effectiveMonth: string;
    endMonth: string | null;
    beforeAmounts: Record<string, number>;
    amounts: Record<string, number>;
    scheduledMonths: number[];
    rule: ForecastRule;
    createdAt: string;
    createdBy: string | null;
    notes: string | null;
    revertedAt: string | null;
    revertedFrom: string | null;
};

export const euro = (amount: number) =>
    new Intl.NumberFormat('nl-NL', {
        style: 'currency',
        currency: 'EUR',
    }).format(amount);
export const signedEuro = (amount: number) =>
    `${amount > 0 ? '+' : ''}${euro(amount)}`;
export const monthLabel = (month: string) =>
    new Intl.DateTimeFormat('nl-NL', { month: 'long', year: 'numeric' }).format(
        new Date(`${month}-01T12:00:00`),
    );
export function addMonths(month: string, count: number): string {
    const [year, number] = month.split('-').map(Number);
    const date = new Date(Date.UTC(year, number - 1 + count, 1));
    return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}`;
}

export function forecastReferenceLabel(cell: ForecastCell): string {
    if (cell.sourceMonth) return monthLabel(cell.sourceMonth);
    return cell.sourceMonth === null
        ? 'Ongedateerd basisbedrag'
        : 'Bronperiode niet vastgelegd';
}

export function forecastComparisonReference(
    month: ForecastSnapshot['months'][number],
) {
    const cells = Object.values(month.beforeItems).filter(
        (cell) => cell.amount !== 0,
    );
    const sources = [...new Set(cells.map(forecastReferenceLabel))];
    const dated = [
        ...new Set(
            cells.flatMap((cell) =>
                cell.sourceMonth ? [cell.sourceMonth] : [],
            ),
        ),
    ].sort();
    const periods =
        dated.length <= 2
            ? dated.map(monthLabel).join(' / ')
            : `${dated.length} bronperiodes (${dated[0].slice(0, 4)}–${dated[dated.length - 1].slice(0, 4)})`;
    const label = [
        periods,
        cells.some((cell) => cell.sourceMonth === null)
            ? periods
                ? 'basisbedragen'
                : 'Ongedateerd basisbedrag'
            : '',
        cells.some((cell) => cell.sourceMonth === undefined)
            ? 'Bronperiode niet vastgelegd'
            : '',
    ]
        .filter(Boolean)
        .join(' + ');
    return {
        label: label || 'Geen begrote betalingen',
        description: sources.length
            ? `Oorspronkelijke maandbegroting uit: ${sources.join(', ')}. De bron staat per post in de tabel.`
            : 'De oorspronkelijke maandbegroting bevat geen betalingen.',
    };
}

export function ruleLabel(rule: ForecastRule): string {
    if (rule.operation === 'set') return `Instellen op ${euro(rule.value)}`;
    if (rule.operation === 'percent')
        return `${rule.value >= 0 ? '+' : ''}${rule.value}%`;
    return signedEuro(rule.value);
}

export function calculateForecast(
    source: ForecastSource,
    rules: ForecastRule[],
    settings: ForecastSetting[] = [],
): ForecastSnapshot {
    settings = normalizeForecastSettings(settings, source);
    const months = source.months.map((month) => ({
        month: month.month,
        items: Object.fromEntries(
            Object.entries(month.items).map(([id, cell]) => [id, { ...cell }]),
        ),
    }));
    const specs: ForecastSpec[] = [];
    for (const setting of settings) {
        if (!source.items.some((item) => item.id === setting.itemId))
            throw new Error(
                'Een ingestelde begrotingspost bestaat niet meer. Haal actuele gegevens op.',
            );
    }
    for (const item of source.items) {
        const setting = {
            itemId: item.id,
            basis:
                item.forecastBasis ??
                ((source.version ?? 3) >= 4 ? 'latest' : 'profile'),
            everyMonth: item.forecastMonthly ?? false,
            included: true,
            excludedMonths: [],
            costMode: 'auto' as 'auto' | 'fixed' | 'extra',
            ...settings.find((setting) => setting.itemId === item.id),
        };
        if (
            setting.basis === 'profile' &&
            settings.find((setting) => setting.itemId === item.id)
                ?.everyMonth === undefined
        )
            setting.everyMonth = false;
        if (
            setting.excludedMonths.some(
                (month) =>
                    month < source.startMonth ||
                    month > source.months[11].month,
            )
        )
            throw new Error(
                'Maanduitsluitingen moeten binnen de twaalf prognosemaanden liggen.',
            );
        const next =
            (source.boundaries[item.id] ?? []).find(
                (month) => month > source.startMonth,
            ) ?? null;
        const latest =
            item.latestFixed ??
            (setting.costMode === 'fixed' ? item.latestUnfiltered : null);
        const spec: ForecastSpec = {
            ruleId: `basis-${item.id}`,
            itemId: item.id,
            name: item.name,
            effectiveMonth: source.startMonth,
            endMonth: next,
            beforeAmounts: {},
            amounts: {},
            scheduledMonths: [],
            firstPayment: null,
            label: `Laatste vaste bedrag${latest?.sourceMonth ? ` uit ${latest.sourceMonth}` : ''}`,
        };
        for (const month of months) {
            const cell = month.items[item.id];
            if (item.type === 'uitgaven' && setting.costMode === 'fixed') {
                cell.amount =
                    cell.rawAmount ?? cell.excludedCost?.amount ?? cell.amount;
                cell.scheduled = cell.rawScheduled ?? cell.amount > 0;
                delete cell.excludedCost;
            } else if (
                item.type === 'uitgaven' &&
                setting.costMode === 'extra'
            ) {
                cell.analysisExtra = !cell.excludedCost;
                if (cell.amount > 0)
                    cell.excludedCost = {
                        amount: cell.amount,
                        sourceMonth: cell.sourceMonth ?? null,
                        kind:
                            cell.sourceMonth === month.month || cell.datedChange
                                ? 'planned'
                                : 'historical',
                        reason: 'marked_extra',
                        entries: [],
                    };
            }
            const before = cell.amount;
            const eligible =
                cell.scheduled || (setting.everyMonth && !cell.excludedCost);
            const slot = Number(month.month.slice(5));
            const explicit = (cell.sourceMonth ?? '') >= source.startMonth;
            if (
                setting.basis === 'latest' &&
                latest &&
                eligible &&
                !explicit &&
                (!next || month.month < next)
            ) {
                cell.amount = latest.amount;
                cell.sourceMonth = latest.sourceMonth;
                cell.basis = 'latest';
                cell.scheduled = true;
            } else if (
                setting.everyMonth &&
                latest &&
                !cell.scheduled &&
                !cell.excludedCost &&
                !explicit &&
                (!next || month.month < next)
            ) {
                cell.amount = latest.amount;
                cell.sourceMonth = latest.sourceMonth;
                cell.basis = 'latest';
                cell.scheduled = true;
            }
            if (!(slot in spec.amounts)) {
                spec.beforeAmounts[slot] = before;
                spec.amounts[slot] = cell.amount;
                if (eligible) spec.scheduledMonths.push(slot);
            }
            if (cell.amount !== before && !spec.firstPayment)
                spec.firstPayment = month.month;
            if (
                !setting.included ||
                setting.excludedMonths.includes(month.month) ||
                cell.analysisExtra
            ) {
                cell.omitted = true;
            }
        }
        if (spec.firstPayment) specs.push(spec);
    }
    const seen = new Set<string>();
    for (const rule of [...rules]
        .filter((rule) => rule.enabled)
        .sort((a, b) => a.effectiveMonth.localeCompare(b.effectiveMonth))) {
        const item = source.items.find((item) => item.id === rule.itemId);
        if (!item)
            throw new Error(
                'Kies een bestaande inkomsten-, uitgaven- of spaarpost.',
            );
        if (
            rule.effectiveMonth < source.startMonth ||
            rule.effectiveMonth > addMonths(source.startMonth, 11)
        ) {
            throw new Error(
                'Iedere ingangsmaand moet binnen de twaalf maanden van de prognose liggen.',
            );
        }
        const key = `${rule.itemId}/${rule.effectiveMonth}`;
        if (seen.has(key))
            throw new Error(
                'Gebruik per post maximaal één wijziging in dezelfde ingangsmaand.',
            );
        seen.add(key);
        if (!Number.isFinite(rule.value))
            throw new Error('Vul een geldig bedrag of percentage in.');
        const end =
            (source.boundaries[rule.itemId] ?? []).find(
                (month) => month > rule.effectiveMonth,
            ) ?? null;
        const spec: ForecastSpec = {
            ruleId: rule.id,
            itemId: rule.itemId,
            name: item.name,
            effectiveMonth: rule.effectiveMonth,
            endMonth: end,
            beforeAmounts: {},
            amounts: {},
            scheduledMonths: [],
            firstPayment: null,
        };
        for (const month of months) {
            if (month.month < rule.effectiveMonth) continue;
            const slot = Number(month.month.slice(5));
            const cell = month.items[rule.itemId];
            const before = cell.amount;
            if (cell.scheduled && (!end || month.month < end)) {
                const cents = Math.round(before * 100);
                cell.amount =
                    rule.operation === 'set'
                        ? Math.round(rule.value * 100) / 100
                        : rule.operation === 'delta'
                          ? (cents + Math.round(rule.value * 100)) / 100
                          : Math.round(cents * (1 + rule.value / 100)) / 100;
                if (cell.amount < 0 || cell.amount > 999999999) {
                    throw new Error(
                        `De wijziging van ${item.name} levert een ongeldig bedrag op. Bedragen moeten tussen €0 en €999.999.999 liggen.`,
                    );
                }
                cell.adjusted = cell.amount !== before;
                if (cell.amount !== before && !spec.firstPayment)
                    spec.firstPayment = month.month;
            }
            if (!(slot in spec.amounts)) {
                spec.beforeAmounts[slot] = before;
                spec.amounts[slot] = cell.amount;
                if (cell.scheduled) spec.scheduledMonths.push(slot);
            }
        }
        specs.push(spec);
    }

    for (const month of months) {
        for (const cell of Object.values(month.items)) {
            if (cell.analysisExtra && cell.excludedCost)
                cell.excludedCost.amount = cell.amount;
            if (cell.omitted) {
                cell.amount = 0;
                cell.scheduled = false;
            }
        }
    }
    const totals = (cells: Record<string, ForecastCell>): ForecastTotals => {
        const cents = { income: 0, expenses: 0, savings: 0, free: 0 };
        for (const item of source.items) {
            const key =
                item.type === 'inkomsten'
                    ? 'income'
                    : item.type === 'sparen'
                      ? 'savings'
                      : 'expenses';
            cents[key] += Math.round(cells[item.id].amount * 100);
        }
        cents.free = cents.income - cents.expenses - cents.savings;
        return Object.fromEntries(
            Object.entries(cents).map(([key, value]) => [key, value / 100]),
        ) as ForecastTotals;
    };
    return {
        version: 7,
        settings,
        specs,
        items: source.items,
        months: months.slice(0, 12).map((month, index) => ({
            month: month.month,
            before: totals(source.months[index].items),
            after: totals(month.items),
            beforeItems: source.months[index].items,
            afterItems: month.items,
            warnings: source.items
                .filter((item) => month.items[item.id].excludedCost)
                .map((item) => ({
                    itemId: item.id,
                    name: item.name,
                    group: item.group,
                    ...month.items[item.id].excludedCost!,
                })),
        })),
    };
}

export function normalizeForecastSettings(
    settings: ForecastSetting[],
    source: ForecastSource,
): ForecastSetting[] {
    if ((source.version ?? 0) < 5) return settings;
    return settings.map((setting) => {
        if (
            setting.basis !== 'latest' ||
            setting.everyMonth !== false ||
            setting.rememberBasis ||
            setting.cadenceOverride !== undefined
        )
            return setting;
        const next = { ...setting };
        delete next.everyMonth;
        return next;
    });
}

export function groupForecastItems(
    snapshot: ForecastSnapshot,
    month: ForecastSnapshot['months'][number],
) {
    const definitions = [
        { type: 'inkomsten', label: 'Inkomsten', color: 'text-emerald-300' },
        {
            type: 'uitgaven',
            label: 'Uitgaven',
            color: 'text-rose-300',
        },
        { type: 'sparen', label: 'Sparen', color: 'text-sky-300' },
    ];
    return definitions
        .map((group) => {
            const rows = snapshot.items
                .filter(
                    (item) =>
                        item.type === group.type &&
                        (month.beforeItems[item.id].scheduled ||
                            month.afterItems[item.id].amount !== 0 ||
                            (snapshot.version >= 3 &&
                                ((item.latestFixed?.amount ??
                                    item.latestUnfiltered?.amount ??
                                    0) > 0 ||
                                    Boolean(
                                        month.afterItems[item.id].excludedCost,
                                    )))),
                )
                .map((item) => ({
                    ...item,
                    before: month.beforeItems[item.id].amount,
                    after: month.afterItems[item.id].amount,
                    cell: month.afterItems[item.id],
                }));
            return {
                ...group,
                rows,
                before:
                    rows.reduce(
                        (sum, item) => sum + Math.round(item.before * 100),
                        0,
                    ) / 100,
                after:
                    rows.reduce(
                        (sum, item) => sum + Math.round(item.after * 100),
                        0,
                    ) / 100,
            };
        })
        .filter((group) => group.rows.length);
}

export function forecastBasisFor(
    item: ForecastItem,
    snapshot: ForecastSnapshot,
    settings: ForecastSetting[],
) {
    return (
        settings.find((setting) => setting.itemId === item.id)?.basis ??
        item.forecastBasis ??
        (snapshot.version >= 4 ? 'latest' : 'profile')
    );
}

/** Category amounts and preferences, without the scenario's overrides or rules. */
function forecastComparisonBaseline(
    snapshot: ForecastSnapshot,
    source?: ForecastSource | null,
    settings: ForecastSetting[] = [],
) {
    const recorded = snapshot.months.map((month) => ({
        month: month.month,
        items: month.beforeItems,
    }));
    return calculateForecast(
        source ??
            snapshot.source ?? {
                version: snapshot.version,
                startMonth: snapshot.months[0].month,
                minMonth: snapshot.months[0].month,
                sourceHash: '',
                items: snapshot.items,
                boundaries: {},
                months: [
                    ...recorded,
                    ...recorded.map((month) => ({
                        ...month,
                        month: addMonths(month.month, 12),
                    })),
                ],
            },
        [],
        settings,
    );
}

/** Separate historical/profile differences from the net effect of amount rules. */
export function forecastBeforeRules(
    snapshot: ForecastSnapshot,
    source?: ForecastSource | null,
) {
    return forecastComparisonBaseline(
        snapshot,
        snapshot.source ?? source,
        snapshot.settings ?? [],
    );
}

export type ForecastKpiRuleEffect = {
    ruleId: string;
    itemId: string;
    name: string;
    label: string;
    effectiveMonth: string;
    effect: number;
    note: string | null;
};

export function forecastKpiRuleEffects(
    snapshot: ForecastSnapshot,
    month: ForecastSnapshot['months'][number],
    rules: ForecastRule[],
    kind: keyof ForecastTotals,
): ForecastKpiRuleEffect[] {
    const slot = Number(month.month.slice(5));
    return rules
        .filter((rule) => rule.enabled && rule.effectiveMonth <= month.month)
        .flatMap((rule) => {
            const item = snapshot.items.find((item) => item.id === rule.itemId);
            if (
                !item ||
                (kind !== 'free' &&
                    item.type !==
                        (kind === 'income'
                            ? 'inkomsten'
                            : kind === 'expenses'
                              ? 'uitgaven'
                              : 'sparen'))
            )
                return [];
            const spec = snapshot.specs.find((spec) => spec.ruleId === rule.id);
            if (!spec || (spec.endMonth && month.month >= spec.endMonth))
                return [];
            const cell = month.afterItems[item.id];
            const omitted = cell.omitted || Boolean(cell.excludedCost);
            const scheduled = spec.scheduledMonths.includes(slot);
            const delta =
                omitted || !scheduled
                    ? 0
                    : (Math.round((spec.amounts[slot] ?? 0) * 100) -
                          Math.round((spec.beforeAmounts[slot] ?? 0) * 100)) /
                      100;
            return [
                {
                    ruleId: rule.id,
                    itemId: item.id,
                    name: item.name,
                    label: ruleLabel(rule),
                    effectiveMonth: rule.effectiveMonth,
                    effect:
                        delta === 0
                            ? 0
                            : kind === 'free' && item.type !== 'inkomsten'
                              ? -delta
                              : delta,
                    note: omitted
                        ? 'Post niet meegeteld in deze KPI'
                        : !scheduled
                          ? 'Deze maand geen betaling'
                          : null,
                },
            ];
        });
}

export function forecastYearOverview(
    snapshot: ForecastSnapshot,
    baseline: ForecastSnapshot,
) {
    const cents = (amount: number) => Math.round(amount * 100);
    const total = snapshot.months.reduce(
        (sum, month) => sum + cents(month.after.free),
        0,
    );
    const beforeRules = baseline.months.reduce(
        (sum, month) => sum + cents(month.after.free),
        0,
    );
    return {
        ruleEffect: (total - beforeRules) / 100,
        averageFree: Math.round(total / snapshot.months.length) / 100,
        deficitMonths: snapshot.months.filter((month) => month.after.free < 0)
            .length,
        lowest: snapshot.months.reduce((lowest, month) =>
            month.after.free < lowest.after.free ? month : lowest,
        ),
    };
}

export function forecastTableBreakdown(
    snapshot: ForecastSnapshot,
    month: ForecastSnapshot['months'][number],
    source?: ForecastSource | null,
) {
    const basis = forecastBeforeRules(snapshot, source).months.find(
        (entry) => entry.month === month.month,
    )!;
    const difference = (after: number, before: number) =>
        (Math.round(after * 100) - Math.round(before * 100)) / 100;
    return Object.fromEntries(
        snapshot.items.map((item) => {
            const reference = month.beforeItems[item.id];
            const base = basis.afterItems[item.id];
            const after = month.afterItems[item.id];
            return [
                item.id,
                {
                    referenceLabel: forecastReferenceLabel(reference),
                    basisLabel: forecastReferenceLabel(base),
                    basis: base.amount,
                    basisDifference: difference(base.amount, reference.amount),
                    ruleEffect: difference(after.amount, base.amount),
                    totalDifference: difference(after.amount, reference.amount),
                },
            ];
        }),
    );
}

export function forecastMonthComparisons(
    snapshot: ForecastSnapshot,
    source?: ForecastSource | null,
) {
    const baseline = forecastComparisonBaseline(snapshot, source);
    return snapshot.months.map((_, index) =>
        forecastMonthComparison(snapshot, index, baseline),
    );
}

export function forecastMonthComparison(
    snapshot: ForecastSnapshot,
    index: number,
    baseline?: ForecastSnapshot,
) {
    if (index < 1 || index >= snapshot.months.length) return null;
    const previous = snapshot.months[index - 1];
    const current = snapshot.months[index];
    const expected = baseline ?? forecastComparisonBaseline(snapshot);
    const cents = (amount: number) => Math.round(amount * 100);
    const rows = snapshot.items
        .flatMap((item) => {
            const a = previous.afterItems[item.id];
            const b = current.afterItems[item.id];
            const expectedA = expected.months[index - 1].afterItems[item.id];
            const expectedB = expected.months[index].afterItems[item.id];
            if (a.omitted && b.omitted) return [];
            const omissionChanges = Boolean(a.omitted) !== Boolean(b.omitted);
            const ruleChanges = snapshot.specs.some(
                (spec) =>
                    spec.itemId === item.id &&
                    !spec.ruleId.startsWith('basis-') &&
                    (spec.effectiveMonth === current.month ||
                        spec.endMonth === current.month),
            );
            // A regular payment appearing or disappearing is normal, including a
            // quarterly payment whose amount has already been adjusted by a rule.
            const followsCadence =
                a.scheduled === expectedA.scheduled &&
                b.scheduled === expectedB.scheduled;
            if (
                expectedA.scheduled !== expectedB.scheduled &&
                followsCadence &&
                !omissionChanges &&
                !ruleChanges
            )
                return [];
            const change =
                cents(b.amount) -
                cents(a.amount) -
                (cents(expectedB.amount) - cents(expectedA.amount));
            if (!change) return [];
            let reason = 'Afwijking van de vaste maandbedragen';
            if (omissionChanges) reason = 'Anders meegenomen in de berekening';
            else if (ruleChanges) reason = 'Bedragwijziging gaat in of eindigt';
            else if (!followsCadence) reason = 'Afwijkend betaalritme';
            return [
                {
                    itemId: item.id,
                    name: item.name,
                    type: item.type,
                    before: a.amount,
                    after: b.amount,
                    expectedBefore: expectedA.amount,
                    expectedAfter: expectedB.amount,
                    effect:
                        (change * (item.type === 'inkomsten' ? 1 : -1)) / 100,
                    reason,
                    previousSource: a.sourceMonth ?? null,
                    currentSource: b.sourceMonth ?? null,
                },
            ];
        })
        .sort((a, b) => Math.abs(b.effect) - Math.abs(a.effect));
    if (!rows.length) return null;
    return {
        previousMonth: previous.month,
        month: current.month,
        before: previous.after.free,
        after: current.after.free,
        difference: rows.reduce((sum, row) => sum + cents(row.effect), 0) / 100,
        rows,
    };
}

export function forecastPostContext(
    snapshot: ForecastSnapshot,
    monthKey: string,
    itemId: string,
): ForecastPostContext | undefined {
    const month = snapshot.months.find((month) => month.month === monthKey);
    const item = snapshot.items.find((item) => item.id === itemId);
    if (!month || !item) return undefined;
    const cell = month.afterItems[itemId];
    const original = month.beforeItems[itemId];
    const setting = snapshot.settings?.find(
        (setting) => setting.itemId === itemId,
    );
    const basis = forecastBasisFor(item, snapshot, snapshot.settings ?? []);
    const costMode = setting?.costMode ?? 'auto';
    const latest =
        item.latestFixed ??
        (costMode === 'fixed' ? item.latestUnfiltered : null);
    const originalAmount =
        costMode === 'fixed'
            ? (original.rawAmount ??
              original.excludedCost?.amount ??
              original.amount)
            : original.amount;
    const explicit = (original.sourceMonth ?? '') >= snapshot.months[0].month;
    const reference = item.references?.[Number(monthKey.slice(5))];
    return {
        month: monthKey,
        startMonth: snapshot.months[0].month,
        basisPeriod: addMonths(snapshot.months[0].month, -1),
        basisMonth: explicit
            ? (original.sourceMonth ?? null)
            : basis === 'latest'
              ? (latest?.sourceMonth ?? null)
              : (original.sourceMonth ?? null),
        basisAmount: explicit
            ? originalAmount
            : basis === 'latest'
              ? (latest?.amount ?? originalAmount)
              : originalAmount,
        referenceMonth: reference?.month ?? original.sourceMonth ?? null,
        referenceAmount: reference?.amount ?? original.amount,
        amount: cell.amount,
        included: !cell.omitted,
        scheduled: cell.scheduled,
        basis,
        monthly:
            setting?.everyMonth ??
            (basis === 'profile' ? false : (item.forecastMonthly ?? false)),
        cadenceReason: item.cadenceReason,
        explicit,
        costMode,
    };
}
