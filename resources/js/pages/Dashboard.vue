<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import MonthlyExpenses from '@/components/finance/Dashboard/MonthlyExpenses.vue';
import ExpensesChart from '@/components/finance/Dashboard/ExpensesChart.vue';
import MonthlyBudgets from '@/components/finance/Dashboard/MonthlyBudgets.vue';
import Stats from '@/components/finance/Dashboard/Stats.vue';
import LatestTransactions from '@/components/finance/Dashboard/LatestTransactions.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { customScrollbar } from '@/composables/scrollbar';
import { home,dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { HandCoins, PiggyBank, Plus, TrendingDown, TrendingUp, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch, onMounted } from 'vue';

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
const categoriesState = ref<Record<string, any>>((page.props.categories as any) ?? {});
const monthlyExpensesSpendState = ref<any[]>(((page.props.monthlyExpenses as any)?.spend ?? []) as any[]);
const monthlyExpensesBudgetsState = ref<any[]>(((page.props.monthlyExpenses as any)?.budgets ?? []) as any[]);
const yearlyExpensesChart = computed(() => page.props.yearlyExpensesChart);
const statsState = ref<any>((page.props.stats as any) ?? {
    income: 0, expenses: 0, left: 0, budgets: 0, currentBalance: 0, budgetedTotal: 0, spentTotal: 0, toPayBudgets: 0, overspentBudgets: 0, remainingBudgets: 0, afterBudgets: 0, hasBudgetCoverage: true, remainingBudgetCategories: [], dynamicBudgets: [],
});
const latestTransactionsState = ref<any[]>((page.props.latestTransactions as any) ?? []);
const latestTransactionsPaginationState = ref<any>((page.props.latestTransactionsPagination as any) ?? null);
const activeType = ref<'all' | 'expense' | 'income' | 'saving'>('all');
const month = ref((page.props.month as string) || new Date().toISOString().slice(0, 7));
const isDashboardDataLoading = ref(false);
const showIncomeModal = ref(false);
const showExpensesModal = ref(false);
const showCoverageModal = ref(false);
const showDynamicBudgetModal = ref(false);
const dynamicBudgetRows = ref<Array<{ name: string; budget: number | string; paid: number | string }>>([]);
const dynamicBudgetErrors = ref<Record<number, { name?: string; budget?: string; paid?: string }>>({});
const isSavingDynamicBudgets = ref(false);

const applyMonth = () => {
    router.get('/dashboard', { month: month.value }, { preserveState: false, preserveScroll: true });
};
const loadDashboardData = async (pageNumber = 1) => {
    isDashboardDataLoading.value = true;
    try {
        const response = await fetch(`/dashboard/data?month=${month.value}&transactions_page=${pageNumber}`, { headers: { Accept: 'application/json' } });
        const data = await response.json();
        categoriesState.value = data.categories ?? {};
        statsState.value = data.stats ?? statsState.value;
        monthlyExpensesSpendState.value = data.monthlyExpenses?.spend ?? [];
        monthlyExpensesBudgetsState.value = data.monthlyExpenses?.budgets ?? [];
        latestTransactionsState.value = data.latestTransactions ?? [];
        latestTransactionsPaginationState.value = data.latestTransactionsPagination ?? null;
    } finally {
        isDashboardDataLoading.value = false;
    }
};
onMounted(() => loadDashboardData(1));
watch(month, () => loadDashboardData(1));

const allTransactions = computed(() =>
    Object.values((categoriesState.value as Record<string, any>) ?? {}).flatMap((category: any) =>
        (category.budgets ?? []).flatMap((budget: any) =>
            (budget.transactions ?? []).map((transaction: any) => ({
                ...transaction,
                category: category.category ?? category.name ?? '-',
                budget: budget.name ?? '-',
            })),
        ),
    ),
);

const incomeTransactions = computed(() => allTransactions.value.filter((transaction: any) => Number(transaction.amount) > 0));
const expenseTransactions = computed(() => allTransactions.value.filter((transaction: any) => Number(transaction.amount) < 0));
const toAmount = (value: unknown) => {
    const n = Number(value);
    return Number.isFinite(n) ? n : 0;
};

const openDynamicBudgetModal = () => {
    dynamicBudgetRows.value = ((statsState.value?.dynamicBudgets ?? []) as any[]).map((row: any) => ({
        name: row.name ?? '',
        budget: toAmount(row.budget),
        paid: toAmount(row.spend),
    }));
    if (dynamicBudgetRows.value.length === 0) {
        dynamicBudgetRows.value.push({ name: '', budget: 0, paid: 0 });
    }
    showDynamicBudgetModal.value = true;
};

const addDynamicBudgetRow = () => dynamicBudgetRows.value.push({ name: '', budget: 0, paid: 0 });
const removeDynamicBudgetRow = (index: number) => dynamicBudgetRows.value.splice(index, 1);
const isZeroPaid = (row: { paid: number | string }) => toAmount(row.paid) === 0;
const setPaidToBudget = (row: { budget: number | string; paid: number | string }, checked: boolean) => {
    if (checked) {
        row.paid = toAmount(row.budget);
        return;
    }

    row.paid = 0;
};
const validateDynamicBudgets = () => {
    const errors: Record<number, { name?: string; budget?: string; paid?: string }> = {};

    dynamicBudgetRows.value.forEach((row, index) => {
        const name = (row.name ?? '').trim();
        const budget = Number(row.budget);
        const paid = Number(row.paid);

        if (name === '') {
            errors[index] = { ...(errors[index] ?? {}), name: 'Naam is verplicht.' };
        }
        if (!Number.isFinite(budget)) {
            errors[index] = { ...(errors[index] ?? {}), budget: 'Budget moet een getal zijn.' };
        }
        if (!Number.isFinite(paid)) {
            errors[index] = { ...(errors[index] ?? {}), paid: 'Betaald moet een getal zijn.' };
        }
    });

    dynamicBudgetErrors.value = errors;

    return Object.keys(errors).length === 0;
};

const saveDynamicBudgets = () => {
    if (!validateDynamicBudgets()) {
        return;
    }

    isSavingDynamicBudgets.value = true;
    router.post('/dashboard/dynamic-budgets', {
        month: month.value,
        rows: dynamicBudgetRows.value.map((row) => ({
            name: row.name,
            budget: row.budget,
            paid: row.paid,
        })),
    }, {
        preserveScroll: true,
        onFinish: () => { isSavingDynamicBudgets.value = false; },
        onSuccess: () => { showDynamicBudgetModal.value = false; },
    });
};

</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <template #header-right>
            <input v-model="month" type="month" class="w-full max-w-[220px] rounded-md border border-input bg-background px-3 py-2 text-sm" @change="applyMonth" />
        </template>
        <div class="flex flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <Stats :stats="statsState" @open-income="showIncomeModal = true" @open-expenses="showExpensesModal = true" @open-coverage="showCoverageModal = true" />
            </div>
            <div class="inline-flex w-fit items-center gap-1 rounded-lg bg-slate-900 p-1">
                <button class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm text-slate-300 transition hover:text-white" :class="activeType === 'all' ? 'bg-slate-700 text-white' : ''" @click="activeType = 'all'"><HandCoins class="h-3.5 w-3.5" />Alle</button>
                <button class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm text-slate-300 transition hover:text-white" :class="activeType === 'expense' ? 'bg-slate-700 text-white' : ''" @click="activeType = 'expense'"><TrendingDown class="h-3.5 w-3.5" />Uitgaven</button>
                <button class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm text-slate-300 transition hover:text-white" :class="activeType === 'income' ? 'bg-slate-700 text-white' : ''" @click="activeType = 'income'"><TrendingUp class="h-3.5 w-3.5" />Inkomsten</button>
                <button class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm text-slate-300 transition hover:text-white" :class="activeType === 'saving' ? 'bg-slate-700 text-white' : ''" @click="activeType = 'saving'"><PiggyBank class="h-3.5 w-3.5" />Sparen</button>
            </div>
            <div class="grid gap-4 md:grid-cols-5">
                <ExpensesChart :yearlyExpensesChart="yearlyExpensesChart" class="md:col-span-3" />
                <MonthlyExpenses :monthlyExpenses="monthlyExpensesBudgetsState" :categories="categoriesState" :filter-type="activeType" title="Begroot per budget" />
                <MonthlyExpenses :monthlyExpenses="monthlyExpensesSpendState" :categories="categoriesState" :filter-type="activeType" title="Uitgaven per budget" />
            </div>
            <div class="grid gap-4 md:grid-cols-4">
                <MonthlyBudgets :monthlyExpenses="monthlyExpensesSpendState" :categories="categoriesState" :filter-type="activeType" />
                <LatestTransactions :latestTransactions="latestTransactionsState" :categories="categoriesState" :pagination="latestTransactionsPaginationState" :month="month" :filter-type="activeType" class="md:col-span-3" @page-change="loadDashboardData" />
            </div>
        </div>

        <Dialog :open="showIncomeModal" @update:open="(open) => (showIncomeModal = open)">
            <DialogContent class="sm:max-w-4xl">
                <DialogHeader><DialogTitle>Inkomsten transacties</DialogTitle></DialogHeader>
                <div :class="`${customScrollbar} max-h-[60vh] overflow-y-auto`">
                    <table class="w-full text-sm">
                        <thead><tr class="text-left text-muted-foreground"><th class="p-2">Datum</th><th class="p-2">Omschrijving</th><th class="p-2">Categorie</th><th class="p-2">Budget</th><th class="p-2 text-right">Bedrag</th></tr></thead>
                        <tbody>
                            <tr v-for="transaction in incomeTransactions" :key="transaction.id" class="border-b border-slate-800">
                                <td class="p-2">{{ transaction.date }}</td><td class="p-2">{{ transaction.description }}</td><td class="p-2">{{ transaction.category }}</td><td class="p-2">{{ transaction.budget }}</td><td class="p-2 text-right">{{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(transaction.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <DialogFooter><Button type="button" variant="secondary" @click="showIncomeModal = false">Sluiten</Button></DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="showExpensesModal" @update:open="(open) => (showExpensesModal = open)">
            <DialogContent class="sm:max-w-4xl">
                <DialogHeader><DialogTitle>Uitgaven transacties</DialogTitle></DialogHeader>
                <div :class="`${customScrollbar} max-h-[60vh] overflow-y-auto`">
                    <table class="w-full text-sm">
                        <thead><tr class="text-left text-muted-foreground"><th class="p-2">Datum</th><th class="p-2">Omschrijving</th><th class="p-2">Categorie</th><th class="p-2">Budget</th><th class="p-2 text-right">Bedrag</th></tr></thead>
                        <tbody>
                            <tr v-for="transaction in expenseTransactions" :key="transaction.id" class="border-b border-slate-800">
                                <td class="p-2">{{ transaction.date }}</td><td class="p-2">{{ transaction.description }}</td><td class="p-2">{{ transaction.category }}</td><td class="p-2">{{ transaction.budget }}</td><td class="p-2 text-right">{{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(transaction.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <DialogFooter><Button type="button" variant="secondary" @click="showExpensesModal = false">Sluiten</Button></DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="showCoverageModal" @update:open="(open) => (showCoverageModal = open)">
            <DialogContent class="sm:max-w-4xl">
                <DialogHeader><DialogTitle>Resterende budgetten per categorie</DialogTitle></DialogHeader>
                <div class="flex justify-end">
                    <Button type="button" variant="outline" @click="openDynamicBudgetModal">Dynamisch budget beheren</Button>
                </div>
                <div :class="`${customScrollbar} max-h-[60vh] overflow-y-auto`">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-muted-foreground">
                                <th class="p-2">Categorie / Budget</th>
                                <th class="p-2 text-right">Budget</th>
                                <th class="p-2 text-right">Besteed</th>
                                <th class="p-2 text-right">Nog te betalen</th>
                                <th class="p-2 text-right">Teveel betaald</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="category in (statsState as any).remainingBudgetCategories ?? []" :key="`cat-${category.id}`">
                                <tr class="border-t border-slate-700 bg-slate-900/40 font-semibold">
                                    <td class="p-2">{{ category.name }}</td>
                                    <td class="p-2 text-right text-slate-300">{{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(toAmount(category.budgeted)) }}</td>
                                    <td class="p-2 text-right">{{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(toAmount(category.spent)) }}</td>
                                    <td class="p-2 text-right" :class="toAmount(category.toPay) === 0 ? 'text-slate-400' : 'text-amber-300'">{{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(toAmount(category.toPay)) }}</td>
                                    <td class="p-2 text-right" :class="toAmount(category.overspent) === 0 ? 'text-slate-400' : 'text-red-300'">{{ toAmount(category.overspent) > 0 ? '-' : '' }}{{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(toAmount(category.overspent)) }}</td>
                                </tr>
                                <tr v-for="budget in category.budgets" :key="`budget-${budget.id}`" class="text-slate-400" :class="toAmount(budget.toPay) === 0 ? 'bg-emerald-500/5' : ''">
                                    <td class="p-2 pl-6">{{ budget.name }}</td>
                                    <td class="p-2 text-right">{{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(toAmount(budget.budget)) }}</td>
                                    <td class="p-2 text-right">{{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(toAmount(budget.spend)) }}</td>
                                    <td class="p-2 text-right" :class="toAmount(budget.toPay) === 0 ? 'text-slate-400' : 'text-amber-300'">{{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(toAmount(budget.toPay)) }}</td>
                                    <td class="p-2 text-right" :class="toAmount(budget.overspent) === 0 ? 'text-slate-400' : 'text-red-300'">{{ toAmount(budget.overspent) > 0 ? '-' : '' }}{{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(toAmount(budget.overspent)) }}</td>
                                </tr>
                            </template>
                            <tr class="border-t-2 border-slate-600 font-bold">
                                <td class="p-2">Totaal</td>
                                <td class="p-2 text-right text-slate-300">{{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(toAmount((statsState as any).budgetedTotal)) }}</td>
                                <td class="p-2 text-right">{{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(toAmount((statsState as any).spentTotal)) }}</td>
                                <td class="p-2 text-right" :class="toAmount((statsState as any).toPayBudgets) === 0 ? 'text-slate-400' : 'text-amber-300'">{{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(toAmount((statsState as any).toPayBudgets)) }}</td>
                                <td class="p-2 text-right" :class="toAmount((statsState as any).overspentBudgets) === 0 ? 'text-slate-400' : 'text-red-300'">{{ toAmount((statsState as any).overspentBudgets) > 0 ? '-' : '' }}{{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(toAmount((statsState as any).overspentBudgets)) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <DialogFooter><Button type="button" variant="secondary" @click="showCoverageModal = false">Sluiten</Button></DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="showDynamicBudgetModal" @update:open="(open) => (showDynamicBudgetModal = open)">
            <DialogContent class="sm:max-w-4xl">
                <DialogHeader><DialogTitle>Dynamisch budget ({{ month }})</DialogTitle></DialogHeader>
                <div :class="`${customScrollbar} max-h-[60vh] overflow-y-auto space-y-3`">
                    <div class="flex justify-end">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-md border border-dashed border-muted-foreground/70 px-3 py-2 text-sm text-muted-foreground transition hover:bg-muted/40"
                            @click="addDynamicBudgetRow"
                        >
                            <Plus class="h-4 w-4" />
                            Voeg budget toe
                        </button>
                    </div>
                    <div class="grid grid-cols-12 gap-2 px-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        <div class="col-span-4">Naam</div>
                        <div class="col-span-3">Budget</div>
                        <div class="col-span-3">Betaald</div>
                        <div class="col-span-1 text-center">Volledig</div>
                        <div class="col-span-1 text-center">Actie</div>
                    </div>
                    <div v-for="(row, index) in dynamicBudgetRows" :key="index" class="grid grid-cols-12 gap-2 items-center">
                        <input v-model="row.name" type="text" placeholder="Naam" class="col-span-4 rounded-md border border-input bg-background px-3 py-2 text-sm" />
                        <input v-model="row.budget" type="number" step="0.01" placeholder="Budget" class="col-span-3 rounded-md border border-input bg-background px-3 py-2 text-sm" />
                        <input v-model="row.paid" type="number" step="0.01" placeholder="Betaald" class="col-span-3 rounded-md border border-input bg-background px-3 py-2 text-sm" />
                        <div class="col-span-1 flex items-center justify-center">
                            <input
                                type="checkbox"
                                :checked="isZeroPaid(row) ? false : toAmount(row.paid) === toAmount(row.budget)"
                                @change="setPaidToBudget(row, ($event.target as HTMLInputElement).checked)"
                                class="h-4 w-4 rounded border-input bg-background"
                            />
                        </div>
                        <div class="col-span-1 flex items-center justify-center">
                            <button type="button" class="inline-flex items-center justify-center text-red-500 hover:text-red-400" @click="removeDynamicBudgetRow(index)">
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                        <p v-if="dynamicBudgetErrors[index]?.name" class="col-span-4 text-xs text-red-300">{{ dynamicBudgetErrors[index]?.name }}</p>
                        <p v-if="dynamicBudgetErrors[index]?.budget" class="col-span-3 text-xs text-red-300">{{ dynamicBudgetErrors[index]?.budget }}</p>
                        <p v-if="dynamicBudgetErrors[index]?.paid" class="col-span-3 text-xs text-red-300">{{ dynamicBudgetErrors[index]?.paid }}</p>
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="secondary" @click="showDynamicBudgetModal = false">Annuleren</Button>
                    <Button type="button" :disabled="isSavingDynamicBudgets" @click="saveDynamicBudgets">
                        {{ isSavingDynamicBudgets ? 'Opslaan...' : 'Opslaan' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
