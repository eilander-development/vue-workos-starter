<script setup lang="ts">
import { computed } from 'vue';

interface BudgetRow {
    id?: number | string;
    name: string;
    budget: number;
    spend: number;
    remaining: number;
    payments?: number;
}

const props = withDefaults(
    defineProps<{
        budgets: BudgetRow[];
        type: 'expense' | 'income' | 'saving';
        totalBudget?: number;
        totalSpend?: number;
        totalUnpaid?: number;
        totalOverdue?: number;
        compact?: boolean;
    }>(),
    {
        compact: false,
    },
);

const isExpense = computed(() => props.type === 'expense');
const paidLabel = computed(() => (props.type === 'income' ? 'Ontvangen' : props.type === 'saving' ? 'Gespaard' : 'Betaald'));
const dueLabel = computed(() => (props.type === 'income' ? 'Nog te ontvangen' : props.type === 'saving' ? 'Nog te sparen' : 'Nog te betalen'));
const overLabel = computed(() => (props.type === 'income' ? 'Teveel ontvangen' : props.type === 'saving' ? 'Teveel gespaard' : 'Teveel betaald'));
const showTotals = computed(() => props.totalBudget !== undefined && props.totalSpend !== undefined && props.totalUnpaid !== undefined);
</script>

<template>
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
            <tr
                v-for="budget in budgets"
                :key="budget.id ?? budget.name"
                class="border-b border-slate-900 transition-colors hover:bg-muted/50 text-sm text-muted-foreground"
            >
                <td class="px-4 py-2 text-left font-black">{{ budget.name }}</td>
                <td class="px-4 py-2 text-center">{{ budget.payments ?? 0 }}</td>
                <td class="px-4 py-2 text-right">{{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(budget.budget ?? 0) }}</td>
                <td class="px-4 py-2 text-right">{{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(budget.spend ?? 0) }}</td>
                <td class="px-4 py-2 text-right" :class="{
                    'text-green-600': isExpense && budget.remaining > 0 || !isExpense && budget.remaining < 0,
                    'text-yellow-600': !isExpense && budget.remaining > 0,
                    'text-red-600': isExpense && budget.remaining < 0
                }">
                    {{ (!isExpense && budget.remaining < 0 ? '+' : '') + new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(isExpense && budget.remaining < 0 ? budget.remaining : Math.abs(budget.remaining ?? 0)) }}
                </td>
            </tr>
            <tr v-if="showTotals" class="border-b border-slate-900 transition-colors bg-muted">
                <td class="h-10 px-4 text-left font-black">Totaal</td>
                <td class="h-10 px-4 text-right">&nbsp;</td>
                <td class="h-10 px-4 text-right">{{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(totalBudget ?? 0) }}</td>
                <td class="h-10 px-4 text-right">{{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(totalSpend ?? 0) }}</td>
                <td class="h-10 px-4 text-right">{{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(totalUnpaid ?? 0) }}</td>
            </tr>
            <tr
                v-if="showTotals && (totalOverdue ?? 0) > 0"
                class="border-b border-slate-900 transition-colors"
                :class="isExpense ? 'bg-red-700' : 'bg-green-700'"
            >
                <td class="h-10 px-4 text-left font-black">{{ overLabel }}</td>
                <td class="h-10 px-4 text-right">&nbsp;</td>
                <td class="h-10 px-4 text-right">&nbsp;</td>
                <td class="h-10 px-4 text-right">&nbsp;</td>
                <td class="h-10 px-4 text-right" :class="isExpense ? 'text-red-100' : 'text-green-100'">
                    {{ (!isExpense ? '+' : '') + new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(isExpense ? ((totalOverdue ?? 0) * -1) : (totalOverdue ?? 0)) }}
                </td>
            </tr>
        </tbody>
    </table>
</template>
