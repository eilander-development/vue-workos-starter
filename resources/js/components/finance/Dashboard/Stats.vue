<script setup lang="ts">
import { Card, CardContent} from '@/components/ui/card';
import { TrendingUp, TrendingDown, ChartPie, PiggyBank } from 'lucide-vue-next';
</script>

<template>
    <Card>
        <CardContent class="flex items-center gap-3 sm:gap-4">
            <div class="bg-emerald-600/10 p-2 sm:p-3 rounded-full text-primary-foreground">
                <TrendingUp class="h-6 w-6 text-emerald-600" />
            </div>
            <div>
                <p class="text-xs sm:text-sm text-muted-foreground">Monthly Income</p>
                <p class="text-lg sm:text-xl md:text-2xl font-bold">€6.840,50</p>
            </div>
        </CardContent>
    </Card>
    <Card>
        <CardContent class="flex items-center gap-3 sm:gap-4">
            <div class="bg-red-600/10 p-2 sm:p-3 rounded-full text-primary-foreground">
                <TrendingDown class="h-6 w-6 text-red-600" />
            </div>
            <div>
                <p class="text-xs sm:text-sm text-muted-foreground">Monthly Expenses</p>
                <p class="text-lg sm:text-xl md:text-2xl font-bold">€4.640,50</p>
            </div>
        </CardContent>
    </Card>
    <Card>
        <CardContent class="flex items-center gap-3 sm:gap-4">
            <div class="bg-yellow-600/10 p-2 sm:p-3 rounded-full text-primary-foreground">
                <PiggyBank class="h-6 w-6 text-yellow-600" />
            </div>
            <div>
                <p class="text-xs sm:text-sm text-muted-foreground">Left this month</p>
                <p class="text-lg sm:text-xl md:text-2xl font-bold">€1.840,50</p>
            </div>
        </CardContent>
    </Card>
    <Card>
        <CardContent class="flex items-center gap-3 sm:gap-4">
            <div class="bg-blue-600/10 p-2 sm:p-3 rounded-full text-primary-foreground">
                <ChartPie class="h-6 w-6 text-blue-600" />
            </div>
            <div>
                <p class="text-xs sm:text-sm text-muted-foreground">Budgets</p>
                <p class="text-lg sm:text-xl md:text-2xl font-bold">5</p>
            </div>
        </CardContent>
    </Card>
</template>