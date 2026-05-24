<script setup lang="ts">
import { Card, CardContent} from '@/components/ui/card';
import { TrendingUp, TrendingDown, Wallet } from 'lucide-vue-next';

interface Props {
    stats: object
}

const props = defineProps<Props>()
const emit = defineEmits<{
    (e: 'open-income'): void;
    (e: 'open-expenses'): void;
    (e: 'open-coverage'): void;
}>()
</script>

<template>
    <Card class="rounded-md shadow-xl cursor-pointer" @click="emit('open-income')">
        <CardContent class="flex min-h-[112px] items-center gap-3 sm:gap-4">
            <div class="bg-emerald-600/10 p-2 sm:p-3 rounded-full text-primary-foreground">
                <TrendingUp class="h-6 w-6 text-emerald-600" />
            </div>
            <div>
                <p class="text-xs sm:text-sm text-muted-foreground">Inkomsten</p>
                <p class="text-lg sm:text-xl md:text-2xl font-bold">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(stats.income) }}</p>
            </div>
        </CardContent>
    </Card>
    <Card class="rounded-md shadow-xl cursor-pointer" @click="emit('open-expenses')">
        <CardContent class="flex min-h-[112px] items-center gap-3 sm:gap-4">
            <div class="bg-red-600/10 p-2 sm:p-3 rounded-full text-primary-foreground">
                <TrendingDown class="h-6 w-6 text-red-600" />
            </div>
            <div>
                <p class="text-xs sm:text-sm text-muted-foreground">Uitgaven</p>
                <p class="text-lg sm:text-xl md:text-2xl font-bold">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(stats.expenses) }}</p>
            </div>
        </CardContent>
    </Card>
    <Card class="rounded-md shadow-xl cursor-pointer sm:col-span-2 lg:col-span-2" @click="emit('open-coverage')">
        <CardContent class="flex min-h-[112px] items-center gap-4 sm:gap-5">
            <div class="flex min-w-[180px] items-center gap-3 sm:gap-4">
                <div class="bg-indigo-600/10 p-2 sm:p-3 rounded-full text-primary-foreground">
                    <Wallet class="h-6 w-6 text-indigo-500" />
                </div>
                <div>
                    <p class="text-xs sm:text-sm text-muted-foreground">Huidig saldo</p>
                    <p class="text-lg sm:text-xl md:text-2xl font-bold">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(stats.currentBalance) }}</p>
                    <p class="text-[11px] sm:text-xs font-semibold"
                        :class="stats.hasBudgetCoverage ? 'text-emerald-300' : 'text-red-300'">
                        {{ stats.hasBudgetCoverage ? 'Voldoende saldo:' : 'Tekort:' }}
                        {{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(Math.abs(stats.afterBudgets)) }}
                    </p>
                </div>
            </div>

            <div class="flex-1 grid grid-cols-2 gap-2 sm:gap-2.5">
                <div class="rounded-md border border-emerald-500/20 bg-emerald-500/5 px-2 py-1.5 sm:px-2.5 sm:py-2">
                    <p class="text-[11px] sm:text-xs text-muted-foreground">Besteed</p>
                    <p class="text-xs sm:text-sm font-semibold text-emerald-400">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(stats.spentTotal) }}</p>
                </div>
                <div class="rounded-md border border-slate-500/20 bg-slate-500/5 px-2 py-1.5 sm:px-2.5 sm:py-2">
                    <p class="text-[11px] sm:text-xs text-muted-foreground">Gebudgetteerd</p>
                    <p class="text-xs sm:text-sm font-semibold">{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(stats.budgetedTotal) }}</p>
                </div>
                <div class="rounded-md border border-amber-500/20 bg-amber-500/5 px-2 py-1.5 sm:px-2.5 sm:py-2">
                    <p class="text-[11px] sm:text-xs text-muted-foreground">Nog te betalen</p>
                    <p class="text-xs sm:text-sm font-semibold text-amber-300">-{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(stats.toPayBudgets) }}</p>
                </div>
                <div class="rounded-md border border-red-500/20 bg-red-500/5 px-2 py-1.5 sm:px-2.5 sm:py-2">
                    <p class="text-[11px] sm:text-xs text-muted-foreground">Teveel betaald</p>
                    <p class="text-xs sm:text-sm font-semibold text-red-300">-{{ new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }).format(stats.overspentBudgets) }}</p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
