<script setup lang="ts">
import Category from '@/components/Category.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { computed } from 'vue';

interface Props {
    monthlyExpenses: any[];
    categories: Record<number, any>;
    filterType?: 'all' | 'expense' | 'income' | 'saving';
}

const props = defineProps<Props>();

const budgets = computed(() =>
    Object.values(props.categories).map((category: any) => {
        const budget = Number(category?.budget ?? 0);
        const spend = Number(category?.spend ?? 0);
        const percentage = budget > 0 ? (spend / budget) * 100 : 0;

        return {
            name: category?.name ?? 'Onbekend',
            color: category?.color ?? 'gray',
            budget,
            spend,
            icon: category?.icon ?? 'circle',
            slug: category?.slug ?? '',
            type: category?.type ?? 'expense',
            percentage,
        };
    })
);

const filteredBudgets = computed(() =>
    budgets.value
        .filter((budget) => (props.filterType ?? 'all') === 'all' || budget.type === (props.filterType ?? 'all'))
        .sort((a, b) => b.percentage - a.percentage)
);
</script>

<template>
    <Card class="rounded-md shadow-xl">
        <CardHeader>
            <CardTitle>Categorieën</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="space-y-6">
                <template v-for="budget in filteredBudgets" :key="`${budget.slug}-${budget.type}`">
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <Category :color="budget.color" :icon="budget.icon" :slug="budget.slug" :category="budget.name" category-font="font-medium" />
                            <span class="ml-auto text-sm text-muted-foreground"><span class="text-xs">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(budget.spend) }}</span> / <span class="font-bold">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(budget.budget) }}</span></span>
                        </div>
                        <div class="relative h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-secondary">
                            <div :class="`bg-${budget.color}-500 h-full transition-all duration-500 ease-out`" :style="`width: ${budget.percentage}%`"></div>
                        </div>
                    </div>
                </template>
            </div>
        </CardContent>
    </Card>
</template>
