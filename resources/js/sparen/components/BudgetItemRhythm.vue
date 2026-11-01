<script setup lang="ts">
import { useElementSize } from '@vueuse/core';
import { Activity, CalendarDays, Receipt, RefreshCw } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { sparenRequest } from '../api';
import {
    budgetRhythmRows,
    budgetRhythmTotals,
    rhythmMonthsInScope,
    type BudgetRhythmMonth,
    type BudgetRhythmScope,
} from '../budgetRhythm';
import { useTransactionDetail } from '../composables/useTransactionDetail';
import { euro, monthLabel } from '../forecast';
import type { BudgetItem, Transaction } from '../types';

const props = defineProps<{ item: BudgetItem; transactions: Transaction[] }>();
const chart = ref<SVGSVGElement | null>(null);
const { width: chartWidth } = useElementSize(chart);
const plotWidth = computed(() =>
    Math.max(280, Math.round(chartWidth.value) || 660),
);
const step = computed(() => (plotWidth.value - 56) / 12);
const barWidth = computed(() => Math.min(12, (step.value - 6) / 2));
const center = (index: number) => 52 + step.value * (index + 0.5);
const months = ref<BudgetRhythmMonth[]>([]);
const scope = ref<BudgetRhythmScope>(null);
const loading = ref(false);
const error = ref('');
const { openTransactionDetail } = useTransactionDetail();
let request = 0;
async function load() {
    const sequence = ++request;
    loading.value = true;
    error.value = '';
    months.value = [];
    try {
        const data = await sparenRequest(
            'GET',
            `/api/sparen/budget-items/${encodeURIComponent(props.item.id)}/rhythm`,
        );
        if (sequence === request) months.value = data.months;
    } catch (err) {
        if (sequence === request)
            error.value =
                err instanceof Error
                    ? err.message
                    : 'Historie ophalen mislukt.';
    } finally {
        if (sequence === request) loading.value = false;
    }
}
watch(
    () => props.item.id,
    () => {
        scope.value = null;
        void load();
    },
    { immediate: true },
);
const rows = computed(() =>
    budgetRhythmRows(props.item, props.transactions, months.value),
);
const totals = computed(() => budgetRhythmTotals(rows.value, months.value));
const selectedMonths = computed(() =>
    rhythmMonthsInScope(months.value, scope.value),
);
const visibleRows = computed(() =>
    rows.value.filter((row) =>
        selectedMonths.value.some((month) => month.month === row.month),
    ),
);
const years = computed(() => [
    ...new Set(months.value.map((month) => month.year)),
]);
const total = computed(
    () =>
        visibleRows.value
            .filter((row) => !row.tx.isPending)
            .reduce((sum, row) => sum + Math.round(row.amount * 100), 0) / 100,
);
const paidMonths = computed(
    () => totals.value.filter((month) => month.count > 0).length,
);
const max = computed(() =>
    Math.max(
        1,
        ...totals.value.flatMap((month) => [month.amount, month.actual]),
    ),
);
const scopeLabel = computed(() =>
    !scope.value
        ? 'Alle 12 maanden'
        : 'month' in scope.value
          ? monthLabel(scope.value.month)
          : `${scope.value.year} · binnen deze 12 maanden`,
);
const dateLabel = (date: string) =>
    new Date(`${date.slice(0, 10)}T12:00:00`).toLocaleDateString('nl-NL', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
const shortMonth = (month: string) =>
    new Intl.DateTimeFormat('nl-NL', { month: 'short' })
        .format(new Date(`${month}-01T12:00:00`))
        .replace('.', '');
function selectMonth(month: string) {
    scope.value =
        scope.value && 'month' in scope.value && scope.value.month === month
            ? null
            : { month };
}
const isSelected = (month: BudgetRhythmMonth) =>
    !scope.value ||
    ('month' in scope.value
        ? scope.value.month === month.month
        : scope.value.year === month.year);
const y = (amount: number) => 172 - (Math.max(0, amount) / max.value) * 140;
</script>

<template>
    <aside class="min-w-0 space-y-5" aria-label="Transacties en betaalritme">
        <section
            class="overflow-hidden rounded-xl border border-slate-700/70 bg-slate-950/40"
        >
            <div class="space-y-3 border-b border-slate-800 p-4">
                <div class="flex items-center justify-between gap-3">
                    <h4
                        class="flex items-center gap-2 text-sm font-semibold text-white"
                    >
                        <Receipt class="h-4 w-4 text-cyan-400" />Transacties ·
                        afgelopen 12 maanden
                    </h4>
                    <span class="font-mono text-sm font-bold text-cyan-300">{{
                        euro(total)
                    }}</span>
                </div>
                <p v-if="months.length" class="text-[11px] text-slate-400">
                    {{ dateLabel(months[0].periodStart) }} –
                    {{ dateLabel(months[months.length - 1].through) }} ·
                    {{ rows.length }} transacties
                </p>
                <div
                    class="flex flex-wrap gap-2"
                    aria-label="Transacties filteren op jaar"
                >
                    <button
                        type="button"
                        :aria-pressed="!scope"
                        class="rounded-lg border px-2.5 py-1.5 text-xs transition-colors"
                        :class="
                            !scope
                                ? 'border-indigo-500 bg-indigo-500/15 text-indigo-200'
                                : 'border-slate-700 text-slate-400 hover:text-white'
                        "
                        @click="scope = null"
                    >
                        Alle 12 maanden
                    </button>
                    <button
                        v-for="year in years"
                        :key="year"
                        type="button"
                        :aria-pressed="
                            !!scope && 'year' in scope && scope.year === year
                        "
                        class="rounded-lg border px-2.5 py-1.5 text-xs transition-colors"
                        :class="
                            scope && 'year' in scope && scope.year === year
                                ? 'border-indigo-500 bg-indigo-500/15 text-indigo-200'
                                : 'border-slate-700 text-slate-400 hover:text-white'
                        "
                        @click="scope = { year }"
                    >
                        {{ year }}
                    </button>
                </div>
                <p class="flex items-center gap-1.5 text-xs text-slate-300">
                    <CalendarDays class="h-3.5 w-3.5 text-indigo-400" />{{
                        scopeLabel
                    }}
                    · {{ visibleRows.length }} transacties
                </p>
            </div>
            <div
                v-if="loading"
                role="status"
                class="p-5 text-sm text-slate-400"
            >
                Transacties en ritme ophalen…
            </div>
            <div
                v-else-if="error"
                role="alert"
                class="space-y-2 p-4 text-xs text-rose-300"
            >
                <p>{{ error }}</p>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 underline"
                    @click="load"
                >
                    <RefreshCw class="h-3.5 w-3.5" />Opnieuw proberen
                </button>
            </div>
            <div
                v-else
                class="max-h-72 overflow-y-auto overscroll-contain"
                tabindex="0"
                aria-label="Gekoppelde transacties"
            >
                <p
                    v-if="!visibleRows.length"
                    class="p-5 text-xs leading-relaxed text-slate-400"
                >
                    Geen gekoppelde transacties in deze periode. Controleer of
                    de banktransacties aan deze post zijn gekoppeld.
                </p>
                <button
                    v-for="row in visibleRows"
                    :key="row.tx.id"
                    type="button"
                    class="flex w-full items-start gap-3 border-b border-slate-800/70 px-4 py-3 text-left transition-colors last:border-b-0 hover:bg-slate-800/60 focus-visible:outline-2 focus-visible:outline-indigo-400"
                    @click="openTransactionDetail(row.tx)"
                >
                    <span class="min-w-0 flex-1"
                        ><span
                            class="block text-xs font-medium break-words text-slate-200"
                            >{{
                                row.tx.counterparty || row.tx.description
                            }}</span
                        ><span
                            v-if="row.tx.counterparty"
                            class="mt-0.5 block text-[11px] break-words text-slate-400"
                            >{{ row.tx.description }}</span
                        ><span class="mt-1 block text-[10px] text-slate-500"
                            >{{ dateLabel(row.tx.date)
                            }}<span v-if="row.tx.allocations?.length">
                                · deel van gesplitste transactie</span
                            ><span
                                v-if="row.tx.isPending"
                                class="text-amber-300"
                            >
                                · reservering, buiten grafiektotaal</span
                            ></span
                        ></span
                    >
                    <span
                        class="shrink-0 font-mono text-xs font-semibold"
                        :class="
                            item.type === 'inkomsten'
                                ? 'text-emerald-300'
                                : item.type === 'sparen'
                                  ? 'text-blue-300'
                                  : 'text-cyan-300'
                        "
                        >{{ euro(row.amount) }}</span
                    >
                </button>
            </div>
        </section>

        <section
            class="rounded-xl border border-indigo-500/20 bg-indigo-500/[0.04] p-4"
            aria-labelledby="budget-rhythm-title"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h4
                    id="budget-rhythm-title"
                    class="flex items-center gap-2 text-sm font-semibold text-white"
                >
                    <Activity class="h-4 w-4 text-indigo-400" />Betaalritme ·
                    afgelopen 12 maanden
                </h4>
                <span class="text-[10px] text-slate-400"
                    >Betaling in {{ paidMonths }} van 12 maanden</span
                >
            </div>
            <p class="mt-2 text-[11px] leading-relaxed text-slate-400">
                Klik op een maand om de transacties te filteren. De lopende
                begrotingsmaand is nog onvolledig.
            </p>
            <div class="mt-3 flex flex-wrap gap-4 text-[10px] text-slate-300">
                <span class="flex items-center gap-1.5"
                    ><span
                        class="h-2 w-3 rounded-sm bg-indigo-400/60"
                    />Begroot</span
                ><span class="flex items-center gap-1.5"
                    ><span class="h-2 w-3 rounded-sm bg-cyan-400" />Geboekt op
                    deze post</span
                >
            </div>
            <svg
                v-if="totals.length"
                ref="chart"
                :viewBox="`0 0 ${plotWidth} 214`"
                class="mt-2 block w-full"
                role="group"
                aria-label="Begrote en geboekte bedragen per maand. Klik op een maand om te filteren."
            >
                <g
                    v-for="fraction in [0, 0.5, 1]"
                    :key="fraction"
                    aria-hidden="true"
                >
                    <line
                        x1="52"
                        :x2="plotWidth - 4"
                        :y1="y(max * fraction)"
                        :y2="y(max * fraction)"
                        stroke="#334155"
                        stroke-dasharray="3 4"
                    />
                    <text
                        x="46"
                        :y="y(max * fraction) + 4"
                        text-anchor="end"
                        fill="#94a3b8"
                        font-size="10"
                    >
                        {{ Math.round(max * fraction).toLocaleString('nl-NL') }}
                    </text>
                </g>
                <g
                    v-for="(month, index) in totals"
                    :key="month.month"
                    role="button"
                    tabindex="0"
                    :aria-label="`${monthLabel(month.month)}: begroot ${euro(month.amount)}, geboekt ${euro(month.actual)}, ${month.count} transacties${month.complete ? '' : ', onvolledige maand'}`"
                    :aria-pressed="
                        !!scope &&
                        'month' in scope &&
                        scope.month === month.month
                    "
                    class="cursor-pointer rounded focus:outline-2 focus:outline-indigo-400"
                    :opacity="isSelected(month) ? 1 : 0.35"
                    @click="selectMonth(month.month)"
                    @keydown.enter.prevent="selectMonth(month.month)"
                    @keydown.space.prevent="selectMonth(month.month)"
                >
                    <title>
                        {{ monthLabel(month.month) }} · Begroot
                        {{ euro(month.amount) }} · Geboekt
                        {{ euro(month.actual) }} ·
                        {{ month.count }} transacties{{
                            month.complete ? '' : ' · lopende maand'
                        }}
                    </title>
                    <rect
                        :x="52 + index * step + 1"
                        y="17"
                        :width="step - 2"
                        height="191"
                        rx="5"
                        :fill="
                            scope &&
                            'month' in scope &&
                            scope.month === month.month
                                ? '#6366f11f'
                                : 'transparent'
                        "
                    />
                    <rect
                        :x="center(index) - barWidth - 1.5"
                        :y="y(month.amount)"
                        :width="barWidth"
                        :height="Math.max(1, 172 - y(month.amount))"
                        rx="3"
                        fill="#818cf8"
                        fill-opacity="0.6"
                    />
                    <rect
                        :x="center(index) + 1.5"
                        :y="y(month.actual)"
                        :width="barWidth"
                        :height="Math.max(1, 172 - y(month.actual))"
                        rx="3"
                        fill="#22d3ee"
                    />
                    <text
                        :x="center(index)"
                        y="189"
                        text-anchor="middle"
                        fill="#cbd5e1"
                        :font-size="plotWidth < 400 ? 9 : 11"
                    >
                        {{ shortMonth(month.month)
                        }}{{ !month.complete ? '*' : '' }}
                    </text>
                </g>
                <template
                    v-for="(month, index) in totals"
                    :key="`year-${month.month}`"
                >
                    <text
                        v-if="
                            index === 0 || totals[index - 1].year !== month.year
                        "
                        :x="center(index)"
                        y="206"
                        text-anchor="middle"
                        fill="#a5b4fc"
                        font-size="12"
                        role="button"
                        tabindex="0"
                        class="cursor-pointer underline focus:outline-2 focus:outline-indigo-400"
                        :aria-label="`Transacties uit ${month.year} binnen de afgelopen 12 maanden`"
                        @click="scope = { year: month.year }"
                        @keydown.enter.prevent="scope = { year: month.year }"
                        @keydown.space.prevent="scope = { year: month.year }"
                    >
                        {{ month.year }}
                    </text>
                </template>
            </svg>
            <div
                v-if="scope && 'month' in scope"
                class="mt-2 rounded-lg border border-indigo-500/25 bg-slate-900/60 p-3 text-xs text-slate-300"
            >
                <p
                    v-for="month in totals.filter(
                        (month) =>
                            scope &&
                            'month' in scope &&
                            month.month === scope.month,
                    )"
                    :key="month.month"
                >
                    {{ monthLabel(month.month) }}: begroot
                    <strong>{{ euro(month.amount) }}</strong> · geboekt
                    <strong class="text-cyan-300">{{
                        euro(month.actual)
                    }}</strong
                    ><span
                        v-if="!month.complete"
                        class="mt-1 block text-amber-300"
                        >Lopende maand: nog geen volledig maandtotaal.</span
                    >
                </p>
            </div>
            <p class="mt-3 text-[10px] leading-relaxed text-slate-500">
                De maanden volgen dezelfde begrotingsperiode als de rest van de
                app. Alleen gekoppelde, geboekte transacties tellen mee; bij
                splitsingen alleen het deel voor deze post. Begrotingsbedragen
                zijn de opgeslagen bedragen of het basisbedrag als er geen
                maandbedrag is vastgelegd.
            </p>
        </section>
    </aside>
</template>
