<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

import { customScrollbar } from '@/composables/scrollbar';

import Category from '@/components/Category.vue';

interface Props {
    latestTransactions: Object;
    categories: Object;
}

const props = defineProps<Props>();
</script>

<template>
    <Card class="rounded-md shadow-xl">
        <CardHeader>
            <CardTitle>Laatste transacties</CardTitle>
        </CardHeader>
        <CardContent class="p-0">
            <div
                :class="`${customScrollbar} relative max-h-110 w-full overflow-y-auto`"
            >
                <table class="w-full table-auto overflow-scroll text-sm">
                    <thead>
                        <tr
                            class="sticky top-0 border-b bg-gray-800 transition-colors"
                        >
                            <th
                                class="h-10 px-4 text-left align-middle font-medium text-muted-foreground"
                            >
                                Categorie
                            </th>
                            <th
                                class="h-10 px-2 text-left align-middle font-medium text-muted-foreground"
                            >
                                Datum
                            </th>
                            <th
                                class="h-10 px-2 text-left align-middle font-medium text-muted-foreground"
                            >
                                Omschrijving
                            </th>
                            <th
                                class="h-10 px-2 text-right align-middle font-medium text-muted-foreground"
                            >
                                Bedrag
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            class="border-b border-slate-900 transition-colors hover:bg-muted/50"
                            v-for="transaction in latestTransactions"
                            :key="transaction.id"
                        >
                            <td class="p-2">
                                <Category
                                    :color="
                                        categories[transaction.categoryId].color
                                    "
                                    :icon="
                                        categories[transaction.categoryId].icon
                                    "
                                    :slug="
                                        categories[transaction.categoryId].slug
                                    "
                                    :budget="
                                        categories[transaction.categoryId]
                                            .budgets[0].name
                                    "
                                    :category="
                                        categories[transaction.categoryId]
                                            .category
                                    "
                                />
                            </td>
                            <td class="p-2">{{ transaction.date }}</td>
                            <td class="p-2">{{ transaction.description }}</td>
                            <td class="p-2 text-right">
                                <span
                                    :class="
                                        transaction.amount > 0
                                            ? 'rounded-md bg-green-800 p-2 py-1 text-white'
                                            : ''
                                    "
                                >
                                    {{
                                        new Intl.NumberFormat('nl-NL', {
                                            style: 'currency',
                                            currency: 'EUR',
                                        }).format(transaction.amount)
                                    }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </CardContent>
    </Card>
</template>
