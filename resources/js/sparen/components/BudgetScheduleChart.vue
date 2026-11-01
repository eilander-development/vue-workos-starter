<script setup lang="ts">
import { useElementSize } from '@vueuse/core';
import { BarChart3 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import type { BudgetYearSchedule } from '../budgetSchedule';
import { euro, monthLabel } from '../forecast';

const props = defineProps<{
    schedules: BudgetYearSchedule[];
    year: number;
    monthId: string;
    draft: Record<string, number>;
}>();
const emit = defineEmits<{ year: [year: number]; month: [monthId: string] }>();
const chart = ref<SVGSVGElement | null>(null);
const { width } = useElementSize(chart);
const w = computed(() => Math.max(280, Math.round(width.value) || 600));
const step = computed(() => (w.value - 58) / 12);
const selected = computed(() =>
    props.schedules.find((schedule) => schedule.year === props.year),
);
const previous = computed(() =>
    [...props.schedules]
        .reverse()
        .find((schedule) => schedule.year < props.year),
);
const amounts = computed(
    () =>
        selected.value?.months.map(
            (month) => props.draft[month.monthId] ?? month.amount,
        ) ?? [],
);
const max = computed(() =>
    Math.max(
        1,
        ...amounts.value,
        ...(previous.value?.months.map((month) => month.amount) ?? []),
    ),
);
const x = (index: number) => 50 + step.value * (index + 0.5);
const y = (amount: number) => 174 - (Math.max(0, amount) / max.value) * 140;
const previousLine = computed(() =>
    previous.value?.months
        .map((month, index) => `${x(index)},${y(month.amount)}`)
        .join(' '),
);
const active = computed(() =>
    selected.value?.months.find((month) => month.monthId === props.monthId),
);
const origin = {
    saved: 'Vastgelegd voor dit jaar',
    forecast: 'Bijgewerkt vanuit een doorgevoerde prognose',
    derived: 'Voortgezet vanuit beschikbare begrotingsgegevens',
};
</script>

<template>
    <section
        class="space-y-3 rounded-xl border border-indigo-500/25 bg-indigo-500/[0.04] p-3.5"
        aria-label="Begrotingshistorie en jaargrafiek"
    >
        <h4
            class="flex items-center gap-2 text-xs font-semibold text-indigo-200"
        >
            <BarChart3 class="h-4 w-4" />Begrotingsschema en historie
        </h4>
        <div
            class="flex flex-wrap gap-2"
            aria-label="Beschikbare begrotingsjaren"
        >
            <button
                v-for="schedule in schedules"
                :key="schedule.year"
                type="button"
                :aria-pressed="year === schedule.year"
                :aria-label="`Begrotingsjaar ${schedule.year}`"
                class="rounded-lg border px-2.5 py-1.5 text-xs"
                :class="
                    year === schedule.year
                        ? 'border-indigo-400 bg-indigo-500/20 text-indigo-200'
                        : 'border-slate-700 text-slate-400 hover:text-white'
                "
                @click="emit('year', schedule.year)"
            >
                {{ schedule.year
                }}<span class="ml-1 text-[9px]">{{
                    schedule.period === 'past'
                        ? 'historisch'
                        : schedule.period === 'future'
                          ? 'toekomst'
                          : 'huidig'
                }}</span>
            </button>
        </div>
        <div class="flex flex-wrap gap-3 text-[10px] text-slate-400">
            <span class="flex items-center gap-1.5"
                ><span class="h-2 w-3 rounded-sm bg-indigo-400" />Begroting
                {{ year }}</span
            ><span v-if="previous" class="flex items-center gap-1.5"
                ><span class="h-0.5 w-3 bg-amber-400" />Vergelijking
                {{ previous.year }}</span
            >
        </div>
        <svg
            v-if="selected"
            ref="chart"
            :viewBox="`0 0 ${w} 210`"
            class="block w-full"
            role="group"
            aria-label="Begrotingsbedragen per maand; klik om een maand te bekijken"
        >
            <g
                v-for="fraction in [0, 0.5, 1]"
                :key="fraction"
                aria-hidden="true"
            >
                <line
                    x1="49"
                    :x2="w - 5"
                    :y1="y(max * fraction)"
                    :y2="y(max * fraction)"
                    stroke="#334155"
                    stroke-dasharray="3 4"
                />
                <text
                    x="43"
                    :y="y(max * fraction) + 4"
                    text-anchor="end"
                    fill="#94a3b8"
                    font-size="10"
                >
                    {{ Math.round(max * fraction).toLocaleString('nl-NL') }}
                </text>
            </g>
            <polyline
                v-if="previous"
                :points="previousLine"
                fill="none"
                stroke="#fbbf24"
                stroke-width="1.5"
                stroke-dasharray="4 3"
                aria-hidden="true"
            />
            <g
                v-for="(month, index) in selected.months"
                :key="month.monthId"
                role="button"
                tabindex="0"
                :aria-label="`Begroting ${monthLabel(month.month)}: ${euro(amounts[index])}`"
                :aria-pressed="monthId === month.monthId"
                class="cursor-pointer focus:outline-2 focus:outline-indigo-400"
                @click="emit('month', month.monthId)"
                @keydown.enter.prevent="emit('month', month.monthId)"
                @keydown.space.prevent="emit('month', month.monthId)"
            >
                <title>
                    {{ monthLabel(month.month) }}: {{ euro(amounts[index])
                    }}{{
                        previous
                            ? ` · ${previous.year}: ${euro(previous.months[index].amount)}`
                            : ''
                    }}
                </title>
                <rect
                    :x="50 + index * step"
                    y="22"
                    :width="step - 1"
                    height="178"
                    rx="4"
                    :fill="
                        monthId === month.monthId ? '#6366f126' : 'transparent'
                    "
                />
                <rect
                    :x="x(index) - Math.min(8, step / 4)"
                    :y="y(amounts[index])"
                    :width="Math.min(16, step / 2)"
                    :height="Math.max(1, 174 - y(amounts[index]))"
                    rx="3"
                    fill="#818cf8"
                    :fill-opacity="monthId === month.monthId ? 1 : 0.65"
                />
                <text
                    :x="x(index)"
                    y="192"
                    text-anchor="middle"
                    fill="#cbd5e1"
                    :font-size="w < 400 ? 9 : 11"
                >
                    {{ month.monthName.slice(0, 3).toLowerCase() }}
                </text>
            </g>
        </svg>
        <div
            v-if="active"
            class="rounded-lg border border-slate-700 bg-slate-900/60 p-3 text-[11px] text-slate-300"
        >
            <p class="font-semibold">
                {{ monthLabel(active.month) }} ·
                {{ euro(draft[active.monthId] ?? active.amount) }}
            </p>
            <p class="mt-1 text-slate-400">
                {{ origin[active.origin]
                }}{{
                    active.sourceMonth
                        ? ` · bron: ${monthLabel(active.sourceMonth)}`
                        : ' · basisbedrag'
                }}
            </p>
            <p v-if="previous" class="mt-1 text-amber-200">
                {{ previous.year }}:
                {{
                    euro(
                        previous.months.find(
                            (month) => month.monthId === active?.monthId,
                        )?.amount ?? 0,
                    )
                }}
            </p>
        </div>
        <p class="text-[10px] leading-relaxed text-slate-500">
            Klik op een jaar of maand. Alle beschikbare oude, huidige en
            toekomstige schema’s zijn zichtbaar. Wijzigingen in de invoervelden
            worden pas vastgelegd bij opslaan van het geselecteerde jaar.
        </p>
    </section>
</template>
