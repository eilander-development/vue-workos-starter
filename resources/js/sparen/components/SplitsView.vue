<script setup lang="ts">
import {
    AlertTriangle,
    CheckCircle2,
    PauseCircle,
    Pencil,
    Plus,
    Split,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useTransactionDetail } from '../composables/useTransactionDetail';
import { formatReportingPeriodLabel, reportingPeriodForMonth } from '../month';
import {
    reviewSplits,
    type SplitReview,
    type SplitVarianceKind,
} from '../splitStatus';
import type { BudgetItem, MonthlyBudget, Rule, Transaction } from '../types';
import SplitEditorModal from './SplitEditorModal.vue';
import TransactionDate from './TransactionDate.vue';

const props = defineProps<{
    rules: Rule[];
    budgetItems: BudgetItem[];
    transactions: Transaction[];
    currentMonth: MonthlyBudget;
    onSave: (rule: Omit<Rule, 'matchedCount'>) => void;
}>();

const { openTransactionDetail } = useTransactionDetail();

const editorOpen = ref(false);
const editingRule = ref<Rule | null>(null);
const useBudgetAmounts = ref(false);

const reviews = computed(() =>
    reviewSplits(
        props.rules,
        props.budgetItems,
        props.transactions,
        props.currentMonth,
    ),
);

const issueCount = computed(
    () => reviews.value.filter((review) => review.hasIssue).length,
);

const periodLabel = computed(() => {
    const period =
        props.currentMonth.periodStart && props.currentMonth.periodEnd
            ? {
                  start: props.currentMonth.periodStart,
                  end: props.currentMonth.periodEnd,
              }
            : reportingPeriodForMonth(props.currentMonth);
    return formatReportingPeriodLabel(period);
});

function openCreate() {
    useBudgetAmounts.value = false;
    editingRule.value = null;
    editorOpen.value = true;
}

function openEdit(rule: Rule, fromBudget = false) {
    useBudgetAmounts.value = fromBudget;
    editingRule.value = rule;
    editorOpen.value = true;
}

function varianceClass(kind: SplitVarianceKind): string {
    if (kind === 'short')
        return 'text-amber-300 bg-amber-950/50 border-amber-800/60';
    if (kind === 'over') return 'text-sky-300 bg-sky-950/40 border-sky-800/60';
    return 'text-emerald-300 bg-emerald-950/40 border-emerald-800/60';
}

function euro(amount: number): string {
    return amount.toLocaleString('nl-NL', { minimumFractionDigits: 2 });
}

function bannerText(review: SplitReview): string {
    if (review.hasBudgetIssue)
        return 'De splitbedragen wijken af van de maandbegroting';
    if (review.transactions.length === 0) {
        return `Geen mutatie deze periode · enveloppen € ${euro(review.plannedTotal)} te kort`;
    }
    if (review.bankVariance.kind === 'short') {
        return `Mutaties dekken de enveloppen niet: ${review.bankVariance.label}`;
    }
    if (review.bankVariance.kind === 'over') {
        return `Mutaties zijn hoger dan de enveloppen: ${review.bankVariance.label}`;
    }
    const envelopeIssue = review.envelopes.find(
        (row) => row.variance.kind !== 'ok',
    );
    if (envelopeIssue) {
        return `${envelopeIssue.name}: ${envelopeIssue.variance.label}`;
    }
    return 'Enveloppen zijn in balans met de mutaties';
}
</script>

<template>
    <div id="splits-view" class="space-y-6">
        <div
            class="flex flex-col justify-between gap-4 rounded-2xl border border-slate-800 bg-slate-900 p-5 shadow-sm md:flex-row md:items-center"
        >
            <div>
                <div class="flex items-center gap-2">
                    <Split class="h-5 w-5 text-indigo-400" />
                    <h2 class="text-xl font-bold tracking-tight text-white">
                        Splits
                    </h2>
                </div>
                <p class="mt-1 max-w-xl text-xs text-slate-400">
                    Eén bankmutatie vult meerdere enveloppen. Over of te kort
                    t.o.v. die enveloppen zie je hier, voor {{ periodLabel }}.
                </p>
            </div>
            <button
                type="button"
                class="flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3.5 py-2 text-xs font-semibold text-white shadow-md shadow-indigo-600/20 transition-all hover:bg-indigo-500 active:scale-95"
                @click="openCreate"
            >
                <Plus class="h-3.5 w-3.5" />
                Nieuwe split
            </button>
        </div>

        <div
            v-if="reviews.length > 0"
            class="px-1 text-xs"
            :class="issueCount > 0 ? 'text-amber-300' : 'text-emerald-300'"
        >
            {{
                issueCount > 0
                    ? `${issueCount} ${issueCount === 1 ? 'split heeft' : 'splits hebben'} over of te kort`
                    : 'Alle splits zijn in balans deze periode'
            }}
        </div>

        <div
            v-if="reviews.length === 0"
            class="space-y-3 rounded-2xl border border-dashed border-slate-800 bg-slate-900 p-10 text-center"
        >
            <Split class="mx-auto h-8 w-8 text-slate-600" />
            <p class="font-semibold text-white">Nog geen splits</p>
            <p class="mx-auto max-w-sm text-xs text-slate-400">
                Voeg enveloppen toe die samen uit één incasso komen, zoals
                InShared auto + woning.
            </p>
            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-indigo-500"
                @click="openCreate"
            >
                <Plus class="h-3.5 w-3.5" />
                Split aanmaken
            </button>
        </div>

        <article
            v-for="review in reviews"
            :key="review.rule.id"
            class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-sm"
        >
            <div class="space-y-4 p-5">
                <div
                    class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-base font-bold text-white">
                                {{ review.rule.name }}
                            </h3>
                            <span
                                v-if="!review.rule.isActive"
                                class="inline-flex items-center gap-1 rounded border border-slate-700 bg-slate-800 px-1.5 py-0.5 text-[10px] text-slate-400"
                            >
                                <PauseCircle class="h-3 w-3" />
                                Gepauzeerd
                            </span>
                        </div>
                        <p class="mt-1 font-mono text-[11px] text-slate-500">
                            {{ review.rule.keyword }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800 px-2.5 py-1.5 text-xs text-indigo-300 hover:bg-slate-700 hover:text-white"
                        @click="openEdit(review.rule)"
                    >
                        <Pencil class="h-3.5 w-3.5" />
                        Enveloppen bewerken
                    </button>
                </div>

                <div
                    class="flex items-start gap-2 rounded-xl border px-3 py-2.5 text-xs"
                    :class="
                        varianceClass(
                            review.hasIssue
                                ? review.bankVariance.kind === 'ok'
                                    ? 'short'
                                    : review.bankVariance.kind
                                : 'ok',
                        )
                    "
                >
                    <AlertTriangle
                        v-if="review.hasIssue"
                        class="mt-0.5 h-4 w-4 shrink-0"
                    />
                    <CheckCircle2 v-else class="mt-0.5 h-4 w-4 shrink-0" />
                    <div>
                        <p class="font-semibold">{{ bannerText(review) }}</p>
                        <p class="mt-0.5 text-[11px] opacity-80">
                            Mutaties € {{ euro(review.bankTotal) }} · enveloppen
                            € {{ euro(review.plannedTotal) }} · gevuld €
                            {{ euro(review.filledTotal) }}
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr
                                class="text-[10px] tracking-wide text-slate-500 uppercase"
                            >
                                <th class="pb-2 font-semibold">Envelop</th>
                                <th class="pb-2 text-right font-semibold">
                                    Begroot {{ currentMonth.monthName }}
                                </th>
                                <th class="pb-2 text-right font-semibold">
                                    Splitbedrag
                                </th>
                                <th class="pb-2 text-right font-semibold">
                                    Gevuld
                                </th>
                                <th class="pb-2 text-right font-semibold">
                                    Gevuld − begroot
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="envelope in review.envelopes"
                                :key="envelope.budgetItemId"
                                class="border-t border-slate-800"
                            >
                                <td class="py-2.5 pr-3">
                                    <p class="font-medium text-white">
                                        {{ envelope.name }}
                                    </p>
                                    <p
                                        v-if="envelope.group"
                                        class="text-[10px] text-slate-500"
                                    >
                                        {{ envelope.group }}
                                    </p>
                                </td>
                                <td
                                    class="py-2.5 text-right font-mono text-indigo-200"
                                >
                                    {{
                                        envelope.budgeted === null
                                            ? 'Post ontbreekt'
                                            : `€ ${euro(envelope.budgeted)}`
                                    }}
                                </td>
                                <td
                                    class="py-2.5 text-right font-mono text-slate-300"
                                >
                                    € {{ euro(envelope.planned)
                                    }}<span
                                        v-if="
                                            envelope.budgetVariance &&
                                            envelope.budgetVariance.kind !==
                                                'ok'
                                        "
                                        class="mt-1 block text-[10px] text-amber-300"
                                        >{{
                                            envelope.budgetVariance.label
                                        }}
                                        t.o.v. begroot</span
                                    >
                                </td>
                                <td
                                    class="py-2.5 text-right font-mono text-white"
                                >
                                    € {{ euro(envelope.filled) }}
                                </td>
                                <td class="py-2.5 text-right">
                                    <span
                                        class="inline-flex rounded border px-1.5 py-0.5 text-[11px] font-medium"
                                        :class="
                                            varianceClass(
                                                envelope.filledBudgetVariance
                                                    ?.kind ?? 'short',
                                            )
                                        "
                                    >
                                        {{
                                            envelope.filledBudgetVariance
                                                ?.label ?? 'Post ontbreekt'
                                        }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="review.hasBudgetIssue"
                    class="space-y-2 rounded-xl border border-amber-500/30 bg-amber-500/5 p-3 text-xs text-amber-200"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <p>
                            Begroting {{ currentMonth.monthName }}
                            {{ currentMonth.year }}:
                            <strong>{{
                                review.budgetedTotal === null
                                    ? 'onvolledig'
                                    : `€ ${euro(review.budgetedTotal)}`
                            }}</strong>
                            · splitbedragen:
                            <strong>€ {{ euro(review.plannedTotal) }}</strong
                            >.
                        </p>
                        <button
                            type="button"
                            class="rounded-lg border border-indigo-500/40 bg-indigo-500/15 px-3 py-2 font-semibold text-indigo-200 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!review.canAdoptBudget"
                            :aria-label="`Begroting overnemen voor ${review.rule.name}`"
                            @click="openEdit(review.rule, true)"
                        >
                            Begroting overnemen
                        </button>
                    </div>
                    <p class="text-[11px] text-slate-400">
                        Neem de begrote bedragen over als verdeelsleutel.
                        Controleer ze in de editor en sla de split op. Het
                        bankbedrag blijft gelijk.
                    </p>
                    <p v-if="!review.canAdoptBudget" class="text-[11px]">
                        Overnemen kan wanneer alle enveloppen een bestaand,
                        positief begrotingsbedrag hebben. Controleer ontbrekende
                        posten of maanden met €0.
                    </p>
                </div>

                <div>
                    <h4
                        class="mb-2 text-[11px] font-bold tracking-wide text-slate-500 uppercase"
                    >
                        Mutaties deze periode
                    </h4>
                    <p
                        v-if="review.transactions.length === 0"
                        class="text-xs text-slate-500"
                    >
                        Geen herkende mutatie in {{ currentMonth.monthName }}.
                    </p>
                    <ul v-else class="space-y-1.5">
                        <li
                            v-for="row in review.transactions"
                            :key="row.tx.id"
                            class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-slate-700/60 bg-slate-800/50 px-3 py-2 hover:bg-slate-800"
                            title="Bekijk alle gegevens"
                            @click="openTransactionDetail(row.tx)"
                        >
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <TransactionDate
                                        :date="row.tx.date"
                                        :time="row.tx.time"
                                        size="sm"
                                    />
                                    <span class="truncate text-xs text-white">{{
                                        row.tx.description
                                    }}</span>
                                </div>
                                <p
                                    class="mt-0.5 text-[10px]"
                                    :class="
                                        row.variance.kind === 'ok'
                                            ? 'text-slate-500'
                                            : 'text-amber-300'
                                    "
                                >
                                    Verdeeld € {{ euro(row.allocated) }}
                                    <span v-if="row.variance.kind !== 'ok'">
                                        · {{ row.variance.label }}</span
                                    >
                                </p>
                            </div>
                            <span
                                class="shrink-0 font-mono font-bold text-rose-400"
                            >
                                −€ {{ euro(row.amount) }}
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </article>

        <SplitEditorModal
            :is-open="editorOpen"
            :editing-rule="editingRule"
            :budget-items="budgetItems"
            :use-budget-amounts="useBudgetAmounts"
            :budget-month-label="`${currentMonth.monthName} ${currentMonth.year}`"
            :on-close="() => (editorOpen = false)"
            :on-save="onSave"
        />
    </div>
</template>
