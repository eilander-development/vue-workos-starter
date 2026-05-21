<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Stats from '@/components/finance/Categories/Stats.vue';
import Category from '@/components/Category.vue';
import CategoryEditModal from '@/components/finance/Categories/CategoryEditModal.vue';
import CreateCategoryModal from '@/components/finance/Categories/CreateCategoryModal.vue';
import { categories, home } from '@/routes';
import { Head, usePage } from '@inertiajs/vue3';
import { type BreadcrumbItem, type TransactionFilter } from '@/types';
import Filters from '@/components/finance/Filters.vue';
import { Form } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';

const page = usePage();
const categoriesData = computed(() =>
    Array.isArray(page.props.categories)
        ? page.props.categories
        : Object.values(page.props.categories ?? []),
);

const activeFilter = ref<TransactionFilter>('all');
const editModalOpen = ref(false);
const createModalOpen = ref(false);
const activeCategory = ref<Record<string, any> | null>(null);

const typeLabels: Record<TransactionFilter, string> = {
    all: 'Alles',
    expense: 'Uitgaven',
    income: 'Inkomsten',
    saving: 'Sparen',
    uncategorized: 'Ongecategoriseerd',
};

const filteredCategories = computed(() => {
    if (activeFilter.value === 'all') {
        return categoriesData.value;
    }

    return categoriesData.value.filter((category: any) => category.type === activeFilter.value);
});

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

const typeBadgeLabel = (type: string) => {
    return typeLabels[type] ?? type;
};

watch(categoriesData, syncActiveCategory);
</script>

<template>
    <Head title="Categorieën" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <Stats :categorieData="categoriesData" />
            </div>
        </div>
        <main class="p-4">
            <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
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
                    <Filters v-model:activeFilter="activeFilter"/>
                </div>
                <div class="p-4 pt-0 sm:p-6 sm:pt-0">
                    <table class="w-full table-auto overflow-scroll text-md">
                        <tbody>
                            <tr
                                class="border-b border-slate-900 transition-colors hover:bg-muted/50"
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
                                    <button  type="button" class="px-4 py-2 text-sm font-medium text-white transition cursor-pointer"
                                        @click="openEditModal(category)">
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                </td>
                                <td class="p-2 text-right w-0">
                                    <Form :action="`/categories/${category.id}`" method="delete">
                                        <button type="submit" class="px-2 py-2 text-sm font-medium text-red-400 transition cursor-pointer" onclick="return confirm('Weet je zeker dat je deze categorie wilt verwijderen?')">
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </Form>
                                </td>
                            </tr>
                            <tr>
                                <td class="p-2" colspan="4">
                                    <button type="button" class="rounded-md border border-dashed border-muted-foreground px-3 py-2 text-xs text-muted-foreground hover:bg-green-900 hover:text-white cursor-pointer" @click="createModalOpen = true">
                                        <div class="flex items-center gap-1">
                                            <Plus class="h-4 w-4" /> Voeg categorie toe
                                        </div>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <CategoryEditModal :open="editModalOpen" :category="activeCategory" @update:open="(value) => editModalOpen = value" />
            <CreateCategoryModal :open="createModalOpen" @update:open="(value) => createModalOpen = value" />
        </main>
    </AppLayout>
</template>
