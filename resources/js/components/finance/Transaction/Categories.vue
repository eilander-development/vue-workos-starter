<script setup lang="ts">
import { ref } from 'vue';
import BudgetSummaryTable from '@/components/finance/Budgets/BudgetSummaryTable.vue';

import {
  Card,
  CardContent,
} from '@/components/ui/card';

interface Props {
    selectedCategory: object,
}

const props = defineProps<Props>()
const selectedBudgetName = ref<string | null>(null);

</script>

<template>
    <Card class="rounded-md shadow-xl">
        <CardContent>
            <div class="flex items-center gap-4">
                <BudgetSummaryTable
                    :budgets="selectedCategory.budgets"
                    :type="selectedCategory.type"
                    :total-budget="selectedCategory.budget"
                    :total-spend="selectedCategory.spend"
                    :total-unpaid="selectedCategory.unpaid"
                    :total-overdue="selectedCategory.overdue"
                />
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
