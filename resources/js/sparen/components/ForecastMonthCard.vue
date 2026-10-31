<script setup lang="ts">
import { CheckCircle2, TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';
import {
    euro,
    monthLabel,
    signedEuro,
    type ForecastSnapshot,
} from '../forecast';
const props = defineProps<{
    month: ForecastSnapshot['months'][number];
    active: boolean;
    structural: boolean;
}>();
const emit = defineEmits<{ select: []; extras: [] }>();
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
            class="w-full p-3.5 text-left focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:outline-none focus-visible:ring-inset"
            :aria-pressed="active"
            @click="emit('select')"
        >
            <span
                class="block text-xs font-semibold text-slate-300 capitalize"
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
                class="mt-1 flex items-center gap-1 text-[10px] font-medium"
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
                class="mt-3 block border-t border-slate-700/50 pt-2 text-[11px] text-slate-400"
                >Begroting {{ euro(month.before.free) }}</span
            >
            <span
                class="mt-1 block text-xs font-semibold tabular-nums"
                :class="
                    month.after.free > month.before.free
                        ? 'text-emerald-300'
                        : month.after.free < month.before.free
                          ? 'text-rose-300'
                          : 'text-slate-400'
                "
                >{{
                    signedEuro(
                        Math.round(
                            (month.after.free - month.before.free) * 100,
                        ) / 100,
                    )
                }}</span
            >
            <span
                v-if="structural"
                class="mt-1 block text-[10px] text-slate-500"
                >Exclusief extra kosten</span
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
