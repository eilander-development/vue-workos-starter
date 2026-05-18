<script setup lang="ts">
import { computed, ref, watchEffect } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { categories, home } from '@/routes';
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';

const page = usePage();
const categoriesData = page.props.categories ?? [];
const selectedBudgetCategory = ref<number | null>(null);

watchEffect(() => {
    if (!selectedBudgetCategory.value && categoriesData.length) {
        selectedBudgetCategory.value = categoriesData[0].id;
    }
});

const selectedCategory = computed(() => {
    return categoriesData.find((item: any) => item.id === selectedBudgetCategory.value) ?? categoriesData[0];
});

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
</script>

<template>
    <Head title="Categorieën" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="grid gap-4 lg:grid-cols-4 p-4">
            <section class="lg:col-span-1 space-y-4">
                <div class="rounded-3xl border bg-card p-4 shadow-sm">
                    <h2 class="text-lg font-semibold">Categorieën</h2>
                    <p class="text-sm text-muted-foreground">Maak nieuwe categorieën en beheer bestaande budgetten.</p>
                </div>

                <div class="space-y-3">
                    <template v-for="category in categoriesData" :key="category.id">
                        <div class="rounded-3xl border bg-card p-4 shadow-sm">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="font-semibold">{{ category.category }}</p>
                                    <p class="text-xs text-muted-foreground">{{ category.budgets.length }} budget(s)</p>
                                </div>
                                <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs">{{ category.slug }}</span>
                            </div>
                        </div>
                    </template>
                </div>
            </section>

            <section class="lg:col-span-3 space-y-4">
                <div class="rounded-3xl border bg-card p-6 shadow-sm">
                    <div class="mb-4 flex items-center justify-between gap-4">
                        <div>
                            <h1 class="text-2xl font-semibold">Nieuwe categorie</h1>
                            <p class="text-sm text-muted-foreground">Voeg een nieuwe categorie toe met kleur en icoon.</p>
                        </div>
                    </div>

                    <Form action="/categories" method="post" class="grid gap-4">
                        <div class="grid gap-2">
                            <label for="name" class="text-sm font-medium">Naam</label>
                            <input id="name" name="name" class="rounded-xl border px-3 py-2 outline-none focus:ring-2 focus:ring-slate-400" placeholder="Eten/drinken" required />
                        </div>
                        <div class="grid gap-2">
                            <label for="slug" class="text-sm font-medium">Slug</label>
                            <input id="slug" name="slug" class="rounded-xl border px-3 py-2 outline-none focus:ring-2 focus:ring-slate-400" placeholder="eten-drinken" required />
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <label for="icon" class="text-sm font-medium">Icoon</label>
                                <input id="icon" name="icon" class="rounded-xl border px-3 py-2 outline-none focus:ring-2 focus:ring-slate-400" placeholder="Salad" />
                            </div>
                            <div class="grid gap-2">
                                <label for="color" class="text-sm font-medium">Kleur</label>
                                <input id="color" name="color" class="rounded-xl border px-3 py-2 outline-none focus:ring-2 focus:ring-slate-400" placeholder="red" />
                            </div>
                        </div>
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary/90">Opslaan</button>
                    </Form>
                </div>

                <div class="rounded-3xl border bg-card p-6 shadow-sm">
                    <div class="mb-4">
                        <h2 class="text-xl font-semibold">Nieuw budget</h2>
                        <p class="text-sm text-muted-foreground">Voeg een budget toe aan een bestaande categorie.</p>
                    </div>

                    <Form :action="selectedBudgetCategory ? `/categories/${selectedBudgetCategory}/budgets` : '/categories/budgets'" method="post" class="grid gap-4">
                        <div class="grid gap-2">
                            <label for="category_id" class="text-sm font-medium">Categorie</label>
                            <select id="category_id" name="category_id" class="rounded-xl border px-3 py-2 outline-none focus:ring-2 focus:ring-slate-400" v-model="selectedBudgetCategory" required>
                                <option v-for="category in categoriesData" :key="category.id" :value="category.id">{{ category.category }}</option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <label for="name" class="text-sm font-medium">Budgetnaam</label>
                            <input id="name" name="name" class="rounded-xl border px-3 py-2 outline-none focus:ring-2 focus:ring-slate-400" placeholder="Restaurants" required />
                        </div>
                        <div class="grid gap-2">
                            <label for="budget" class="text-sm font-medium">Bedrag</label>
                            <input id="budget" name="budget" type="number" step="0.01" class="rounded-xl border px-3 py-2 outline-none focus:ring-2 focus:ring-slate-400" placeholder="100.00" required />
                        </div>
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary/90" :disabled="!selectedBudgetCategory">Opslaan</button>
                    </Form>
                </div>

                <div class="rounded-3xl border bg-card p-6 shadow-sm">
                    <h2 class="text-xl font-semibold mb-4">Budgetten per categorie</h2>

                    <div v-if="categoriesData.length" class="space-y-4">
                        <template v-for="category in categoriesData" :key="category.id">
                            <div class="rounded-2xl border bg-background p-4">
                                <div class="flex flex-wrap items-center justify-between gap-4">
                                    <p class="font-semibold">{{ category.category }}</p>
                                    <span class="text-sm text-muted-foreground">{{ category.budgets.length }} budget(s)</span>
                                </div>
                                <div class="mt-3 grid gap-2">
                                    <div v-for="budget in category.budgets" :key="budget.id" class="rounded-2xl border bg-card p-3">
                                        <div class="flex items-center justify-between gap-4">
                                            <div>
                                                <p class="font-medium">{{ budget.name }}</p>
                                                <p class="text-sm text-muted-foreground">{{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(budget.budget) }}</p>
                                            </div>
                                            <div class="text-right text-sm">
                                                <p>{{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(budget.spend) }} betaald</p>
                                                <p class="text-xs text-muted-foreground">Nog {{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(budget.remaining) }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div v-else class="rounded-2xl border bg-card p-4 text-sm text-muted-foreground">Geen categorieën gevonden. Voeg eerst een categorie toe.</div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
