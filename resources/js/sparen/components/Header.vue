<script setup lang="ts">
import { computed } from "vue";
import {
  Calendar,
  RefreshCw,
  Plus,
  ArrowDownCircle,
  Wifi,
  Menu,
  Cloud,
  CloudOff,
  CheckCircle2,
} from "lucide-vue-next";
import type { Component } from "vue";
import type { MonthlyBudget, BankAccount, SaveState } from "../types";
import { formatReportingPeriodShort, reportingPeriodForMonth } from "../month";

const props = withDefaults(
  defineProps<{
    currentMonth: MonthlyBudget;
    allMonths: MonthlyBudget[];
    reportingPeriodLabel: string;
    bankAccount: BankAccount;
    isSyncing: boolean;
    saveState?: SaveState;
    onOpenMobileMenu?: () => void;
  }>(),
  {
    saveState: "idle",
  }
);

const emit = defineEmits<{
  selectMonth: [monthId: string];
  sync: [];
  openAddTransaction: [];
  openAddBudgetItem: [];
}>();

const SAVE_LABELS: Record<
  Exclude<SaveState, "idle">,
  { text: string; className: string; Icon: Component }
> = {
  saving: {
    text: "Opslaan…",
    className: "text-indigo-300 border-indigo-800/60 bg-indigo-950/50",
    Icon: Cloud,
  },
  saved: {
    text: "Opgeslagen",
    className: "text-emerald-400 border-emerald-800/60 bg-emerald-950/40",
    Icon: CheckCircle2,
  },
  error: {
    text: "Niet opgeslagen",
    className: "text-rose-400 border-rose-800/60 bg-rose-950/40",
    Icon: CloudOff,
  },
};

const save = computed(() =>
  props.saveState === "idle" ? null : SAVE_LABELS[props.saveState]
);

const quickMonthIds = ["mei", "jun", "jul", "aug", "sep", "okt"];

function monthById(mId: string) {
  return props.allMonths.find((x) => x.monthId === mId);
}

function monthShortName(mId: string) {
  return monthById(mId)?.monthName.slice(0, 3) ?? "";
}

function monthPeriodShort(mId: string) {
  const month = monthById(mId);
  if (!month) {
    return "";
  }

  const period =
    month.periodStart && month.periodEnd
      ? { start: month.periodStart, end: month.periodEnd }
      : reportingPeriodForMonth(month);

  return formatReportingPeriodShort(period);
}

function onMonthSelectChange(event: Event) {
  emit("selectMonth", (event.target as HTMLSelectElement).value);
}
</script>

<template>
  <header
    id="app-header"
    class="bg-slate-900/90 backdrop-blur-md border-b border-slate-800 sticky top-0 z-20 px-3 sm:px-6 py-2.5 sm:py-3.5 flex items-center justify-between gap-2 sm:gap-4"
  >
    <div class="flex items-center gap-2 sm:gap-3">
      <button
        v-if="onOpenMobileMenu"
        type="button"
        class="md:hidden p-2 rounded-xl bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 border border-slate-700 transition-colors"
        title="Open navigatiemenu"
        @click="onOpenMobileMenu()"
      >
        <Menu class="w-4 h-4" />
      </button>

      <div
        class="flex items-center gap-1.5 sm:gap-2 bg-slate-800/90 border border-slate-700/80 rounded-xl px-2.5 sm:px-3 py-1.5 shadow-sm"
      >
        <Calendar class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-indigo-400 shrink-0" />
        <select
          id="header-month-select"
          :value="currentMonth.monthId"
          class="bg-transparent text-xs sm:text-sm font-semibold text-white focus:outline-none cursor-pointer pr-1"
          @change="onMonthSelectChange"
        >
          <option
            v-for="m in allMonths"
            :key="m.monthId"
            :value="m.monthId"
            class="bg-slate-800 text-white"
          >
            {{ m.monthName }} {{ m.year }}
            ({{
              m.periodStart && m.periodEnd
                ? formatReportingPeriodShort({ start: m.periodStart, end: m.periodEnd })
                : formatReportingPeriodShort(reportingPeriodForMonth(m))
            }})
          </option>
        </select>
      </div>

      <div
        class="flex items-center bg-slate-800/60 border border-slate-700/60 rounded-xl px-2 sm:px-3 py-1.5 text-[10px] sm:text-[11px] text-slate-300 max-w-[9rem] sm:max-w-none"
        :title="`Rapportageperiode: ${reportingPeriodLabel}`"
      >
        <span class="text-slate-400 hidden md:inline">Periode</span>
        <span class="font-medium text-slate-200 md:ml-1.5 truncate">{{ reportingPeriodLabel }}</span>
      </div>

      <div
        class="hidden xl:flex items-center gap-1 bg-slate-800/40 p-1 rounded-xl border border-slate-800"
      >
        <template v-for="mId in quickMonthIds" :key="mId">
          <button
            v-if="monthById(mId)"
            type="button"
            class="text-xs px-2.5 py-1 rounded-lg font-medium transition-colors"
            :class="
              currentMonth.monthId === mId
                ? 'bg-indigo-600 text-white shadow-sm'
                : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800'
            "
            :title="monthPeriodShort(mId)"
            @click="emit('selectMonth', mId)"
          >
            {{ monthShortName(mId) }}
          </button>
        </template>
      </div>
    </div>

    <div class="flex items-center gap-1.5 sm:gap-3">
      <div
        v-if="save"
        id="header-save-state"
        class="flex items-center gap-1.5 border rounded-xl px-2.5 py-1.5 text-[11px] font-semibold"
        :class="save.className"
        title="Status van het automatisch opslaan in de database"
      >
        <component
          :is="save.Icon"
          class="w-3.5 h-3.5"
          :class="saveState === 'saving' ? 'animate-pulse' : ''"
        />
        <span class="hidden sm:inline">{{ save.text }}</span>
      </div>

      <div
        class="hidden sm:flex items-center gap-2 bg-slate-800/60 border border-slate-700/60 rounded-xl px-2.5 sm:px-3 py-1.5 text-xs text-slate-300"
      >
        <Wifi
          class="w-3.5 h-3.5"
          :class="bankAccount.status === 'connected' ? 'text-emerald-400' : 'text-amber-400'"
        />
        <span class="hidden md:inline text-slate-400">PSD2:</span>
        <span
          class="font-mono font-medium"
          :class="bankAccount.status === 'connected' ? 'text-emerald-400' : 'text-amber-400'"
        >
          ING
        </span>
        <span class="text-[10px] text-slate-500 hidden lg:inline">
          • {{ bankAccount.lastSync || "niet gekoppeld" }}
        </span>
      </div>

      <button
        id="header-sync-btn"
        type="button"
        :disabled="isSyncing"
        class="flex items-center gap-1 sm:gap-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white px-2.5 sm:px-3 py-1.5 rounded-xl text-xs font-semibold border border-slate-700 transition-all shadow-sm active:scale-95 disabled:opacity-50"
        title="Haal laatste mutaties op via EnableBanking"
        @click="emit('sync')"
      >
        <RefreshCw
          class="w-3.5 h-3.5 text-indigo-400"
          :class="isSyncing ? 'animate-spin' : ''"
        />
        <span class="hidden lg:inline">{{ isSyncing ? "Ophalen..." : "Sync Bank" }}</span>
      </button>

      <button
        id="header-add-transaction-btn"
        type="button"
        class="flex items-center gap-1 sm:gap-1.5 bg-emerald-600 hover:bg-emerald-500 text-white px-2.5 sm:px-3 py-1.5 rounded-xl text-xs font-semibold shadow-md shadow-emerald-600/20 transition-all active:scale-95"
        title="Nieuwe transactie toevoegen"
        @click="emit('openAddTransaction')"
      >
        <Plus class="w-3.5 h-3.5" />
        <span class="hidden sm:inline">Transactie</span>
      </button>

      <button
        id="header-add-budget-item-btn"
        type="button"
        class="flex items-center gap-1 sm:gap-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 px-2.5 sm:px-3 py-1.5 rounded-xl text-xs font-semibold border border-slate-700 transition-all active:scale-95"
        title="Nieuwe begrotingspost toevoegen"
        @click="emit('openAddBudgetItem')"
      >
        <ArrowDownCircle class="w-3.5 h-3.5 text-indigo-400" />
        <span class="hidden sm:inline">Nieuwe Post</span>
      </button>
    </div>
  </header>
</template>
