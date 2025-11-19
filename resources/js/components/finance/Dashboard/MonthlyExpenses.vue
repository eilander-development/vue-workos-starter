<script setup lang="ts">

import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';

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
  const total = props.monthlyExpenses.reduce((sum, item) => sum + item.amount, 0)
  let cumulativePercentage = 0

  return props.monthlyExpenses.map(item => {
    const percentage = (item.amount / total) * 100
    cumulativePercentage += percentage
    return {
      category: props.categories[item.categoryId].category,
      amount: item.amount,
      color: props.categories[item.categoryId].color,
      icon: props.categories[item.categoryId].icon,
      percentage,
      cumulativePercentage: cumulativePercentage - percentage,
    }
  })
}

</script>

<template>
    <Card class="rounded-md shadow-xl">
        <CardHeader>
            <CardTitle>Uitgaven per budget</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="w-full">
                <div class="mb-4 flex h-2 overflow-hidden rounded bg-gray-100 text-xs">
                    <template v-for="budget in Expenses()" :key="budget.category">
                        <Popover>
                            <PopoverTrigger as-child>
                                <div :title="`${budget.category}: €${budget.amount} (${budget.percentage.toFixed(0)}%)`"
                                :style="`width: ${budget.percentage}%`"
                                :class="`bg-${budget.color}-500 transition-all duration-500 ease-out`"/>
                            </PopoverTrigger>
                            <PopoverContent class="w-50 flex items-center justify-between text-xs text-white" :class="`bg-${budget.color}-500`">
                                <span>
                                    {{ budget.category }}
                                </span>
                                <span class="font-bold">
                                    {{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(budget.amount) }}
                                </span>
                                <span>
                                    {{ budget.percentage.toFixed(0) }}%
                                </span>
                            </PopoverContent>
                        </Popover>
                    </template>
                </div>
            </div>
            <div>
                <div v-for="budget in Expenses()" :key="budget.category" class="flex items-center justify-between py-3 border-b border-gray-900">
                    <Category :color="budget.color" :icon="budget.icon" :category="budget.category" />
                    <div class="flex items-center gap-4">
                        <div class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(budget.amount) }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ budget.percentage.toFixed(0) }}%</div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
