<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Stats from '@/components/finance/Categories/Stats.vue';
import Category from '@/components/Category.vue';
import CategoryEditModal from '@/components/finance/Categories/CategoryEditModal.vue';
import CreateCategoryModal from '@/components/finance/Categories/CreateCategoryModal.vue';
import { categories, home } from '@/routes';
import { Head, router, usePage } from '@inertiajs/vue3';
import { type BreadcrumbItem, type TransactionFilter } from '@/types';
import Filters from '@/components/finance/Filters.vue';
import { Form } from '@inertiajs/vue3';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { EllipsisVertical, Pencil, Plus, Trash2 } from 'lucide-vue-next';

const page = usePage();
const categoriesData = computed(() =>
    Array.isArray(page.props.categories)
        ? page.props.categories
        : Object.values(page.props.categories ?? []),
);

const activeFilter = ref<TransactionFilter>((page.props.activeFilter as TransactionFilter) || 'all');
const editModalOpen = ref(false);
const createModalOpen = ref(false);
const activeCategory = ref<Record<string, any> | null>(null);
const deleteCategoryId = ref<number | null>(null);

const typeLabels: Record<TransactionFilter, string> = {
    all: 'Alles',
    expense: 'Uitgaven',
    income: 'Inkomsten',
    saving: 'Sparen',
    uncategorized: 'Ongecategoriseerd',
};

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
const openDeleteCategoryModal = (categoryId: number) => {
    deleteCategoryId.value = categoryId;
};

const typeBadgeLabel = (type: string) => {
    return typeLabels[type] ?? type;
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
        { filter: value, page: currentPage.value },
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
        { filter: activeFilter.value, page: currentPage.value },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['categories', 'stats', 'activeFilter', 'pagination'],
        },
    );
};
</script>

<template>
    <Head title="Categorieën" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <Stats :stats="(page.props.stats as any)" />
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
                            <tr
                                class="group border-b border-slate-900 transition-colors hover:bg-muted/50"
                                v-for="category in filteredCategories"
                                :key="category.id"
                            >
                                <td class="p-2">
                                    <Category
                                        :color="category.color ?? null"
                                        :icon="category.icon ?? null"
                                        :slug="category.slug ?? null"
                                        :category="category.category ?? null"
                                        :budget="`${category.budgets.length} budgetten`"
                                    />
                                </td>
                                <td class="p-2 text-right">
                                    <span class="rounded-full bg-slate-950 px-3 py-1 text-xs text-white">{{ typeBadgeLabel(category.type) }}</span>
                                </td>
                                <td class="p-2 text-right w-0">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <button
                                                type="button"
                                                class="rounded-md p-1 text-muted-foreground opacity-0 transition hover:bg-muted group-hover:opacity-100 focus:opacity-100 focus-visible:opacity-100"
                                                aria-label="Meer acties"
                                            >
                                                <EllipsisVertical class="h-4 w-4" />
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="w-40">
                                            <DropdownMenuItem @click="openEditModal(category)">
                                                <Pencil class="mr-2 h-4 w-4" />
                                                Bewerken
                                            </DropdownMenuItem>
                                            <DropdownMenuItem @click="openDeleteCategoryModal(category.id)">
                                                <Trash2 class="mr-2 h-4 w-4 text-red-500" />
                                                <span class="text-red-500">Verwijderen</span>
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex items-center justify-between rounded-b-md border-t border-slate-900 bg-muted px-4 py-3 text-sm text-muted-foreground">
                    <div>
                        Toon {{ page.props.pagination?.from ?? 0 }} - {{ page.props.pagination?.to ?? 0 }} van {{ page.props.pagination?.total ?? 0 }} categorieën
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-md border border-input bg-background px-3 py-1 text-xs transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                            @click="goToPage((page.props.pagination?.current_page ?? 1) - 1)"
                            :disabled="(page.props.pagination?.current_page ?? 1) <= 1"
                        >
                            Vorige
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-input bg-background px-3 py-1 text-xs transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                            @click="goToPage((page.props.pagination?.current_page ?? 1) + 1)"
                            :disabled="(page.props.pagination?.current_page ?? 1) >= (page.props.pagination?.last_page ?? 1)"
                        >
                            Volgende
                        </button>
                    </div>
                </div>
            </div>
            <CategoryEditModal :open="editModalOpen" :category="activeCategory" @update:open="(value) => editModalOpen = value" />
            <CreateCategoryModal :open="createModalOpen" @update:open="(value) => createModalOpen = value" />
            <Dialog :open="deleteCategoryId !== null" @update:open="(open) => { if (!open) deleteCategoryId = null; }">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Categorie verwijderen</DialogTitle>
                        <DialogDescription>Weet je zeker dat je deze categorie wilt verwijderen?</DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="deleteCategoryId = null">Annuleren</Button>
                        <Form v-if="deleteCategoryId !== null" :action="`/categories/${deleteCategoryId}`" method="delete">
                            <Button type="submit" variant="destructive">Verwijderen</Button>
                        </Form>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </main>
    </AppLayout>
</template>
