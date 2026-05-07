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
                    <div class="text-xs sm:text-sm text-muted-foreground">Uitgegeven</div>
                    <div class="text-lg sm:text-2xl font-bold">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(selectedCategory.spend) }}</div>
                </div>
            </CardContent>
        </Card>
        <Card class="rounded-md shadow-xl">
            <CardContent>
                <div class="space-y-1">
                    <div class="text-xs sm:text-sm text-muted-foreground">Te besteden</div>
                    <div class="text-lg sm:text-2xl font-bold">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(selectedCategory.remaining) }}</div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
