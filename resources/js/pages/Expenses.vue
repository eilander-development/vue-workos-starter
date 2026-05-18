<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { home, expenses } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage, Link } from '@inertiajs/vue3';

import Budget from '@/components/finance/Transaction/Budget.vue';
import Category from '@/components/Category.vue';
import Categories from '@/components/finance/Transaction/Categories.vue';
import Stats from '@/components/finance/Transaction/Stats.vue';
import Progress from '@/components/finance/Transaction/Progress.vue';
import { dynamicBackgroundColor } from '@/composables/colorVariants';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Home',
        href: home().url,
    },
    {
        title: 'Uitgaven',
        href: expenses().url,
    },
];

const page = usePage();
const categories = page.props.categories;
const selectedCategory = page.props.selected;

</script>

<template>

    <Head title="Uitgaven" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="p-4">
            <div class="lg:hidden overflow-x-auto mb-1">
                <div class="flex gap-4">
                    <template v-for="budget in categories" :key="budget.id">
                        <!-- active: border-primary/50 -->
                        <Link :href="`/expenses/${budget.slug}`" as="div">
                            <div :class="[selectedCategory.id == budget.id ? dynamicBackgroundColor(selectedCategory.color, true) : '']"
                                class="rounded-lg border bg-card text-card-foreground shadow-sm transition-all hover:border-primary/50 cursor-pointer flex-shrink-0 w-[260px]">
                                <Budget :budget="budget" />
                            </div>
                        </Link>
                    </template>
                </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6">
                    <div class="hidden lg:block lg:col-span-3 space-y-4">
                        <template v-for="budget in categories" :key="budget.id">
                             <!-- active: border-primary/50 -->
                            <Link :href="`/expenses/${budget.slug}`" as="div">
                                <div :class="[selectedCategory.id == budget.id ? dynamicBackgroundColor(selectedCategory.color, true) : '']"
                                    class="rounded-lg border bg-card text-card-foreground shadow-sm transition-all hover:border-primary/50 cursor-pointer flex-shrink-0 w-[260px] sm:w-full">
                                   <Budget :budget="budget" />
                                </div>
                            </Link>
                        </template>
                    </div>
                    <div class="lg:col-span-9">
                        <div class="space-y-4">
                            <div :class="`${dynamicBackgroundColor(selectedCategory.color, true)} rounded-lg border bg-card text-card-foreground shadow-sm p-6`">
                                <Category :color="selectedCategory.color" :icon="selectedCategory.icon" :category="selectedCategory.name" icon-container-size="p-2 sm:p-3" icon-size="h-6 w-6" category-font="text-xl sm:text-2xl font-bold"  />
                            </div>
                            <Progress :selectedCategory="selectedCategory" />
                            <Stats :selectedCategory="selectedCategory" />
                            <Categories :selectedCategory="selectedCategory" />
                        </div>
                    </div>
                </div>
        </main>
    </AppLayout>
</template>
