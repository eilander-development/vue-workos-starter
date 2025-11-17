<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import GroupedBudgets from '@/components/finance/Dashboard/MontlyExpenses.vue';
import ExpensesChart from '@/components/finance/Dashboard/ExpensesChart.vue';
import Stats from '@/components/finance/Dashboard/Stats.vue';
import { home,dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import { stat } from 'fs';

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
const monthlyBudgetExpenses = page.props.monthlyBudgetExpenses;
const yearlyExpensesChart = page.props.yearlyExpensesChart;

</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <stats />
            </div>  
            <div class="grid gap-4 md:grid-cols-3">
                <GroupedBudgets :budgetExpenses="monthlyBudgetExpenses" />
                <ExpensesChart :yearlyExpensesChart="yearlyExpensesChart" class="md:col-span-2" />
            </div>
        </div>
    </AppLayout>
</template>
