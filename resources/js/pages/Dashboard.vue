<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import GroupedBudgets from '@/components/finance/Dashboard/MontlyExpenses.vue';
import ExpensesChart from '@/components/finance/Dashboard/ExpensesChart.vue';
import { home,dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';

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
const monthlyExpensesChart = page.props.monthlyExpensesChart;

</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="grid gap-4 md:grid-cols-3">
                <div
                    class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
                >
                    <PlaceholderPattern />
                </div>
                <div
                    class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
                >
                    <PlaceholderPattern />
                </div>
                <div
                    class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
                >
                    <PlaceholderPattern />
                </div>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
                <GroupedBudgets :budgetExpenses="monthlyBudgetExpenses" />
                <ExpensesChart :monthyExpensesChart="monthlyExpensesChart" class="md:col-span-2" />
            </div>
        </div>
    </AppLayout>
</template>
