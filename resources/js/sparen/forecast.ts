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
    amountVariants?: number[];
};
export type ForecastSetting = {
    itemId: string;
    basis: 'profile' | 'latest';
    included: boolean;
    excludedMonths: string[];
};
export type ForecastSettingChange = {
    itemId: string;
    basis?: 'profile' | 'latest';
    included?: boolean;
    month?: string;
    monthIncluded?: boolean;
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
    minMonth: string;
    sourceHash: string;
    items: ForecastItem[];
    months: { month: string; items: Record<string, ForecastCell> }[];
    boundaries: Record<string, string[]>;
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
    const months = source.months.map((month) => ({
        month: month.month,
        items: Object.fromEntries(
            Object.entries(month.items).map(([id, cell]) => [id, { ...cell }]),
        ),
    }));
    const specs: ForecastSpec[] = [];
    for (const setting of settings) {
        const item = source.items.find((item) => item.id === setting.itemId);
        if (!item)
            throw new Error(
                'Een ingestelde begrotingspost bestaat niet meer. Haal actuele gegevens op.',
            );
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
        const latest = item.latestFixed;
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
            const before = cell.amount;
            const eligible = cell.scheduled;
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
                setting.excludedMonths.includes(month.month)
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
        if (!item || item.type === 'sparen')
            throw new Error('Kies een bestaande inkomsten- of uitgavenpost.');
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
        version: 3,
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
                .filter(
                    (item) => source.months[index].items[item.id].excludedCost,
                )
                .map((item) => ({
                    itemId: item.id,
                    name: item.name,
                    group: item.group,
                    ...source.months[index].items[item.id].excludedCost!,
                })),
        })),
    };
}

export function groupForecastItems(
    snapshot: ForecastSnapshot,
    month: ForecastSnapshot['months'][number],
) {
    const definitions = [
        { type: 'inkomsten', label: 'Inkomsten', color: 'text-emerald-300' },
        {
            type: 'uitgaven',
            label: snapshot.version >= 2 ? 'Uitgaven (vast)' : 'Uitgaven',
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
                                (item.latestFixed?.amount ?? 0) > 0)),
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
