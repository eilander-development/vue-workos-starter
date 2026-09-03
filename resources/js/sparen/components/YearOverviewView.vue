<script setup lang="ts">
import { computed, ref } from "vue";
import { Calendar } from "lucide-vue-next";
import type { MonthlyBudget, SavingsGoal, Transaction } from "../types";
import { kpiFromMonthlyBudget } from "../monthKpi";
import { computePeriodCashflow, monthEndBalances } from "../cashflow";
import { defaultReportingMonth } from "../month";

const props = withDefaults(
  defineProps<{
    allMonths: MonthlyBudget[];
    transactions?: Transaction[];
    savingsGoals?: SavingsGoal[];
    bankBalance?: number;
    onSelectMonth: (monthId: string) => void;
  }>(),
  {
    transactions: () => [],
    savingsGoals: () => [],
    bankBalance: 0,
  }
);

const lens = ref<"plan" | "bank">("bank");
const reportingAnchor = computed(() => defaultReportingMonth());

const endBalances = computed(() =>
  monthEndBalances(props.allMonths, reportingAnchor.value, props.bankBalance, props.transactions)
);

const monthData = computed(() =>
  props.allMonths.map((m, index) => {
    const kpi = kpiFromMonthlyBudget(m, m.opRekening ?? 0);
    const flow = computePeriodCashflow(props.transactions, m, props.savingsGoals);
    const end = endBalances.value[index];
    return {
      monthId: m.monthId,
      name: m.monthName,
      saldo: end?.balance ?? m.opRekening,
      income: lens.value === "plan" ? kpi.totalIncomeBudget : flow.received,
      expense: lens.value === "plan" ? kpi.totalExpenseBudget : flow.spent,
      savings: lens.value === "plan" ? kpi.totalSavingsBudget : flow.netSavings,
      net:
        lens.value === "plan"
          ? kpi.netBudget
          : flow.netFromAccount,
    };
  })
);

const totalYearIncome = computed(() => monthData.value.reduce((s, m) => s + m.income, 0));
const totalYearExpense = computed(() => monthData.value.reduce((s, m) => s + m.expense, 0));
const totalYearSavings = computed(() => monthData.value.reduce((s, m) => s + m.savings, 0));
const totalYearNet = computed(() => monthData.value.reduce((s, m) => s + m.net, 0));

function euro(n: number): string {
  return n.toLocaleString("nl-NL", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<template>
  <div id="year-overview-view" class="space-y-6">
    <div
      class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4"
    >
      <div>
        <div class="flex items-center gap-2">
          <Calendar class="w-5 h-5 text-indigo-400" />
          <h2 class="text-xl font-bold text-white tracking-tight">
            Jaaroverzicht {{ allMonths[0]?.year ?? "" }}
          </h2>
        </div>
        <p class="text-xs text-slate-400 mt-1">
          {{
            lens === "plan"
              ? "Plan: begroot per maand, dezelfde lens als maandbegroting"
              : "Bank: echte in/uit en netto sparen, dezelfde lens als dashboard-cashflow"
          }}
        </p>
      </div>
      <div class="flex items-center gap-1 bg-slate-800 p-1 rounded-xl border border-slate-700">
        <button
          type="button"
          class="text-xs px-3 py-1 rounded-lg font-medium transition-colors"
          :class="lens === 'plan' ? 'bg-slate-600 text-white' : 'text-slate-400 hover:text-white'"
          @click="lens = 'plan'"
        >
          Plan
        </button>
        <button
          type="button"
          class="text-xs px-3 py-1 rounded-lg font-medium transition-colors"
          :class="lens === 'bank' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white'"
          @click="lens = 'bank'"
        >
          Bank
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl">
        <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider block">
          {{ lens === "plan" ? "Jaar inkomsten (begroot)" : "Jaar echt ontvangen" }}
        </span>
        <div class="text-2xl font-black text-emerald-400 font-mono mt-2">
          € {{ euro(totalYearIncome) }}
        </div>
      </div>
      <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl">
        <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider block">
          {{ lens === "plan" ? "Jaar uitgaven (begroot)" : "Jaar echt uitgegeven" }}
        </span>
        <div class="text-2xl font-black text-rose-400 font-mono mt-2">
          € {{ euro(totalYearExpense) }}
        </div>
      </div>
      <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl">
        <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider block">
          {{ lens === "plan" ? "Jaar spaarreservering" : "Jaar netto gespaard" }}
        </span>
        <div class="text-2xl font-black text-blue-400 font-mono mt-2">
          € {{ euro(totalYearSavings) }}
        </div>
      </div>
      <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl">
        <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider block">
          {{ lens === "plan" ? "Netto jaaroverschot (plan)" : "Netto van de rekening" }}
        </span>
        <div
          class="text-2xl font-black font-mono mt-2"
          :class="totalYearNet >= 0 ? 'text-emerald-400' : 'text-rose-400'"
        >
          € {{ euro(totalYearNet) }}
        </div>
      </div>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs font-mono">
          <thead>
            <tr class="border-b border-slate-800 bg-slate-950/40 text-slate-400 font-sans font-semibold">
              <th class="py-3 px-4">Maand</th>
              <th class="py-3 px-4 text-right">Saldo eind</th>
              <th class="py-3 px-4 text-right text-emerald-400">Inkomsten</th>
              <th class="py-3 px-4 text-right text-rose-400">Uitgaven</th>
              <th class="py-3 px-4 text-right text-blue-400">
                {{ lens === "plan" ? "Sparen" : "Netto spaar" }}
              </th>
              <th class="py-3 px-4 text-right font-bold text-white">Netto</th>
              <th class="py-3 px-4 text-center font-sans">Actie</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr
              v-for="m in monthData"
              :key="m.monthId"
              class="hover:bg-slate-800/40 transition-colors"
            >
              <td class="py-3 px-4 font-sans font-bold text-white">{{ m.name }}</td>
              <td class="py-3 px-4 text-right text-slate-300">€ {{ euro(m.saldo) }}</td>
              <td class="py-3 px-4 text-right text-emerald-400 font-semibold">€ {{ euro(m.income) }}</td>
              <td class="py-3 px-4 text-right text-rose-400 font-semibold">€ {{ euro(m.expense) }}</td>
              <td class="py-3 px-4 text-right text-blue-400">€ {{ euro(m.savings) }}</td>
              <td
                class="py-3 px-4 text-right font-bold"
                :class="m.net >= 0 ? 'text-emerald-400' : 'text-rose-400'"
              >
                {{ m.net >= 0 ? "+" : "" }}€ {{ euro(m.net) }}
              </td>
              <td class="py-3 px-4 text-center font-sans">
                <button
                  type="button"
                  class="text-xs bg-slate-800 hover:bg-indigo-600 text-slate-300 hover:text-white px-2.5 py-1 rounded-lg border border-slate-700 transition-colors"
                  @click="onSelectMonth(m.monthId)"
                >
                  Bekijk Maand
                </button>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-slate-950/60 border-t-2 border-slate-700 font-bold text-white">
              <td class="py-3.5 px-4 font-sans text-sm">Jaartotaal</td>
              <td class="py-3.5 px-4 text-right text-slate-400">-</td>
              <td class="py-3.5 px-4 text-right text-emerald-400 text-sm">€ {{ euro(totalYearIncome) }}</td>
              <td class="py-3.5 px-4 text-right text-rose-400 text-sm">€ {{ euro(totalYearExpense) }}</td>
              <td class="py-3.5 px-4 text-right text-blue-400 text-sm">€ {{ euro(totalYearSavings) }}</td>
              <td class="py-3.5 px-4 text-right text-emerald-400 text-sm">€ {{ euro(totalYearNet) }}</td>
              <td class="py-3.5 px-4"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</template>
