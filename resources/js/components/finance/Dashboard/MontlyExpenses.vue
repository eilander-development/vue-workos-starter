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

interface Props {
  budgetExpenses: Object
}

const props = defineProps<Props>()

function ExpenseBar() {
  const total = props.budgetExpenses.reduce((sum, item) => sum + item.amount, 0)
  let cumulativePercentage = 0

  return props.budgetExpenses.map(item => {
    const percentage = (item.amount / total) * 100
    cumulativePercentage += percentage
    return {
      category: item.category,
      amount: item.amount,
      color: item.color,
      percentage,
      cumulativePercentage: cumulativePercentage - percentage,
    }
  })
}

</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Monthly Expenses</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="w-full">
                <div class="mb-4 flex h-2 overflow-hidden rounded bg-gray-100 text-xs">
                    <template v-for="budget in ExpenseBar()" :key="budget.category">
                        <Popover>
                            <PopoverTrigger as-child>
                                <div :title="`${budget.category}: €${budget.amount} (${budget.percentage.toFixed(0)}%)`"
                                :style="`width: ${budget.percentage}%`"
                                :class="budget.color + ' transition-all duration-500 ease-out'"/>
                            </PopoverTrigger>
                            <PopoverContent class="w-50 flex items-center justify-between text-xs text-white" :class="budget.color">
                                <span>
                                    {{ budget.category }}
                                </span>
                                <span class="font-bold">
                                    {{ new Intl.NumberFormat("de-DE", { style: "currency", currency: "EUR" }).format(budget.amount) }}
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
                <div v-for="budget in ExpenseBar()" :key="budget.category" class="flex items-center justify-between py-3 border-b border-gray-900">
                    <div class="flex items-center gap-2">
                        <div :class="budget.color + ' inline-block h-3 w-3 rounded-full'"></div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ budget.category }}</div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ new Intl.NumberFormat("de-DE", { style: "currency", currency: "EUR" }).format(budget.amount) }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ budget.percentage.toFixed(0) }}%</div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
