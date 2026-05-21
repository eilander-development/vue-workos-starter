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

const remainingLabel = computed(() => {
    if ((props.selectedCategory as any).type === 'income') return 'Te ontvangen';
    if ((props.selectedCategory as any).type === 'saving') return 'Te sparen';
    return 'Resterend budget';
});
const isExpense = computed(() => (props.selectedCategory as any).type === 'expense');
const remainingValue = computed(() => Number((props.selectedCategory as any).remaining ?? 0));
const displayRemaining = computed(() => (isExpense.value ? remainingValue.value : Math.abs(remainingValue.value)));
const remainingClass = computed(() => {
    if (remainingValue.value < 0) return isExpense.value ? 'text-red-600' : 'text-green-600';
    return isExpense.value ? 'text-green-600' : 'text-yellow-600';
});
const remainingPrefix = computed(() => (!isExpense.value && remainingValue.value < 0 ? '+' : ''));

</script>

<template>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <Card class="rounded-md shadow-xl">
            <CardContent>
                <div class="space-y-1">
                    <div class="text-xs sm:text-sm text-muted-foreground">Budget</div>
                    <div class="text-lg sm:text-2xl font-bold">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(selectedCategory.budget) }}</div>
                </div>
            </CardContent>
        </Card>
        <Card class="rounded-md shadow-xl">
            <CardContent>
                <div class="space-y-1">
                    <div class="text-xs sm:text-sm text-muted-foreground">{{ spendLabel }}</div>
                    <div class="text-lg sm:text-2xl font-bold">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(selectedCategory.spend) }}</div>
                </div>
            </CardContent>
        </Card>
        <Card class="rounded-md shadow-xl">
            <CardContent>
                <div class="space-y-1">
                    <div class="text-xs sm:text-sm text-muted-foreground">{{ remainingLabel }}</div>
                    <div class="text-lg sm:text-2xl font-bold" :class="remainingClass">
                        {{ remainingPrefix + new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(displayRemaining) }}
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
