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
const monthlyExpenses = page.props.monthlyExpenses;
const yearlyExpensesChart = page.props.yearlyExpensesChart;
const stats = page.props.stats;
const latestTransactions = page.props.latestTransactions;

</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <Stats :stats="stats" />
            </div>
            <div class="grid gap-4 md:grid-cols-4">
                <ExpensesChart :yearlyExpensesChart="yearlyExpensesChart" class="md:col-span-3" />
                <MonthlyExpenses :monthlyExpenses="monthlyExpenses" :categories="categories" />
            </div>
            <div class="grid gap-4 md:grid-cols-4">
                <MonthlyBudgets :monthlyExpenses="monthlyExpenses" :categories="categories" />
                <LatestTransactions :latestTransactions="latestTransactions" :categories="categories" class="md:col-span-3" />
            </div>
        </div>
    </AppLayout>
</template>
