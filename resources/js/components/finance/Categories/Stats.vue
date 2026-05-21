<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import {
    Folder,
    TrendingDown,
    TrendingUp,
    PiggyBank,
    Euro,
} from 'lucide-vue-next';

interface Props {
    categorieData: Array;
}

const categoryCount = computed(() => props.categorieData.length);
const budgetCount = computed(() =>
    props.categorieData.reduce(
        (total, category: any) => total + (category.budgets?.length ?? 0),
        0,
    ),
);
const totalBudget = computed(() =>
    props.categorieData.reduce(
        (sum, category: any) =>
            sum +
            (category.budgets?.reduce(
                (sub: number, budget: any) => sub + Number(budget.budget || 0),
                0,
            ) ?? 0),
        0,
    ),
);
const totalSpend = computed(() =>
    props.categorieData.reduce(
        (sum, category: any) =>
            sum +
            (category.budgets?.reduce(
                (sub: number, budget: any) => sub + Number(budget.spend || 0),
                0,
            ) ?? 0),
        0,
    ),
);

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
                    {{ categoryCount }}
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
                    {{ budgetCount }}
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
                    {{ currency(totalBudget) }}
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
                    {{ currency(totalSpend) }}
                </p>
            </div>
        </CardContent>
    </Card>
    <Card class="rounded-md shadow-xl">
        <CardContent class="flex items-center gap-3 sm:gap-4">
            <div
                class="rounded-full bg-yellow-600/10 p-2 text-primary-foreground sm:p-3"
            >
                <TrendingUp class="h-6 w-6 text-yellow-600" />
            </div>
            <div>
                <p class="text-xs text-muted-foreground sm:text-sm">
                    Gebudgetteerde inkomsten
                </p>
                <p class="text-lg font-bold sm:text-xl md:text-2xl">
                    {{ currency(totalBudget) }}
                </p>
            </div>
        </CardContent>
    </Card>
    <Card class="rounded-md shadow-xl">
        <CardContent class="flex items-center gap-3 sm:gap-4">
            <div
                class="rounded-full bg-green-600/10 p-2 text-primary-foreground sm:p-3"
            >
                <TrendingUp class="h-6 w-6 text-green-600" />
            </div>
            <div>
                <p class="text-xs text-muted-foreground sm:text-sm">
                    Ontvangen tot nu toe
                </p>
                <p class="text-lg font-bold sm:text-xl md:text-2xl">
                    {{ currency(totalSpend) }}
                </p>
            </div>
        </CardContent>
    </Card>
    <Card class="rounded-md shadow-xl">
        <CardContent class="flex items-center gap-3 sm:gap-4">
            <div
                class="rounded-full bg-yellow-600/10 p-2 text-primary-foreground sm:p-3"
            >
                <PiggyBank class="h-6 w-6 text-yellow-600" />
            </div>
            <div>
                <p class="text-xs text-muted-foreground sm:text-sm">
                    Gebudgetteerde spaarbedragen
                </p>
                <p class="text-lg font-bold sm:text-xl md:text-2xl">
                    {{ currency(totalBudget) }}
                </p>
            </div>
        </CardContent>
    </Card>
    <Card class="rounded-md shadow-xl">
        <CardContent class="flex items-center gap-3 sm:gap-4">
            <div
                class="rounded-full bg-green-600/10 p-2 text-primary-foreground sm:p-3"
            >
                <PiggyBank class="h-6 w-6 text-green-600" />
            </div>
            <div>
                <p class="text-xs text-muted-foreground sm:text-sm">
                    Gespaard tot nu toe
                </p>
                <p class="text-lg font-bold sm:text-xl md:text-2xl">
                    {{ currency(totalSpend) }}
                </p>
            </div>
        </CardContent>
    </Card>
</template>
