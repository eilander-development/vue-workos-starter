<script setup lang="ts">
import {
    ArrowDownCircle,
    ArrowUpCircle,
    PiggyBank,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { euro, signedEuro, type ForecastTotals } from '../forecast';

const props = defineProps<{
    kind: keyof ForecastTotals;
    label: string;
    before: number;
    after: number;
}>();
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
        <div
            class="mt-4 flex flex-wrap items-baseline justify-between gap-1 border-t border-slate-700/50 pt-3 text-[11px]"
        >
            <span class="text-slate-400">Begroting</span
            ><span class="font-mono text-slate-300">{{ euro(before) }}</span>
        </div>
        <span
            class="mt-2 self-start rounded-lg border px-2 py-1 text-[11px] font-semibold tabular-nums"
            :class="deltaTone"
            >Verschil {{ signedEuro(difference) }}</span
        >
    </article>
</template>
