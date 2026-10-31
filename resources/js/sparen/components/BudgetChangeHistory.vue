<script setup lang="ts">
import { ref, watch } from 'vue';
import { sparenRequest } from '../api';
import {
    euro,
    monthLabel,
    ruleLabel,
    type BudgetHistoryEntry,
} from '../forecast';

const props = defineProps<{ itemId: string }>();
const rows = ref<BudgetHistoryEntry[]>([]);
const loading = ref(false);
const error = ref('');
let request = 0;
watch(
    () => props.itemId,
    async (id) => {
        const current = ++request;
        loading.value = true;
        error.value = '';
        rows.value = [];
        try {
            const result = await sparenRequest(
                'GET',
                `/api/sparen/budget-items/${encodeURIComponent(id)}/history`,
            );
            if (current === request) rows.value = result;
        } catch (err) {
            if (current === request)
                error.value =
                    err instanceof Error
                        ? err.message
                        : 'Historie laden mislukt.';
        } finally {
            if (current === request) loading.value = false;
        }
    },
    { immediate: true },
);
const shortMonth = (slot: number) =>
    new Intl.DateTimeFormat('nl-NL', { month: 'short' }).format(
        new Date(2026, slot - 1, 1),
    );
const dateLabel = (date: string) =>
    new Date(date).toLocaleString('nl-NL', { timeZone: 'Europe/Amsterdam' });
</script>

<template>
    <section class="space-y-3" aria-label="Wijzigingshistorie">
        <h3 class="font-semibold text-white">
            Wijzigingshistorie vanuit prognoses
        </h3>
        <p v-if="loading" class="text-sm text-slate-400" role="status">
            Historie laden…
        </p>
        <p v-else-if="error" class="text-sm text-rose-300" role="alert">
            {{ error }}
        </p>
        <p v-else-if="!rows.length" class="text-sm text-slate-400">
            Deze post heeft nog geen wijzigingen vanuit een prognose.
        </p>
        <article
            v-for="row in rows"
            :key="row.id"
            class="space-y-2 rounded-xl border border-slate-700 bg-slate-950/40 p-3 text-sm"
        >
            <div class="flex flex-wrap justify-between gap-2">
                <strong class="text-white">{{
                    row.forecastName || 'Prognose'
                }}</strong>
                <span class="text-slate-400">{{
                    dateLabel(row.createdAt)
                }}</span>
            </div>
            <p class="text-slate-300">
                Vanaf {{ monthLabel(row.effectiveMonth) }} ·
                {{
                    row.rule
                        ? ruleLabel(row.rule)
                        : row.description || 'Vast bedrag'
                }}
            </p>
            <p v-if="row.endMonth" class="text-amber-300">
                Geldig tot {{ monthLabel(row.endMonth) }} (exclusief).
            </p>
            <p v-if="row.revertedAt" class="text-amber-300">
                Teruggedraaid op {{ dateLabel(row.revertedAt) }}, vanaf
                {{ monthLabel(row.revertedFrom!) }}.
            </p>
            <div class="flex flex-wrap gap-2">
                <span
                    v-for="slot in row.scheduledMonths"
                    :key="slot"
                    class="rounded-lg bg-slate-800 px-2 py-1 text-xs text-slate-300"
                >
                    {{ shortMonth(slot) }}:
                    {{ euro(row.beforeAmounts[slot] ?? 0) }} →
                    {{ euro(row.amounts[slot] ?? 0) }}
                </span>
            </div>
            <p v-if="row.createdBy" class="text-xs text-slate-500">
                Vastgelegd door {{ row.createdBy }}
            </p>
            <p v-if="row.notes" class="whitespace-pre-wrap text-slate-400">
                {{ row.notes }}
            </p>
        </article>
    </section>
</template>
