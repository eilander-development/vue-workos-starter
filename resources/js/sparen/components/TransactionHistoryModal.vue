<script setup lang="ts">
import { History, X } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { loadTransactionHistory } from '../api';
import { useTransactionDetail } from '../composables/useTransactionDetail';
import { transactionArchiveReason } from '../transactionStatus';
import type { Transaction } from '../types';
import ModalShell from './ModalShell.vue';
const props = defineProps<{ transactionKey?: string }>();
const emit = defineEmits<{ close: [] }>();
const { openTransactionDetail } = useTransactionDetail();
const rows = ref<Transaction[]>([]);
const search = ref('');
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const loading = ref(false);
const error = ref('');
async function load(nextPage = 1) {
    if (loading.value) return;
    loading.value = true;
    error.value = '';
    try {
        const data = await loadTransactionHistory(
            search.value,
            nextPage,
            props.transactionKey,
        );
        rows.value = data.data;
        page.value = data.current_page;
        lastPage.value = data.last_page;
        total.value = data.total;
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Historie laden mislukt';
    } finally {
        loading.value = false;
    }
}
onMounted(() => load());
const euro = (amount: number) =>
    amount.toLocaleString('nl-NL', { style: 'currency', currency: 'EUR' });
</script>

<template>
    <ModalShell :on-close="() => emit('close')">
        <section
            role="dialog"
            aria-modal="true"
            aria-labelledby="transaction-history-title"
            class="max-h-[90vh] w-full max-w-4xl space-y-4 overflow-y-auto rounded-2xl border border-slate-700 bg-slate-900 p-5 text-slate-200"
        >
            <div class="flex items-center gap-3">
                <History class="h-5 w-5 text-indigo-300" />
                <h2
                    id="transaction-history-title"
                    class="text-lg font-bold text-white"
                >
                    Transactiehistorie
                </h2>
                <span class="text-xs text-slate-400"
                    >{{ total }} registraties</span
                ><button
                    type="button"
                    class="ml-auto rounded-lg bg-slate-800 p-2"
                    aria-label="Transactiehistorie sluiten"
                    @click="emit('close')"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>
            <p
                class="rounded-xl border border-indigo-500/20 bg-slate-950/50 p-3 text-sm text-slate-300"
            >
                Deze registraties hebben het label Historie. Ze blijven
                inzichtelijk, maar tellen nooit mee in begrotingen, saldi,
                betaalritmes of potcompensatie. Koppelingen en splitsingen zijn
                bewaard.
            </p>
            <form class="flex gap-2" @submit.prevent="load()">
                <input
                    v-model="search"
                    type="search"
                    aria-label="Zoek in transactiehistorie"
                    placeholder="Zoek omschrijving"
                    class="min-w-0 flex-1 rounded-xl border border-slate-700 bg-slate-800 p-2 text-sm"
                /><button
                    type="submit"
                    :disabled="loading"
                    class="rounded-xl bg-indigo-600 px-4 text-sm"
                >
                    Zoeken
                </button>
            </form>
            <p v-if="error" role="alert" class="text-rose-300">{{ error }}</p>
            <p v-if="loading" role="status" class="text-sm text-slate-400">
                Historie laden…
            </p>
            <p v-else-if="!rows.length" class="text-sm text-slate-400">
                Geen historische registraties gevonden.
            </p>
            <article
                v-for="tx in rows"
                :key="tx.id"
                class="space-y-2 rounded-xl border border-slate-700 bg-slate-950/30 p-4"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <button
                        type="button"
                        class="min-w-0 text-left font-semibold text-white underline decoration-indigo-500/40 underline-offset-4"
                        @click="
                            emit('close');
                            openTransactionDetail(tx);
                        "
                    >
                        {{ tx.description }}</button
                    ><span
                        class="rounded-md bg-slate-700 px-2 py-1 text-xs font-semibold"
                        >Historie</span
                    >
                </div>
                <p class="text-sm text-slate-300">
                    {{ tx.date.split('-').reverse().join('-') }} ·
                    {{ euro(tx.amount) }} ·
                    {{ tx.isPending ? 'Was reservering' : 'Was geboekt' }}
                </p>
                <p class="text-xs text-indigo-200">
                    {{ transactionArchiveReason(tx.archiveReason) }}
                </p>
                <p class="text-xs text-slate-400">
                    {{ tx.categoryGroup
                    }}{{ tx.budgetItemId ? ` · ${tx.budgetItemName ?? tx.budgetItemId}` : ''
                    }}{{
                        tx.allocations?.length
                            ? ` · ${tx.allocations.length} splits`
                            : ''
                    }}
                </p>
                <p
                    v-if="tx.supersededBy"
                    class="text-xs break-all text-slate-500"
                >
                    Actieve transactie: {{ tx.supersededBy }}
                </p>
            </article>
            <div class="flex items-center justify-between gap-3 text-xs">
                <button
                    type="button"
                    :disabled="loading || page <= 1"
                    class="rounded-lg bg-slate-800 px-3 py-2 disabled:opacity-40"
                    @click="load(page - 1)"
                >
                    Vorige</button
                ><span>{{ page }} / {{ lastPage }}</span
                ><button
                    type="button"
                    :disabled="loading || page >= lastPage"
                    class="rounded-lg bg-slate-800 px-3 py-2 disabled:opacity-40"
                    @click="load(page + 1)"
                >
                    Volgende
                </button>
            </div>
        </section>
    </ModalShell>
</template>
