<script setup lang="ts">
import Category from '@/components/Category.vue';

interface Props {
    budget: Object,
}

const props = defineProps<Props>()

</script>

<template>
    <div class="p-4 sm:p-6">
        <div class="flex items-center gap-3 sm:gap-4">
            <Category :color="budget.color" :icon="budget.icon" icon-container-size="p-2 sm:p-3" icon-size="h-6 w-6" category-font="font-medium"  />
            <div class="flex-1">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-medium text-sm sm:text-base">{{ budget.category }}</div>
                        <div class="text-xs sm:text-sm text-muted-foreground">{{new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(budget.budget)}}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
