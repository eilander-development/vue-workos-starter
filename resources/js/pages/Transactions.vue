<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Filters from '@/components/finance/Transactions/Filters.vue';
import { useLiveSearch } from '@/composables/useLiveSearch';
import { index } from '@/actions/App/Http/Controllers/TransactionsController';
import { home, transactions } from '@/routes';
import { type BreadcrumbItem, type TransactionFilter } from '@/types';

import { Head, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import debounce from 'lodash/debounce';

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

const page = usePage();
const filters = ref(page.props.filters || []);
const transactionList = ref(page.props.transactions || []);
const activeFilter = ref<TransactionFilter>(page.props.filters?.type || 'all');
const searchTerm = ref<string>(page.props.filters?.search || '');

// axios commands
const {
    execute: fetchTransactions,
    isLoading,
    axios,
} = useLiveSearch(async (signal: AbortSignal) => {
    const routeInfo = index();

    const response = await axios.get<{
        transactions: [];
        filters: [];
    }>(routeInfo.url, {
        params: {
            search: searchTerm.value,
            type: activeFilter.value,
        },
        headers: { Accept: 'application/json' },
        signal,
    });

    transactionList.value = response.data.transactions;
    filters.value = response.data.filters;
});

// methods
const debouncedSearch = debounce(() => {
    fetchTransactions();
}, 300);
</script>

<template>
    <Head title="Transacties" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="p-4">
            <div
                class="rounded-lg border bg-card text-card-foreground shadow-sm"
            >
                <div class="flex flex-col space-y-1.5 p-4 sm:p-6">
                    <div
                        class="text-base font-medium tracking-tight sm:text-lg"
                    >
                        Transacties
                    </div>
                    <div class="text-xs text-muted-foreground sm:text-sm">
                        Laatste transacties
                    </div>
                    <div
                        class="mt-3 flex flex-col gap-3 sm:mt-4 sm:flex-row sm:gap-4"
                    >
                        <div class="relative max-w-full flex-1 sm:max-w-sm">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="24"
                                height="24"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="lucide lucide-search absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 transform text-muted-foreground sm:h-4 sm:w-4"
                            >
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.3-4.3"></path>
                            </svg>
                            <input
                                class="flex h-8 w-full rounded-md border border-input bg-background px-3 py-2 pl-9 text-xs ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 sm:h-10 sm:text-sm md:text-sm"
                                placeholder="Zoeken..."
                                value=""
                                :disabled="isLoading"
                                v-model="searchTerm"
                                @input="debouncedSearch"
                            />
                        </div>
                        <Filters
                            v-model:activeFilter="activeFilter"
                            @change="fetchTransactions"
                            :isLoading="isLoading"
                        />
                    </div>
                    <div>
                        Find {{ searchTerm }} in {{ activeFilter }}
                        {{ filters }}
                    </div>
                </div>
                <div class="p-4 pt-0 sm:p-6 sm:pt-0">
                    <div class="-mx-4 overflow-x-auto sm:mx-0">
                        <div
                            class="inline-block min-w-full px-4 align-middle sm:px-0"
                        >
                            <div class="relative w-full overflow-auto">
                                <table class="w-full caption-bottom text-sm">
                                    <thead>
                                        <tr
                                            class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted"
                                        >
                                            <th
                                                class="h-12 px-4 text-left align-middle text-xs font-medium text-muted-foreground sm:text-sm"
                                            >
                                                Category
                                            </th>
                                            <th
                                                class="hidden h-12 px-4 text-left align-middle text-xs font-medium text-muted-foreground sm:table-cell sm:text-sm"
                                            >
                                                Date
                                            </th>
                                            <th
                                                class="h-12 px-4 text-left align-middle text-xs font-medium text-muted-foreground sm:text-sm"
                                            >
                                                Description
                                            </th>
                                            <th
                                                class="hidden h-12 px-4 text-left align-middle text-xs font-medium text-muted-foreground sm:text-sm md:table-cell"
                                            >
                                                Account
                                            </th>
                                            <th
                                                class="h-12 px-4 text-right align-middle text-xs font-medium text-muted-foreground sm:text-sm"
                                            >
                                                Amount
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted"
                                        >
                                            <td
                                                class="p-4 py-2 align-middle sm:py-4"
                                            >
                                                <div
                                                    class="flex items-center gap-1.5 sm:gap-2"
                                                ></div>
                                            </td>
                                            <td
                                                class="hidden p-4 py-2 align-middle text-xs sm:table-cell sm:py-4 sm:text-sm"
                                            >
                                                Mar 28
                                            </td>
                                            <td
                                                class="max-w-[120px] truncate p-4 py-2 align-middle text-xs sm:max-w-none sm:py-4 sm:text-sm"
                                            >
                                                Salary Deposit
                                            </td>
                                            <td
                                                class="hidden p-4 py-2 align-middle text-xs sm:py-4 sm:text-sm md:table-cell"
                                            >
                                                Checking Account
                                            </td>
                                            <td
                                                class="p-4 py-2 text-right align-middle text-xs font-medium text-green-500 sm:py-4 sm:text-sm"
                                            >
                                                +$3,200
                                            </td>
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
