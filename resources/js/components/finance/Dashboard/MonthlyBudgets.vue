<script setup lang="ts">

import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';

import Category from '@/components/Category.vue';

interface Props {
    monthlyExpenses: Object,
    categories: Object,
}

const props = defineProps<Props>()

function Expenses() {
    return props.monthlyExpenses.map(item => {
        const percentage = (props.categories[item.categoryId].spend / props.categories[item.categoryId].budget) * 100
        return {
            category: props.categories[item.categoryId].name,
            color: props.categories[item.categoryId].color,
            budget: props.categories[item.categoryId].budget,
            spend: props.categories[item.categoryId].spend,
            icon: props.categories[item.categoryId].icon,
            slug: props.categories[item.categoryId].slug,
            percentage,
        }
    })
}

</script>

<template>
    <Card class="rounded-md shadow-xl">
        <CardHeader>
            <CardTitle>Budgetten</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="space-y-6">
            <template v-for="budget in Expenses()" :key="budget.category">
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <Category :color="budget.color" :icon="budget.icon" :slug="budget.slug" :category="budget.category" category-font="font-medium"  />
                        <span class="text-sm text-muted-foreground ml-auto"><span class="text-xs">{{new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(budget.spend)}}</span> / <span class="font-bold">{{new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(budget.budget)}}</span></span>
                    </div>
                    <div class="relative h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-secondary">
                        <div :class="`bg-${budget.color}-500 h-full transition-all duration-500 ease-out`"
                             :style="`width: ${budget.percentage}%`"></div>
                    </div>
                </div>
            </template>
            </div>
        </CardContent>
    </Card>
</template>
