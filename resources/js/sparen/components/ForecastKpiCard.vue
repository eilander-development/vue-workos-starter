<script setup lang="ts">
import {
    ArrowDownCircle,
    ArrowRightLeft,
    ArrowUpCircle,
    PiggyBank,
    SlidersHorizontal,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import {
    euro,
    monthLabel,
    signedEuro,
    type ForecastKpiRuleEffect,
    type ForecastTotals,
} from '../forecast';

const props = defineProps<{
    kind: keyof ForecastTotals;
    label: string;
    before: number;
    after: number;
    basis: number;
    reference: { label: string; description: string };
    ruleEffects: ForecastKpiRuleEffect[];
}>();
const emit = defineEmits<{ openItem: [itemId: string] }>();
const tones = {
    income: {
        icon: ArrowUpCircle,
        card: 'border-emerald-500/25 from-emerald-500/10',
        badge: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-400',
        value: 'text-emerald-400',
        stripe: 'bg-emerald-400',
    },
    expenses: {
        icon: ArrowDownCircle,
        card: 'border-rose-500/25 from-rose-500/10',
        badge: 'border-rose-500/20 bg-rose-500/10 text-rose-400',
        value: 'text-rose-400',
        stripe: 'bg-rose-400',
    },
    savings: {
        icon: PiggyBank,
        card: 'border-blue-500/25 from-blue-500/10',
        badge: 'border-blue-500/20 bg-blue-500/10 text-blue-400',
        value: 'text-blue-400',
        stripe: 'bg-blue-400',
    },
    free: {
        icon: Wallet,
        card: 'border-indigo-500/30 from-indigo-500/15',
        badge: 'border-indigo-500/25 bg-indigo-500/15 text-indigo-300',
        value: 'text-indigo-300',
        stripe: 'bg-indigo-400',
    },
};
const tone = computed(() => tones[props.kind]);
const difference = computed(
    () => Math.round((props.after - props.before) * 100) / 100,
);
const ruleEffect = computed(
    () => Math.round((props.after - props.basis) * 100) / 100,
);
const deltaTone = computed(() => {
    if (difference.value === 0)
        return 'border-slate-700/70 bg-slate-800/60 text-slate-400';
    if (props.kind === 'savings')
        return 'border-blue-500/20 bg-blue-500/10 text-blue-300';
    const favorable =
        props.kind === 'expenses' ? difference.value < 0 : difference.value > 0;
    return favorable
        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-300'
        : 'border-rose-500/20 bg-rose-500/10 text-rose-300';
});
</script>

<template>
    <article
        class="relative flex min-w-0 flex-col overflow-hidden rounded-2xl border bg-slate-900 bg-gradient-to-br to-slate-900 p-4 shadow-sm"
        :class="tone.card"
    >
        <div
            class="absolute inset-x-0 top-0 h-0.5"
            :class="tone.stripe"
            aria-hidden="true"
        />
        <div class="mb-4 flex items-start justify-between gap-2">
            <h3
                class="pt-1 text-xs leading-relaxed font-semibold text-slate-300"
            >
                {{ label }}
            </h3>
            <span
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border"
                :class="tone.badge"
                ><component :is="tone.icon" class="h-4 w-4" aria-hidden="true"
            /></span>
        </div>
        <p
            class="font-mono text-2xl font-black tracking-tight"
            :class="kind === 'free' && after < 0 ? 'text-rose-400' : tone.value"
        >
            {{ euro(after) }}
        </p>
        <div class="mt-4 space-y-2 border-t border-slate-700/50 pt-3 text-xs">
            <div class="flex flex-wrap justify-between gap-2 text-slate-400">
                <span>Vóór jouw regels</span
                ><span class="font-mono text-slate-300">{{ euro(basis) }}</span>
            </div>
            <div
                class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-violet-500/20 bg-violet-500/10 px-2 py-2 text-violet-200"
            >
                <span class="flex items-center gap-1.5"
                    ><SlidersHorizontal
                        class="h-3.5 w-3.5"
                        aria-hidden="true"
                    />Effect van jouw regels</span
                ><strong class="font-mono">{{ signedEuro(ruleEffect) }}</strong>
            </div>
        </div>
        <div
            class="mt-3 mb-3 space-y-2 text-xs"
            aria-label="Effect per prognoseregel"
        >
            <p v-if="!ruleEffects.length" class="text-slate-500">
                Geen regels voor deze KPI in deze maand.
            </p>
            <div
                v-for="rule in ruleEffects"
                :key="rule.ruleId"
                class="flex items-start justify-between gap-3 rounded-lg bg-violet-500/[0.04] p-2"
            >
                <div class="min-w-0">
                    <button
                        type="button"
                        class="text-left font-semibold text-violet-200 underline decoration-violet-500/30 underline-offset-2"
                        :aria-label="`Post bij regel bekijken: ${rule.name}`"
                        @click="emit('openItem', rule.itemId)"
                    >
                        {{ rule.name }}
                    </button>
                    <p class="mt-1 text-[11px] leading-relaxed text-slate-400">
                        {{ rule.label }} · vanaf
                        {{ monthLabel(rule.effectiveMonth) }}
                    </p>
                    <p v-if="rule.note" class="mt-1 text-[11px] text-amber-200">
                        {{ rule.note }}
                    </p>
                </div>
                <strong
                    class="shrink-0 font-mono tabular-nums"
                    :class="
                        kind === 'free'
                            ? rule.effect > 0
                                ? 'text-emerald-300'
                                : rule.effect < 0
                                  ? 'text-rose-300'
                                  : 'text-slate-500'
                            : 'text-violet-200'
                    "
                    >{{ signedEuro(rule.effect) }}</strong
                >
            </div>
        </div>
        <div
            class="mt-auto space-y-2 rounded-xl border border-indigo-500/20 bg-slate-950/60 p-3 text-xs"
            :title="reference.description"
        >
            <div class="flex items-start gap-2">
                <ArrowRightLeft
                    class="h-4 w-4 shrink-0 text-indigo-300"
                    aria-hidden="true"
                /><span class="sr-only">Vergelijking met </span
                ><strong
                    class="min-w-0 font-semibold [overflow-wrap:anywhere] text-indigo-200"
                    >{{ reference.label }}</strong
                >
            </div>
            <div class="flex flex-wrap justify-between gap-2 text-slate-400">
                <span>Referentiebegroting</span
                ><span class="font-mono text-slate-300">{{
                    euro(before)
                }}</span>
            </div>
            <div
                class="flex flex-wrap items-center justify-between gap-2 text-slate-400"
            >
                <span>vs referentie</span
                ><span
                    class="rounded-lg border px-2 py-1 font-semibold tabular-nums"
                    :class="deltaTone"
                    >{{ signedEuro(difference) }}</span
                >
            </div>
        </div>
    </article>
</template>
