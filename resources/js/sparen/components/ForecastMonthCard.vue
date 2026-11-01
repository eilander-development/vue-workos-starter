<script setup lang="ts">
import { ArrowRightLeft, CheckCircle2, TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';
import {
    euro,
    monthLabel,
    signedEuro,
    type ForecastSnapshot,
    type forecastMonthComparison,
} from '../forecast';
const props = defineProps<{
    month: ForecastSnapshot['months'][number];
    active: boolean;
    structural: boolean;
    comparison?: ReturnType<typeof forecastMonthComparison>;
    reference: { label: string; description: string };
}>();
const emit = defineEmits<{ select: []; extras: []; compare: [] }>();
const historical = computed(
    () =>
        props.month.warnings?.filter((warning) => warning.kind === 'historical')
            .length ?? 0,
);
const planned = computed(
    () =>
        props.month.warnings?.filter((warning) => warning.kind === 'planned')
            .length ?? 0,
);
</script>

<template>
    <div
        class="relative overflow-hidden rounded-2xl border shadow-sm transition-all duration-150 motion-safe:hover:-translate-y-0.5"
        :class="
            active
                ? 'border-indigo-400 bg-indigo-500/15 ring-2 shadow-indigo-500/10 ring-indigo-500/20'
                : month.after.free < 0
                  ? 'border-rose-500/30 bg-rose-500/[0.06] hover:border-rose-400/60'
                  : 'border-emerald-500/20 bg-emerald-500/[0.04] hover:border-emerald-400/50'
        "
    >
        <button
            type="button"
            class="w-full p-4 text-left focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:outline-none focus-visible:ring-inset"
            :aria-pressed="active"
            @click="emit('select')"
        >
            <span
                class="block text-sm font-semibold text-slate-300 capitalize"
                >{{ monthLabel(month.month) }}</span
            >
            <span
                class="mt-3 block font-mono text-base font-bold tracking-tight sm:text-lg"
                :class="
                    month.after.free < 0 ? 'text-rose-400' : 'text-emerald-400'
                "
                >{{ euro(month.after.free) }}</span
            >
            <span
                class="mt-1 flex items-center gap-1 text-xs font-medium"
                :class="
                    month.after.free < 0
                        ? 'text-rose-300'
                        : 'text-emerald-300/80'
                "
                ><TriangleAlert
                    v-if="month.after.free < 0"
                    class="h-3 w-3"
                    aria-hidden="true"
                /><CheckCircle2 v-else class="h-3 w-3" aria-hidden="true" />{{
                    month.after.free < 0
                        ? 'Tekort deze maand'
                        : 'Ruimte na sparen'
                }}</span
            >
            <span
                class="mt-4 block w-full min-w-0 space-y-2 rounded-xl border border-indigo-500/20 bg-slate-950/60 p-3 text-xs leading-relaxed"
                :title="reference.description"
            >
                <span class="flex w-full items-start gap-2 text-slate-300"
                    ><ArrowRightLeft
                        class="mt-0.5 h-4 w-4 shrink-0 text-indigo-300"
                        aria-hidden="true"
                    /><span class="sr-only">Vergelijking met </span
                    ><strong
                        class="min-w-0 font-semibold [overflow-wrap:anywhere] text-indigo-200"
                        >{{ reference.label }}</strong
                    ></span
                >
                <span
                    class="flex w-full flex-wrap justify-between gap-x-3 gap-y-1 text-slate-400"
                    ><span>Begroting</span
                    ><span class="whitespace-nowrap tabular-nums">{{
                        euro(month.before.free)
                    }}</span></span
                >
                <span
                    class="flex w-full flex-wrap justify-between gap-x-3 gap-y-1 font-semibold"
                    :class="
                        month.after.free > month.before.free
                            ? 'text-emerald-300'
                            : month.after.free < month.before.free
                              ? 'text-rose-300'
                              : 'text-slate-400'
                    "
                    ><span>vs begroting</span
                    ><span class="whitespace-nowrap tabular-nums">{{
                        signedEuro(
                            Math.round(
                                (month.after.free - month.before.free) * 100,
                            ) / 100,
                        )
                    }}</span></span
                >
                <span v-if="structural" class="block text-slate-400"
                    >Exclusief extra kosten</span
                >
            </span>
        </button>
        <button
            v-if="comparison"
            type="button"
            class="flex w-full items-start gap-1.5 border-t border-indigo-500/15 bg-indigo-500/[0.04] px-3 py-2 text-left text-[10px] text-indigo-300 hover:bg-indigo-500/10"
            :aria-label="`Afwijkingen buiten vaste begroting voor ${monthLabel(month.month)}`"
            @click="emit('compare')"
        >
            <ArrowRightLeft class="mt-0.5 h-3 w-3 shrink-0" /><span
                >Afwijking sinds {{ monthLabel(comparison.previousMonth)
                }}<strong
                    class="block font-mono"
                    :class="
                        comparison.difference > 0
                            ? 'text-emerald-300'
                            : comparison.difference < 0
                              ? 'text-rose-300'
                              : 'text-slate-400'
                    "
                    >{{ signedEuro(comparison.difference) }} ·
                    {{ comparison.rows.length }}
                    {{
                        comparison.rows.length === 1 ? 'post' : 'posten'
                    }}</strong
                ></span
            >
        </button>
        <button
            v-if="historical || planned"
            type="button"
            class="flex w-full items-start gap-1.5 border-t border-amber-500/20 bg-amber-500/[0.07] p-2.5 text-left text-[11px] text-amber-300 transition-colors hover:bg-amber-500/15 focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:outline-none focus-visible:ring-inset"
            :aria-label="`Extra kosten bekijken voor ${monthLabel(month.month)}`"
            @click="emit('extras')"
        >
            <TriangleAlert class="mt-0.5 h-3.5 w-3.5 shrink-0" />
            <span
                ><span v-if="historical" class="block"
                    >Historische referentie: {{ historical }}
                    {{ historical === 1 ? 'post' : 'posten' }}</span
                ><span v-if="planned" class="block"
                    >Geplande extra kosten: {{ planned }}
                    {{ planned === 1 ? 'post' : 'posten' }}</span
                ></span
            >
        </button>
    </div>
</template>
