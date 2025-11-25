<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { home, budgets } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

import Category from '@/components/Category.vue';
import { dynamicBackgroundColor } from '@/composables/colorVariants';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Home',
        href: home().url,
    },
    {
        title: 'Budgets',
        href: budgets().url,
    },
];

const page = usePage();
const categories = page.props.categories;
let selectedCategory = ref(categories[1]);

function selectCategory(categoryId) {
    selectedCategory.value = categories[categoryId];
}

</script>

<template>

    <Head title="Budgets" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="p-4">
            <div class="lg:hidden overflow-x-auto">
                <div class="flex gap-4">
                    <template v-for="budget in categories" :key="budget.id">
                        <!-- active: border-primary/50 -->
                        <div @click="selectCategory(budget.id)"
                             :class="{'border-primary/50' : selectedCategory.id == budget.id}"
                             class="rounded-lg border bg-card text-card-foreground shadow-sm transition-all hover:border-primary/50 cursor-pointer flex-shrink-0 w-[260px]">
                            <div class="p-4 sm:p-6">
                                <div class="flex items-center gap-3 sm:gap-4">
                                    <Category :color="budget.color" :icon="budget.icon" icon-container-size="p-2 sm:p-3" icon-size="h-6 w-6" category-font="font-medium"  />
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="font-medium text-sm sm:text-base">{{ budget.category }}</div>
                                                <div class="text-xs sm:text-sm text-muted-foreground">{{new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(budget.budget)}}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6">
                    <div class="hidden lg:block lg:col-span-3 space-y-4">
                        <template v-for="budget in categories" :key="budget.id">
                             <!-- active: border-primary/50 -->
                        <div @click="selectCategory(budget.id)"
                             :class="{'border-primary/50' : selectedCategory.id == budget.id}"
                             class="rounded-lg border bg-card text-card-foreground shadow-sm transition-all hover:border-primary/50 cursor-pointer flex-shrink-0 w-[260px] sm:w-full">
                            <div class="p-4">
                                <div class="flex items-center gap-3 sm:gap-4">
                                    <Category :color="budget.color" :icon="budget.icon" icon-container-size="p-2 sm:p-3" icon-size="h-6 w-6" category-font="font-medium"  />
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="font-medium text-sm sm:text-base">{{ budget.category }}</div>
                                                <div class="text-xs sm:text-sm text-muted-foreground">{{new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(budget.budget)}}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </template>
                    </div>
                    <div class="lg:col-span-9">
                        <div class="space-y-4">
                            <div :class="`${dynamicBackgroundColor(selectedCategory.color, true)} rounded-lg border bg-card text-card-foreground shadow-sm p-6`">
                                <Category :color="selectedCategory.color" :icon="selectedCategory.icon" :category="selectedCategory.category" icon-container-size="p-2 sm:p-3" icon-size="h-6 w-6" category-font="text-xl sm:text-2xl font-bold"  />
                            </div>
                            <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
                                <div class="p-6 pt-4 sm:pt-6">
                                    <div class="space-y-3 sm:space-y-4">
                                        <div
                                            class="flex items-center justify-between text-xs sm:text-sm text-muted-foreground">
                                            <div>Uitgegeven</div>
                                            <div>Budget</div>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <div class="text-lg sm:text-2xl font-bold">$650.75</div>
                                            <div class="text-lg sm:text-2xl font-bold">$850</div>
                                        </div>
                                        <div aria-valuemax="100" aria-valuemin="0" role="progressbar"
                                            data-state="indeterminate" data-max="100"
                                            class="relative w-full overflow-hidden rounded-full bg-slate-100 dark:bg-secondary h-2">
                                            <div data-state="indeterminate" data-max="100"
                                                class=""
                                                 :class="`bg-${selectedCategory.color}-500 h-full w-full flex-1 bg-primary transition-all`"
                                                style="transform: translateX(-23.4412%);"></div>
                                        </div>
                                        <div class="flex items-center justify-between text-xs sm:text-sm">
                                            <div class="">77%</div>
                                            <div class="text-muted-foreground">23%</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
                                    <div class="p-4 sm:p-6">
                                        <div class="space-y-1">
                                            <div class="text-xs sm:text-sm text-muted-foreground">Last Month</div>
                                            <div class="text-lg sm:text-2xl font-bold">$820.5</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
                                    <div class="p-4 sm:p-6">
                                        <div class="space-y-1">
                                            <div class="text-xs sm:text-sm text-muted-foreground">Expenses</div>
                                            <div class="text-lg sm:text-2xl font-bold">$650.75</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
                                    <div class="p-4 sm:p-6">
                                        <div class="space-y-1">
                                            <div class="text-xs sm:text-sm text-muted-foreground">Savings</div>
                                            <div class="text-lg sm:text-2xl font-bold">$169.75</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
                                <div class="flex flex-col space-y-1.5 p-6 pb-2 pt-4 px-4 sm:pb-4 sm:pt-6 sm:px-6">
                                    <div class="font-medium tracking-tight text-base sm:text-lg">Monthly Spending Trend
                                    </div>
                                </div>
                                <div class="p-6 pt-0 px-2 sm:px-6">
                                    <div class="h-[200px] sm:h-[250px] md:h-[300px]">
                                        <div class="recharts-responsive-container"
                                            style="width: 100%; height: 100%; min-width: 0px;">
                                            <div class="recharts-wrapper"
                                                style="position: relative; cursor: default; width: 100%; height: 100%; max-height: 300px; max-width: 946px;">
                                                <svg class="recharts-surface" width="946" height="300"
                                                    viewBox="0 0 946 300" style="width: 100%; height: 100%;">
                                                    <title></title>
                                                    <desc></desc>
                                                    <defs>
                                                        <clipPath id="recharts125-clip">
                                                            <rect x="40" y="5" height="265" width="896"></rect>
                                                        </clipPath>
                                                    </defs>
                                                    <defs>
                                                        <linearGradient id="colorSpending" x1="0" y1="0" x2="0" y2="1">
                                                            <stop offset="5%" stop-color="#4F46E5" stop-opacity="0.1">
                                                            </stop>
                                                            <stop offset="95%" stop-color="#4F46E5" stop-opacity="0">
                                                            </stop>
                                                        </linearGradient>
                                                    </defs>
                                                    <g
                                                        class="recharts-layer recharts-cartesian-axis recharts-xAxis xAxis">
                                                        <g class="recharts-cartesian-axis-ticks">
                                                            <g class="recharts-layer recharts-cartesian-axis-tick"><text
                                                                    orientation="bottom" width="896" height="30"
                                                                    stroke="none" font-size="10" x="40" y="288"
                                                                    class="recharts-text recharts-cartesian-axis-tick-value"
                                                                    text-anchor="middle" fill="#6B7280">
                                                                    <tspan x="40" dy="0.71em">Jan</tspan>
                                                                </text></g>
                                                            <g class="recharts-layer recharts-cartesian-axis-tick"><text
                                                                    orientation="bottom" width="896" height="30"
                                                                    stroke="none" font-size="10" x="121.45454545454545"
                                                                    y="288"
                                                                    class="recharts-text recharts-cartesian-axis-tick-value"
                                                                    text-anchor="middle" fill="#6B7280">
                                                                    <tspan x="121.45454545454545" dy="0.71em">Feb
                                                                    </tspan>
                                                                </text></g>
                                                            <g class="recharts-layer recharts-cartesian-axis-tick"><text
                                                                    orientation="bottom" width="896" height="30"
                                                                    stroke="none" font-size="10" x="202.9090909090909"
                                                                    y="288"
                                                                    class="recharts-text recharts-cartesian-axis-tick-value"
                                                                    text-anchor="middle" fill="#6B7280">
                                                                    <tspan x="202.9090909090909" dy="0.71em">Mar</tspan>
                                                                </text></g>
                                                            <g class="recharts-layer recharts-cartesian-axis-tick"><text
                                                                    orientation="bottom" width="896" height="30"
                                                                    stroke="none" font-size="10" x="284.3636363636364"
                                                                    y="288"
                                                                    class="recharts-text recharts-cartesian-axis-tick-value"
                                                                    text-anchor="middle" fill="#6B7280">
                                                                    <tspan x="284.3636363636364" dy="0.71em">Apr</tspan>
                                                                </text></g>
                                                            <g class="recharts-layer recharts-cartesian-axis-tick"><text
                                                                    orientation="bottom" width="896" height="30"
                                                                    stroke="none" font-size="10" x="365.8181818181818"
                                                                    y="288"
                                                                    class="recharts-text recharts-cartesian-axis-tick-value"
                                                                    text-anchor="middle" fill="#6B7280">
                                                                    <tspan x="365.8181818181818" dy="0.71em">May</tspan>
                                                                </text></g>
                                                            <g class="recharts-layer recharts-cartesian-axis-tick"><text
                                                                    orientation="bottom" width="896" height="30"
                                                                    stroke="none" font-size="10" x="447.27272727272725"
                                                                    y="288"
                                                                    class="recharts-text recharts-cartesian-axis-tick-value"
                                                                    text-anchor="middle" fill="#6B7280">
                                                                    <tspan x="447.27272727272725" dy="0.71em">Jun
                                                                    </tspan>
                                                                </text></g>
                                                            <g class="recharts-layer recharts-cartesian-axis-tick"><text
                                                                    orientation="bottom" width="896" height="30"
                                                                    stroke="none" font-size="10" x="528.7272727272727"
                                                                    y="288"
                                                                    class="recharts-text recharts-cartesian-axis-tick-value"
                                                                    text-anchor="middle" fill="#6B7280">
                                                                    <tspan x="528.7272727272727" dy="0.71em">Jul</tspan>
                                                                </text></g>
                                                            <g class="recharts-layer recharts-cartesian-axis-tick"><text
                                                                    orientation="bottom" width="896" height="30"
                                                                    stroke="none" font-size="10" x="610.1818181818181"
                                                                    y="288"
                                                                    class="recharts-text recharts-cartesian-axis-tick-value"
                                                                    text-anchor="middle" fill="#6B7280">
                                                                    <tspan x="610.1818181818181" dy="0.71em">Aug</tspan>
                                                                </text></g>
                                                            <g class="recharts-layer recharts-cartesian-axis-tick"><text
                                                                    orientation="bottom" width="896" height="30"
                                                                    stroke="none" font-size="10" x="691.6363636363636"
                                                                    y="288"
                                                                    class="recharts-text recharts-cartesian-axis-tick-value"
                                                                    text-anchor="middle" fill="#6B7280">
                                                                    <tspan x="691.6363636363636" dy="0.71em">Sep</tspan>
                                                                </text></g>
                                                            <g class="recharts-layer recharts-cartesian-axis-tick"><text
                                                                    orientation="bottom" width="896" height="30"
                                                                    stroke="none" font-size="10" x="773.0909090909091"
                                                                    y="288"
                                                                    class="recharts-text recharts-cartesian-axis-tick-value"
                                                                    text-anchor="middle" fill="#6B7280">
                                                                    <tspan x="773.0909090909091" dy="0.71em">Oct</tspan>
                                                                </text></g>
                                                            <g class="recharts-layer recharts-cartesian-axis-tick"><text
                                                                    orientation="bottom" width="896" height="30"
                                                                    stroke="none" font-size="10" x="854.5454545454545"
                                                                    y="288"
                                                                    class="recharts-text recharts-cartesian-axis-tick-value"
                                                                    text-anchor="middle" fill="#6B7280">
                                                                    <tspan x="854.5454545454545" dy="0.71em">Nov</tspan>
                                                                </text></g>
                                                            <g class="recharts-layer recharts-cartesian-axis-tick"><text
                                                                    orientation="bottom" width="896" height="30"
                                                                    stroke="none" font-size="10" x="936" y="288"
                                                                    class="recharts-text recharts-cartesian-axis-tick-value"
                                                                    text-anchor="middle" fill="#6B7280">
                                                                    <tspan x="936" dy="0.71em">Dec</tspan>
                                                                </text></g>
                                                        </g>
                                                    </g>
                                                    <g
                                                        class="recharts-layer recharts-cartesian-axis recharts-yAxis yAxis">
                                                        <g class="recharts-cartesian-axis-ticks">
                                                            <g class="recharts-layer recharts-cartesian-axis-tick"><text
                                                                    orientation="left" width="30" height="265"
                                                                    stroke="none" font-size="10" x="22" y="270"
                                                                    class="recharts-text recharts-cartesian-axis-tick-value"
                                                                    text-anchor="end" fill="#6B7280">
                                                                    <tspan x="22" dy="0.355em">0</tspan>
                                                                </text></g>
                                                            <g class="recharts-layer recharts-cartesian-axis-tick"><text
                                                                    orientation="left" width="30" height="265"
                                                                    stroke="none" font-size="10" x="22" y="203.75"
                                                                    class="recharts-text recharts-cartesian-axis-tick-value"
                                                                    text-anchor="end" fill="#6B7280">
                                                                    <tspan x="22" dy="0.355em">80</tspan>
                                                                </text></g>
                                                            <g class="recharts-layer recharts-cartesian-axis-tick"><text
                                                                    orientation="left" width="30" height="265"
                                                                    stroke="none" font-size="10" x="22" y="137.5"
                                                                    class="recharts-text recharts-cartesian-axis-tick-value"
                                                                    text-anchor="end" fill="#6B7280">
                                                                    <tspan x="22" dy="0.355em">160</tspan>
                                                                </text></g>
                                                            <g class="recharts-layer recharts-cartesian-axis-tick"><text
                                                                    orientation="left" width="30" height="265"
                                                                    stroke="none" font-size="10" x="22" y="71.25"
                                                                    class="recharts-text recharts-cartesian-axis-tick-value"
                                                                    text-anchor="end" fill="#6B7280">
                                                                    <tspan x="22" dy="0.355em">240</tspan>
                                                                </text></g>
                                                            <g class="recharts-layer recharts-cartesian-axis-tick"><text
                                                                    orientation="left" width="30" height="265"
                                                                    stroke="none" font-size="10" x="22" y="7.5"
                                                                    class="recharts-text recharts-cartesian-axis-tick-value"
                                                                    text-anchor="end" fill="#6B7280">
                                                                    <tspan x="22" dy="0.355em">320</tspan>
                                                                </text></g>
                                                        </g>
                                                    </g>
                                                    <g class="recharts-layer recharts-area">
                                                        <g class="recharts-layer">
                                                            <path stroke-width="2" fill="url(#colorSpending)"
                                                                width="896" height="265" fill-opacity="0.6"
                                                                stroke="none" class="recharts-curve recharts-area-area"
                                                                d="M40,228.594C67.152,207.891,94.303,187.188,121.455,187.188C148.606,187.188,175.758,191.328,202.909,191.328C230.061,191.328,257.212,145.781,284.364,145.781C311.515,145.781,338.667,154.063,365.818,154.063C392.97,154.063,420.121,104.375,447.273,104.375C474.424,104.375,501.576,159.583,528.727,170.625C555.879,181.667,583.03,187.188,610.182,187.188C637.333,187.188,664.485,145.781,691.636,145.781C718.788,145.781,745.939,154.063,773.091,154.063C800.242,154.063,827.394,129.219,854.545,104.375C881.697,79.531,908.848,42.266,936,5L936,270C908.848,270,881.697,270,854.545,270C827.394,270,800.242,270,773.091,270C745.939,270,718.788,270,691.636,270C664.485,270,637.333,270,610.182,270C583.03,270,555.879,270,528.727,270C501.576,270,474.424,270,447.273,270C420.121,270,392.97,270,365.818,270C338.667,270,311.515,270,284.364,270C257.212,270,230.061,270,202.909,270C175.758,270,148.606,270,121.455,270C94.303,270,67.152,270,40,270Z">
                                                            </path>
                                                            <path stroke="#4F46E5" stroke-width="2" fill="none"
                                                                width="896" height="265" fill-opacity="0.6"
                                                                class="recharts-curve recharts-area-curve"
                                                                d="M40,228.594C67.152,207.891,94.303,187.188,121.455,187.188C148.606,187.188,175.758,191.328,202.909,191.328C230.061,191.328,257.212,145.781,284.364,145.781C311.515,145.781,338.667,154.063,365.818,154.063C392.97,154.063,420.121,104.375,447.273,104.375C474.424,104.375,501.576,159.583,528.727,170.625C555.879,181.667,583.03,187.188,610.182,187.188C637.333,187.188,664.485,145.781,691.636,145.781C718.788,145.781,745.939,154.063,773.091,154.063C800.242,154.063,827.394,129.219,854.545,104.375C881.697,79.531,908.848,42.266,936,5">
                                                            </path>
                                                        </g>
                                                    </g>
                                                </svg>
                                                <div tabindex="-1" class="recharts-tooltip-wrapper"
                                                    style="visibility: hidden; pointer-events: none; position: absolute; top: 0px; left: 0px;">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
        </main>
    </AppLayout>
</template>
