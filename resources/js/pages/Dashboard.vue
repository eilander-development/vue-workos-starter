<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import GroupedBudgets from '@/components/finance/MontlyExpenses.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const spendings = [
    { category: 'Marketing', amount: 5000, color: 'bg-green-500' },
    { category: 'Sales', amount: 7000, color: 'bg-yellow-500' },
    { category: 'Support', amount: 4000, color: 'bg-red-500' },
    { category: 'Finance', amount: 2500, color: 'bg-blue-500' },
];

</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle>Monthly Expenses</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <GroupedBudgets :budgetSpendings="spendings" />
                    </CardContent>
                </Card>
                <Card class="col-span-2">
                    <CardHeader>
                        <CardTitle>Budget Expenses</CardTitle>
                    </CardHeader>
                    <CardContent>
                        Chart
                    </CardContent>
                </Card>
            </div>
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
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
            <div
                class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border"
            >
                <PlaceholderPattern />
            </div>
        </div>
    </AppLayout>
</template>
