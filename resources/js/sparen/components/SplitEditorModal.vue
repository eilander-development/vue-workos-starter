<script setup lang="ts">
import { Plus, Split, Trash2, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { normalizeAllocations, roundMoney } from '../allocations';
import { splitAllocationsFromBudget } from '../splitStatus';
import type { BudgetItem, BudgetType, Rule } from '../types';
import ModalShell from './ModalShell.vue';

const props = withDefaults(
    defineProps<{
        isOpen: boolean;
        onClose: () => void;
        onSave: (rule: Omit<Rule, 'matchedCount'>) => void;
        editingRule?: Rule | null;
        budgetItems: BudgetItem[];
        useBudgetAmounts?: boolean;
        budgetMonthLabel?: string;
    }>(),
    {
        editingRule: null,
    },
);

const name = ref('');
const keyword = ref('');
const matchField = ref<'description' | 'counterparty' | 'both'>('description');
const isActive = ref(true);
const rows = ref<{ budgetItemId: string; amount: number }[]>([]);
const budgetCopied = ref(false);
const budgetAllocations = computed(() =>
    splitAllocationsFromBudget(rows.value, props.budgetItems),
);
function adoptBudget() {
    if (!budgetAllocations.value) return;
    rows.value = budgetAllocations.value.map((row) => ({ ...row }));
    budgetCopied.value = true;
}

watch(
    () => [props.isOpen, props.editingRule, props.useBudgetAmounts] as const,
    () => {
        if (!props.isOpen) {
            return;
        }

        const editing = props.editingRule;
        budgetCopied.value = false;
        if (editing) {
            name.value = editing.name;
            keyword.value = editing.keyword;
            matchField.value = editing.matchField;
            isActive.value = editing.isActive;
            const existing = normalizeAllocations(editing.allocations);
            rows.value = existing
                ? existing.map((row) => ({
                      budgetItemId: row.budgetItemId,
                      amount: row.amount,
                  }))
                : editing.targetBudgetItemId
                  ? [
                        {
                            budgetItemId: editing.targetBudgetItemId,
                            amount: amountForItem(editing.targetBudgetItemId),
                        },
                    ]
                  : [];
            if (props.useBudgetAmounts) adoptBudget();
            return;
        }

        name.value = '';
        keyword.value = '';
        matchField.value = 'description';
        isActive.value = true;
        rows.value = [];
    },
);

const unusedItems = computed(() => {
    const used = new Set(rows.value.map((row) => row.budgetItemId));
    return props.budgetItems.filter((item) => !used.has(item.id));
});

const plannedTotal = computed(() =>
    roundMoney(
        rows.value.reduce((sum, row) => sum + (Number(row.amount) || 0), 0),
    ),
);

const canSave = computed(
    () =>
        name.value.trim().length > 0 &&
        keyword.value.trim().length >= 2 &&
        rows.value.length >= 2 &&
        rows.value.every((row) => row.budgetItemId && Number(row.amount) > 0),
);

function amountForItem(itemId: string): number {
    const item = props.budgetItems.find((entry) => entry.id === itemId);
    return roundMoney(Math.abs(item?.actual ?? 0));
}

function itemName(itemId: string): string {
    return props.budgetItems.find((item) => item.id === itemId)?.name ?? itemId;
}

function itemGroup(itemId: string): string {
    return props.budgetItems.find((item) => item.id === itemId)?.group ?? '';
}

function addEnvelope(itemId: string) {
    if (!itemId) {
        return;
    }
    rows.value = [
        ...rows.value,
        { budgetItemId: itemId, amount: amountForItem(itemId) },
    ];
}

function removeRow(index: number) {
    rows.value = rows.value.filter((_, i) => i !== index);
}

function typeFromItem(item: BudgetItem | undefined): BudgetType {
    return item?.type ?? 'uitgaven';
}

function handleSubmit() {
    if (!canSave.value) {
        return;
    }

    const first = props.budgetItems.find(
        (item) => item.id === rows.value[0].budgetItemId,
    );
    props.onSave({
        id: props.editingRule?.id ?? `rule-${Date.now()}`,
        name: name.value.trim(),
        keyword: keyword.value.trim(),
        matchField: matchField.value,
        targetGroup: first?.group ?? 'Overige Kosten',
        targetBudgetItemId: rows.value[0].budgetItemId,
        targetType: typeFromItem(first),
        allocations: rows.value.map((row) => ({
            budgetItemId: row.budgetItemId,
            amount: roundMoney(Number(row.amount) || 0),
        })),
        isActive: isActive.value,
    });
    props.onClose();
}

function euro(amount: number): string {
    return amount.toLocaleString('nl-NL', { minimumFractionDigits: 2 });
}
</script>

<template>
    <ModalShell v-if="isOpen" :on-close="onClose" :close-on-backdrop="false">
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="split-editor-title"
            class="flex max-h-[92vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl"
        >
            <div
                class="flex shrink-0 items-center justify-between border-b border-slate-800 px-5 py-4"
            >
                <div class="flex items-center gap-2">
                    <Split class="h-4 w-4 text-indigo-400" />
                    <h3
                        id="split-editor-title"
                        class="text-base font-bold text-white"
                    >
                        {{ editingRule ? 'Split bewerken' : 'Nieuwe split' }}
                    </h3>
                </div>
                <button
                    type="button"
                    class="rounded-lg p-1 text-slate-400 hover:text-white"
                    @click="onClose"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <form
                class="flex min-h-0 flex-1 flex-col"
                @submit.prevent="handleSubmit"
            >
                <div class="flex-1 space-y-4 overflow-y-auto p-5 text-xs">
                    <p class="text-slate-400">
                        Kies de enveloppen die één bankmutatie samen vullen. Het
                        trefwoord herkent die mutaties.
                    </p>
                    <p
                        v-if="budgetCopied"
                        role="status"
                        class="rounded-xl border border-indigo-500/30 bg-indigo-500/10 p-3 text-indigo-200"
                    >
                        Begrotingsbedragen {{ budgetMonthLabel }} overgenomen.
                        Controleer de verdeling en kies Split opslaan.
                    </p>
                    <p
                        v-if="editingRule"
                        class="rounded-lg border border-slate-700 p-3 text-[11px] leading-relaxed text-slate-400"
                    >
                        Opslaan werkt de splitregel bij en past de
                        verdeelsleutel opnieuw toe op transacties die aan deze
                        regel gekoppeld zijn. De bankbedragen blijven behouden.
                    </p>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label
                                class="mb-1 block font-semibold text-slate-300"
                                >Naam</label
                            >
                            <input
                                v-model="name"
                                type="text"
                                required
                                placeholder="Bijv. InShared"
                                class="w-full rounded-xl border border-slate-700 bg-slate-800 px-3.5 py-2 text-white placeholder-slate-400 focus:border-indigo-500 focus:outline-none"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-1 block font-semibold text-slate-300"
                                >Trefwoord</label
                            >
                            <input
                                v-model="keyword"
                                type="text"
                                required
                                placeholder="Bijv. InShared"
                                class="w-full rounded-xl border border-slate-700 bg-slate-800 px-3.5 py-2 font-mono text-white placeholder-slate-400 focus:border-indigo-500 focus:outline-none"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label
                                class="mb-1 block font-semibold text-slate-300"
                                >Zoek in</label
                            >
                            <select
                                v-model="matchField"
                                class="w-full cursor-pointer rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none"
                            >
                                <option value="description">
                                    Omschrijving
                                </option>
                                <option value="counterparty">
                                    Tegenpartij
                                </option>
                                <option value="both">Beide</option>
                            </select>
                        </div>
                        <label
                            class="flex cursor-pointer items-end gap-2 pb-2 text-slate-300"
                        >
                            <input
                                v-model="isActive"
                                type="checkbox"
                                class="h-4 w-4 rounded accent-indigo-600"
                            />
                            <span class="font-semibold">{{
                                isActive ? 'Actief' : 'Gepauzeerd'
                            }}</span>
                        </label>
                    </div>

                    <div class="space-y-2">
                        <button
                            type="button"
                            class="rounded-lg border border-indigo-500/30 bg-indigo-500/10 px-3 py-2 text-indigo-200 disabled:opacity-40"
                            :disabled="!budgetAllocations"
                            @click="adoptBudget"
                        >
                            Begroting overnemen{{
                                budgetMonthLabel ? ` · ${budgetMonthLabel}` : ''
                            }}
                        </button>
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-bold text-white">
                                Enveloppen
                            </h4>
                            <span class="font-mono text-slate-300">
                                Samen € {{ euro(plannedTotal) }}
                            </span>
                        </div>

                        <div
                            v-if="rows.length === 0"
                            class="rounded-xl border border-dashed border-slate-800 p-4 text-center text-slate-500"
                        >
                            Voeg minstens twee enveloppen toe.
                        </div>

                        <div
                            v-for="(row, index) in rows"
                            :key="`${row.budgetItemId}-${index}`"
                            class="grid grid-cols-[1fr_7rem_auto] items-center gap-2 rounded-xl border border-slate-700/70 bg-slate-800/60 px-3 py-2"
                        >
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-white">
                                    {{ itemName(row.budgetItemId) }}
                                </p>
                                <p class="truncate text-[10px] text-slate-500">
                                    {{ itemGroup(row.budgetItemId) }}
                                </p>
                                <p class="mt-1 text-[10px] text-indigo-300">
                                    Begroot: €
                                    {{ euro(amountForItem(row.budgetItemId)) }}
                                </p>
                            </div>
                            <input
                                v-model.number="row.amount"
                                :aria-label="`Splitbedrag ${itemName(row.budgetItemId)}`"
                                type="number"
                                min="0.01"
                                step="0.01"
                                class="w-full rounded-lg border border-slate-600 bg-slate-900 px-2 py-1.5 text-right font-mono text-white focus:border-indigo-500 focus:outline-none"
                            />
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-slate-400 hover:text-rose-400"
                                title="Envelop verwijderen"
                                @click="removeRow(index)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <select
                            v-if="unusedItems.length > 0"
                            class="w-full cursor-pointer rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none"
                            @change="
                                addEnvelope(
                                    ($event.target as HTMLSelectElement).value,
                                );
                                ($event.target as HTMLSelectElement).value = '';
                            "
                        >
                            <option value="">+ Envelop toevoegen</option>
                            <option
                                v-for="item in unusedItems"
                                :key="item.id"
                                :value="item.id"
                            >
                                {{ item.group }} · {{ item.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <div
                    class="flex shrink-0 justify-end gap-2 border-t border-slate-800 px-5 py-3"
                >
                    <button
                        type="button"
                        class="rounded-xl bg-slate-800 px-4 py-2 font-medium text-slate-300 hover:bg-slate-700"
                        @click="onClose"
                    >
                        Annuleren
                    </button>
                    <button
                        type="submit"
                        :disabled="!canSave"
                        class="flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Plus class="h-4 w-4" />
                        {{ editingRule ? 'Split opslaan' : 'Split aanmaken' }}
                    </button>
                </div>
            </form>
        </div>
    </ModalShell>
</template>
