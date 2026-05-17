<script setup lang="ts">
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Filters from '@/components/finance/Transactions/Filters.vue';
import { home, transactions } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Home',
        href: home().url,
    },
    {
        title: 'Transactions',
        href: transactions().url,
    },
];

type ActiveFilter = 'all' | 'expenses' | 'income' | 'savings';
const page = usePage();
const categories = page.props.categories;
const activeFilter = ref<ActiveFilter>('all');
const search = ref('');

// Doe iets wanneer de ouder de verandering opmerkt
watch(activeFilter, (nieuwFilter) => {
  activeFilter.value = nieuwFilter;
});

</script>

<template>

    <Head title="Uitgaven" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="p-4">
            <div class="rounded-lg bg-card text-card-foreground border shadow-sm">
                <div class="flex flex-col space-y-1.5 p-4 sm:p-6">
                    <div class="font-medium tracking-tight text-base sm:text-lg">Transacties</div>
                    <div class="text-muted-foreground text-xs sm:text-sm">Laatste transacties</div>
                    <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 mt-3 sm:mt-4">
                        <div class="relative flex-1 max-w-full sm:max-w-sm">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-search absolute left-3 top-1/2 transform -translate-y-1/2 h-3.5 w-3.5 sm:h-4 sm:w-4 text-muted-foreground">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.3-4.3"></path>
                            </svg>
                            <input
                                class="flex w-full rounded-md border border-input bg-background px-3 py-2 ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm pl-9 h-8 sm:h-10 text-xs sm:text-sm"
                                placeholder="Zoeken..." value="" v-model="search">
                        </div>
                        <Filters v-model="activeFilter" />
                        Find {{ search }} in {{ activeFilter }}
                    </div>
                </div>
                <div class="p-4 sm:p-6 pt-0 sm:pt-0">
                    <div class="overflow-x-auto -mx-4 sm:mx-0">
                        <div class="inline-block min-w-full align-middle px-4 sm:px-0">
                            <div class="relative w-full overflow-auto">
                                <table class="w-full caption-bottom text-sm">
                                    <thead>
                                        <tr
                                            class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                            <th
                                                class="h-12 px-4 text-left align-middle font-medium text-muted-foreground text-xs sm:text-sm">
                                                Category
                                            </th>
                                            <th
                                                class="h-12 px-4 text-left align-middle font-medium text-muted-foreground text-xs sm:text-sm hidden sm:table-cell">
                                                Date
                                            </th>
                                            <th
                                                class="h-12 px-4 text-left align-middle font-medium text-muted-foreground text-xs sm:text-sm">
                                                Description
                                            </th>
                                            <th
                                                class="h-12 px-4 text-left align-middle font-medium text-muted-foreground text-xs sm:text-sm hidden md:table-cell">
                                                Account
                                            </th>
                                            <th
                                                class="h-12 px-4 align-middle font-medium text-muted-foreground text-xs sm:text-sm text-right">
                                                Amount
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                            <td class="p-4 align-middle py-2 sm:py-4">
                                                <div class="flex items-center gap-1.5 sm:gap-2">

                                                </div>
                                            </td>
                                            <td
                                                class="p-4 align-middle py-2 sm:py-4 text-xs sm:text-sm hidden sm:table-cell">
                                                Mar 28</td>
                                            <td
                                                class="p-4 align-middle py-2 sm:py-4 text-xs sm:text-sm max-w-[120px] sm:max-w-none truncate">
                                                Salary Deposit</td>
                                            <td
                                                class="p-4 align-middle py-2 sm:py-4 text-xs sm:text-sm hidden md:table-cell">
                                                Checking Account</td>
                                            <td
                                                class="p-4 align-middle py-2 sm:py-4 text-right text-xs sm:text-sm font-medium text-green-500">
                                                +$3,200</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </AppLayout>
</template>
