<script setup lang="ts">
import { Receipt, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { normalizeAllocations } from '../allocations';
import { transactionMatchesSavingsGoal } from '../matchSavings';
import {
    transactionArchiveReason,
    transactionStatusLabel,
} from '../transactionStatus';
import type { BudgetItem, Rule, SavingsGoal, Transaction } from '../types';
import ModalShell from './ModalShell.vue';
import TransactionDate from './TransactionDate.vue';
import TransactionHistoryModal from './TransactionHistoryModal.vue';

const props = withDefaults(
    defineProps<{
        transaction: Transaction | null;
        budgetItems?: BudgetItem[];
        rules?: Rule[];
        savingsGoals?: SavingsGoal[];
        onClose: () => void;
    }>(),
    {
        budgetItems: () => [],
        rules: () => [],
        savingsGoals: () => [],
    },
);
const showHistory = ref(false);

const euro = (n: number) =>
    n.toLocaleString('nl-NL', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const budgetItemMap = computed(() => {
    const map = new Map<string, BudgetItem>();
    props.budgetItems.forEach((item) => map.set(item.id, item));
    return map;
});

const matchedRule = computed(() => {
    const id = props.transaction?.matchedRuleId;
    if (!id) return null;
    return props.rules.find((rule) => rule.id === id) ?? null;
});

const savingsGoal = computed(() => {
    const transaction = props.transaction;
    if (!transaction) return null;
    if (transaction.assignedSavingsGoalId) {
        return (
            props.savingsGoals.find(
                (goal) => goal.id === transaction.assignedSavingsGoalId,
            ) ?? null
        );
    }
    return (
        props.savingsGoals.find((goal) =>
            transactionMatchesSavingsGoal(transaction, goal),
        ) ?? null
    );
});

const allocations = computed(() =>
    props.transaction
        ? (normalizeAllocations(props.transaction.allocations) ?? [])
        : [],
);

const bankRecord = computed(() => asRecord(props.transaction?.bankPayload));

const bankDates = computed(() => {
    const payload = bankRecord.value;
    if (!payload) return [];

    const nested = asRecord(payload.raw) ?? payload;
    const rows: Array<{ label: string; value: string }> = [];
    const add = (label: string, keys: string[]) => {
        const value = stringField(nested, keys) ?? stringField(payload, keys);
        if (value) rows.push({ label, value });
    };

    add('Boekdatum (bank)', ['booking_date', 'bookingDate']);
    add('Boekdatum + tijd', [
        'booking_datetime',
        'booking_date_time',
        'bookingDateTime',
    ]);
    add('Valutadatum', ['value_date', 'valueDate']);
    add('Transactiedatum', ['transaction_date', 'transactionDate']);
    return rows;
});

const rawJson = computed(() => {
    if (!props.transaction?.bankPayload) return null;
    try {
        return JSON.stringify(props.transaction.bankPayload, null, 2);
    } catch {
        return null;
    }
});

function asRecord(value: unknown): Record<string, unknown> | null {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
        return value as Record<string, unknown>;
    }
    return null;
}

function stringField(
    record: Record<string, unknown> | null,
    keys: string[],
): string | null {
    if (!record) return null;
    for (const key of keys) {
        const value = record[key];
        if (typeof value === 'string' && value.trim() !== '') {
            return value.trim();
        }
    }
    return null;
}
</script>

<template>
    <ModalShell v-if="transaction" :on-close="onClose">
        <div
            role="dialog"
            aria-modal="true"
            aria-label="Transactie"
            class="flex max-h-[92vh] w-full flex-col overflow-hidden rounded-t-2xl border border-slate-800 bg-slate-900 shadow-2xl sm:max-w-2xl sm:rounded-2xl"
            @click.stop
        >
            <div
                class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-800 px-5 py-4"
            >
                <div class="flex min-w-0 items-start gap-3">
                    <div
                        class="shrink-0 rounded-xl border border-indigo-500/30 bg-indigo-500/15 p-2 text-indigo-400"
                    >
                        <Receipt class="h-4 w-4" />
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-base font-bold text-white">
                            Transactie
                        </h3>
                        <p class="mt-0.5 text-xs break-words text-slate-400">
                            {{ transaction.description }}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    class="shrink-0 rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-800 hover:text-white"
                    @click="onClose"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <div class="flex-1 space-y-5 overflow-y-auto p-5 text-xs">
                <section class="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                    <div
                        class="rounded-xl border border-slate-700/70 bg-slate-800/60 p-3"
                    >
                        <p
                            class="text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                        >
                            Boekdatum
                        </p>
                        <div class="mt-1">
                            <TransactionDate
                                :date="transaction.date"
                                :time="transaction.time"
                            />
                        </div>
                    </div>
                    <div
                        class="rounded-xl border border-slate-700/70 bg-slate-800/60 p-3"
                    >
                        <p
                            class="text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                        >
                            Bedrag
                        </p>
                        <p
                            class="mt-1 font-mono text-sm font-bold"
                            :class="
                                transaction.amount >= 0
                                    ? 'text-emerald-400'
                                    : 'text-rose-400'
                            "
                        >
                            {{ transaction.amount >= 0 ? '+' : '−' }}€
                            {{ euro(Math.abs(transaction.amount)) }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-slate-700/70 bg-slate-800/60 p-3"
                    >
                        <p
                            class="text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                        >
                            Type
                        </p>
                        <p class="mt-1 font-semibold text-slate-200">
                            {{ transaction.type }}
                        </p>
                    </div>
                </section>

                <section class="space-y-2">
                    <h4
                        class="text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                    >
                        Bankmutatie
                    </h4>
                    <dl
                        class="divide-y divide-slate-800 rounded-xl border border-slate-800 bg-slate-800/40"
                    >
                        <div class="flex justify-between gap-3 px-3.5 py-2.5">
                            <dt class="shrink-0 text-slate-500">
                                Omschrijving
                            </dt>
                            <dd class="text-right break-words text-slate-200">
                                {{ transaction.description }}
                            </dd>
                        </div>
                        <div
                            v-if="transaction.counterparty"
                            class="flex justify-between gap-3 px-3.5 py-2.5"
                        >
                            <dt class="shrink-0 text-slate-500">Tegenpartij</dt>
                            <dd class="text-right break-words text-slate-200">
                                {{ transaction.counterparty }}
                            </dd>
                        </div>
                        <div
                            v-if="transaction.counterpartyIban"
                            class="flex justify-between gap-3 px-3.5 py-2.5"
                        >
                            <dt class="shrink-0 text-slate-500">
                                Tegenpartij IBAN
                            </dt>
                            <dd
                                class="text-right font-mono break-all text-slate-200"
                            >
                                {{ transaction.counterpartyIban }}
                            </dd>
                        </div>
                        <div
                            v-if="transaction.accountIban"
                            class="flex justify-between gap-3 px-3.5 py-2.5"
                        >
                            <dt class="shrink-0 text-slate-500">
                                Eigen rekening
                            </dt>
                            <dd
                                class="text-right font-mono break-all text-slate-200"
                            >
                                {{ transaction.accountIban }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3 px-3.5 py-2.5">
                            <dt class="shrink-0 text-slate-500">Bron</dt>
                            <dd class="text-slate-200">
                                {{ transaction.source }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3 px-3.5 py-2.5">
                            <dt class="shrink-0 text-slate-500">Status</dt>
                            <dd
                                :class="
                                    transaction.status === 'history'
                                        ? 'text-slate-300'
                                        : transaction.isPending
                                          ? 'text-amber-300'
                                          : 'text-emerald-300'
                                "
                            >
                                {{ transactionStatusLabel(transaction) }}
                            </dd>
                        </div>
                        <div
                            v-if="transaction.status === 'history'"
                            class="px-3.5 py-2.5 text-xs text-slate-400"
                        >
                            {{
                                transactionArchiveReason(
                                    transaction.archiveReason,
                                )
                            }}. Telt nergens mee.
                        </div>
                        <div
                            v-if="transaction.importedAt"
                            class="flex justify-between gap-3 px-3.5 py-2.5"
                        >
                            <dt class="shrink-0 text-slate-500">
                                Geïmporteerd
                            </dt>
                            <dd class="font-mono text-slate-200">
                                {{ transaction.importedAt }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3 px-3.5 py-2.5">
                            <dt class="shrink-0 text-slate-500">ID</dt>
                            <dd
                                class="text-right font-mono break-all text-slate-400"
                            >
                                {{ transaction.id }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section v-if="bankDates.length > 0" class="space-y-2">
                    <h4
                        class="text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                    >
                        Datums van de bank
                    </h4>
                    <dl
                        class="divide-y divide-slate-800 rounded-xl border border-slate-800 bg-slate-800/40"
                    >
                        <div
                            v-for="row in bankDates"
                            :key="row.label"
                            class="flex justify-between gap-3 px-3.5 py-2.5"
                        >
                            <dt class="shrink-0 text-slate-500">
                                {{ row.label }}
                            </dt>
                            <dd class="text-right font-mono text-slate-200">
                                {{ row.value }}
                            </dd>
                        </div>
                    </dl>
                    <p class="text-[10px] text-slate-500">
                        De app gebruikt overal de boekdatum. Factuurdatums in de
                        omschrijving tellen niet mee.
                    </p>
                </section>

                <section class="space-y-2">
                    <h4
                        class="text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                    >
                        Koppeling
                    </h4>
                    <dl
                        class="divide-y divide-slate-800 rounded-xl border border-slate-800 bg-slate-800/40"
                    >
                        <div class="flex justify-between gap-3 px-3.5 py-2.5">
                            <dt class="shrink-0 text-slate-500">Rubriek</dt>
                            <dd class="text-right text-slate-200">
                                {{ transaction.categoryGroup }}
                            </dd>
                        </div>
                        <div v-if="allocations.length > 0">
                            <div
                                v-for="row in allocations"
                                :key="row.budgetItemId"
                                class="flex justify-between gap-3 px-3.5 py-2.5"
                            >
                                <dt class="shrink-0 text-slate-500">
                                    {{
                                        allocations.length > 1
                                            ? 'Deelpost'
                                            : 'Begrotingspost'
                                    }}
                                </dt>
                                <dd class="text-right text-slate-200">
                                    {{
                                        budgetItemMap.get(row.budgetItemId)
                                            ?.name ?? row.budgetItemId
                                    }}
                                    <span
                                        v-if="allocations.length > 1"
                                        class="ml-1 font-mono text-slate-400"
                                    >
                                        € {{ euro(row.amount) }}
                                    </span>
                                </dd>
                            </div>
                        </div>
                        <div
                            v-else-if="transaction.budgetItemId"
                            class="flex justify-between gap-3 px-3.5 py-2.5"
                        >
                            <dt class="shrink-0 text-slate-500">
                                Begrotingspost
                            </dt>
                            <dd class="text-right text-slate-200">
                                {{
                                    budgetItemMap.get(transaction.budgetItemId)
                                        ?.name ?? transaction.budgetItemId
                                }}
                            </dd>
                        </div>
                        <div
                            v-if="matchedRule"
                            class="flex justify-between gap-3 px-3.5 py-2.5"
                        >
                            <dt class="shrink-0 text-slate-500">Koppelregel</dt>
                            <dd class="text-right text-slate-200">
                                {{ matchedRule.name }}
                            </dd>
                        </div>
                        <div
                            v-if="savingsGoal"
                            class="flex justify-between gap-3 px-3.5 py-2.5"
                        >
                            <dt class="shrink-0 text-slate-500">Spaardoel</dt>
                            <dd class="text-right text-slate-200">
                                {{ savingsGoal.name }}
                            </dd>
                        </div>
                        <div
                            v-if="transaction.linkExcluded"
                            class="flex justify-between gap-3 px-3.5 py-2.5"
                        >
                            <dt class="shrink-0 text-slate-500">Verwerking</dt>
                            <dd class="text-right text-slate-200">
                                Automatisch verwerkt als spaaroverboeking
                                <span
                                    v-if="savingsGoal"
                                    class="mt-0.5 block text-indigo-300"
                                >
                                    {{
                                        savingsGoal.kind === 'pot'
                                            ? 'Potje'
                                            : 'Spaarrekening'
                                    }}: {{ savingsGoal.name }}
                                </span>
                                <span
                                    v-if="transaction.linkExclusionReason"
                                    class="mt-0.5 block text-slate-500"
                                >
                                    {{ transaction.linkExclusionReason }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </section>

                <section v-if="rawJson" class="space-y-2">
                    <h4
                        class="text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                    >
                        Ruwe bankdata
                    </h4>
                    <pre
                        class="max-h-72 overflow-x-auto rounded-xl border border-slate-800 bg-slate-950 p-3 font-mono text-[10px] break-all whitespace-pre-wrap text-slate-400"
                        >{{ rawJson }}</pre
                    >
                </section>
            </div>

            <div
                class="flex shrink-0 justify-between border-t border-slate-800 px-5 py-3"
            >
                <button
                    v-if="transaction && transaction.status !== 'history'"
                    type="button"
                    class="rounded-lg bg-slate-800 px-3.5 py-1.5 text-xs text-indigo-200"
                    @click="showHistory = true"
                >
                    Historie van deze transactie
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-slate-800 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-slate-700"
                    @click="onClose"
                >
                    Sluiten
                </button>
            </div>
        </div>
    </ModalShell>
    <TransactionHistoryModal
        v-if="showHistory && transaction"
        :transaction-key="transaction.id"
        @close="showHistory = false"
    />
</template>
