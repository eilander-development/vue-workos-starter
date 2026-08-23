<script setup lang="ts">
import { computed } from "vue";
import { Calendar } from "lucide-vue-next";
import type { MonthlyBudget } from "../types";

const props = defineProps<{
  allMonths: MonthlyBudget[];
  onSelectMonth: (monthId: string) => void;
}>();

const monthData = computed(() =>
  props.allMonths.map((m) => {
    const incomeBudget = m.items
      .filter((i) => i.type === "inkomsten")
      .reduce((s, i) => s + (i.actual ?? i.estimated ?? 0), 0);
    const expenseBudget = m.items
      .filter((i) => i.type === "uitgaven")
      .reduce((s, i) => s + (i.actual ?? i.estimated ?? 0), 0);
    const savingsBudget = m.items
      .filter((i) => i.type === "sparen")
      .reduce((s, i) => s + (i.actual ?? i.estimated ?? 0), 0);
    const income = m.items
      .filter((i) => i.type === "inkomsten")
      .reduce((s, i) => s + Number(i.paidOrReceived ?? 0), 0);
    const expense = m.items
      .filter((i) => i.type === "uitgaven")
      .reduce((s, i) => s + Number(i.paidOrReceived ?? 0), 0);
    const savings = m.items
      .filter((i) => i.type === "sparen")
      .reduce((s, i) => s + Number(i.paidOrReceived ?? 0), 0);
    const net = income - expense - savings;
    return {
      monthId: m.monthId,
      name: m.monthName,
      opRekening: m.opRekening,
      income,
      expense,
      savings,
      net,
      incomeBudget,
      expenseBudget,
      savingsBudget,
    };
  })
);

const totalYearIncome = computed(() => monthData.value.reduce((s, m) => s + m.income, 0));
const totalYearExpense = computed(() => monthData.value.reduce((s, m) => s + m.expense, 0));
const totalYearSavings = computed(() => monthData.value.reduce((s, m) => s + m.savings, 0));
const totalYearNet = computed(
  () => totalYearIncome.value - totalYearExpense.value - totalYearSavings.value
);
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
            Jaaroverzicht Begroting 2026 (12 Maanden Matrix)
          </h2>
        </div>
        <p class="text-xs text-slate-400 mt-1">
          Werkelijke bankmutaties per maand. Begrote bedragen staan in de maandbegroting; die zijn nog niet
          per maand ingevuld.
        </p>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl">
        <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider block">Jaar Inkomsten</span>
        <div class="text-2xl font-black text-emerald-400 font-mono mt-2">
          € {{ totalYearIncome.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
        </div>
      </div>
      <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl">
        <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider block">Jaar Uitgaven</span>
        <div class="text-2xl font-black text-rose-400 font-mono mt-2">
          € {{ totalYearExpense.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
        </div>
      </div>
      <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl">
        <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider block">
          Jaar Spaarreservering
        </span>
        <div class="text-2xl font-black text-blue-400 font-mono mt-2">
          € {{ totalYearSavings.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
        </div>
      </div>
      <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl">
        <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider block">
          Netto Jaaroverschot
        </span>
        <div class="text-2xl font-black text-emerald-400 font-mono mt-2">
          € {{ totalYearNet.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
        </div>
      </div>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs font-mono">
          <thead>
            <tr class="border-b border-slate-800 bg-slate-950/40 text-slate-400 font-sans font-semibold">
              <th class="py-3 px-4">Maand</th>
              <th class="py-3 px-4 text-right">Op rekening</th>
              <th class="py-3 px-4 text-right text-emerald-400">Inkomsten</th>
              <th class="py-3 px-4 text-right text-rose-400">Uitgaven</th>
              <th class="py-3 px-4 text-right text-blue-400">Sparen</th>
              <th class="py-3 px-4 text-right font-bold text-white">Netto Saldo</th>
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
              <td class="py-3 px-4 text-right text-slate-300">
                € {{ m.opRekening.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </td>
              <td class="py-3 px-4 text-right text-emerald-400 font-semibold">
                € {{ m.income.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </td>
              <td class="py-3 px-4 text-right text-rose-400 font-semibold">
                € {{ m.expense.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </td>
              <td class="py-3 px-4 text-right text-blue-400">
                € {{ m.savings.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </td>
              <td
                class="py-3 px-4 text-right font-bold"
                :class="m.net >= 0 ? 'text-emerald-400' : 'text-rose-400'"
              >
                {{ m.net >= 0 ? "+" : "" }}€
                {{ m.net.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
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
              <td class="py-3.5 px-4 font-sans text-sm">JAARTOTAAL 2026</td>
              <td class="py-3.5 px-4 text-right text-slate-400">-</td>
              <td class="py-3.5 px-4 text-right text-emerald-400 text-sm">
                € {{ totalYearIncome.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </td>
              <td class="py-3.5 px-4 text-right text-rose-400 text-sm">
                € {{ totalYearExpense.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </td>
              <td class="py-3.5 px-4 text-right text-blue-400 text-sm">
                € {{ totalYearSavings.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </td>
              <td class="py-3.5 px-4 text-right text-emerald-400 text-sm">
                € {{ totalYearNet.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </td>
              <td class="py-3.5 px-4"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</template>
