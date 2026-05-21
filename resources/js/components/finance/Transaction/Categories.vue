<script setup lang="ts">
import { computed } from 'vue';

import {
  Card,
  CardContent,
} from '@/components/ui/card';

interface Props {
    selectedCategory: Object,
}

const props = defineProps<Props>()
const isIncome = computed(() => (props.selectedCategory as any).type === 'income');
const isSaving = computed(() => (props.selectedCategory as any).type === 'saving');
const isExpense = computed(() => (props.selectedCategory as any).type === 'expense');
const paidLabel = computed(() => (isIncome.value ? 'Ontvangen' : isSaving.value ? 'Gespaard' : 'Betaald'));
const dueLabel = computed(() => (isIncome.value ? 'Nog te ontvangen' : isSaving.value ? 'Nog te sparen' : 'Nog te betalen'));
const overLabel = computed(() => (isIncome.value ? 'Teveel ontvangen' : isSaving.value ? 'Teveel gespaard' : 'Teveel betaald'));

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
                        <tr class="border-b border-slate-900 transition-colors hover:bg-muted/50 text-sm text-muted-foreground">
                            <td class="px-4 py-2 text-left font-black">{{ budget.name }}</td>
                            <td class="px-4 py-2 text-center">2</td>
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
        </CardContent>
    </Card>
</template>
