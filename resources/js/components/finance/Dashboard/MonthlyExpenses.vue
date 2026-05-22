<script setup lang="ts">
import Category from '@/components/Category.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { computed } from 'vue';

interface Props {
    title?: string;
    monthlyExpenses: any[];
    categories: Record<number, any>;
    filterType?: 'all' | 'expense' | 'income' | 'saving';
}

const props = defineProps<Props>();

const expenses = computed(() => {
    const mapped = props.monthlyExpenses.map((item) => {
        const category = props.categories[item.categoryId] ?? {};
        return {
            categoryId: Number(item.categoryId ?? 0),
            name: category.name ?? 'Onbekend',
            amount: Number(item.amount ?? 0),
            color: category.color ?? 'gray',
            icon: category.icon ?? 'circle',
            slug: category.slug ?? '',
            type: category.type ?? 'expense',
        };
    });

    const selectedType = props.filterType ?? 'all';
    const aggregatedByCategory = Object.values(
        mapped.reduce((acc: Record<number, any>, item) => {
            if (!acc[item.categoryId]) {
                acc[item.categoryId] = { ...item };
            } else {
                acc[item.categoryId].amount += item.amount;
            }

            return acc;
        }, {})
    );

    const filtered = aggregatedByCategory.filter((item: any) => selectedType === 'all' || item.type === selectedType);
    const total = filtered.reduce((sum, item) => sum + item.amount, 0);

    return filtered
        .map((item) => ({
            ...item,
            percentage: total > 0 ? (item.amount / total) * 100 : 0,
        }))
        .sort((a, b) => b.percentage - a.percentage);
});
</script>

<template>
    <Card class="rounded-md shadow-xl">
        <CardHeader>
            <CardTitle v-if="props.title">{{ props.title }}</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="w-full">
                <div class="mb-4 flex h-2 overflow-hidden rounded bg-gray-100 text-xs">
                    <template v-for="budget in expenses" :key="`${budget.slug}-${budget.type}`">
                        <Popover>
                            <PopoverTrigger as-child>
                                <div
                                    :title="`${budget.name}: €${budget.amount.toFixed(2)} (${budget.percentage.toFixed(0)}%)`"
                                    :style="`width: ${budget.percentage}%`"
                                    :class="`bg-${budget.color}-500 transition-all duration-500 ease-out`"
                                />
                            </PopoverTrigger>
                            <PopoverContent class="flex w-50 items-center justify-between text-xs text-white" :class="`bg-${budget.color}-500`">
                                <span>{{ budget.name }}</span>
                                <span class="font-bold">
                                    {{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(budget.amount) }}
                                </span>
                                <span>{{ budget.percentage.toFixed(0) }}%</span>
                            </PopoverContent>
                        </Popover>
                    </template>
                </div>
            </div>
            <div>
                <div v-for="budget in expenses" :key="`${budget.slug}-${budget.type}`" class="flex items-center justify-between border-b border-gray-900 py-3">
                    <Category :color="budget.color" :icon="budget.icon" :slug="budget.slug" :category="budget.name" />
                    <div class="flex items-center gap-4">
                        <div class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(budget.amount) }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ budget.percentage.toFixed(0) }}%</div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
