<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { customScrollbar } from '@/composables/scrollbar';
import Category from '@/components/Category.vue';
import { MoneyAmount } from '@/components/ui/money-amount';
import { TablePagination } from '@/components/ui/table-pagination';
import { TypeBadge } from '@/components/ui/type-badge';
import { computed } from 'vue';

interface Props {
    latestTransactions: any[];
    categories: Record<number, any>;
    filterType?: 'all' | 'expense' | 'income' | 'saving';
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    month?: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{ (e: 'page-change', page: number): void }>();

const budgetLabelForTransaction = (transaction: Record<string, any>) => {
    if (!transaction?.categoryId || !transaction?.budgetId) {
        return null;
    }

    const category = props.categories[transaction.categoryId];
    return (
        category?.budgets?.find(
            (budget: any) => budget.id === transaction.budgetId,
        )?.name ?? null
    );
};

const filteredTransactions = computed(() =>
    props.latestTransactions.filter((transaction) => {
        if ((props.filterType ?? 'all') === 'all') return true;
        const categoryType =
            props.categories[transaction.categoryId]?.type ?? null;
        return categoryType === props.filterType;
    }),
);

const goToPage = (pageNumber: number) => {
    if (pageNumber < 1 || pageNumber > (props.pagination?.last_page ?? 1)) {
        return;
    }

    emit('page-change', pageNumber);
};
</script>

<template>
    <Card class="flex h-full flex-col rounded-md shadow-xl">
        <CardHeader>
            <CardTitle>Laatste transacties</CardTitle>
        </CardHeader>
        <CardContent class="flex-1 p-0">
            <div
                :class="`${customScrollbar} relative h-[calc(100%-27px)] w-full overflow-y-auto`"
            >
                <table class="w-full table-auto overflow-scroll text-sm">
                    <thead>
                        <tr
                            class="sticky top-0 z-10 bg-gray-800 shadow-[inset_0_-1px_0_0_rgb(55_65_81)]"
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
                                class="h-10 px-2 text-left align-middle font-medium text-muted-foreground"
                            >
                                Type
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
                            class="group border-b border-slate-900 transition-colors hover:bg-muted/50"
                            v-for="transaction in filteredTransactions"
                            :key="transaction.id"
                        >
                            <td class="p-2">
                                <template v-if="transaction.categoryId">
                                    <Category
                                        :color="
                                            categories[transaction.categoryId]
                                                ? categories[
                                                      transaction.categoryId
                                                  ].color
                                                : null
                                        "
                                        :icon="
                                            categories[transaction.categoryId]
                                                ? categories[
                                                      transaction.categoryId
                                                  ].icon
                                                : null
                                        "
                                        :slug="
                                            categories[transaction.categoryId]
                                                ? categories[
                                                      transaction.categoryId
                                                  ].slug
                                                : null
                                        "
                                        :budget="
                                            budgetLabelForTransaction(
                                                transaction,
                                            )
                                        "
                                        :category="
                                            categories[transaction.categoryId]
                                                ? categories[
                                                      transaction.categoryId
                                                  ].category
                                                : null
                                        "
                                    />
                                </template>
                            </td>
                            <td class="p-2">{{ transaction.date }}</td>
                            <td class="p-2">{{ transaction.description }}</td>
                            <td class="p-2">
                                <TypeBadge :type="transaction.type" />
                            </td>
                            <td class="p-2 text-right">
                                <MoneyAmount
                                    :amount="transaction.amount"
                                    :highlight-positive="true"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <TablePagination
                :pagination="props.pagination"
                item-label="transacties"
                @previous="goToPage((props.pagination?.current_page ?? 1) - 1)"
                @next="goToPage((props.pagination?.current_page ?? 1) + 1)"
            />
        </CardContent>
    </Card>
</template>
