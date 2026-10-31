<script setup lang="ts">
import {
    ArrowDownCircle,
    ArrowUpCircle,
    Calculator,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Copy,
    FolderClock,
    Layers3,
    Menu,
    Plus,
    RefreshCw,
    Save,
    SlidersHorizontal,
    Trash2,
    TrendingUp,
    Undo2,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { sparenRequest } from '../api';
import {
    addMonths,
    calculateForecast,
    euro,
    monthLabel,
    ruleLabel,
    signedEuro,
    type ForecastRule,
    type ForecastSetting,
    type ForecastSettingChange,
    type ForecastSnapshot,
    type ForecastSource,
    type ForecastSpec,
    type ForecastTotals,
    type SavedForecast,
} from '../forecast';
import BudgetChangeHistory from './BudgetChangeHistory.vue';
import ForecastCalculationModal from './ForecastCalculationModal.vue';
import ForecastExtraCostsModal from './ForecastExtraCostsModal.vue';
import ForecastItemsTable from './ForecastItemsTable.vue';
import ForecastKpiCard from './ForecastKpiCard.vue';
import ForecastMonthCard from './ForecastMonthCard.vue';
import ModalShell from './ModalShell.vue';

const emit = defineEmits<{ changed: []; openMobileMenu: [] }>();
const records = ref<SavedForecast[]>([]);
const active = ref<SavedForecast | null>(null);
const source = ref<ForecastSource | null>(null);
const minMonth = ref('');
const startMonth = ref('');
const name = ref('Nieuwe prognose');
const notes = ref('');
const rules = ref<ForecastRule[]>([]);
const settings = ref<ForecastSetting[]>([]);
const calculationMonth = ref<string | null>(null);
const calculation = computed(() =>
    snapshot.value?.months.find(
        (month) => month.month === calculationMonth.value,
    ),
);
const settingsState = (entries: ForecastSetting[]) =>
    JSON.stringify(
        entries
            .map((setting) => [
                setting.itemId,
                setting.basis,
                setting.included,
                setting.excludedMonths,
            ])
            .sort((a, b) => String(a[0]).localeCompare(String(b[0]))),
    );
function updateSetting(change: ForecastSettingChange) {
    const previous = settings.value.find(
        (setting) => setting.itemId === change.itemId,
    ) ?? {
        itemId: change.itemId,
        basis: 'profile' as const,
        included: true,
        excludedMonths: [],
    };
    const next = {
        ...previous,
        basis: change.basis ?? previous.basis,
        included: change.included ?? previous.included,
    };
    if (change.month)
        next.excludedMonths = change.monthIncluded
            ? previous.excludedMonths.filter((month) => month !== change.month)
            : [...new Set([...previous.excludedMonths, change.month])];
    settings.value = [
        ...settings.value.filter((setting) => setting.itemId !== change.itemId),
        next,
    ];
}
const hasOmissions = computed(() =>
    settings.value.some(
        (setting) => !setting.included || setting.excludedMonths.length,
    ),
);
const reviewCount = computed(
    () =>
        snapshot.value?.items.filter(
            (item) =>
                (item.amountVariants?.length ?? 0) > 1 &&
                !settings.value.some((setting) => setting.itemId === item.id),
        ).length ?? 0,
);
const specLabel = (spec: ForecastSpec) =>
    spec.label ??
    (active.value?.rules?.find((rule) => rule.id === spec.ruleId)
        ? ruleLabel(active.value.rules.find((rule) => rule.id === spec.ruleId)!)
        : 'Vast bedrag');
function moveSettings(oldStart: string, newStart: string) {
    const offset =
        (Number(newStart.slice(0, 4)) - Number(oldStart.slice(0, 4))) * 12 +
        Number(newStart.slice(5)) -
        Number(oldStart.slice(5));
    settings.value = settings.value.map((setting) => ({
        ...setting,
        excludedMonths: setting.excludedMonths.map((month) =>
            addMonths(month, offset),
        ),
    }));
}
const copiedFrom = ref<string | null>(null);
const alreadyApplied = ref<string[]>([]);
const selectedMonth = ref(0);
const busy = ref(false);
const loadingSource = ref(false);
const error = ref('');
const message = ref('');
const showReview = ref<'apply' | 'revert' | null>(null);
const revertFrom = ref('');
const historyItem = ref<{ id: string; name: string } | null>(null);
let sourceRequest = 0;

const editable = computed(() => !active.value?.appliedAt);
const formState = (
    label: string,
    description: string | null,
    start: string,
    entries: ForecastRule[],
) =>
    JSON.stringify([
        label,
        description ?? '',
        start,
        entries.map((rule) => [
            rule.id,
            rule.itemId,
            rule.effectiveMonth,
            rule.operation,
            Number(rule.value),
            rule.enabled,
        ]),
    ]);
const dirty = computed(() => {
    if (!editable.value) return false;
    if (!active.value)
        return (
            rules.value.length > 0 ||
            settings.value.length > 0 ||
            notes.value !== '' ||
            name.value !== 'Nieuwe prognose' ||
            startMonth.value !== minMonth.value
        );
    return (
        formState(name.value, notes.value, startMonth.value, rules.value) !==
            formState(
                active.value.name,
                active.value.notes,
                active.value.startMonth,
                active.value.rules ?? [],
            ) ||
        Boolean(
            source.value &&
                (source.value.sourceHash !== active.value.sourceHash ||
                    (active.value.snapshot?.version ?? 1) < 3),
        ) ||
        settingsState(settings.value) !==
            settingsState(active.value.snapshot?.settings ?? [])
    );
});
const preview = computed<{ snapshot: ForecastSnapshot | null; error: string }>(
    () => {
        if (active.value && (!editable.value || !source.value))
            return { snapshot: active.value.snapshot!, error: '' };
        if (!source.value) return { snapshot: null, error: '' };
        try {
            return {
                snapshot: calculateForecast(
                    source.value,
                    rules.value,
                    settings.value,
                ),
                error: '',
            };
        } catch (err) {
            return {
                snapshot: null,
                error:
                    err instanceof Error ? err.message : 'Berekenen mislukt.',
            };
        }
    },
);
const snapshot = computed(() => preview.value.snapshot);
const structural = computed(() => (snapshot.value?.version ?? 2) >= 2);
const extraCostMonth = ref<ForecastSnapshot['months'][number] | null>(null);
const current = computed(() => snapshot.value?.months[selectedMonth.value]);
const itemOptions = computed(() =>
    (source.value?.items ?? active.value?.snapshot?.items ?? []).filter(
        (item) => item.type !== 'sparen',
    ),
);
const monthOptions = computed(() =>
    Array.from({ length: 12 }, (_, i) =>
        startMonth.value ? addMonths(startMonth.value, i) : '',
    ),
);
const hasChanges = computed(() =>
    snapshot.value?.specs.some((spec) => spec.firstPayment),
);
const canApply = computed(
    () =>
        active.value &&
        !active.value.appliedAt &&
        active.value.startMonth >= minMonth.value &&
        hasChanges.value &&
        structural.value &&
        !dirty.value &&
        !loadingSource.value,
);
const kpis = computed<{ key: keyof ForecastTotals; label: string }[]>(() => [
    { key: 'income', label: 'Begrote inkomsten' },
    {
        key: 'expenses',
        label: structural.value ? 'Vaste begrote uitgaven' : 'Begrote uitgaven',
    },
    { key: 'savings', label: 'Gepland sparen' },
    {
        key: 'free',
        label: structural.value
            ? 'Structurele vrije ruimte'
            : 'Vrije ruimte na sparen',
    },
]);
const totalDifference = computed(
    () =>
        snapshot.value?.months.reduce(
            (sum, month) =>
                sum + Math.round((month.after.free - month.before.free) * 100),
            0,
        ) / 100 || 0,
);
const dateLabel = (date: string) =>
    new Date(date).toLocaleString('nl-NL', {
        timeZone: 'Europe/Amsterdam',
        dateStyle: 'medium',
        timeStyle: 'short',
    });
const status = (record: SavedForecast) =>
    record.revertedAt
        ? 'Teruggedraaid'
        : record.appliedAt
          ? 'Doorgevoerd'
          : 'Opgeslagen';
const ruleIsIncome = (rule: ForecastRule) =>
    (snapshot.value?.items ?? source.value?.items ?? []).find(
        (item) => item.id === rule.itemId,
    )?.type === 'inkomsten';
const statusTone = (record: SavedForecast) =>
    record.revertedAt
        ? 'border-amber-500/20 bg-amber-500/10 text-amber-300'
        : record.appliedAt
          ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-300'
          : 'border-indigo-500/20 bg-indigo-500/10 text-indigo-300';
const slotLabel = (slot: number) =>
    new Intl.DateTimeFormat('nl-NL', { month: 'short' }).format(
        new Date(2026, slot - 1, 1),
    );

async function run(action: () => Promise<void>) {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    message.value = '';
    try {
        await action();
    } catch (err) {
        error.value =
            err instanceof Error ? err.message : 'De actie is mislukt.';
    } finally {
        busy.value = false;
    }
}
async function loadList() {
    const data = await sparenRequest('GET', '/api/sparen/forecasts');
    records.value = data.forecasts;
    minMonth.value = data.minMonth;
    revertFrom.value = data.minMonth;
}
async function loadSource() {
    const request = ++sourceRequest;
    source.value = null;
    loadingSource.value = true;
    try {
        const data = await sparenRequest(
            'GET',
            `/api/sparen/forecasts/source?startMonth=${encodeURIComponent(startMonth.value)}`,
        );
        if (request === sourceRequest) {
            source.value = data;
            minMonth.value = data.minMonth;
        }
    } finally {
        if (request === sourceRequest) loadingSource.value = false;
    }
}
function discardDraft() {
    return (
        !dirty.value || window.confirm('De niet-opgeslagen prognose verlaten?')
    );
}
async function newForecast() {
    if (!discardDraft()) return;
    await run(async () => {
        await loadList();
        active.value = null;
        startMonth.value = minMonth.value;
        name.value = 'Nieuwe prognose';
        notes.value = '';
        rules.value = [];
        settings.value = [];
        alreadyApplied.value = [];
        copiedFrom.value = null;
        selectedMonth.value = 0;
        await loadSource();
    });
}
async function changeStart() {
    if (!startMonth.value || startMonth.value < minMonth.value) {
        error.value = 'Kies een toekomstige begrotingsmaand.';
        source.value = null;
        return;
    }
    const old = source.value?.startMonth ?? active.value?.startMonth;
    if (old) {
        moveSettings(old, startMonth.value);
        rules.value = rules.value.map((rule) => {
            const offset =
                (Number(rule.effectiveMonth.slice(0, 4)) -
                    Number(old.slice(0, 4))) *
                    12 +
                Number(rule.effectiveMonth.slice(5)) -
                Number(old.slice(5));
            return {
                ...rule,
                effectiveMonth: addMonths(startMonth.value, offset),
            };
        });
    }
    selectedMonth.value = 0;
    await run(loadSource);
}
async function openForecast(id: string) {
    if (!discardDraft()) return;
    await run(async () => {
        const record: SavedForecast = await sparenRequest(
            'GET',
            `/api/sparen/forecasts/${id}`,
        );
        active.value = record;
        startMonth.value = record.startMonth;
        name.value = record.name;
        notes.value = record.notes ?? '';
        rules.value = structuredClone(record.rules!);
        settings.value = structuredClone(record.snapshot?.settings ?? []);
        copiedFrom.value = record.copiedFrom;
        alreadyApplied.value = [];
        selectedMonth.value = 0;
        source.value = null;
        ++sourceRequest;
        if (!record.appliedAt) {
            if (record.snapshot?.source?.version === 3) {
                source.value = record.snapshot.source;
            } else if (record.startMonth >= minMonth.value) {
                await loadSource();
                message.value =
                    'Je kunt deze prognose verder aanpassen. De actuele begrotingsgegevens zijn opgehaald; opslaan werkt dezelfde prognose bij.';
            }
        }
    });
}
function addRule() {
    if (!itemOptions.value.length) return;
    rules.value.push({
        id: crypto.randomUUID(),
        itemId: itemOptions.value[0].id,
        effectiveMonth: startMonth.value,
        operation: 'delta',
        value: 0,
        enabled: true,
    });
}
async function save() {
    if (
        !source.value ||
        preview.value.error ||
        !name.value.trim() ||
        !editable.value
    )
        return;
    await run(async () => {
        const existing = active.value;
        const result: SavedForecast = await sparenRequest(
            existing ? 'PUT' : 'POST',
            existing
                ? `/api/sparen/forecasts/${existing.id}`
                : '/api/sparen/forecasts',
            {
                name: name.value,
                notes: notes.value,
                startMonth: startMonth.value,
                sourceHash: source.value!.sourceHash,
                rules: rules.value,
                settings: settings.value,
                copiedFrom: copiedFrom.value,
                ...(existing ? { revision: existing.revision } : {}),
            },
        );
        active.value = result;
        name.value = result.name;
        notes.value = result.notes ?? '';
        rules.value = structuredClone(result.rules!);
        settings.value = structuredClone(result.snapshot?.settings ?? []);
        source.value = result.snapshot?.source ?? source.value;
        await loadList();
        message.value = existing
            ? 'Wijzigingen opgeslagen in dezelfde prognose. Je kunt de regels verder aanpassen totdat je doorvoert.'
            : 'Prognose opgeslagen. Je kunt deze verder aanpassen. De begroting verandert pas bij doorvoeren.';
    });
}
async function copyForecast() {
    if (!active.value || !discardDraft()) return;
    await run(async () => {
        await loadList();
        const original: SavedForecast = await sparenRequest(
            'GET',
            `/api/sparen/forecasts/${active.value!.id}`,
        );
        const newStart =
            original.startMonth < minMonth.value
                ? minMonth.value
                : original.startMonth;
        copiedFrom.value = original.id;
        settings.value = structuredClone(original.snapshot?.settings ?? []);
        moveSettings(original.startMonth, newStart);
        alreadyApplied.value = original.appliedRuleIds;
        rules.value = original.rules!.map((rule) => {
            const offset =
                (Number(rule.effectiveMonth.slice(0, 4)) -
                    Number(original.startMonth.slice(0, 4))) *
                    12 +
                Number(rule.effectiveMonth.slice(5)) -
                Number(original.startMonth.slice(5));
            return {
                ...rule,
                enabled:
                    rule.enabled && !alreadyApplied.value.includes(rule.id),
                effectiveMonth: addMonths(newStart, offset),
            };
        });
        startMonth.value = newStart;
        name.value = `${original.name} (kopie)`.slice(0, 150);
        notes.value = original.notes ?? '';
        active.value = null;
        selectedMonth.value = 0;
        await loadSource();
        message.value =
            'Regels gekopieerd en actuele begroting opgehaald. Controleer de ingangsmaanden; ze zijn relatief aan de nieuwe startmaand overgenomen.';
    });
}
async function confirmAction() {
    if (!active.value || !showReview.value) return;
    await run(async () => {
        const action = showReview.value!;
        active.value = await sparenRequest(
            'POST',
            `/api/sparen/forecasts/${active.value!.id}/${action}`,
            action === 'revert'
                ? { fromMonth: revertFrom.value }
                : { revision: active.value!.revision },
        );
        source.value = null;
        showReview.value = null;
        await loadList();
        emit('changed');
        message.value =
            action === 'apply'
                ? 'De vaste bedragen zijn doorgevoerd. Je opgeslagen prognose blijft behouden.'
                : 'De toekomstige wijzigingen zijn teruggedraaid. De historie blijft behouden.';
    });
}
onMounted(() => {
    void newForecast();
});
</script>

<template>
    <div class="space-y-5" data-testid="forecasts">
        <div
            class="relative flex flex-wrap items-center justify-between gap-4 overflow-hidden rounded-2xl border border-indigo-500/25 bg-slate-900 bg-gradient-to-r from-indigo-500/10 via-slate-900 to-blue-500/[0.06] p-5 shadow-sm"
        >
            <div class="flex items-start gap-3">
                <button
                    type="button"
                    class="rounded-xl border border-slate-700 p-2 md:hidden"
                    aria-label="Open navigatiemenu"
                    @click="emit('openMobileMenu')"
                >
                    <Menu class="h-5 w-5" />
                </button>
                <div>
                    <h1
                        class="flex items-center gap-2 text-xl font-bold text-white"
                    >
                        <span
                            class="flex h-10 w-10 items-center justify-center rounded-xl border border-indigo-400/25 bg-indigo-500/15 shadow-sm"
                            ><TrendingUp class="h-5 w-5 text-indigo-300"
                        /></span>
                        Prognoses
                    </h1>
                    <p class="mt-1 text-sm text-slate-400">
                        Twaalf maanden vooruit. Iedere maand begint op €0;
                        bedragen worden niet doorgeschoven.
                    </p>
                </div>
            </div>
            <button
                type="button"
                class="forecast-button forecast-primary"
                :disabled="busy"
                @click="newForecast"
            >
                <Plus class="h-4 w-4" /> Nieuwe prognose
            </button>
        </div>

        <p
            v-if="error"
            role="alert"
            class="rounded-xl border border-rose-800 bg-rose-950/40 p-3 text-sm text-rose-200"
        >
            {{ error }}
        </p>
        <p
            v-if="message"
            role="status"
            class="rounded-xl border border-indigo-800 bg-indigo-950/40 p-3 text-sm text-indigo-200"
        >
            {{ message }}
        </p>

        <div class="grid gap-5 xl:grid-cols-[250px_minmax(0,1fr)]">
            <aside class="forecast-panel forecast-accent space-y-4 self-start">
                <div class="flex items-center gap-2">
                    <span
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-indigo-500/20 bg-indigo-500/10 text-indigo-300"
                        ><FolderClock class="h-4 w-4"
                    /></span>
                    <h2 class="text-sm font-semibold text-white">
                        Opgeslagen prognoses
                    </h2>
                    <span
                        class="ml-auto rounded-md bg-indigo-500/10 px-2 py-0.5 text-xs font-semibold text-indigo-300"
                        >{{ records.length }}</span
                    >
                </div>
                <p
                    v-if="!records.length"
                    class="rounded-xl border border-dashed border-indigo-500/25 bg-indigo-500/[0.04] p-4 text-xs leading-relaxed text-slate-400"
                >
                    Je hebt nog geen prognoses opgeslagen.
                </p>
                <div class="max-h-64 space-y-2 overflow-y-auto xl:max-h-[65vh]">
                    <button
                        v-for="record in records"
                        :key="record.id"
                        type="button"
                        class="w-full rounded-xl border p-3 text-left transition-colors"
                        :class="
                            active?.id === record.id
                                ? 'border-indigo-400/60 bg-indigo-500/15 shadow-sm shadow-indigo-500/10'
                                : 'border-slate-800 bg-slate-950/20 hover:border-indigo-500/40 hover:bg-indigo-500/[0.04]'
                        "
                        :disabled="busy"
                        @click="openForecast(record.id)"
                    >
                        <span
                            class="block text-sm font-medium break-words text-white"
                            >{{ record.name }}</span
                        >
                        <span class="mt-1 block text-xs text-slate-400"
                            >{{ monthLabel(record.startMonth) }} ·
                            <span
                                class="mt-2 inline-flex rounded-md border px-2 py-0.5 text-[10px] font-semibold"
                                :class="statusTone(record)"
                                >{{ status(record) }}</span
                            ></span
                        >
                    </button>
                </div>
            </aside>

            <div class="min-w-0 space-y-5" :aria-busy="busy || loadingSource">
                <section class="forecast-panel forecast-accent space-y-4">
                    <div
                        class="flex items-center gap-2 border-b border-slate-800/80 pb-3"
                    >
                        <SlidersHorizontal class="h-4 w-4 text-indigo-400" />
                        <h2 class="text-sm font-semibold text-white">
                            Jouw prognose
                        </h2>
                        <span
                            class="ml-auto rounded-full border px-2.5 py-1 text-[10px] font-semibold"
                            :class="
                                active
                                    ? statusTone(active)
                                    : 'border-indigo-500/20 bg-indigo-500/10 text-indigo-300'
                            "
                            >{{
                                active
                                    ? editable
                                        ? dirty
                                            ? 'Niet opgeslagen'
                                            : 'Bewerkbaar'
                                        : status(active)
                                    : 'Concept'
                            }}</span
                        >
                    </div>
                    <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_190px]">
                        <label class="forecast-label"
                            >Naam
                            <input
                                v-model="name"
                                class="forecast-input"
                                maxlength="150"
                                :readonly="!editable"
                                :disabled="busy"
                            />
                        </label>
                        <label class="forecast-label"
                            >Startmaand
                            <input
                                v-model="startMonth"
                                type="month"
                                class="forecast-input"
                                :min="minMonth"
                                max="2198-12"
                                :disabled="!editable || busy || loadingSource"
                                @change="changeStart"
                            />
                        </label>
                    </div>
                    <label class="forecast-label"
                        >Toelichting
                        <span class="font-normal text-slate-500"
                            >(optioneel)</span
                        >
                        <textarea
                            v-model="notes"
                            rows="2"
                            maxlength="5000"
                            class="forecast-input resize-y"
                            :readonly="!editable"
                            :disabled="busy"
                        />
                    </label>
                    <div
                        v-if="active"
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div class="space-y-1 text-xs text-slate-400">
                            <p>
                                {{ status(active) }} ·
                                {{ dateLabel(active.createdAt)
                                }}<template v-if="active.createdBy">
                                    · {{ active.createdBy }}</template
                                >
                            </p>
                            <p v-if="active.appliedAt">
                                Doorgevoerd op
                                {{ dateLabel(active.appliedAt) }}.
                            </p>
                            <p v-if="active.revertedAt">
                                Teruggedraaid vanaf
                                {{ monthLabel(active.revertedFrom!) }} op
                                {{ dateLabel(active.revertedAt) }}.
                            </p>
                            <p
                                v-if="editable"
                                class="max-w-xl leading-relaxed text-indigo-300"
                            >
                                Deze prognose is bewerkbaar. Wijzig de regels
                                hieronder en kies Wijzigingen opslaan. Na
                                doorvoeren staat ze op slot.
                            </p>
                            <p
                                v-else
                                class="max-w-xl leading-relaxed text-indigo-300"
                            >
                                Doorgevoerd ? alleen bekijken. Gebruik Kopi?ren
                                met actuele gegevens voor een nieuwe prognose.
                            </p>
                            <p v-if="editable && dirty" class="text-amber-300">
                                Er zijn nog niet-opgeslagen wijzigingen. Sla
                                deze eerst op voordat je doorvoert.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="forecast-button"
                                :class="!editable ? 'forecast-primary' : ''"
                                :disabled="busy"
                                @click="copyForecast"
                                title="Maak een aanpasbare kopie met de actuele begrotingsgegevens"
                            >
                                <Copy class="h-4 w-4" /> Kopiëren met actuele
                                gegevens
                            </button>
                            <button
                                v-if="canApply"
                                type="button"
                                class="forecast-button forecast-apply"
                                :disabled="busy"
                                @click="showReview = 'apply'"
                            >
                                Doorvoeren in begroting
                            </button>
                            <button
                                v-if="active.appliedAt && !active.revertedAt"
                                type="button"
                                class="forecast-button"
                                :disabled="busy"
                                @click="
                                    revertFrom = minMonth;
                                    showReview = 'revert';
                                "
                            >
                                <Undo2 class="h-4 w-4" /> Terugdraaien
                            </button>
                        </div>
                        <p
                            v-if="
                                !active.appliedAt &&
                                active.startMonth < minMonth
                            "
                            class="text-sm text-amber-300"
                        >
                            Deze startmaand is inmiddels verstreken. Kies een
                            toekomstige startmaand om deze prognose op te slaan
                            of door te voeren.
                        </p>
                    </div>
                    <div v-if="editable" class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="forecast-button forecast-primary"
                            :disabled="
                                busy ||
                                loadingSource ||
                                !source ||
                                !!preview.error ||
                                !name.trim() ||
                                startMonth < minMonth
                            "
                            @click="save"
                        >
                            <Save class="h-4 w-4" />
                            {{
                                active
                                    ? 'Wijzigingen opslaan'
                                    : 'Prognose opslaan'
                            }}
                        </button>
                        <button
                            type="button"
                            class="forecast-button"
                            :disabled="
                                busy ||
                                loadingSource ||
                                !startMonth ||
                                startMonth < minMonth
                            "
                            @click="run(loadSource)"
                        >
                            <RefreshCw class="h-4 w-4" /> Actuele gegevens
                            ophalen
                        </button>
                    </div>
                </section>

                <section
                    v-if="snapshot && current"
                    class="space-y-3"
                    aria-label="Maandresultaat"
                >
                    <div class="flex items-center justify-between gap-2">
                        <button
                            type="button"
                            class="forecast-button"
                            :disabled="selectedMonth === 0"
                            aria-label="Vorige prognosemaand"
                            @click="selectedMonth--"
                        >
                            <ChevronLeft class="h-4 w-4" />
                        </button>
                        <h2
                            class="flex items-center justify-center gap-2 rounded-xl border border-indigo-500/20 bg-indigo-500/10 px-3 py-2.5 text-center text-sm font-semibold text-white capitalize"
                        >
                            <CalendarDays
                                class="h-4 w-4 shrink-0 text-indigo-400"
                            />{{ monthLabel(current.month) }}
                            <span
                                class="ml-1 rounded-md bg-indigo-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-300"
                                >{{ selectedMonth + 1 }}/12</span
                            >
                        </h2>
                        <button
                            type="button"
                            class="forecast-button"
                            :disabled="selectedMonth === 11"
                            aria-label="Volgende prognosemaand"
                            @click="selectedMonth++"
                        >
                            <ChevronRight class="h-4 w-4" />
                        </button>
                    </div>
                    <div
                        class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                        aria-live="polite"
                    >
                        <ForecastKpiCard
                            v-for="kpi in kpis"
                            :key="kpi.key"
                            :kind="kpi.key"
                            :label="kpi.label"
                            :before="current.before[kpi.key]"
                            :after="current.after[kpi.key]"
                        />
                    </div>
                    <button
                        type="button"
                        class="forecast-button"
                        @click="calculationMonth = current.month"
                    >
                        <Calculator class="h-4 w-4 text-indigo-400" />Bekijk
                        berekening en bronnen
                    </button>
                    <p
                        v-if="reviewCount && editable"
                        class="rounded-xl border border-amber-500/20 bg-amber-500/5 p-3 text-xs leading-relaxed text-amber-300"
                    >
                        Bij {{ reviewCount }} posten verschillen de
                        bronbedragen. Controleer in Berekening en bronnen of je
                        het laatste vaste bedrag wilt gebruiken of het
                        maandprofiel wilt behouden. De app kiest dit niet
                        stilzwijgend voor je.
                    </p>
                    <button
                        v-if="current.warnings?.length"
                        type="button"
                        class="forecast-button forecast-warning"
                        @click="extraCostMonth = current"
                    >
                        Historische referenties / extra kosten bekijken ({{
                            current.warnings.length
                        }})
                    </button>
                </section>

                <p
                    v-if="active && !structural"
                    class="rounded-xl border border-amber-800/50 bg-amber-950/20 p-3 text-sm text-amber-200"
                >
                    Deze opgeslagen prognose bevat de oorspronkelijke berekening
                    met alle kosten. Kopieer met actuele gegevens voor de
                    structurele berekening met historische kosten apart.
                </p>
                <section class="forecast-panel space-y-3">
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <h2
                            class="flex items-center gap-2 font-semibold text-white"
                        >
                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-lg border border-violet-500/20 bg-violet-500/10 text-violet-300"
                                ><SlidersHorizontal class="h-4 w-4" /></span
                            >Aanpassingen
                        </h2>
                        <button
                            v-if="editable"
                            type="button"
                            class="forecast-button"
                            :disabled="
                                busy || loadingSource || !itemOptions.length
                            "
                            @click="addRule"
                        >
                            <Plus class="h-4 w-4" /> Post aanpassen
                        </button>
                    </div>
                    <p
                        v-if="loadingSource"
                        class="text-sm text-slate-400"
                        role="status"
                    >
                        Actuele begroting laden…
                    </p>
                    <p
                        v-else-if="editable && source && !itemOptions.length"
                        class="text-sm text-slate-400"
                    >
                        Voeg eerst een inkomsten- of uitgavenpost toe aan je
                        begroting.
                    </p>
                    <p v-else-if="!rules.length" class="text-sm text-slate-400">
                        Voeg een post toe om het effect direct te bekijken. Het
                        bestaande betalingspatroon blijft behouden.
                    </p>
                    <div
                        v-for="(rule, index) in rules"
                        :key="rule.id"
                        class="rounded-xl border border-l-4 bg-slate-950/30 p-3 transition-colors"
                        :class="
                            !rule.enabled
                                ? 'border-slate-700 opacity-60'
                                : ruleIsIncome(rule)
                                  ? 'border-emerald-500/20 border-l-emerald-400 bg-emerald-500/[0.03]'
                                  : 'border-rose-500/20 border-l-rose-400 bg-rose-500/[0.03]'
                        "
                    >
                        <div
                            class="mb-3 flex items-center gap-2 text-[11px] font-semibold"
                            :class="
                                ruleIsIncome(rule)
                                    ? 'text-emerald-300'
                                    : 'text-rose-300'
                            "
                        >
                            <component
                                :is="
                                    ruleIsIncome(rule)
                                        ? ArrowUpCircle
                                        : ArrowDownCircle
                                "
                                class="h-4 w-4"
                            /><span>{{
                                ruleIsIncome(rule) ? 'Inkomsten' : 'Uitgaven'
                            }}</span
                            ><span
                                class="ml-auto text-[10px] font-normal text-slate-500"
                                >Aanpassing {{ index + 1 }}</span
                            >
                        </div>
                        <div
                            v-if="editable"
                            class="grid items-end gap-3 sm:grid-cols-2 2xl:grid-cols-[minmax(140px,1.5fr)_minmax(130px,1fr)_minmax(110px,1fr)_minmax(150px,1fr)_auto]"
                        >
                            <label class="forecast-label"
                                >Post
                                <select
                                    v-model="rule.itemId"
                                    aria-label="Post"
                                    class="forecast-input"
                                    :disabled="busy || loadingSource"
                                >
                                    <option
                                        v-for="item in itemOptions"
                                        :key="item.id"
                                        :value="item.id"
                                    >
                                        {{
                                            item.type === 'inkomsten'
                                                ? 'Inkomst'
                                                : 'Uitgave'
                                        }}
                                        · {{ item.name }}
                                    </option>
                                </select>
                            </label>
                            <label class="forecast-label"
                                >Wijziging
                                <select
                                    v-model="rule.operation"
                                    aria-label="Wijziging"
                                    class="forecast-input"
                                    :disabled="busy"
                                >
                                    <option value="delta">
                                        Bedrag erbij/eraf
                                    </option>
                                    <option value="percent">
                                        Percentage erbij/eraf
                                    </option>
                                    <option value="set">
                                        Nieuw vast bedrag
                                    </option>
                                </select>
                            </label>
                            <label class="forecast-label"
                                >{{
                                    rule.operation === 'percent'
                                        ? 'Percentage (%)'
                                        : 'Bedrag (€)'
                                }}
                                <input
                                    v-model.number="rule.value"
                                    type="number"
                                    step="0.01"
                                    :min="
                                        rule.operation === 'set' ? 0 : undefined
                                    "
                                    class="forecast-input"
                                    :disabled="busy"
                                />
                            </label>
                            <label class="forecast-label"
                                >Ingangsmaand
                                <select
                                    v-model="rule.effectiveMonth"
                                    aria-label="Ingangsmaand"
                                    class="forecast-input"
                                    :disabled="busy"
                                >
                                    <option
                                        v-for="month in monthOptions"
                                        :key="month"
                                        :value="month"
                                    >
                                        {{ monthLabel(month) }}
                                    </option>
                                </select>
                            </label>
                            <button
                                type="button"
                                class="forecast-button text-rose-300"
                                :aria-label="`Verwijder aanpassing ${index + 1}`"
                                :disabled="busy"
                                @click="rules.splice(index, 1)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                            <label
                                class="flex items-center gap-2 text-xs text-slate-300 sm:col-span-2 2xl:col-span-5"
                                ><input
                                    v-model="rule.enabled"
                                    type="checkbox"
                                    :disabled="busy"
                                />
                                Meenemen in prognose
                                <span
                                    v-if="alreadyApplied.includes(rule.id)"
                                    class="text-amber-300"
                                    >· Al doorgevoerd in oorspronkelijke
                                    prognose; bewust inschakelen om opnieuw toe
                                    te passen.</span
                                ></label
                            >
                        </div>
                        <div
                            v-else
                            class="flex flex-wrap items-center justify-between gap-2 text-sm"
                        >
                            <span class="font-medium text-white">{{
                                snapshot?.items.find(
                                    (item) => item.id === rule.itemId,
                                )?.name || rule.itemId
                            }}</span>
                            <span class="text-slate-300"
                                >{{ ruleLabel(rule) }} · vanaf
                                {{ monthLabel(rule.effectiveMonth)
                                }}<template v-if="!rule.enabled">
                                    · uitgeschakeld</template
                                ></span
                            >
                        </div>
                        <p
                            v-if="
                                rule.enabled &&
                                snapshot?.specs.find(
                                    (spec) => spec.ruleId === rule.id,
                                )?.endMonth
                            "
                            class="mt-2 text-xs text-amber-300"
                        >
                            Een bestaande latere wijziging blijft behouden vanaf
                            {{
                                monthLabel(
                                    snapshot!.specs.find(
                                        (spec) => spec.ruleId === rule.id,
                                    )!.endMonth!,
                                )
                            }}.
                        </p>
                        <p
                            v-if="
                                rule.enabled &&
                                snapshot &&
                                !snapshot.specs.find(
                                    (spec) => spec.ruleId === rule.id,
                                )?.firstPayment
                            "
                            class="mt-2 text-xs text-amber-300"
                        >
                            Deze aanpassing verandert geen geplande bedragen.
                        </p>
                        <p
                            v-if="
                                rule.enabled &&
                                settings.some(
                                    (setting) =>
                                        setting.itemId === rule.itemId &&
                                        (!setting.included ||
                                            setting.excludedMonths.length),
                                )
                            "
                            class="mt-2 text-xs leading-relaxed text-amber-300"
                        >
                            Deze post is uitgezet in een deel van de berekening.
                            De regel blijft actief bij doorvoeren. Zet de regel
                            uit als je de bedragwijziging niet wilt doorvoeren.
                        </p>
                    </div>
                    <p
                        v-if="editable && rules.length"
                        class="text-xs text-slate-500"
                    >
                        Gebruik een minteken voor een verlaging. Percentages
                        worden één keer berekend op het bedrag dat op de
                        ingangsmaand geldt, inclusief eerdere regels.
                    </p>
                    <p
                        v-if="preview.error"
                        role="alert"
                        class="text-sm text-rose-300"
                    >
                        {{ preview.error }}
                    </p>
                </section>

                <template v-if="snapshot && current">
                    <section class="forecast-panel forecast-accent space-y-4">
                        <h2
                            class="flex items-center gap-2 font-semibold text-white"
                        >
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-indigo-500/20 bg-indigo-500/10 text-indigo-300"
                                ><CalendarDays class="h-4 w-4"
                            /></span>
                            {{
                                structural
                                    ? 'Structurele vrije ruimte per maand'
                                    : 'Vrije ruimte per maand'
                            }}
                        </h2>
                        <p class="text-xs text-slate-400">
                            Inkomsten − uitgaven − sparen. Totaal verschil over
                            twaalf maanden:
                            <strong
                                class="inline-flex rounded-md border px-2 py-0.5 font-mono"
                                :class="
                                    totalDifference > 0
                                        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-300'
                                        : totalDifference < 0
                                          ? 'border-rose-500/20 bg-rose-500/10 text-rose-300'
                                          : 'border-indigo-500/20 bg-indigo-500/10 text-indigo-300'
                                "
                                >{{ signedEuro(totalDifference) }}</strong
                            >
                        </p>
                        <div
                            class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-6"
                        >
                            <ForecastMonthCard
                                v-for="(month, index) in snapshot.months"
                                :key="month.month"
                                :month="month"
                                :active="index === selectedMonth"
                                :structural="structural"
                                @select="selectedMonth = index"
                                @extras="extraCostMonth = month"
                            />
                        </div>
                    </section>

                    <section class="forecast-panel space-y-3">
                        <h2
                            class="flex items-center gap-2 font-semibold text-white"
                        >
                            <span
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-blue-500/20 bg-blue-500/10 text-blue-300"
                                ><Layers3 class="h-4 w-4"
                            /></span>
                            Posten in {{ monthLabel(current.month) }}
                        </h2>
                        <ForecastItemsTable
                            :snapshot="snapshot"
                            :month="current"
                            :editable="editable && !busy && !loadingSource"
                            :settings="settings"
                            @setting="updateSetting"
                            @history="historyItem = $event"
                        />
                    </section>
                </template>
            </div>
        </div>

        <ModalShell
            v-if="showReview && active && snapshot"
            :on-close="
                () => {
                    if (!busy) showReview = null;
                }
            "
            :dismissible="!busy"
        >
            <section
                role="dialog"
                aria-modal="true"
                aria-labelledby="forecast-review-title"
                class="max-h-[90vh] w-full max-w-4xl space-y-4 overflow-y-auto rounded-2xl border border-slate-700 bg-slate-900 p-5"
            >
                <div class="flex items-center justify-between gap-3">
                    <h2
                        id="forecast-review-title"
                        class="text-lg font-bold text-white"
                    >
                        {{
                            showReview === 'apply'
                                ? 'Wijzigingen doorvoeren'
                                : 'Toekomstige wijzigingen terugdraaien'
                        }}
                    </h2>
                    <button
                        type="button"
                        :disabled="busy"
                        aria-label="Sluiten"
                        @click="showReview = null"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <template v-if="showReview === 'apply'">
                    <p class="text-sm text-slate-300">
                        De onderstaande uitkomsten worden vaste bedragen in de
                        begroting. Het betalingspatroon blijft behouden, ook na
                        de twaalf maanden van deze prognose.
                    </p>
                    <p
                        v-if="hasOmissions"
                        class="rounded-xl border border-amber-500/25 bg-amber-500/5 p-3 text-sm text-amber-300"
                    >
                        Uitgeschakelde posten zijn alleen uit deze prognose
                        gefilterd. Hun echte begrotingsbedragen worden niet
                        verwijderd. Alle bedragwijzigingen hieronder worden wel
                        doorgevoerd, ook bij een post die je voor de berekening
                        hebt uitgezet. Zet een regel bij Aanpassingen uit als je
                        haar niet wilt doorvoeren.
                    </p>
                    <article
                        v-for="spec in snapshot.specs.filter(
                            (spec) => spec.firstPayment,
                        )"
                        :key="spec.ruleId"
                        class="space-y-2 rounded-xl border border-slate-700 p-3"
                    >
                        <h3 class="font-semibold text-white">
                            {{ spec.name }} ·
                            {{ specLabel(spec) }}
                        </h3>
                        <p class="text-sm text-slate-400">
                            Ingang: {{ monthLabel(spec.effectiveMonth) }} ·
                            Eerste gewijzigde betaling:
                            {{ monthLabel(spec.firstPayment!) }}
                        </p>
                        <p v-if="spec.endMonth" class="text-sm text-amber-300">
                            De bestaande latere wijziging blijft behouden vanaf
                            {{ monthLabel(spec.endMonth) }}.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="slot in spec.scheduledMonths"
                                :key="slot"
                                class="rounded-lg bg-slate-800 p-2 text-xs text-slate-300"
                                >{{ slotLabel(slot) }}:
                                {{ euro(spec.beforeAmounts[slot]) }} →
                                {{ euro(spec.amounts[slot]) }}</span
                            >
                        </div>
                    </article>
                    <p class="text-sm text-slate-300">
                        Effect op vrije ruimte over twaalf maanden:
                        <strong>{{ signedEuro(totalDifference) }}</strong
                        >.
                    </p>
                </template>
                <template v-else>
                    <p class="text-sm text-slate-300">
                        Beëindig de wijzigingen uit deze prognose vanaf een
                        toekomstige maand. Afgelopen en lopende maanden blijven
                        behouden. Bij latere of handmatige wijzigingen wordt
                        deze actie geblokkeerd en kun je een nieuwe prognose als
                        correctie maken.
                    </p>
                    <label class="forecast-label"
                        >Terugdraaien vanaf<input
                            v-model="revertFrom"
                            type="month"
                            :min="minMonth"
                            class="forecast-input"
                            :disabled="busy"
                    /></label>
                </template>
                <p v-if="error" role="alert" class="text-sm text-rose-300">
                    {{ error }}
                </p>
                <div class="flex flex-wrap justify-end gap-2">
                    <button
                        type="button"
                        class="forecast-button"
                        :disabled="busy"
                        @click="showReview = null"
                    >
                        Annuleren</button
                    ><button
                        type="button"
                        class="forecast-button forecast-primary"
                        :disabled="
                            busy ||
                            (showReview === 'revert' &&
                                (!revertFrom || revertFrom < minMonth))
                        "
                        @click="confirmAction"
                    >
                        {{
                            busy
                                ? 'Bezig…'
                                : showReview === 'apply'
                                  ? 'Definitief doorvoeren'
                                  : 'Terugdraaien bevestigen'
                        }}
                    </button>
                </div>
            </section>
        </ModalShell>

        <ForecastCalculationModal
            v-if="snapshot && calculation"
            :snapshot="snapshot"
            :month="calculation"
            :settings="settings"
            :editable="editable && !busy && !loadingSource"
            :on-close="() => (calculationMonth = null)"
            @setting="updateSetting"
            @history="historyItem = $event"
        />
        <ForecastExtraCostsModal
            v-if="extraCostMonth"
            :month="extraCostMonth"
            :on-close="() => (extraCostMonth = null)"
        />

        <ModalShell v-if="historyItem" :on-close="() => (historyItem = null)">
            <section
                role="dialog"
                aria-modal="true"
                aria-labelledby="forecast-history-title"
                class="max-h-[85vh] w-full max-w-2xl space-y-4 overflow-y-auto rounded-2xl border border-slate-700 bg-slate-900 p-5"
            >
                <div class="flex items-center justify-between gap-3">
                    <h2
                        id="forecast-history-title"
                        class="text-lg font-bold text-white"
                    >
                        {{ historyItem.name }}
                    </h2>
                    <button
                        type="button"
                        aria-label="Sluiten"
                        @click="historyItem = null"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <BudgetChangeHistory :item-id="historyItem.id" />
            </section>
        </ModalShell>
    </div>
</template>

<style scoped>
@reference "tailwindcss";
.forecast-panel {
    @apply rounded-2xl border border-slate-800 bg-slate-900 p-4 shadow-sm;
}
.forecast-label {
    @apply block text-xs font-medium text-slate-400;
}
.forecast-input {
    @apply mt-1 block w-full min-w-0 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white outline-none focus:border-indigo-400 disabled:opacity-50;
}
.forecast-button {
    @apply inline-flex items-center justify-center gap-2 rounded-xl border border-slate-700 bg-slate-800 px-3.5 py-2 text-xs font-semibold text-slate-200 shadow-sm transition-all hover:border-slate-600 hover:bg-slate-700 focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:outline-none active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40;
}
.forecast-primary {
    @apply border-indigo-500/70 bg-indigo-600 text-white shadow-md shadow-indigo-500/15 hover:border-indigo-400 hover:bg-indigo-500;
}

.forecast-panel.forecast-accent {
    @apply border-indigo-500/20;
}
.forecast-input[readonly] {
    @apply cursor-default border-dashed bg-slate-950/30 text-slate-300;
}
.forecast-apply {
    @apply border-emerald-500/30 bg-emerald-500/10 text-emerald-300 hover:border-emerald-400/50 hover:bg-emerald-500/20;
}
.forecast-warning {
    @apply border-amber-500/25 bg-amber-500/[0.06] text-amber-300 hover:bg-amber-500/10;
}
</style>
