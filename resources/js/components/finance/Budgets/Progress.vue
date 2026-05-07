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
            <div class="space-y-3 sm:space-y-4">
                <div
                    class="flex items-center justify-between text-xs sm:text-sm text-muted-foreground">
                    <div>Uitgegeven</div>
                    <div>Budget</div>
                </div>
                <div class="flex items-center justify-between">
                    <div class="text-lg sm:text-2xl font-bold">{{new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(selectedCategory.spend)}}</div>
                    <div class="text-lg sm:text-2xl font-bold">{{new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(selectedCategory.budget)}}</div>
                </div>
                <div aria-valuemax="100" aria-valuemin="0" role="progressbar"
                    data-state="indeterminate" data-max="100"
                    class="relative w-full overflow-hidden rounded-full h-2"
                    :class="`bg-${selectedCategory.color}-500`">
                    <div data-state="indeterminate" data-max="100"
                        class="h-full w-full flex-1 transition-all bg-slate-100 dark:bg-secondary"
                        :style="`transform: translateX(${Math.round((selectedCategory.spend / selectedCategory.budget) * 100)}% );`"></div>
                </div>
                <div class="flex items-center justify-between text-xs sm:text-sm">
                    <div class="">{{ Math.round((selectedCategory.spend / selectedCategory.budget) * 100) }}%</div>
                    <div class="text-muted-foreground">{{ Math.round((1 - (selectedCategory.spend / selectedCategory.budget)) * 100) }}%</div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
