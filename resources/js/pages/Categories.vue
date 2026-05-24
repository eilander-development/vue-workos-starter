<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import Stats from '@/components/finance/Categories/Stats.vue';
import Category from '@/components/Category.vue';
import CategoryEditModal from '@/components/finance/Categories/CategoryEditModal.vue';
import CreateCategoryModal from '@/components/finance/Categories/CreateCategoryModal.vue';
import { categories, home } from '@/routes';
import { Head, router, usePage } from '@inertiajs/vue3';
import { type BreadcrumbItem, type TransactionFilter } from '@/types';
import Filters from '@/components/finance/Filters.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { TablePagination } from '@/components/ui/table-pagination';
import { TypeBadge } from '@/components/ui/type-badge';
import { EllipsisVertical, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import BudgetSummaryTable from '@/components/finance/Budgets/BudgetSummaryTable.vue';
import { customScrollbar } from '@/composables/scrollbar';
import { useNotification } from '@/composables/useNotification';

const page = usePage();
const { showSuccess } = useNotification();
const categoriesData = computed(() =>
    Array.isArray(page.props.categories)
        ? page.props.categories
        : Object.values(page.props.categories ?? []),
);

const activeFilter = ref<TransactionFilter>((page.props.activeFilter as TransactionFilter) || 'all');
const month = ref((page.props.month as string) || new Date().toISOString().slice(0, 7));
const editModalOpen = ref(false);
const createModalOpen = ref(false);
const activeCategory = ref<Record<string, any> | null>(null);
const deleteCategoryPayload = ref<{ id: number; categoryName: string; budgetCount: number; transactionsCount: number } | null>(null);
const deletingCategory = ref(false);
const expandedCategoryId = ref<number | null>(null);
const statsTransactionsOpen = ref(false);
const statsTransactionsType = ref<'expense' | 'income' | 'saving'>('expense');
const statsBudgetsOpen = ref(false);
const statsBudgetsType = ref<'all' | 'expense' | 'income' | 'saving'>('all');
const budgetSearch = ref('');
const budgetSort = ref<{ key: 'category' | 'budgetName' | 'budget' | 'spend' | 'remaining' | 'payments'; direction: 'asc' | 'desc' }>({
    key: 'category',
    direction: 'asc',
});
const statsTransactions = ref<any[]>([]);
const statsTransactionsPagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 100,
    total: 0,
    from: 0,
    to: 0,
});

const filteredCategories = computed(() => categoriesData.value);
const currentPage = ref(page.props.pagination?.current_page ?? 1);
const lastPage = computed(() => page.props.pagination?.last_page ?? 1);

const syncActiveCategory = () => {
    if (!activeCategory.value?.id) {
        return;
    }

    const updated = categoriesData.value.find(
        (category: any) => category.id === activeCategory.value?.id,
    );

    if (updated) {
        activeCategory.value = updated;
        return;
    }

    editModalOpen.value = false;
    activeCategory.value = null;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Home',
        href: home().url,
    },
    {
        title: 'Categorieën',
        href: categories().url,
    },
];

const openEditModal = (category: Record<string, any>) => {
    activeCategory.value = category;
    editModalOpen.value = true;
};
const openDeleteCategoryModal = (category: Record<string, any>) => {
    deleteCategoryPayload.value = {
        id: Number(category.id),
        categoryName: String(category.category ?? category.name ?? 'Onbekende categorie'),
        budgetCount: Number((category.budgets ?? []).length),
        transactionsCount: Number((category.budgets ?? []).reduce((sum: number, budget: any) => sum + Number(budget.payments ?? 0), 0)),
    };
};

const deleteCategory = () => {
    if (!deleteCategoryPayload.value || deletingCategory.value) {
        return;
    }

    deletingCategory.value = true;
    const id = deleteCategoryPayload.value.id;

    router.delete(`/categories/${id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showSuccess('Categorie verwijderd en transacties ontkoppeld.');
            deleteCategoryPayload.value = null;
            router.reload({
                only: ['categories', 'stats', 'activeFilter', 'pagination'],
                preserveScroll: true,
            });
        },
        onFinish: () => {
            deletingCategory.value = false;
        },
    });
};

const toggleCategoryDetails = (categoryId: number) => {
    expandedCategoryId.value = expandedCategoryId.value === categoryId ? null : categoryId;
};

watch(categoriesData, syncActiveCategory);
watch(
    () => page.props.activeFilter,
    (value) => {
        activeFilter.value = (value as TransactionFilter) || 'all';
    },
);

const onFilterChange = (value: TransactionFilter) => {
    currentPage.value = 1;

    router.get(
        '/categories',
        { filter: value, page: currentPage.value, month: month.value },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['categories', 'stats', 'activeFilter', 'pagination'],
        },
    );
};

const goToPage = (pageNumber: number) => {
    if (pageNumber < 1 || pageNumber > lastPage.value) {
        return;
    }

    currentPage.value = pageNumber;

    router.get(
        '/categories',
        { filter: activeFilter.value, page: currentPage.value, month: month.value },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['categories', 'stats', 'activeFilter', 'pagination'],
        },
    );
};

const fetchStatsTransactions = async (type: 'expense' | 'income' | 'saving', pageNumber = 1) => {
    try {
        const response = await axios.get('/transactions', {
            params: { type, page: pageNumber },
            headers: { Accept: 'application/json' },
        });

        statsTransactions.value = response.data.transactions ?? [];
        statsTransactionsPagination.value = response.data.pagination ?? statsTransactionsPagination.value;
    } catch (error) {
        console.error('Kon statistiektransacties niet ophalen:', error);
    }
};

const openStatsTransactions = async (type: 'expense' | 'income' | 'saving') => {
    statsTransactionsType.value = type;
    statsTransactionsOpen.value = true;
    await fetchStatsTransactions(type, 1);
};

const budgetRows = computed(() => {
    const rows = categoriesData.value
        .filter((category: any) => statsBudgetsType.value === 'all' || category.type === statsBudgetsType.value)
        .flatMap((category: any) =>
            (category.budgets ?? []).map((budget: any) => ({
                id: budget.id,
                category: category.category ?? category.name ?? '-',
                budgetName: budget.name ?? '-',
                budget: Number(budget.budget ?? 0),
                spend: Number(budget.spend ?? 0),
                remaining: Number(budget.remaining ?? 0),
                payments: Number(budget.payments ?? 0),
            })),
        );

    const query = budgetSearch.value.trim().toLowerCase();
    const filtered = query === ''
        ? rows
        : rows.filter((row: any) =>
            row.category.toLowerCase().includes(query) ||
            row.budgetName.toLowerCase().includes(query),
        );

    const { key, direction } = budgetSort.value;
    const factor = direction === 'asc' ? 1 : -1;

    return [...filtered].sort((a: any, b: any) => {
        const av = a[key];
        const bv = b[key];

        if (typeof av === 'number' && typeof bv === 'number') {
            return (av - bv) * factor;
        }

        return String(av).localeCompare(String(bv), 'nl') * factor;
    });
});

const budgetRowsForSummaryTable = computed(() =>
    budgetRows.value.map((row: any) => ({
        id: row.id,
        name: `${row.category} - ${row.budgetName}`,
        budget: row.budget,
        spend: row.spend,
        remaining: row.remaining,
        payments: row.payments,
    })),
);
const budgetTotalsForSummaryTable = computed(() => ({
    budget: budgetRowsForSummaryTable.value.reduce((sum: number, row: any) => sum + Number(row.budget ?? 0), 0),
    spend: budgetRowsForSummaryTable.value.reduce((sum: number, row: any) => sum + Number(row.spend ?? 0), 0),
    unpaid: budgetRowsForSummaryTable.value.reduce((sum: number, row: any) => sum + Math.max(0, Number(row.remaining ?? 0)), 0),
    overdue: budgetRowsForSummaryTable.value.reduce((sum: number, row: any) => sum + Math.max(0, -Number(row.remaining ?? 0)), 0),
}));

const openStatsBudgets = (type: 'all' | 'expense' | 'income' | 'saving') => {
    statsBudgetsType.value = type;
    budgetSearch.value = '';
    budgetSort.value = { key: 'category', direction: 'asc' };
    statsBudgetsOpen.value = true;
};

const toggleBudgetSort = (key: 'category' | 'budgetName' | 'budget' | 'spend' | 'remaining' | 'payments') => {
    if (budgetSort.value.key === key) {
        budgetSort.value.direction = budgetSort.value.direction === 'asc' ? 'desc' : 'asc';

        return;
    }

    budgetSort.value = { key, direction: 'asc' };
};

const budgetModalTitle = computed(() => {
    if (statsBudgetsType.value === 'expense') return 'Budgetten - Uitgaven';
    if (statsBudgetsType.value === 'income') return 'Budgetten - Inkomsten';
    if (statsBudgetsType.value === 'saving') return 'Budgetten - Sparen';

    return 'Budgetten - Alle categorieen';
});

const applyMonth = () => {
    currentPage.value = 1;
    router.get('/categories', { filter: activeFilter.value, page: 1, month: month.value }, {
        preserveState: false,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Categorieën" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <template #header-right>
            <input v-model="month" type="month" class="w-full max-w-[220px] rounded-md border border-input bg-background px-3 py-2 text-sm" @change="applyMonth" />
        </template>
        <div class="flex flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <Stats :stats="(page.props.stats as any)" @open-transactions="openStatsTransactions" @open-budgets="openStatsBudgets" />
            </div>
        </div>
        <main class="p-4">
            <div class="rounded-md border bg-card text-card-foreground shadow-sm">
                <div class="flex gap-6 lg:flex-row lg:items-center lg:justify-between pr-6">
                    <div class="flex flex-col space-y-1.5 p-4 sm:p-6">
                        <div class="text-base font-medium tracking-tight sm:text-lg">
                            Categorieën
                        </div>
                        <div class="text-xs text-muted-foreground sm:text-sm">
                            Organiseer categorieën, beheer budgetten en filter snel op type voor beter inzicht.
                        </div>
                    </div>
                    <button
                        type="button"
                        class="rounded-md border border-dashed border-muted-foreground px-3 py-2 text-xs text-muted-foreground hover:bg-green-900 hover:text-white cursor-pointer"
                        @click="createModalOpen = true"
                    >
                        <div class="flex items-center gap-1">
                            <Plus class="h-4 w-4" /> Voeg categorie toe
                        </div>
                    </button>
                </div>
                <div class="p-4">
                    <Filters v-model:activeFilter="activeFilter" @change="onFilterChange" />
                </div>
                <div class="p-4 pt-0 sm:p-6 sm:pt-0">
                    <table class="w-full table-auto overflow-scroll text-md">
                        <tbody>
                            <template v-for="category in filteredCategories" :key="category.id">
                            <tr
                                class="group border-b border-slate-900 transition-colors hover:bg-muted/50"
                                @click="toggleCategoryDetails(category.id)"
                            >
                                <td class="p-2">
                                    <div class="flex items-center justify-between gap-3">
                                        <Category
                                            :color="category.color ?? null"
                                            :icon="category.icon ?? null"
                                            :slug="category.slug ?? null"
                                            :category="category.category ?? null"
                                            :budget="`${category.budgets.length} budgetten`"
                                            :type="category.type"
                                            :clickable="false"
                                        />
                                        <a
                                            :href="category.type === 'income' ? `/income/${category.slug}` : (category.type === 'saving' ? `/savings/${category.slug}` : `/expenses/${category.slug}`)"
                                            class="text-xs text-primary underline-offset-2 hover:underline"
                                            @click.stop
                                        >
                                            Openen
                                        </a>
                                    </div>
                                </td>
                                <td class="p-2 text-right">
                                    <TypeBadge :type="category.type" income-label="Inkomsten" expense-label="Uitgaven" />
                                </td>
                                <td class="p-2 text-right w-0">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <button
                                                type="button"
                                                @click.stop
                                                class="rounded-md p-1 text-muted-foreground opacity-0 transition hover:bg-muted group-hover:opacity-100 focus:opacity-100 focus-visible:opacity-100"
                                                aria-label="Meer acties"
                                            >
                                                <EllipsisVertical class="h-4 w-4" />
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="w-40">
                                            <DropdownMenuItem @click.stop="openEditModal(category)">
                                                <Pencil class="mr-2 h-4 w-4" />
                                                Bewerken
                                            </DropdownMenuItem>
                                            <DropdownMenuItem @click.stop="openDeleteCategoryModal(category)">
                                                <Trash2 class="mr-2 h-4 w-4 text-red-500" />
                                                <span class="text-red-500">Verwijderen</span>
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>
                            </tr>
                            <tr v-if="expandedCategoryId === category.id" class="border-b border-slate-900 bg-slate-950/30">
                                <td colspan="3" class="p-3">
                                    <div :class="`${customScrollbar} overflow-x-auto rounded-md border border-slate-800`">
                                        <BudgetSummaryTable
                                            :budgets="(category.budgets ?? []).map((budget: any) => ({
                                                id: budget.id,
                                                name: budget.name,
                                                budget: Number(budget.budget ?? 0),
                                                spend: Number(budget.spend ?? 0),
                                                remaining: Number(budget.remaining ?? 0),
                                                payments: Number(budget.payments ?? 0),
                                            }))"
                                            :type="category.type"
                                            :total-budget="Number(category.budget ?? 0)"
                                            :total-spend="Number(category.spend ?? 0)"
                                            :total-unpaid="Number(category.unpaid ?? 0)"
                                            :total-overdue="Number(category.overdue ?? 0)"
                                        />
                                    </div>
                                </td>
                            </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <TablePagination
                    class="rounded-b-md"
                    :pagination="(page.props.pagination as any)"
                    item-label="categorieën"
                    @previous="goToPage((page.props.pagination?.current_page ?? 1) - 1)"
                    @next="goToPage((page.props.pagination?.current_page ?? 1) + 1)"
                />
            </div>
            <CategoryEditModal :open="editModalOpen" :category="activeCategory" @update:open="(value) => editModalOpen = value" />
            <CreateCategoryModal :open="createModalOpen" @update:open="(value) => createModalOpen = value" />
            <Dialog :open="deleteCategoryPayload !== null" @update:open="(open) => { if (!open) deleteCategoryPayload = null; }">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Categorie verwijderen</DialogTitle>
                        <DialogDescription>
                            Categorie <strong>{{ deleteCategoryPayload?.categoryName }}</strong> bevat
                            <strong>{{ deleteCategoryPayload?.budgetCount ?? 0 }}</strong> budgetten.
                            In totaal zijn er <strong>{{ deleteCategoryPayload?.transactionsCount ?? 0 }}</strong> gekoppelde transacties.
                            Bij verwijderen worden bijbehorende transacties ontkoppeld.
                            Weet je zeker dat je wilt verwijderen?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button type="button" variant="secondary" :disabled="deletingCategory" @click="deleteCategoryPayload = null">Annuleren</Button>
                        <Button type="button" variant="destructive" :disabled="deletingCategory" @click="deleteCategory">
                            {{ deletingCategory ? 'Verwijderen...' : 'Verwijderen' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <Dialog :open="statsTransactionsOpen" @update:open="(open) => { statsTransactionsOpen = open; }">
                <DialogContent class="sm:max-w-5xl">
                    <DialogHeader>
                        <DialogTitle>Transacties</DialogTitle>
                        <DialogDescription>
                            Overzicht van {{ statsTransactionsType === 'expense' ? 'uitgaven' : statsTransactionsType === 'income' ? 'inkomsten' : 'spaartransacties' }}.
                        </DialogDescription>
                    </DialogHeader>
                    <div :class="`${customScrollbar} max-h-[60vh] overflow-x-auto overflow-y-auto rounded-xl border border-slate-800 bg-slate-950/20`">
                        <table class="w-full table-auto text-sm">
                            <thead class="sticky top-0 bg-slate-950">
                                <tr class="text-left text-xs uppercase tracking-wide text-muted-foreground">
                                    <th class="p-2">Categorie</th>
                                    <th class="p-2">Datum</th>
                                    <th class="p-2">Omschrijving</th>
                                    <th class="p-2">Type</th>
                                    <th class="p-2 text-right">Bedrag</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="transaction in statsTransactions" :key="transaction.id" class="border-b border-slate-800">
                                    <td class="p-2">{{ transaction.categoryId ? (categoriesData.find((c: any) => c.id === transaction.categoryId)?.category ?? 'Onbekend') : 'Onbekend' }}</td>
                                    <td class="p-2">{{ transaction.date }}</td>
                                    <td class="p-2">{{ transaction.description }}</td>
                                    <td class="p-2"><TypeBadge :type="transaction.type" /></td>
                                    <td class="p-2 text-right">{{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(transaction.amount) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex items-center justify-between border-t border-slate-800 px-4 py-3 text-sm text-muted-foreground">
                        <div>Toon {{ statsTransactionsPagination.from }} - {{ statsTransactionsPagination.to }} van {{ statsTransactionsPagination.total }} transacties</div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded-md border border-input bg-background px-3 py-1 text-xs transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                                @click="fetchStatsTransactions(statsTransactionsType, statsTransactionsPagination.current_page - 1)"
                                :disabled="statsTransactionsPagination.current_page <= 1"
                            >
                                Vorige
                            </button>
                            <button
                                type="button"
                                class="rounded-md border border-input bg-background px-3 py-1 text-xs transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                                @click="fetchStatsTransactions(statsTransactionsType, statsTransactionsPagination.current_page + 1)"
                                :disabled="statsTransactionsPagination.current_page >= statsTransactionsPagination.last_page"
                            >
                                Volgende
                            </button>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="statsTransactionsOpen = false">Sluiten</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <Dialog :open="statsBudgetsOpen" @update:open="(open) => { statsBudgetsOpen = open; }">
                <DialogContent class="sm:max-w-5xl">
                    <DialogHeader>
                        <DialogTitle>{{ budgetModalTitle }}</DialogTitle>
                        <DialogDescription>
                            Overzicht van de budgetten binnen deze selectie.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="flex items-center justify-between gap-3">
                        <input
                            v-model="budgetSearch"
                            type="text"
                            placeholder="Zoek op categorie of budget..."
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                        />
                    </div>
                    <div :class="`${customScrollbar} max-h-[60vh] overflow-x-auto overflow-y-auto rounded-xl border border-slate-800 bg-slate-950/20`">
                        <BudgetSummaryTable
                            :budgets="budgetRowsForSummaryTable"
                            :type="statsBudgetsType === 'all' ? 'expense' : statsBudgetsType"
                            :total-budget="budgetTotalsForSummaryTable.budget"
                            :total-spend="budgetTotalsForSummaryTable.spend"
                            :total-unpaid="budgetTotalsForSummaryTable.unpaid"
                            :total-overdue="budgetTotalsForSummaryTable.overdue"
                        />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="statsBudgetsOpen = false">Sluiten</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </main>
    </AppLayout>
</template>
