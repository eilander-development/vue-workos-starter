<script setup lang="ts">
import {
    CalendarDays,
    Check,
    ListPlus,
    Repeat,
    SlidersHorizontal,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { sparenRequest } from '../api';
import { scheduleAsMonths, type BudgetSchedules } from '../budgetSchedule';
import type { ForecastPostContext } from '../forecast';
import type {
    BudgetCategoryGroup,
    BudgetItem,
    BudgetMonthEntry,
    BudgetType,
    CategoryDefinition,
    MonthlyBudget,
    Transaction,
} from '../types';
import BudgetChangeHistory from './BudgetChangeHistory.vue';
import BudgetItemRhythm from './BudgetItemRhythm.vue';
import BudgetMonthEntriesModal from './BudgetMonthEntriesModal.vue';
import BudgetScheduleChart from './BudgetScheduleChart.vue';
import ConfirmDialog from './ConfirmDialog.vue';
import ForecastPostContextPanel from './ForecastPostContextPanel.vue';
import ModalShell from './ModalShell.vue';

type FrequencyMode =
    | 'current_only'
    | 'all_months'
    | 'quarterly'
    | 'custom'
    | 'monthly_lines';

const props = defineProps<{
    isOpen: boolean;
    onClose: () => void;
    item: BudgetItem | null;
    currentMonthId: string;
    allMonths: MonthlyBudget[];
    transactions: Transaction[];
    fromForecast?: boolean;
    forecastContext?: ForecastPostContext;
    categories?: CategoryDefinition[];
    onSave: (
        itemId: string,
        updatedData: {
            name: string;
            group: BudgetCategoryGroup;
            type?: BudgetType;
            year: number;
            scheduleRevision?: string;
            monthlyAmounts: Record<string, number>;
            monthlyEntries?: Record<string, BudgetMonthEntry[]>;
        },
    ) => void | Promise<void>;
    onDelete?: (itemId: string) => void | Promise<void>;
}>();

function sumEntries(entries: BudgetMonthEntry[]): number {
    return (
        Math.round(
            entries.reduce((s, e) => s + (Number(e.amount) || 0), 0) * 100,
        ) / 100
    );
}

function itemUsesMonthlyLines(
    item: BudgetItem | null,
    allMonths: MonthlyBudget[],
): boolean {
    if (!item) return false;
    return allMonths.some((m) => {
        const found = m.items.find((i) => i.id === item.id);
        return (found?.monthEntries?.length ?? 0) > 0;
    });
}

const name = ref('');
const group = ref<BudgetCategoryGroup>('Dagelijks Leven');
const currentAmount = ref(0);
const frequencyMode = ref<FrequencyMode>('current_only');
const monthlyValues = ref<Record<string, number>>({});
const monthlyEntries = ref<Record<string, BudgetMonthEntry[]>>({});
const entriesModalOpen = ref(false);
const confirmDelete = ref(false);
const deleting = ref(false);

const usesLines = computed(() => frequencyMode.value === 'monthly_lines');
const currentMonthEntriesTotal = computed(() =>
    sumEntries(monthlyEntries.value[editMonthId.value] || []),
);

const scheduleData = ref<BudgetSchedules | null>(null);
const selectedYear = ref(props.allMonths[0]?.year ?? new Date().getFullYear());
const editMonthId = ref(props.currentMonthId);
const loadingSchedules = ref(false);
const saving = ref(false);
const saveError = ref('');
const initialYearState = ref('');
const schemaMonths = computed(() => {
    const schedule = scheduleData.value?.schedules.find(
        (schedule) => schedule.year === selectedYear.value,
    );
    return schedule && props.item
        ? scheduleAsMonths(schedule, props.item)
        : props.allMonths;
});
const yearState = () =>
    JSON.stringify([monthlyValues.value, monthlyEntries.value]);
function initializeYear(year: number) {
    selectedYear.value = year;
    const amounts: Record<string, number> = {};
    const entries: Record<string, BudgetMonthEntry[]> = {};
    for (const month of schemaMonths.value) {
        const item = month.items.find((item) => item.id === props.item?.id);
        entries[month.monthId] =
            item?.monthEntries?.map((row) => ({ ...row })) ?? [];
        amounts[month.monthId] = entries[month.monthId].length
            ? sumEntries(entries[month.monthId])
            : (item?.actual ?? 0);
    }
    monthlyValues.value = amounts;
    monthlyEntries.value = entries;
    currentAmount.value = amounts[editMonthId.value] ?? 0;
    frequencyMode.value = itemUsesMonthlyLines(props.item, schemaMonths.value)
        ? 'monthly_lines'
        : 'current_only';
    entriesModalOpen.value = false;
    initialYearState.value = yearState();
}
function selectYear(year: number) {
    if (year === selectedYear.value) return;
    if (
        yearState() !== initialYearState.value &&
        !window.confirm('Niet-opgeslagen maandbedragen verlaten?')
    )
        return;
    initializeYear(year);
}
function handleYearSelect(event: Event) {
    const target = event.target as HTMLSelectElement;
    selectYear(Number(target.value));
    target.value = String(selectedYear.value);
}
function selectMonth(monthId: string) {
    editMonthId.value = monthId;
    currentAmount.value = monthlyValues.value[monthId] ?? 0;
}
let scheduleRequest = 0;
watch(
    () => [props.item?.id, props.isOpen] as const,
    async () => {
        const sequence = ++scheduleRequest;
        if (!props.isOpen || !props.item) return;
        const item = props.item;
        name.value = item.name;
        group.value = item.group;
        editMonthId.value = props.forecastContext
            ? [
                  'jan',
                  'feb',
                  'mrt',
                  'apr',
                  'mei',
                  'jun',
                  'jul',
                  'aug',
                  'sep',
                  'okt',
                  'nov',
                  'dec',
              ][Number(props.forecastContext.month.slice(5)) - 1]
            : props.currentMonthId;
        scheduleData.value = null;
        saveError.value = '';
        confirmDelete.value = false;
        deleting.value = false;
        initializeYear(props.allMonths[0]?.year ?? selectedYear.value);
        loadingSchedules.value = true;
        try {
            const data: BudgetSchedules = await sparenRequest(
                'GET',
                `/api/sparen/budget-items/${encodeURIComponent(item.id)}/schedule`,
            );
            if (sequence !== scheduleRequest) return;
            scheduleData.value = data;
            const preferredYear = props.forecastContext
                ? Number(props.forecastContext.month.slice(0, 4))
                : selectedYear.value;
            initializeYear(
                data.schedules.some(
                    (schedule) => schedule.year === preferredYear,
                )
                    ? preferredYear
                    : data.currentYear,
            );
        } catch (error) {
            if (sequence === scheduleRequest)
                saveError.value =
                    error instanceof Error
                        ? error.message
                        : 'Jaarschema’s ophalen mislukt.';
        } finally {
            if (sequence === scheduleRequest) loadingSchedules.value = false;
        }
    },
    { immediate: true },
);

const groupOptions = computed(() =>
    props.categories && props.categories.length > 0
        ? props.categories.map((c) => c.name)
        : [
              'Inkomsten',
              'Woning',
              'Dagelijks Leven',
              'Vervoersmiddelen',
              'Verzekeringen',
              'Spaargeld',
              'Leningen',
              'Overige Vaste Kosten',
              'Overige Kosten',
          ],
);

function syncAmountsFromEntries() {
    const updated = { ...monthlyValues.value };
    schemaMonths.value.forEach((m) => {
        updated[m.monthId] = sumEntries(monthlyEntries.value[m.monthId] || []);
    });
    monthlyValues.value = updated;
    currentAmount.value = updated[editMonthId.value] || 0;
}

function handleEntriesSave(entriesByMonth: Record<string, BudgetMonthEntry[]>) {
    monthlyEntries.value = entriesByMonth;
    syncAmountsFromEntries();
}

function handleFrequencyChange(mode: FrequencyMode) {
    frequencyMode.value = mode;

    if (mode === 'monthly_lines') {
        entriesModalOpen.value = true;
        return;
    }

    const updated = { ...monthlyValues.value };
    if (mode === 'all_months') {
        schemaMonths.value.forEach((m) => {
            updated[m.monthId] = currentAmount.value;
        });
    } else if (mode === 'quarterly') {
        if (currentAmount.value <= 0)
            currentAmount.value =
                [...Object.values(monthlyValues.value)]
                    .reverse()
                    .find((amount) => amount > 0) ?? 0;
        schemaMonths.value.forEach((m) => {
            if (['jan', 'apr', 'jul', 'okt'].includes(m.monthId)) {
                updated[m.monthId] =
                    currentAmount.value > 0
                        ? currentAmount.value
                        : ([...Object.values(monthlyValues.value)]
                              .reverse()
                              .find((amount) => amount > 0) ?? 0);
            } else {
                updated[m.monthId] = 0;
            }
        });
    } else if (mode === 'current_only') {
        updated[editMonthId.value] = currentAmount.value;
    }
    monthlyValues.value = updated;
}

function handleCurrentAmountChange(val: number) {
    if (usesLines.value) return;
    currentAmount.value = val;
    const updated = { ...monthlyValues.value };

    if (frequencyMode.value === 'current_only') {
        updated[editMonthId.value] = val;
    } else if (frequencyMode.value === 'all_months') {
        schemaMonths.value.forEach((m) => {
            updated[m.monthId] = val;
        });
    } else if (frequencyMode.value === 'quarterly') {
        schemaMonths.value.forEach((m) => {
            if (['jan', 'apr', 'jul', 'okt'].includes(m.monthId)) {
                updated[m.monthId] = val;
            } else {
                updated[m.monthId] = 0;
            }
        });
    }
    monthlyValues.value = updated;
}

function handleMonthAmountChange(monthId: string, val: number) {
    if (usesLines.value) return;
    monthlyValues.value = { ...monthlyValues.value, [monthId]: val };
    if (monthId === editMonthId.value) {
        currentAmount.value = val;
    }
    frequencyMode.value = 'custom';
}

function buildFinalPayload() {
    const item = props.item!;
    const finalAmounts: Record<string, number> = { ...monthlyValues.value };
    let finalEntries: Record<string, BudgetMonthEntry[]> | undefined;

    if (frequencyMode.value === 'monthly_lines') {
        finalEntries = {};
        schemaMonths.value.forEach((m) => {
            const cleaned = (monthlyEntries.value[m.monthId] || [])
                .map((row) => ({
                    ...row,
                    description: row.description.trim(),
                    amount: Number(row.amount) || 0,
                }))
                .filter(
                    (row) => row.description !== '' || Math.abs(row.amount) > 0,
                );
            finalEntries![m.monthId] = cleaned;
            finalAmounts[m.monthId] = sumEntries(cleaned);
        });
    } else {
        finalEntries = {};
        schemaMonths.value.forEach((m) => {
            finalEntries![m.monthId] = [];
        });

        if (frequencyMode.value === 'current_only') {
            finalAmounts[editMonthId.value] = currentAmount.value;
        } else if (frequencyMode.value === 'all_months') {
            schemaMonths.value.forEach((m) => {
                finalAmounts[m.monthId] = currentAmount.value;
            });
        } else if (frequencyMode.value === 'quarterly') {
            schemaMonths.value.forEach((m) => {
                finalAmounts[m.monthId] = ['jan', 'apr', 'jul', 'okt'].includes(
                    m.monthId,
                )
                    ? currentAmount.value
                    : 0;
            });
        }
    }

    let matchedType: BudgetType = item.type;
    const foundCat = props.categories?.find((c) => c.name === group.value);
    if (foundCat) {
        matchedType = foundCat.type;
    } else if (group.value === 'Inkomsten') {
        matchedType = 'inkomsten';
    } else if (group.value === 'Spaargeld') {
        matchedType = 'sparen';
    } else {
        matchedType = 'uitgaven';
    }

    return {
        name: name.value.trim(),
        group: group.value,
        type: matchedType,
        year: selectedYear.value,
        scheduleRevision: scheduleData.value?.revision,
        monthlyAmounts: finalAmounts,
        monthlyEntries: finalEntries,
    };
}

async function handleSubmit() {
    const item = props.item;
    if (
        !item ||
        !name.value.trim() ||
        loadingSchedules.value ||
        saving.value ||
        !scheduleData.value
    )
        return;
    saving.value = true;
    saveError.value = '';
    try {
        await props.onSave(item.id, buildFinalPayload());
        props.onClose();
    } catch (error) {
        saveError.value =
            error instanceof Error ? error.message : 'Opslaan mislukt.';
    } finally {
        saving.value = false;
    }
}

function handleConfirmDelete() {
    if (!props.onDelete || !props.item || deleting.value) return;
    deleting.value = true;
    Promise.resolve(props.onDelete(props.item.id))
        .then(() => {
            confirmDelete.value = false;
            props.onClose();
        })
        .catch(() => {
            deleting.value = false;
        });
}

const frequencyOptions = [
    ['current_only', 'Alleen deze maand', 'Enkele aanpassing'],
    ['all_months', 'Elke maand', '12x per jaar hetzelfde'],
    ['quarterly', 'Per kwartaal (4x)', 'Jan, Apr, Jul, Okt'],
    ['custom', 'Handmatig / Maatwerk', 'Per maand instellen'],
    ['monthly_lines', 'Regels per maand', 'Omschrijving + bedrag per regel'],
] as const;
</script>

<template>
    <ModalShell
        v-if="isOpen && item"
        :on-close="onClose"
        :dismissible="!deleting && !saving"
        :close-on-backdrop="false"
    >
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="edit-budget-item-title"
            class="flex max-h-[94vh] w-full max-w-[1800px] flex-col overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl"
        >
            <div
                class="bg-slate-850 flex items-center justify-between border-b border-slate-800 p-5"
            >
                <div>
                    <h3
                        id="edit-budget-item-title"
                        class="text-base font-bold text-white"
                    >
                        Begrotingspost Aanpassen
                    </h3>
                    <p class="text-xs text-slate-400">
                        Stel het budgetbedrag in en bepaal in welke maanden deze
                        post actief is
                    </p>
                </div>
                <button
                    type="button"
                    aria-label="Postmodal sluiten"
                    :disabled="saving || deleting"
                    class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-800 hover:text-white"
                    @click="onClose"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <form
                class="grid min-h-0 grid-cols-1 gap-5 overflow-y-auto p-5 lg:grid-cols-2 lg:gap-5 xl:grid-cols-3"
                :inert="loadingSchedules || saving"
                @submit.prevent="handleSubmit"
            >
                <p
                    v-if="fromForecast"
                    class="rounded-xl border border-indigo-500/25 bg-indigo-500/10 p-3 text-xs leading-relaxed text-indigo-200 lg:col-span-2 xl:col-span-3"
                >
                    Je bekijkt het actuele jaarschema van de post.
                    Prognosekeuzes bewaar je alleen in de prognose. Na opslaan
                    wordt een bewerkbare prognose opnieuw berekend; een
                    doorgevoerde prognose behoudt haar bewaarde berekening.
                </p>
                <p
                    v-if="saveError"
                    role="alert"
                    class="rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-200 lg:col-span-2 xl:col-span-3"
                >
                    {{ saveError }}
                </p>
                <p
                    v-if="loadingSchedules"
                    role="status"
                    class="text-xs text-slate-400 lg:col-span-2 xl:col-span-3"
                >
                    Beschikbare jaarschema’s laden…
                </p>
                <div class="min-w-0 space-y-5" aria-label="Postinstellingen">
                    <h4
                        class="flex items-center gap-2 text-sm font-semibold text-white"
                    >
                        <SlidersHorizontal
                            class="h-4 w-4 text-indigo-400"
                        />Instellingen
                    </h4>
                    <ForecastPostContextPanel
                        v-if="forecastContext"
                        :context="forecastContext"
                    />
                    <p class="text-[10px] leading-relaxed text-slate-500">
                        Naam en categoriegroep gelden voor de post.
                        Maandbedragen worden vastgelegd in het gekozen jaar;
                        afgeleide bedragen in latere jaren kunnen hierop
                        voortbouwen. Vastgelegde bedragen van andere jaren
                        blijven behouden.
                    </p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label
                                class="mb-1 block text-xs font-semibold text-slate-300"
                                >Naam Begrotingspost</label
                            >
                            <input
                                v-model="name"
                                type="text"
                                required
                                class="w-full rounded-xl border border-slate-700 bg-slate-800 px-3.5 py-2 text-xs text-white focus:border-indigo-500 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-xs font-semibold text-slate-300"
                                >Categoriegroep</label
                            >
                            <select
                                v-model="group"
                                class="w-full rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-xs text-white focus:border-indigo-500 focus:outline-none"
                            >
                                <option
                                    v-for="g in groupOptions"
                                    :key="g"
                                    :value="g"
                                >
                                    {{ g }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <section
                        class="space-y-3 rounded-xl border border-indigo-500/25 bg-indigo-500/[0.06] p-3.5 text-xs"
                        aria-label="Prognoses automatisch"
                    >
                        <h4 class="font-semibold text-indigo-200">
                            Prognoses beginnen altijd op Automatisch
                        </h4>
                        <p class="leading-relaxed text-slate-400">
                            Deze post bewaart bedragen en betaalmaanden.
                            Prognosekeuzes worden alleen opgeslagen bij de
                            betreffende prognose, nooit hier bij de post.
                        </p>
                        <p class="leading-relaxed text-slate-300">
                            De prognose gebruikt de laatst bekende begroting
                            vóór de startmaand. Eerdere jaren zijn de
                            vergelijking. Een begonnen maandelijkse reeks loopt
                            door; kwartaalbetalingen behouden hun betaalmaanden.
                        </p>
                        <details
                            class="rounded-lg border border-slate-700/70 p-3"
                        >
                            <summary
                                class="cursor-pointer font-medium text-indigo-300"
                            >
                                Wanneer zou ik in de prognose afwijken?
                            </summary>
                            <dl
                                class="mt-3 space-y-3 text-[11px] leading-relaxed text-slate-400"
                            >
                                <div>
                                    <dt class="font-semibold text-slate-200">
                                        Automatisch — de standaard
                                    </dt>
                                    <dd>
                                        Geschikt voor normale vaste posten,
                                        zoals verzekeringen, salaris en een
                                        vaste aflossing. Losse begrotingsregels
                                        en Klarna/creditcard staan standaard
                                        apart als extra kosten.
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-slate-200">
                                        Vast meetellen
                                    </dt>
                                    <dd>
                                        Kies dit in een prognose als een
                                        automatisch apart gezette post toch een
                                        vaste terugkerende verplichting is. Het
                                        bedrag telt dan mee in de structurele
                                        vrije ruimte.
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-slate-200">
                                        Als extra apart houden
                                    </dt>
                                    <dd>
                                        Het bedrag staat apart bij extra kosten
                                        en telt niet mee in de structurele vrije
                                        ruimte. Een historische referentie wordt
                                        hierdoor geen voorspelde betaling.
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-slate-200">
                                        Maandprofiel of betaalritme aanpassen
                                    </dt>
                                    <dd>
                                        Alleen nodig voor een bewuste
                                        uitzondering, zoals seizoensbedragen of
                                        een ontbrekende betaalmaand. Je ziet het
                                        effect direct in die prognose en bewaart
                                        het met Prognose opslaan.
                                    </dd>
                                </div>
                            </dl>
                        </details>
                    </section>
                </div>
                <div class="min-w-0 space-y-5" aria-label="Begroting per jaar">
                    <h4
                        class="flex items-center gap-2 text-sm font-semibold text-white"
                    >
                        <CalendarDays
                            class="h-4 w-4 text-indigo-400"
                        />Begroting
                    </h4>
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-indigo-500/25 bg-indigo-500/5 p-3"
                    >
                        <label class="text-xs font-semibold text-indigo-200"
                            >Begrotingsjaar<select
                                aria-label="Begrotingsjaar"
                                class="ml-2 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white"
                                :value="selectedYear"
                                :disabled="loadingSchedules"
                                @change="handleYearSelect"
                            >
                                <option
                                    v-for="schedule in scheduleData?.schedules ??
                                    []"
                                    :key="schedule.year"
                                    :value="schedule.year"
                                >
                                    {{ schedule.year }} ·
                                    {{
                                        schedule.period === 'past'
                                            ? 'historisch'
                                            : schedule.period === 'future'
                                              ? 'toekomst'
                                              : 'huidig'
                                    }}
                                </option>
                            </select></label
                        >
                        <span class="text-[10px] text-slate-400"
                            >Maandbedragen voor {{ selectedYear }}</span
                        >
                    </div>

                    <div
                        class="space-y-3 rounded-xl border border-slate-800 bg-slate-950/60 p-4"
                    >
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-semibold text-slate-200"
                                >Budget Bedrag (€)</label
                            >
                            <span class="font-mono text-[11px] text-indigo-400">
                                {{
                                    usesLines
                                        ? 'Som van regels'
                                        : `Begroting ${selectedYear}`
                                }}
                            </span>
                        </div>
                        <div class="relative">
                            <span
                                class="absolute top-1/2 left-3.5 -translate-y-1/2 font-mono text-sm text-slate-400"
                                >€</span
                            >
                            <input
                                type="number"
                                step="0.01"
                                inputmode="decimal"
                                required
                                :value="
                                    usesLines
                                        ? currentMonthEntriesTotal
                                        : currentAmount
                                "
                                :readonly="usesLines"
                                class="w-full rounded-xl border border-slate-700 bg-slate-800 py-2 pr-4 pl-8 font-mono text-base font-bold text-white focus:border-indigo-500 focus:outline-none"
                                :class="
                                    usesLines ? 'cursor-default opacity-90' : ''
                                "
                                placeholder="0.00"
                                @input="
                                    !usesLines &&
                                    handleCurrentAmountChange(
                                        parseFloat(
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        ) || 0,
                                    )
                                "
                            />
                        </div>
                        <button
                            v-if="usesLines"
                            type="button"
                            class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-indigo-500/40 bg-indigo-600/10 py-2 text-xs font-medium text-indigo-200 transition-colors hover:bg-indigo-600/20"
                            @click="entriesModalOpen = true"
                        >
                            <ListPlus class="h-3.5 w-3.5" />
                            Regels per maand beheren
                        </button>
                    </div>

                    <div class="space-y-2.5">
                        <label
                            class="block flex items-center gap-1.5 text-xs font-semibold text-slate-300"
                        >
                            <Repeat class="h-3.5 w-3.5 text-indigo-400" />
                            <span>Bedrag wijzigen voor</span>
                        </label>

                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            <button
                                v-for="[
                                    mode,
                                    title,
                                    subtitle,
                                ] in frequencyOptions"
                                :key="mode"
                                type="button"
                                class="rounded-xl border p-2.5 text-left text-xs transition-all"
                                :class="
                                    frequencyMode === mode
                                        ? 'border-indigo-500 bg-indigo-600/20 font-semibold text-white'
                                        : 'border-slate-700/60 bg-slate-800/60 text-slate-300 hover:bg-slate-800'
                                "
                                @click="handleFrequencyChange(mode)"
                            >
                                <span class="block font-medium">{{
                                    title
                                }}</span>
                                <span class="text-[10px] text-slate-400">{{
                                    subtitle
                                }}</span>
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="!usesLines"
                        class="rounded-xl border border-slate-800/80 bg-slate-950/40 p-3.5"
                    >
                        <div class="mb-2.5 flex items-center justify-between">
                            <span
                                class="text-[11px] font-bold tracking-wider text-slate-400 uppercase"
                            >
                                Begroot bedrag over {{ selectedYear }} per maand
                            </span>
                            <span class="text-[11px] text-slate-500">
                                In maanden met € 0,00 staat de post niet op "In
                                afwachting"
                            </span>
                        </div>

                        <div
                            class="grid grid-cols-3 gap-2 font-mono sm:grid-cols-4 md:grid-cols-6"
                        >
                            <div
                                v-for="m in schemaMonths"
                                :key="m.monthId"
                                class="rounded-lg border p-2 text-center transition-all"
                                :class="
                                    m.monthId === editMonthId
                                        ? 'border-indigo-500/50 bg-indigo-950/40'
                                        : 'border-slate-800 bg-slate-900'
                                "
                            >
                                <span
                                    class="mb-1 block font-sans text-[10px] font-semibold text-slate-400"
                                >
                                    {{ m.monthName.slice(0, 3)
                                    }}{{
                                        m.monthId === editMonthId ? ' ★' : ''
                                    }}
                                </span>
                                <input
                                    type="number"
                                    step="0.01"
                                    inputmode="decimal"
                                    :value="monthlyValues[m.monthId] || 0"
                                    :aria-label="`Begroot ${m.monthName.toLowerCase()} ${selectedYear}`"
                                    class="w-full rounded border bg-slate-800 px-1 py-1 text-center text-xs focus:border-indigo-500 focus:outline-none"
                                    :class="
                                        (monthlyValues[m.monthId] || 0) === 0
                                            ? 'border-slate-700/40 text-slate-500'
                                            : 'border-slate-700 font-bold text-white'
                                    "
                                    @input="
                                        handleMonthAmountChange(
                                            m.monthId,
                                            parseFloat(
                                                (
                                                    $event.target as HTMLInputElement
                                                ).value,
                                            ) || 0,
                                        )
                                    "
                                />
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="rounded-xl border border-indigo-500/20 bg-slate-950/40 p-3.5 text-[11px] text-slate-400"
                    >
                        Bedragen per maand worden berekend uit de regels. Open
                        <button
                            type="button"
                            class="text-indigo-300 underline hover:text-indigo-200"
                            @click="entriesModalOpen = true"
                        >
                            regels per maand
                        </button>
                        om omschrijvingen en bedragen te beheren.
                    </div>

                    <BudgetScheduleChart
                        v-if="scheduleData"
                        :schedules="scheduleData.schedules"
                        :year="selectedYear"
                        :month-id="editMonthId"
                        :draft="monthlyValues"
                        @year="selectYear"
                        @month="selectMonth"
                    />

                    <details class="border-t border-slate-800 pt-3">
                        <summary class="cursor-pointer text-sm text-indigo-300">
                            Wijzigingshistorie vanuit prognoses
                        </summary>
                        <BudgetChangeHistory :item-id="item.id" class="mt-3" />
                    </details>
                </div>
                <BudgetItemRhythm
                    :item="item"
                    :transactions="transactions"
                    class="lg:col-span-2 xl:col-span-1"
                />

                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-800 pt-3 lg:col-span-2 xl:col-span-3"
                >
                    <button
                        v-if="onDelete"
                        type="button"
                        class="flex items-center gap-1.5 rounded-lg p-2 text-xs text-rose-400 transition-colors hover:bg-rose-500/10 hover:text-rose-300"
                        @click="confirmDelete = true"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                        <span>Post Verwijderen</span>
                    </button>
                    <div v-else />

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-xl px-4 py-2 text-xs font-medium text-slate-300 transition-colors hover:bg-slate-800"
                            @click="onClose"
                        >
                            Annuleren
                        </button>
                        <button
                            type="submit"
                            :disabled="
                                loadingSchedules || saving || !scheduleData
                            "
                            class="flex items-center gap-1.5 rounded-xl bg-indigo-600 px-5 py-2 text-xs font-semibold text-white shadow-md shadow-indigo-600/20 transition-all hover:bg-indigo-500 active:scale-95"
                        >
                            <Check class="h-4 w-4" />
                            <span>{{
                                saving
                                    ? 'Opslaan…'
                                    : `Wijzigingen Opslaan · ${selectedYear}`
                            }}</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <BudgetMonthEntriesModal
            :is-open="entriesModalOpen"
            :item-name="name || item.name"
            :all-months="schemaMonths"
            :current-month-id="editMonthId"
            :entries-by-month="monthlyEntries"
            :on-close="() => (entriesModalOpen = false)"
            :on-save="handleEntriesSave"
        />

        <ConfirmDialog
            :is-open="confirmDelete"
            :title="`“${name || item.name}” verwijderen?`"
            description="Deze begrotingspost verdwijnt uit alle maanden. Gekoppelde mutaties blijven staan, maar raken deze post kwijt."
            confirm-label="Ja, verwijderen"
            :busy="deleting"
            :on-cancel="
                () => {
                    if (!deleting) confirmDelete = false;
                }
            "
            :on-confirm="handleConfirmDelete"
        />
    </ModalShell>
</template>
