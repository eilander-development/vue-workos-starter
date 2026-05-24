<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Filters from '@/components/finance/Filters.vue';
import Category from '@/components/Category.vue';
import AssignTransactionCategoryModal from '@/components/finance/Transactions/AssignTransactionCategoryModal.vue';
import { useLiveSearch } from '@/composables/useLiveSearch';
import { customScrollbar } from '@/composables/scrollbar';
import { index } from '@/actions/App/Http/Controllers/TransactionsController';
import { home, transactions } from '@/routes';
import { type BreadcrumbItem, type TransactionFilter } from '@/types';
import { EllipsisVertical, Pencil, Eye, Trash2, Plus } from 'lucide-vue-next';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import debounce from 'lodash/debounce';
import { Button } from '@/components/ui/button';
import { MoneyAmount } from '@/components/ui/money-amount';
import { TablePagination } from '@/components/ui/table-pagination';
import { TypeBadge } from '@/components/ui/type-badge';
import NotificationBanner from '@/components/NotificationBanner.vue';
import { useNotification } from '@/composables/useNotification';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

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

const page = usePage() as any;
const categories = page.props.categories as Record<number, any>;
const budgetLabelForTransaction = (transaction: Record<string, any>) => {
    if (!transaction?.categoryId || !transaction?.budgetId) {
        return null;
    }

    const category = categories[transaction.categoryId];
    return category?.budgets?.find((budget: any) => budget.id === transaction.budgetId)?.name ?? null;
};
const filters = ref<any>(page.props.filters || {});
const transactionList = ref<any[]>(page.props.transactions || []);
const pagination = ref<any>(page.props.pagination ?? {
    current_page: 1,
    last_page: 1,
    per_page: 100,
    total: transactionList.value.length,
    from: transactionList.value.length ? 1 : 0,
    to: transactionList.value.length,
});
const currentPage = ref(pagination.value.current_page);
const lastPage = ref(pagination.value.last_page);
const displayPagination = computed(() => {
    const value = pagination.value ?? {
        current_page: 1,
        last_page: 1,
        per_page: 100,
        total: transactionList.value.length,
        from: transactionList.value.length ? 1 : 0,
        to: transactionList.value.length,
    };

    return {
        current_page: value.current_page ?? 1,
        last_page: value.last_page ?? 1,
        per_page: value.per_page ?? 100,
        total: value.total ?? transactionList.value.length,
        from: value.from ?? (transactionList.value.length ? 1 : 0),
        to: value.to ?? transactionList.value.length,
    };
});
const activeFilter = ref<TransactionFilter>(page.props.filters?.type || 'all');
const searchTerm = ref<string>(page.props.filters?.search || '');
const selectedBudgetId = ref<string>(page.props.filters?.budget_id ? String(page.props.filters.budget_id) : 'all');
const selectedSourceType = ref<string>(page.props.filters?.source_type || 'all');
const bulkDialogOpen = ref(false);
const bulkPrefix = ref('');
const bulkFromBudgetId = ref<string>('');
const bulkToBudgetId = ref<string>('');
const bulkLoading = ref(false);
const bulkSourceDeleteLoading = ref(false);
const assignDialogOpen = ref(false);
const selectedTransaction = ref<Record<string, any> | null>(null);
const showDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const createRuleDialogOpen = ref(false);
const showSimilarTransactionsDialog = ref(false);
const matchingTransactions = ref<Record<string, any>[]>([]);
const matchingPagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 100,
    total: 0,
    from: 0,
    to: 0,
});
const matchingRuleId = ref<number | null>(null);
const ruleType = ref<'iban' | 'description'>('iban');
const ruleMatchValue = ref('');
const ruleCategoryId = ref<number | null>(null);
const ruleBudgetId = ref<number | null>(null);
const ruleTransactionIban = ref('');
const ruleTransactionDescription = ref('');
const ruleSaveLoading = ref(false);
const { notification, showNotification } = useNotification();
const budgetOptions = computed(() => Object.values(categories).flatMap((category: any) =>
    (category.budgets ?? []).map((budget: any) => ({
        id: String(budget.id),
        label: `${category.category ?? category.name} · ${budget.name}`,
        category: category.category ?? category.name,
        budget: budget.name,
        color: category.color,
        icon: category.icon,
    }))
));

const openAssignDialog = (transaction: Record<string, any>) => {
    selectedTransaction.value = transaction;
    assignDialogOpen.value = true;
};

const openTransactionDetails = (transaction: Record<string, any>) => {
    selectedTransaction.value = transaction;
    showDialogOpen.value = true;
};

const confirmDeleteTransaction = (transaction: Record<string, any>) => {
    selectedTransaction.value = transaction;
    deleteDialogOpen.value = true;
};

const deleteTransaction = async () => {
    if (!selectedTransaction.value || !selectedTransaction.value.id) {
        return;
    }

    try {
        await axios.delete(`/transactions/${selectedTransaction.value.id}`);

        transactionList.value = transactionList.value.filter(
            (item: any) => item.id !== selectedTransaction.value?.id,
        );

        deleteDialogOpen.value = false;
        selectedTransaction.value = null;

        fetchTransactions();
        showNotification('Transactie succesvol verwijderd.', 'success');
    } catch (error) {
        console.error('Kon transactie niet verwijderen:', error);
        showNotification('Kon transactie niet verwijderen.', 'error');
    }
};

const handleCategoryAssigned = async (payload: {
    transactionId: number;
    categoryId: number;
    budgetId: number;
    type: string;
    icon: string;
    color: string;
}) => {
    try {
        await axios.post(`/transactions/${payload.transactionId}/assign`, {
            categoryId: payload.categoryId,
            budgetId: payload.budgetId,
            type: payload.type,
            icon: payload.icon,
            color: payload.color,
        });

        const transaction = transactionList.value.find(
            (item: any) => item.id === payload.transactionId,
        );

        if (!transaction) {
            return;
        }

        // vul de transaction aan met de nieuwe categoriegegevens
        transaction.categoryId = payload.categoryId;
        transaction.budgetId = payload.budgetId;
        transaction.type = payload.type;
        transaction.icon = payload.icon;
        transaction.color = payload.color;

        const description = transaction.description ?? '';
        const iban = transaction.counterpartyIban ?? transaction.counterparty_iban ?? '';

        if (!description && !iban) {
            return;
        }

        ruleCategoryId.value = payload.categoryId;
        ruleBudgetId.value = payload.budgetId;
        ruleTransactionDescription.value = description;
        ruleTransactionIban.value = iban;
        ruleType.value = iban ? 'iban' : 'description';
        ruleMatchValue.value = iban || description;
        createRuleDialogOpen.value = true;
        showNotification('Transactiegegevens zijn opgeslagen.', 'success');
    } catch (error) {
        console.error('Kon de transactie niet opslaan:', error);
        showNotification('Kon de transactie niet opslaan.', 'error');
    }
};

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
        pagination: any;
    }>(routeInfo.url, {
        params: {
            search: searchTerm.value,
            type: activeFilter.value,
            budget_id: selectedBudgetId.value === 'all' ? undefined : Number(selectedBudgetId.value),
            source_type: selectedSourceType.value === 'all' ? undefined : selectedSourceType.value,
            page: currentPage.value,
        },
        headers: { Accept: 'application/json' },
        signal,
    });

    transactionList.value = response.data.transactions ?? [];
    filters.value = response.data.filters ?? [];
    pagination.value = response.data.pagination ?? pagination.value;
    currentPage.value = pagination.value.current_page;
    lastPage.value = pagination.value.last_page;
});

// methods
const debouncedSearch = debounce(() => {
    pagination.value.current_page = 1;
    fetchTransactions();
}, 300);

const onFilterChange = () => {
    currentPage.value = 1;
    pagination.value.current_page = 1;
    fetchTransactions();
};
const onBudgetFilterChange = () => {
    currentPage.value = 1;
    pagination.value.current_page = 1;
    fetchTransactions();
};
const onSourceFilterChange = () => {
    currentPage.value = 1;
    pagination.value.current_page = 1;
    fetchTransactions();
};
const deleteBySource = async () => {
    if (selectedSourceType.value === 'all') return;
    if (!confirm(`Weet je zeker dat je alle ${selectedSourceType.value.toUpperCase()} transacties wilt verwijderen?`)) return;
    bulkSourceDeleteLoading.value = true;
    try {
        const response = await axios.delete('/transactions/source', {
            data: { source_type: selectedSourceType.value },
            headers: { Accept: 'application/json' },
        });
        showNotification(`${response.data.deleted} transacties verwijderd.`, 'success');
        fetchTransactions();
    } catch (error) {
        showNotification('Verwijderen op bron is mislukt.', 'error');
    } finally {
        bulkSourceDeleteLoading.value = false;
    }
};
const applyBulkReassign = async () => {
    if (!bulkPrefix.value.trim() || !bulkFromBudgetId.value || !bulkToBudgetId.value) return;
    bulkLoading.value = true;
    try {
        const response = await axios.post('/transactions/bulk-reassign-budget', {
            description_prefix: bulkPrefix.value.trim(),
            from_budget_id: Number(bulkFromBudgetId.value),
            to_budget_id: Number(bulkToBudgetId.value),
        });
        showNotification(`${response.data.updated} transacties bijgewerkt.`, 'success');
        bulkDialogOpen.value = false;
        fetchTransactions();
    } catch (error) {
        showNotification('Bulk wijziging mislukt.', 'error');
    } finally {
        bulkLoading.value = false;
    }
};

const matchPlaceholder = computed(() => (ruleType.value === 'iban' ? 'NL00BANK...' : 'Bijv. Albert Heijn'));

const closeCreateRuleDialog = () => {
    createRuleDialogOpen.value = false;
    ruleSaveLoading.value = false;
};

const fetchMatchingTransactions = async (ruleId: number | null, page = 1) => {
    try {
        const response = await axios.get(`/imports/transactions/rules/${ruleId}/transactions`, {
            params: { page },
            headers: { Accept: 'application/json' },
        });

        matchingTransactions.value = response.data.transactions ?? [];
        matchingPagination.value = response.data.pagination ?? matchingPagination.value;
    } catch (error) {
        console.error('Kon gekoppelde transacties niet ophalen:', error);
        showNotification('Kon gekoppelde transacties niet ophalen.', 'error');
    }
};

const saveImportRule = async () => {
    if (!ruleCategoryId.value || !ruleBudgetId.value || !ruleMatchValue.value.trim()) {
        return;
    }

    ruleSaveLoading.value = true;

    try {
        const response = await axios.post('/imports/transactions/rules', {
            type: ruleType.value,
            match_value: ruleMatchValue.value,
            category_id: ruleCategoryId.value,
            budget_id: ruleBudgetId.value,
            transaction_id: selectedTransaction.value?.id,
        }, {
            headers: { Accept: 'application/json' },
        });

        const rule = response.data.rule;
        const matches = response.data.matchedTransactions ?? [];

        if (rule) {
            matchingRuleId.value = rule.id;
        }

        if (rule) {
            showNotification('Koppelregel succesvol aangemaakt.', 'success');
        }

        if (matches.length) {
            matchingTransactions.value = matches;
            matchingPagination.value = response.data.pagination ?? matchingPagination.value;
            showSimilarTransactionsDialog.value = true;
        }
    } catch (error) {
        console.error('Kon de koppelregel niet aanmaken:', error);
        showNotification('Kon de koppelregel niet aanmaken.', 'error');
    } finally {
        ruleSaveLoading.value = false;
        closeCreateRuleDialog();
    }
};

const applyRuleToMatchingTransactions = async () => {
    if (!matchingRuleId.value) {
        return;
    }

    try {
        await axios.post(`/imports/transactions/rules/${matchingRuleId.value}/apply`, {}, {
            headers: { Accept: 'application/json' },
        });
        showSimilarTransactionsDialog.value = false;
        fetchTransactions();
        showNotification('Vergelijkbare transacties succesvol gekoppeld.', 'success');
    } catch (error) {
        console.error('Kon de vergelijkbare transacties niet koppelen:', error);
        showNotification('Kon de vergelijkbare transacties niet koppelen.', 'error');
    }
};

const closeSimilarTransactionsDialog = () => {
    showSimilarTransactionsDialog.value = false;
    matchingTransactions.value = [];
    matchingRuleId.value = null;
};

const goToPage = (pageNumber: number) => {
    if (pageNumber < 1 || pageNumber > lastPage.value) {
        return;
    }

    currentPage.value = pageNumber;
    pagination.value.current_page = pageNumber;
    fetchTransactions();
};
</script>

<template>
    <Head title="Transacties" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="p-4 h-full">
            <NotificationBanner v-if="notification" :type="notification.type" :message="notification.message" />
            <div class="flex h-full flex-col rounded-lg border bg-card text-card-foreground shadow-sm">
                <div class="flex flex-col space-y-1.5 p-4 sm:p-6 ">
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
                                :disabled="isLoading"
                                v-model="searchTerm"
                                @input="debouncedSearch"
                            />
                        </div>
                        <Filters
                            v-model:activeFilter="activeFilter"
                            @change="onFilterChange"
                            :isLoading="isLoading"
                        />
                        <div class="min-w-[240px]">
                            <select
                                v-model="selectedBudgetId"
                                @change="onBudgetFilterChange"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="all">Alle budgetten</option>
                                <option v-for="budget in budgetOptions" :key="budget.id" :value="budget.id">
                                    {{ budget.label }}
                                </option>
                            </select>
                        </div>
                        <div class="min-w-[180px]">
                            <select
                                v-model="selectedSourceType"
                                @change="onSourceFilterChange"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="all">Alle bronnen</option>
                                <option value="csv">CSV</option>
                                <option value="api">API</option>
                            </select>
                        </div>
                        <Button variant="outline" @click="bulkDialogOpen = true">Bulk wijzigen</Button>
                        <Button
                            variant="destructive"
                            :disabled="selectedSourceType === 'all' || bulkSourceDeleteLoading"
                            @click="deleteBySource"
                        >
                            {{ bulkSourceDeleteLoading ? 'Verwijderen...' : `Verwijder alles (${selectedSourceType.toUpperCase()})` }}
                        </Button>
                    </div>
                </div>
                <div class="flex-1 p-0">
                    <div class="-mx-4 overflow-x-auto sm:mx-0">
                        <div class="inline-block min-w-full px-4 align-middle sm:px-0" >
                            <div :class="`${customScrollbar} relative h-[calc(100vh-24rem)] min-h-[20rem] w-full overflow-y-auto`" >
                                <table
                                    class="w-full table-auto overflow-scroll text-sm"
                                >
                                    <thead>
                                        <tr
                                            class="sticky top-0 z-10 bg-gray-800 shadow-[inset_0_-1px_0_0_rgb(55_65_81)]"
                                        >
                                            <th
                                                class="h-10 px-4 text-left align-middle font-medium text-muted-foreground"
                                            >
                                                Categorie
                                            </th>
                                            <th
                                                class="h-10 px-2 text-left align-middle font-medium text-muted-foreground"
                                            >
                                                Datum
                                            </th>
                                            <th
                                                class="h-10 px-2 text-left align-middle font-medium text-muted-foreground"
                                            >
                                                Omschrijving
                                            </th>
                                            <th
                                                class="h-10 px-2 text-left align-middle font-medium text-muted-foreground"
                                            >
                                                Type
                                            </th>
                                            <th
                                                class="h-10 px-2 text-right align-middle font-medium text-muted-foreground"
                                            >
                                                Bedrag
                                            </th>
                                            <th
                                                class="h-10 px-2 text-right align-middle font-medium text-muted-foreground"
                                            >
                                                &nbsp;
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            class="group border-b border-slate-900 transition-colors hover:bg-muted/50"
                                            v-for="transaction in transactionList"
                                            :key="transaction.id"
                                        >
                                            <td class="p-2">
                                                <template v-if="transaction.categoryId">
                                                    <Category
                                                        :color="categories[transaction.categoryId] ? categories[transaction.categoryId].color : null"
                                                        :icon="categories[transaction.categoryId] ? categories[transaction.categoryId].icon : null"
                                                        :slug="categories[transaction.categoryId] ? categories[transaction.categoryId].slug : null"
                                                        :budget="budgetLabelForTransaction(transaction)"
                                                        :category="categories[transaction.categoryId] ? categories[transaction.categoryId].category : null"
                                                    />
                                                </template>
                                                <template v-else>
                                                    <button
                                                        type="button"
                                                        class="rounded-md border border-dashed border-muted-foreground px-3 py-2 text-xs text-muted-foreground hover:bg-green-900 hover:text-white cursor-pointer"
                                                        @click="openAssignDialog(transaction)"
                                                    >
                                                        <div class="flex items-center gap-1">
                                                            <Plus class="h-4 w-4" /> Voeg categorie toe
                                                        </div>
                                                    </button>
                                                </template>
                                            </td>
                                            <td class="p-2">
                                                {{ transaction.date }}
                                            </td>
                                            <td class="p-2">
                                                {{ transaction.description }}
                                            </td>
                                            <td class="p-2">
                                                <TypeBadge :type="transaction.type" />
                                            </td>
                                            <td class="p-2 text-right">
                                                <MoneyAmount :amount="transaction.amount" :highlight-positive="true" />
                                            </td>
                                            <td class="w-2 p-2 text-right">
                                                <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-150 ease-in-out inline-flex justify-end">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger as-child>
                                                            <button
                                                                type="button"
                                                                class="rounded-md p-2 text-muted-foreground hover:bg-slate-800/60"
                                                            >
                                                                <EllipsisVertical class="h-4 w-4" />
                                                            </button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end" class="w-44">
                                                            <DropdownMenuItem @click="openTransactionDetails(transaction)">
                                                                <Eye class="mr-2 h-4 w-4" />
                                                                Bekijk
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem @click="openAssignDialog(transaction)">
                                                                <Pencil class="mr-2 h-4 w-4" />
                                                                Bewerken
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem @click="confirmDeleteTransaction(transaction)">
                                                                <Trash2 class="mr-2 h-4 w-4 text-red-500" />
                                                                <span class="text-red-500">Verwijderen</span>
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <TablePagination
                    class="rounded-b-md"
                    :pagination="displayPagination"
                    item-label="transacties"
                    @previous="goToPage(currentPage - 1)"
                    @next="goToPage(currentPage + 1)"
                />
            </div>
            <Dialog :open="showDialogOpen" @update:open="(value) => showDialogOpen = value">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Bekijk transactie</DialogTitle>
                        <DialogDescription>
                            Alle details van de geselecteerde transactie.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-xl border border-slate-700 bg-slate-950 p-4">
                                <div class="text-xs text-muted-foreground">Datum</div>
                                <div class="mt-1 text-sm font-semibold">{{ selectedTransaction?.date ?? 'N.v.t.' }}</div>
                            </div>
                            <div class="rounded-xl border border-slate-700 bg-slate-950 p-4">
                                <div class="text-xs text-muted-foreground">Bedrag</div>
                                <div class="mt-1 text-sm font-semibold">
                                    {{ selectedTransaction ? new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(selectedTransaction.amount) : 'N.v.t.' }}
                                </div>
                            </div>
                                <div class="rounded-xl border border-slate-700 bg-slate-950 p-4">
                                    <div class="text-xs text-muted-foreground">Budget</div>
                                    <div class="mt-1 text-sm font-semibold">
                                        {{ selectedTransaction ? budgetLabelForTransaction(selectedTransaction) ?? 'Onbekend' : 'Onbekend' }}
                                    </div>
                                </div>
                            <div class="rounded-xl border border-slate-700 bg-slate-950 p-4">
                                <div class="text-xs text-muted-foreground">Type</div>
                                <div class="mt-1 text-sm font-semibold uppercase">
                                    {{ selectedTransaction?.type === 'income' ? 'Inkomen' : selectedTransaction?.type === 'expense' ? 'Uitgave' : selectedTransaction?.type === 'saving' ? 'Sparen' : 'Onbekend' }}
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-700 bg-slate-950 p-4">
                                <div class="text-xs text-muted-foreground">Categorie</div>
                                <div class="mt-1 text-sm font-semibold">
                                    {{ selectedTransaction?.categoryId && categories[selectedTransaction.categoryId] ? categories[selectedTransaction.categoryId].category : 'Onbekend' }}
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-700 bg-slate-950 p-4">
                            <div class="text-xs text-muted-foreground">Omschrijving</div>
                            <div class="mt-1 text-sm">{{ selectedTransaction?.description ?? 'N.v.t.' }}</div>
                        </div>
                    </div>

                    <DialogFooter class="mt-4 gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary">Sluiten</Button>
                        </DialogClose>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog :open="deleteDialogOpen" @update:open="(value) => deleteDialogOpen = value">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Verwijder transactie</DialogTitle>
                        <DialogDescription>
                            Weet je zeker dat je deze transactie wilt verwijderen? Dit kan niet ongedaan worden gemaakt.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-3">
                        <div class="rounded-xl border border-slate-700 bg-slate-950 p-4">
                            <div class="text-xs text-muted-foreground">Omschrijving</div>
                            <div class="mt-1 text-sm">{{ selectedTransaction?.description ?? 'N.v.t.' }}</div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-xl border border-slate-700 bg-slate-950 p-4">
                                <div class="text-xs text-muted-foreground">Datum</div>
                                <div class="mt-1 text-sm font-semibold">{{ selectedTransaction?.date ?? 'N.v.t.' }}</div>
                            </div>
                            <div class="rounded-xl border border-slate-700 bg-slate-950 p-4">
                                <div class="text-xs text-muted-foreground">Bedrag</div>
                                <div class="mt-1 text-sm font-semibold">
                                    {{ selectedTransaction ? new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(selectedTransaction.amount) : 'N.v.t.' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="mt-4 gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary">Annuleren</Button>
                        </DialogClose>
                        <Button variant="destructive" @click="deleteTransaction">Verwijder</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog :open="createRuleDialogOpen" @update:open="(value) => { if (!value) closeCreateRuleDialog(); createRuleDialogOpen = value; }">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Koppelregel aanmaken?</DialogTitle>
                        <DialogDescription>
                            Maak een regel aan zodat vergelijkbare transacties in de toekomst automatisch gekoppeld worden.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Regeltype</label>
                                <select
                                    v-model="ruleType"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                                >
                                    <option value="iban" :disabled="!ruleTransactionIban">Rekeningnummer bevat</option>
                                    <option value="description">Omschrijving bevat</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Zoekterm</label>
                                <input
                                    v-model="ruleMatchValue"
                                    :placeholder="matchPlaceholder"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                                />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Categorie</label>
                                <select
                                    v-model="ruleCategoryId"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                                >
                                    <option value="" disabled>Keuze categorie</option>
                                    <option
                                        v-for="(category, index) in Object.values(categories)"
                                        :key="index"
                                        :value="category.id"
                                    >
                                        {{ category.category ?? category.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Budget</label>
                                <select
                                    v-model="ruleBudgetId"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                                >
                                    <option value="" disabled>Keuze budget</option>
                                    <template v-for="(category, index) in Object.values(categories)" :key="index">
                                        <optgroup :label="category.category ?? category.name">
                                            <option
                                                v-for="budget in category.budgets ?? []"
                                                :key="budget.id"
                                                :value="budget.id"
                                            >
                                                {{ budget.name }}
                                            </option>
                                        </optgroup>
                                    </template>
                                </select>
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="mt-4 gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary" @click="closeCreateRuleDialog">Nee, bedankt</Button>
                        </DialogClose>
                        <Button :disabled="!ruleMatchValue.trim() || !ruleCategoryId || !ruleBudgetId || (ruleType === 'iban' && !ruleTransactionIban)" @click="saveImportRule">
                            Ja, regel aanmaken
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog :open="showSimilarTransactionsDialog" @update:open="(value) => { if (!value) closeSimilarTransactionsDialog(); showSimilarTransactionsDialog = value; }">
                <DialogContent class="sm:max-w-4xl">
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Vergelijkbare transacties</DialogTitle>
                        <DialogDescription>
                            Deze transacties matchen met de nieuwe koppelregel. Wil je ze ook koppelen?
                        </DialogDescription>
                    </DialogHeader>

                    <div class="overflow-x-auto max-h-[60vh]">
                        <table class="w-full table-auto text-sm">
                            <thead>
                                <tr class="bg-slate-900 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                    <th class="p-2">Datum</th>
                                    <th class="p-2">Omschrijving</th>
                                    <th class="p-2">IBAN</th>
                                    <th class="p-2 text-right">Bedrag</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="transaction in matchingTransactions" :key="transaction.id" class="border-b border-slate-800">
                                    <td class="p-2 text-sm">{{ transaction.date }}</td>
                                    <td class="p-2 text-sm">{{ transaction.description }}</td>
                                    <td class="p-2 text-sm">{{ transaction.counterparty_iban ?? '-' }}</td>
                                    <td class="p-2 text-right text-sm">
                                        {{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(transaction.amount) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between border-t border-slate-800 px-4 py-3 text-sm text-muted-foreground">
                        <div>
                            Toon {{ matchingPagination.from }} - {{ matchingPagination.to }} van {{ matchingPagination.total }} transacties
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded-md border border-input bg-background px-3 py-1 text-xs transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                                @click="fetchMatchingTransactions(matchingRuleId, matchingPagination.current_page - 1)"
                                :disabled="matchingPagination.current_page <= 1"
                            >
                                Vorige
                            </button>
                            <button
                                type="button"
                                class="rounded-md border border-input bg-background px-3 py-1 text-xs transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                                @click="fetchMatchingTransactions(matchingRuleId, matchingPagination.current_page + 1)"
                                :disabled="matchingPagination.current_page >= matchingPagination.last_page"
                            >
                                Volgende
                            </button>
                        </div>
                    </div>

                    <DialogFooter class="mt-4 gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary" @click="closeSimilarTransactionsDialog">Nee</Button>
                        </DialogClose>
                        <Button @click="applyRuleToMatchingTransactions">Ja, koppel deze transacties</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <AssignTransactionCategoryModal
                :open="assignDialogOpen"
                @update:open="(value) => assignDialogOpen = value"
                :transaction="selectedTransaction"
                :categories="categories"
                @assigned="handleCategoryAssigned"
            />

            <Dialog :open="bulkDialogOpen" @update:open="(value) => bulkDialogOpen = value">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Budget bulk wijzigen</DialogTitle>
                        <DialogDescription>Werk budgetten bij op basis van omschrijving-prefix.</DialogDescription>
                    </DialogHeader>
                    <div class="space-y-3">
                        <input v-model="bulkPrefix" placeholder="Bijv. Naar Oranje" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                        <select v-model="bulkFromBudgetId" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                            <option value="" disabled>Van budget</option>
                            <option v-for="budget in budgetOptions" :key="`from-${budget.id}`" :value="budget.id">{{ budget.label }}</option>
                        </select>
                        <select v-model="bulkToBudgetId" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                            <option value="" disabled>Naar budget</option>
                            <option v-for="budget in budgetOptions" :key="`to-${budget.id}`" :value="budget.id">{{ budget.label }}</option>
                        </select>
                    </div>
                    <DialogFooter>
                        <DialogClose as-child><Button variant="secondary">Annuleren</Button></DialogClose>
                        <Button :disabled="bulkLoading || !bulkPrefix.trim() || !bulkFromBudgetId || !bulkToBudgetId || bulkFromBudgetId === bulkToBudgetId" @click="applyBulkReassign">
                            {{ bulkLoading ? 'Bezig...' : 'Toepassen' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </main>
    </AppLayout>
</template>
