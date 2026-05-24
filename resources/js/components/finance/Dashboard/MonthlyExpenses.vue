<script setup lang="ts">
import Category from '@/components/Category.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

interface Props {
    title?: string;
    monthlyExpenses: any[];
    categories: Record<number, any>;
    filterType?: 'all' | 'expense' | 'income' | 'saving';
}

const props = defineProps<Props>();
const hoveredCategoryId = ref<number | null>(null);
const cardRef = ref<HTMLElement | null>(null);

const toggleTouchHighlight = (categoryId: number) => {
    hoveredCategoryId.value = hoveredCategoryId.value === categoryId ? null : categoryId;
};

const clearHighlightOnOutsideTap = (event: Event) => {
    const target = event.target as Node | null;
    if (!cardRef.value || !target) return;
    if (!cardRef.value.contains(target)) {
        hoveredCategoryId.value = null;
    }
};

onMounted(() => {
    document.addEventListener('pointerdown', clearHighlightOnOutsideTap);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', clearHighlightOnOutsideTap);
});

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
    <Card ref="cardRef" class="rounded-md shadow-xl">
        <CardHeader>
            <CardTitle v-if="props.title">{{ props.title }}</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="w-full">
                <div class="mb-4 flex h-2 overflow-visible rounded bg-gray-100 text-xs">
                    <template v-for="budget in expenses" :key="`${budget.slug}-${budget.type}`">
                        <Popover>
                            <PopoverTrigger as-child>
                                <div
                                    :title="`${budget.name}: EUR ${budget.amount.toFixed(2)} (${budget.percentage.toFixed(0)}%)`"
                                    :style="`width: ${budget.percentage}%`"
                                    :class="[
                                        `bg-${budget.color}-500 relative transition-all duration-200 ease-out`,
                                        hoveredCategoryId !== null && hoveredCategoryId !== budget.categoryId ? 'opacity-35' : 'opacity-100',
                                        hoveredCategoryId === budget.categoryId ? 'z-10 h-4 -mt-1 rounded-sm ring-1 ring-white/60' : 'h-2',
                                    ]"
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
                <div
                    v-for="budget in expenses"
                    :key="`${budget.slug}-${budget.type}`"
                    class="flex items-center justify-between border-b border-gray-900 py-3 transition-colors duration-150"
                    :class="hoveredCategoryId === budget.categoryId ? 'bg-white/5' : ''"
                    @mouseenter="hoveredCategoryId = budget.categoryId"
                    @mouseleave="hoveredCategoryId = null"
                    @click="toggleTouchHighlight(budget.categoryId)"
                >
                    <Category :color="budget.color" :icon="budget.icon" :slug="budget.slug" :category="budget.name" :type="budget.type" />
                    <div class="flex items-center gap-4">
                        <div class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(budget.amount) }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ budget.percentage.toFixed(0) }}%</div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
