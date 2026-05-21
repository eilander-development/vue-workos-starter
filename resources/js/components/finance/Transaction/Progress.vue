<script setup lang="ts">
import { computed } from 'vue';

import {
  Card,
  CardContent,
} from '@/components/ui/card';

interface Props {
    selectedCategory: Object,
}

const props = defineProps<Props>()
const spendLabel = computed(() => {
    if ((props.selectedCategory as any).type === 'income') return 'Ontvangen';
    if ((props.selectedCategory as any).type === 'saving') return 'Gespaard';
    return 'Uitgegeven';
});
const spend = computed(() => Number((props.selectedCategory as any).spend ?? 0));
const budget = computed(() => Number((props.selectedCategory as any).budget ?? 0));
const progressPercent = computed(() => {
    if (budget.value <= 0) return 0;
    return Math.round((spend.value / budget.value) * 100);
});
const clampedProgress = computed(() => Math.min(100, Math.max(0, progressPercent.value)));
const remainingPercent = computed(() => Math.max(0, 100 - clampedProgress.value));
const overPercent = computed(() => Math.max(0, progressPercent.value - 100));

</script>

<template>
    <Card class="rounded-md shadow-xl">
        <CardContent>
            <div class="space-y-3 sm:space-y-4">
                <div
                    class="flex items-center justify-between text-xs sm:text-sm text-muted-foreground">
                    <div>{{ spendLabel }}</div>
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
                        :style="`transform: translateX(${clampedProgress}% );`"></div>
                </div>
                <div class="flex items-center justify-between text-xs sm:text-sm">
                    <div>{{ clampedProgress }}%</div>
                    <div v-if="overPercent > 0" class="text-green-500">+{{ overPercent }}% teveel</div>
                    <div v-else class="text-muted-foreground">{{ remainingPercent }}%</div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
