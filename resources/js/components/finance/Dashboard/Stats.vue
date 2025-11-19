<script setup lang="ts">
import { Card, CardContent} from '@/components/ui/card';
import { TrendingUp, TrendingDown, ChartPie, PiggyBank } from 'lucide-vue-next';

interface Props {
    stats: Object
}

const props = defineProps<Props>()
</script>

<template>
    <Card class="rounded-md shadow-xl">
        <CardContent class="flex items-center gap-3 sm:gap-4">
            <div class="bg-emerald-600/10 p-2 sm:p-3 rounded-full text-primary-foreground">
                <TrendingUp class="h-6 w-6 text-emerald-600" />
            </div>
            <div>
                <p class="text-xs sm:text-sm text-muted-foreground">Inkomsten</p>
                <p class="text-lg sm:text-xl md:text-2xl font-bold">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(stats.income) }}</p>
            </div>
        </CardContent>
    </Card>
    <Card class="rounded-md shadow-xl">
        <CardContent class="flex items-center gap-3 sm:gap-4">
            <div class="bg-red-600/10 p-2 sm:p-3 rounded-full text-primary-foreground">
                <TrendingDown class="h-6 w-6 text-red-600" />
            </div>
            <div>
                <p class="text-xs sm:text-sm text-muted-foreground">Uitgaven</p>
                <p class="text-lg sm:text-xl md:text-2xl font-bold">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(stats.expenses) }}</p>
            </div>
        </CardContent>
    </Card>
    <Card class="rounded-md shadow-xl">
        <CardContent class="flex items-center gap-3 sm:gap-4">
            <div class="bg-yellow-600/10 p-2 sm:p-3 rounded-full text-primary-foreground">
                <PiggyBank class="h-6 w-6 text-yellow-600" />
            </div>
            <div>
                <p class="text-xs sm:text-sm text-muted-foreground">Te besteden</p>
                <p class="text-lg sm:text-xl md:text-2xl font-bold"
                   :class="{'text-red-400' : stats.left < 0}">
                    {{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(stats.left) }}
                </p>
            </div>
        </CardContent>
    </Card>
    <Card class="rounded-md shadow-xl">
        <CardContent class="flex items-center gap-3 sm:gap-4">
            <div class="bg-blue-600/10 p-2 sm:p-3 rounded-full text-primary-foreground">
                <ChartPie class="h-6 w-6 text-blue-600" />
            </div>
            <div>
                <p class="text-xs sm:text-sm text-muted-foreground">Budgets</p>
                <p class="text-lg sm:text-xl md:text-2xl font-bold">{{stats.budgets}}</p>
            </div>
        </CardContent>
    </Card>
</template>
