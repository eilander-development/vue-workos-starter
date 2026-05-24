<script setup lang="ts">
import { computed, ref } from 'vue';

import {
  Card,
  CardContent,
} from '@/components/ui/card';

interface Props {
    selectedCategory: object,
}

const props = defineProps<Props>()
const isIncome = computed(() => (props.selectedCategory as any).type === 'income');
const isSaving = computed(() => (props.selectedCategory as any).type === 'saving');
const isExpense = computed(() => (props.selectedCategory as any).type === 'expense');
const paidLabel = computed(() => (isIncome.value ? 'Ontvangen' : isSaving.value ? 'Gespaard' : 'Betaald'));
const dueLabel = computed(() => (isIncome.value ? 'Nog te ontvangen' : isSaving.value ? 'Nog te sparen' : 'Nog te betalen'));
const overLabel = computed(() => (isIncome.value ? 'Teveel ontvangen' : isSaving.value ? 'Teveel gespaard' : 'Teveel betaald'));
const selectedBudgetName = ref<string | null>(null);
const selectBudget = (budgetName: string) => {
    selectedBudgetName.value = selectedBudgetName.value === budgetName ? null : budgetName;
};

</script>

<template>
    <Card class="rounded-md shadow-xl">
        <CardContent>
            <div class="flex items-center gap-4">
                <table class="table-auto overflow-scroll w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-900">
                            <th class="h-10 px-4 text-left font-medium">Categorie</th>
                            <th class="h-10 px-4 text-center font-medium w-24">Betalingen</th>
                            <th class="h-10 px-4 text-right font-medium w-32">Budget</th>
                            <th class="h-10 px-4 text-right font-medium w-32">{{ paidLabel }}</th>
                            <th class="h-10 px-4 text-right font-medium w-48">{{ dueLabel }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="budget in selectedCategory.budgets" :key="budget.id">
                        <tr
                            class="border-b border-slate-900 transition-colors hover:bg-muted/50 text-sm text-muted-foreground"
                            :class="selectedBudgetName === budget.name ? 'bg-muted/50' : ''"
                            @click="selectBudget(budget.name)"
                        >
                            <td class="px-4 py-2 text-left font-black">{{ budget.name }}</td>
                            <td class="px-4 py-2 text-center">{{ budget.payments ?? 0 }}</td>
                            <td class="px-4 py-2 text-right">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(budget.budget) }}</td>
                            <td class="px-4 py-2 text-right">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(budget.spend) }}</td>
                            <td class="px-4 py-2 text-right" :class="{
                                'text-green-600': isExpense && budget.remaining > 0 || !isExpense && budget.remaining < 0,
                                'text-yellow-600': !isExpense && budget.remaining > 0,
                                'text-red-600': isExpense && budget.remaining < 0
                            }">
                                {{ (!isExpense && budget.remaining < 0 ? '+' : '') + new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(isExpense && budget.remaining < 0 ? budget.remaining : Math.abs(budget.remaining)) }}
                            </td>
                        </tr>
                        </template>
                        <tr class="border-b border-slate-900 transition-colors bg-muted">
                            <td class="h-10 px-4 text-left font-black">Totaal</td>
                            <td class="h-10 px-4 text-right">&nbsp;</td>
                            <td class="h-10 px-4 text-right">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(selectedCategory.budget) }}</td>
                            <td class="h-10 px-4 text-right">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(selectedCategory.spend) }}</td>
                            <td class="h-10 px-4 text-right">
                                {{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(selectedCategory.unpaid) }}
                            </td>
                        </tr>
                        <tr v-if="selectedCategory.overdue > 0" class="border-b border-slate-900 transition-colors" :class="isExpense ? 'bg-red-700' : 'bg-green-700'">
                            <td class="h-10 px-4 text-left font-black">{{ overLabel }}</td>
                            <td class="h-10 px-4 text-right">&nbsp;</td>
                            <td class="h-10 px-4 text-right">&nbsp;</td>
                            <td class="h-10 px-4 text-right">&nbsp;</td>
                            <td class="h-10 px-4 text-right" :class="isExpense ? 'text-red-100' : 'text-green-100'">
                                {{ (!isExpense ? '+' : '') + new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(isExpense ? (selectedCategory.overdue * -1) : selectedCategory.overdue) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-6">
                <div class="mb-2 flex items-center justify-between">
                    <div class="text-sm font-medium">Transacties binnen selectie</div>
                    <button
                        v-if="selectedBudgetName"
                        type="button"
                        class="text-xs text-primary underline underline-offset-2"
                        @click="selectedBudgetName = null"
                    >
                        Alles tonen
                    </button>
                </div>
                <div v-if="!(selectedCategory.transactions ?? []).length" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                    Geen transacties gevonden binnen deze selectie.
                </div>
                <table v-else class="table-auto w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-900">
                            <th class="h-10 px-4 text-left font-medium">Datum</th>
                            <th class="h-10 px-4 text-left font-medium">Omschrijving</th>
                            <th class="h-10 px-4 text-left font-medium">Budget</th>
                            <th class="h-10 px-4 text-right font-medium">Bedrag</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="transaction in selectedCategory.transactions"
                            :key="transaction.id"
                            class="border-b border-slate-900 transition-colors hover:bg-muted/50 text-sm text-muted-foreground"
                            v-show="!selectedBudgetName || transaction.budget === selectedBudgetName"
                        >
                            <td class="px-4 py-2">{{ transaction.date ?? '-' }}</td>
                            <td class="px-4 py-2">{{ transaction.description ?? '-' }}</td>
                            <td class="px-4 py-2">{{ transaction.budget ?? '-' }}</td>
                            <td class="px-4 py-2 text-right">
                                {{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(transaction.amount ?? 0) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </CardContent>
    </Card>
</template>
