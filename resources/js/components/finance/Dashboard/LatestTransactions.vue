<script setup lang="ts">

import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';

import Category from '@/components/Category.vue';

interface Props {
    latestTransactions: Object,
    categories: Object,
}

const props = defineProps<Props>()

</script>

<template>
    <Card class="rounded-md shadow-xl">
        <CardHeader>
            <CardTitle>Laatste transacties</CardTitle>
        </CardHeader>
        <CardContent class="p-0">
            <div class="relative w-full overflow-y-auto max-h-110">
                <table class="table-auto overflow-scroll w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-900">
                            <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">Budget</th>
                            <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">Datum</th>
                            <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">Omschrijving</th>
                            <th class="h-10 px-4 text-right align-middle font-medium text-muted-foreground">Bedrag</th>
                        </tr>
                    </thead>
                    <tbody>
                    <tr class="border-b border-slate-900 transition-colors hover:bg-muted/50" v-for="transaction in latestTransactions" :key="transaction.id">
                        <td class="p-2">
                            <Category :color="categories[transaction.categoryId].color"
                                      :icon="categories[transaction.categoryId].icon"
                                      :category="categories[transaction.categoryId].category"  />
                        </td>
                        <td class="p-2">{{transaction.date}}</td>
                        <td class="p-2">{{transaction.description}}</td>
                        <td class="p-2 text-right">
                            {{new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(transaction.amount)}}
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </CardContent>
    </Card>
</template>
