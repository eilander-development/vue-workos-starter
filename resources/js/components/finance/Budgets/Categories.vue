<script setup lang="ts">

import {
  Card,
  CardContent,
} from '@/components/ui/card';

interface Props {
    selectedCategory: Object,
}

const props = defineProps<Props>()

</script>

<template>
    <Card class="rounded-md shadow-xl">
        <CardContent>
            <div class="flex items-center gap-4">
                <table class="table-auto overflow-scroll w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-900">
                            <th class="h-10 px-4 text-left font-medium">Categorie</th>
                            <th class="h-10 px-4 text-left font-medium">Betaald op</th>
                            <th class="h-10 px-4 text-right font-medium">Budget</th>
                            <th class="h-10 px-4 text-right font-medium">Betaald</th>
                            <th class="h-10 px-4 text-right font-medium">Nog te betalen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="budget in selectedCategory.budgets" :key="budget.id">
                        <tr class="border-b border-slate-900 transition-colors hover:bg-muted/50 text-sm text-muted-foreground">
                            <td class="px-4 py-2">{{ budget.name }}</td>
                            <td class="px-4 py-2">07-05-2026</td>
                            <td class="px-4 py-2 text-right">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(budget.budget) }}</td>
                            <td class="px-4 py-2 text-right">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(budget.spend) }}</td>
                            <td class="px-4 py-2 text-right" :class="{'text-green-600': budget.budget - budget.spend > 0, 'text-red-600': budget.remaining < 0}">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(budget.remaining) }}</td>
                        </tr>
                        </template>
                        <tr class="border-b border-slate-900 transition-colors bg-muted">
                            <td class="h-10 px-4 font-black">Totaal</td>
                            <td class="h-10 px-4">&nbsp;</td>
                            <td class="h-10 px-4 text-right">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(selectedCategory.budget) }}</td>
                            <td class="h-10 px-4 text-right">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(selectedCategory.spend) }}</td>
                            <td class="h-10 px-4 text-right">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(selectedCategory.unpaid) }}</td>
                        </tr>
                        <tr v-if="selectedCategory.overdue > 0" class="border-b border-slate-900 transition-colors bg-red-600">
                            <td class="h-10 px-4 font-black">Teveel betaald</td>
                            <td class="h-10 px-4">&nbsp;</td>
                            <td class="h-10 px-4 text-right">&nbsp;</td>
                            <td class="h-10 px-4 text-right">&nbsp;</td>
                            <td class="h-10 px-4 text-right">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(selectedCategory.overdue) }}</td>
                        </tr>
                    </tbody>    
                </table>
            </div>
        </CardContent>
    </Card>
</template>
