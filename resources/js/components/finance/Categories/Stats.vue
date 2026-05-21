<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import {
    Folder,
    TrendingDown,
    TrendingUp,
    PiggyBank,
    Euro,
} from 'lucide-vue-next';

interface Props {
    stats: {
        categoryCount: number;
        budgetCount: number;
        totalBudget: number;
        totalSpend: number;
        expenseBudget: number;
        expenseSpend: number;
        incomeBudget: number;
        incomeSpend: number;
        savingBudget: number;
        savingSpend: number;
    };
}

const props = defineProps<Props>();
const currency = (value: number) =>
    new Intl.NumberFormat('nl-NL', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
</script>

<template>
    <Card class="rounded-md shadow-xl">
        <CardContent class="flex items-center gap-3 sm:gap-4">
            <div
                class="rounded-full bg-gray-600/10 p-2 text-primary-foreground sm:p-3"
            >
                <Folder class="h-6 w-6 text-gray-600" />
            </div>
            <div>
                <p class="text-xs text-muted-foreground sm:text-sm">
                    Aantal categorieën
                </p>
                <p class="text-lg font-bold sm:text-xl md:text-2xl">
                    {{ props.stats.categoryCount }}
                </p>
            </div>
        </CardContent>
    </Card>
    <Card class="rounded-md shadow-xl">
        <CardContent class="flex items-center gap-3 sm:gap-4">
            <div
                class="rounded-full bg-gray-600/10 p-2 text-primary-foreground sm:p-3"
            >
                <Euro class="h-6 w-6 text-gray-600" />
            </div>
            <div>
                <p class="text-xs text-muted-foreground sm:text-sm">
                    Actieve budgetten
                </p>
                <p class="text-lg font-bold sm:text-xl md:text-2xl">
                    {{ props.stats.budgetCount }}
                </p>
            </div>
        </CardContent>
    </Card>
    <Card class="rounded-md shadow-xl">
        <CardContent class="flex items-center gap-3 sm:gap-4">
            <div
                class="rounded-full bg-yellow-600/10 p-2 text-primary-foreground sm:p-3"
            >
                <TrendingDown class="h-6 w-6 text-yellow-600" />
            </div>
            <div>
                <p class="text-xs text-muted-foreground sm:text-sm">
                    Gebudgetteerde uitgaven
                </p>
                <p class="text-lg font-bold sm:text-xl md:text-2xl">
                    {{ currency(props.stats.totalBudget) }}
                </p>
            </div>
        </CardContent>
    </Card>
    <Card class="rounded-md shadow-xl">
        <CardContent class="flex items-center gap-3 sm:gap-4">
            <div
                class="rounded-full bg-red-600/10 p-2 text-primary-foreground sm:p-3"
            >
                <TrendingDown class="h-6 w-6 text-red-600" />
            </div>
            <div>
                <p class="text-xs text-muted-foreground sm:text-sm">
                    Uitgegeven tot nu toe
                </p>
                <p class="text-lg font-bold sm:text-xl md:text-2xl">
                    {{ currency(props.stats.totalSpend) }}
                </p>
            </div>
        </CardContent>
    </Card>
    <Card class="rounded-md shadow-xl">
        <CardContent class="flex items-center gap-3 sm:gap-4">
            <div class="rounded-full bg-yellow-600/10 p-2 text-primary-foreground sm:p-3">
                <TrendingUp class="h-6 w-6 text-yellow-600" />
            </div>
            <div>
                <p class="text-xs text-muted-foreground sm:text-sm">Gebudgetteerde inkomsten</p>
                <p class="text-lg font-bold sm:text-xl md:text-2xl">{{ currency(props.stats.incomeBudget) }}</p>
            </div>
        </CardContent>
    </Card>
    <Card class="rounded-md shadow-xl">
        <CardContent class="flex items-center gap-3 sm:gap-4">
            <div class="rounded-full bg-green-600/10 p-2 text-primary-foreground sm:p-3">
                <TrendingUp class="h-6 w-6 text-green-600" />
            </div>
            <div>
                <p class="text-xs text-muted-foreground sm:text-sm">Ontvangen tot nu toe</p>
                <p class="text-lg font-bold sm:text-xl md:text-2xl">{{ currency(props.stats.incomeSpend) }}</p>
            </div>
        </CardContent>
    </Card>
    <Card class="rounded-md shadow-xl">
        <CardContent class="flex items-center gap-3 sm:gap-4">
            <div class="rounded-full bg-yellow-600/10 p-2 text-primary-foreground sm:p-3">
                <PiggyBank class="h-6 w-6 text-yellow-600" />
            </div>
            <div>
                <p class="text-xs text-muted-foreground sm:text-sm">Gebudgetteerde spaarbedragen</p>
                <p class="text-lg font-bold sm:text-xl md:text-2xl">{{ currency(props.stats.savingBudget) }}</p>
            </div>
        </CardContent>
    </Card>
    <Card class="rounded-md shadow-xl">
        <CardContent class="flex items-center gap-3 sm:gap-4">
            <div class="rounded-full bg-green-600/10 p-2 text-primary-foreground sm:p-3">
                <PiggyBank class="h-6 w-6 text-green-600" />
            </div>
            <div>
                <p class="text-xs text-muted-foreground sm:text-sm">Gespaard tot nu toe</p>
                <p class="text-lg font-bold sm:text-xl md:text-2xl">{{ currency(props.stats.savingSpend) }}</p>
            </div>
        </CardContent>
    </Card>
</template>
