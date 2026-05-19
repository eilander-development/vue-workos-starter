<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent} from '@/components/ui/card';
import { Folder, TrendingDown, PiggyBank, Euro } from 'lucide-vue-next';

interface Props {
    categorieData: Array
}

const categoryCount = computed(() => props.categorieData.length);
const budgetCount = computed(() => props.categorieData.reduce((total, category: any) => total + (category.budgets?.length ?? 0), 0));
const totalBudget = computed(() => props.categorieData.reduce((sum, category: any) => sum + (category.budgets?.reduce((sub: number, budget: any) => sub + Number(budget.budget || 0), 0) ?? 0), 0));
const totalSpend = computed(() => props.categorieData.reduce((sum, category: any) => sum + (category.budgets?.reduce((sub: number, budget: any) => sub + Number(budget.spend || 0), 0) ?? 0), 0));

const props = defineProps<Props>()
const currency = (value: number) =>
    new Intl.NumberFormat('nl-NL', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
</script>

<template>
    <Card class="rounded-md shadow-xl">
        <CardContent class="flex items-center gap-3 sm:gap-4">
            <div class="bg-gray-600/10 p-2 sm:p-3 rounded-full text-primary-foreground">
                <Folder class="h-6 w-6 text-gray-600" />
            </div>
            <div>
                <p class="text-xs sm:text-sm text-muted-foreground">Aantal categorieën</p>
                <p class="text-lg sm:text-xl md:text-2xl font-bold">{{ categoryCount }}</p>
            </div>
        </CardContent>
    </Card>
    <Card class="rounded-md shadow-xl">
        <CardContent class="flex items-center gap-3 sm:gap-4">
            <div class="bg-gray-600/10 p-2 sm:p-3 rounded-full text-primary-foreground">
                <Euro class="h-6 w-6 text-gray-600" />
            </div>
            <div>
                <p class="text-xs sm:text-sm text-muted-foreground">Actieve budgetten</p>
                <p class="text-lg sm:text-xl md:text-2xl font-bold">{{ budgetCount }}</p>
            </div>
        </CardContent>
    </Card>
    <Card class="rounded-md shadow-xl">
        <CardContent class="flex items-center gap-3 sm:gap-4">
            <div class="bg-yellow-600/10 p-2 sm:p-3 rounded-full text-primary-foreground">
                <PiggyBank class="h-6 w-6 text-yellow-600" />
            </div>
            <div>
                <p class="text-xs sm:text-sm text-muted-foreground">Gebudgetteerd bedrag</p>
                <p class="text-lg sm:text-xl md:text-2xl font-bold">{{ currency(totalBudget) }}</p>
            </div>
        </CardContent>
    </Card>
    <Card class="rounded-md shadow-xl">
        <CardContent class="flex items-center gap-3 sm:gap-4">
            <div class="bg-red-600/10 p-2 sm:p-3 rounded-full text-primary-foreground">
                <TrendingDown class="h-6 w-6 text-red-600" />
            </div>
            <div>
                <p class="text-xs sm:text-sm text-muted-foreground">Uitgegeven tot nu toe</p>
                <p class="text-lg sm:text-xl md:text-2xl font-bold">{{ currency(totalSpend) }}</p>
            </div>
        </CardContent>
    </Card>
</template>
