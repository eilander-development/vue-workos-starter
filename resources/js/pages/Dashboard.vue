<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import MonthlyExpenses from '@/components/finance/Dashboard/MonthlyExpenses.vue';
import ExpensesChart from '@/components/finance/Dashboard/ExpensesChart.vue';
import MonthlyBudgets from '@/components/finance/Dashboard/MonthlyBudgets.vue';
import Stats from '@/components/finance/Dashboard/Stats.vue';
import LatestTransactions from '@/components/finance/Dashboard/LatestTransactions.vue';
import { home,dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { HandCoins, PiggyBank, TrendingDown, TrendingUp } from 'lucide-vue-next';
import { ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Home',
        href: home().url,
    },
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const page = usePage();
const categories = page.props.categories;
const monthlyExpensesSpend = page.props.monthlyExpenses.spend;
const monthlyExpensesBudgets = page.props.monthlyExpenses.budgets;
const yearlyExpensesChart = page.props.yearlyExpensesChart;
const stats = page.props.stats;
const latestTransactions = page.props.latestTransactions;
const latestTransactionsPagination = page.props.latestTransactionsPagination;
const activeType = ref<'all' | 'expense' | 'income' | 'saving'>('all');

</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <Stats :stats="stats" />
            </div>
            <div class="inline-flex w-fit items-center gap-1 rounded-lg bg-slate-900 p-1">
                <button class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm text-slate-300 transition hover:text-white" :class="activeType === 'all' ? 'bg-slate-700 text-white' : ''" @click="activeType = 'all'"><HandCoins class="h-3.5 w-3.5" />Alle</button>
                <button class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm text-slate-300 transition hover:text-white" :class="activeType === 'expense' ? 'bg-slate-700 text-white' : ''" @click="activeType = 'expense'"><TrendingDown class="h-3.5 w-3.5" />Uitgaven</button>
                <button class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm text-slate-300 transition hover:text-white" :class="activeType === 'income' ? 'bg-slate-700 text-white' : ''" @click="activeType = 'income'"><TrendingUp class="h-3.5 w-3.5" />Inkomsten</button>
                <button class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm text-slate-300 transition hover:text-white" :class="activeType === 'saving' ? 'bg-slate-700 text-white' : ''" @click="activeType = 'saving'"><PiggyBank class="h-3.5 w-3.5" />Sparen</button>
            </div>
            <div class="grid gap-4 md:grid-cols-5">
                <ExpensesChart :yearlyExpensesChart="yearlyExpensesChart" class="md:col-span-3" />
                <MonthlyExpenses :monthlyExpenses="monthlyExpensesBudgets" :categories="categories" :filter-type="activeType" title="Begroot per budget" />
                <MonthlyExpenses :monthlyExpenses="monthlyExpensesSpend" :categories="categories" :filter-type="activeType" title="Uitgaven per budget" />
            </div>
            <div class="grid gap-4 md:grid-cols-4">
                <MonthlyBudgets :monthlyExpenses="monthlyExpensesSpend" :categories="categories" :filter-type="activeType" />
                <LatestTransactions :latestTransactions="latestTransactions" :categories="categories" :pagination="latestTransactionsPagination" :filter-type="activeType" class="md:col-span-3" />
            </div>
        </div>
    </AppLayout>
</template>
