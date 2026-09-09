<script setup lang="ts">
import { computed, ref } from "vue";
import {
  Split,
  Plus,
  Pencil,
  AlertTriangle,
  CheckCircle2,
  PauseCircle,
} from "lucide-vue-next";
import type { BudgetItem, MonthlyBudget, Rule, Transaction } from "../types";
import { formatReportingPeriodLabel, reportingPeriodForMonth } from "../month";
import { reviewSplits, type SplitReview, type SplitVarianceKind } from "../splitStatus";
import TransactionDate from "./TransactionDate.vue";
import SplitEditorModal from "./SplitEditorModal.vue";
import { useTransactionDetail } from "../composables/useTransactionDetail";

const props = defineProps<{
  rules: Rule[];
  budgetItems: BudgetItem[];
  transactions: Transaction[];
  currentMonth: MonthlyBudget;
  onSave: (rule: Omit<Rule, "matchedCount">) => void;
}>();

const { openTransactionDetail } = useTransactionDetail();

const editorOpen = ref(false);
const editingRule = ref<Rule | null>(null);

const reviews = computed(() =>
  reviewSplits(props.rules, props.budgetItems, props.transactions, props.currentMonth)
);

const issueCount = computed(() => reviews.value.filter((review) => review.hasIssue).length);

const periodLabel = computed(() => {
  const period =
    props.currentMonth.periodStart && props.currentMonth.periodEnd
      ? { start: props.currentMonth.periodStart, end: props.currentMonth.periodEnd }
      : reportingPeriodForMonth(props.currentMonth);
  return formatReportingPeriodLabel(period);
});

function openCreate() {
  editingRule.value = null;
  editorOpen.value = true;
}

function openEdit(rule: Rule) {
  editingRule.value = rule;
  editorOpen.value = true;
}

function varianceClass(kind: SplitVarianceKind): string {
  if (kind === "short") return "text-amber-300 bg-amber-950/50 border-amber-800/60";
  if (kind === "over") return "text-sky-300 bg-sky-950/40 border-sky-800/60";
  return "text-emerald-300 bg-emerald-950/40 border-emerald-800/60";
}

function euro(amount: number): string {
  return amount.toLocaleString("nl-NL", { minimumFractionDigits: 2 });
}

function bannerText(review: SplitReview): string {
  if (review.transactions.length === 0) {
    return `Geen mutatie deze periode · enveloppen € ${euro(review.plannedTotal)} te kort`;
  }
  if (review.bankVariance.kind === "short") {
    return `Mutaties dekken de enveloppen niet: ${review.bankVariance.label}`;
  }
  if (review.bankVariance.kind === "over") {
    return `Mutaties zijn hoger dan de enveloppen: ${review.bankVariance.label}`;
  }
  const envelopeIssue = review.envelopes.find((row) => row.variance.kind !== "ok");
  if (envelopeIssue) {
    return `${envelopeIssue.name}: ${envelopeIssue.variance.label}`;
  }
  return "Enveloppen zijn in balans met de mutaties";
}
</script>

<template>
  <div id="splits-view" class="space-y-6">
    <div
      class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4"
    >
      <div>
        <div class="flex items-center gap-2">
          <Split class="w-5 h-5 text-indigo-400" />
          <h2 class="text-xl font-bold text-white tracking-tight">Splits</h2>
        </div>
        <p class="text-xs text-slate-400 mt-1 max-w-xl">
          Eén bankmutatie vult meerdere enveloppen. Over of te kort t.o.v. die enveloppen zie je hier,
          voor {{ periodLabel }}.
        </p>
      </div>
      <button
        type="button"
        class="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-500 text-white px-3.5 py-2 rounded-xl text-xs font-semibold shadow-md shadow-indigo-600/20 transition-all active:scale-95"
        @click="openCreate"
      >
        <Plus class="w-3.5 h-3.5" />
        Nieuwe split
      </button>
    </div>

    <div
      v-if="reviews.length > 0"
      class="text-xs px-1"
      :class="issueCount > 0 ? 'text-amber-300' : 'text-emerald-300'"
    >
      {{
        issueCount > 0
          ? `${issueCount} ${issueCount === 1 ? "split heeft" : "splits hebben"} over of te kort`
          : "Alle splits zijn in balans deze periode"
      }}
    </div>

    <div
      v-if="reviews.length === 0"
      class="bg-slate-900 border border-dashed border-slate-800 rounded-2xl p-10 text-center space-y-3"
    >
      <Split class="w-8 h-8 text-slate-600 mx-auto" />
      <p class="text-white font-semibold">Nog geen splits</p>
      <p class="text-xs text-slate-400 max-w-sm mx-auto">
        Voeg enveloppen toe die samen uit één incasso komen, zoals InShared auto + woning.
      </p>
      <button
        type="button"
        class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-500 text-white px-3.5 py-2 rounded-xl text-xs font-semibold"
        @click="openCreate"
      >
        <Plus class="w-3.5 h-3.5" />
        Split aanmaken
      </button>
    </div>

    <article
      v-for="review in reviews"
      :key="review.rule.id"
      class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm"
    >
      <div class="p-5 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <h3 class="text-base font-bold text-white">{{ review.rule.name }}</h3>
              <span
                v-if="!review.rule.isActive"
                class="inline-flex items-center gap-1 text-[10px] text-slate-400 bg-slate-800 border border-slate-700 px-1.5 py-0.5 rounded"
              >
                <PauseCircle class="w-3 h-3" />
                Gepauzeerd
              </span>
            </div>
            <p class="text-[11px] text-slate-500 mt-1 font-mono">
              {{ review.rule.keyword }}
            </p>
          </div>
          <button
            type="button"
            class="inline-flex items-center gap-1.5 text-xs text-indigo-300 hover:text-white bg-slate-800 hover:bg-slate-700 border border-slate-700 px-2.5 py-1.5 rounded-xl"
            @click="openEdit(review.rule)"
          >
            <Pencil class="w-3.5 h-3.5" />
            Enveloppen bewerken
          </button>
        </div>

        <div
          class="flex items-start gap-2 text-xs border rounded-xl px-3 py-2.5"
          :class="varianceClass(review.hasIssue ? (review.bankVariance.kind === 'ok' ? 'short' : review.bankVariance.kind) : 'ok')"
        >
          <AlertTriangle v-if="review.hasIssue" class="w-4 h-4 shrink-0 mt-0.5" />
          <CheckCircle2 v-else class="w-4 h-4 shrink-0 mt-0.5" />
          <div>
            <p class="font-semibold">{{ bannerText(review) }}</p>
            <p class="text-[11px] opacity-80 mt-0.5">
              Mutaties € {{ euro(review.bankTotal) }}
              · enveloppen € {{ euro(review.plannedTotal) }}
              · gevuld € {{ euro(review.filledTotal) }}
            </p>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-[10px] uppercase tracking-wide text-slate-500">
                <th class="pb-2 font-semibold">Envelop</th>
                <th class="pb-2 font-semibold text-right">Begroot in split</th>
                <th class="pb-2 font-semibold text-right">Gevuld</th>
                <th class="pb-2 font-semibold text-right">Verschil</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="envelope in review.envelopes" :key="envelope.budgetItemId" class="border-t border-slate-800">
                <td class="py-2.5 pr-3">
                  <p class="text-white font-medium">{{ envelope.name }}</p>
                  <p v-if="envelope.group" class="text-[10px] text-slate-500">{{ envelope.group }}</p>
                </td>
                <td class="py-2.5 text-right font-mono text-slate-300">€ {{ euro(envelope.planned) }}</td>
                <td class="py-2.5 text-right font-mono text-white">€ {{ euro(envelope.filled) }}</td>
                <td class="py-2.5 text-right">
                  <span
                    class="inline-flex font-medium text-[11px] px-1.5 py-0.5 rounded border"
                    :class="varianceClass(envelope.variance.kind)"
                  >
                    {{ envelope.variance.label }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div>
          <h4 class="text-[11px] font-bold uppercase tracking-wide text-slate-500 mb-2">
            Mutaties deze periode
          </h4>
          <p v-if="review.transactions.length === 0" class="text-xs text-slate-500">
            Geen herkende mutatie in {{ currentMonth.monthName }}.
          </p>
          <ul v-else class="space-y-1.5">
            <li
              v-for="row in review.transactions"
              :key="row.tx.id"
              class="bg-slate-800/50 border border-slate-700/60 rounded-xl px-3 py-2 flex items-center justify-between gap-3 cursor-pointer hover:bg-slate-800"
              title="Bekijk alle gegevens"
              @click="openTransactionDetail(row.tx)"
            >
              <div class="min-w-0">
                <div class="flex items-center gap-2">
                  <TransactionDate :date="row.tx.date" :time="row.tx.time" size="sm" />
                  <span class="text-white truncate text-xs">{{ row.tx.description }}</span>
                </div>
                <p class="text-[10px] mt-0.5" :class="row.variance.kind === 'ok' ? 'text-slate-500' : 'text-amber-300'">
                  Verdeeld € {{ euro(row.allocated) }}
                  <span v-if="row.variance.kind !== 'ok'"> · {{ row.variance.label }}</span>
                </p>
              </div>
              <span class="font-mono font-bold text-rose-400 shrink-0">
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
      :on-close="() => (editorOpen = false)"
      :on-save="onSave"
    />
  </div>
</template>
