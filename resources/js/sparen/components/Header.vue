<script setup lang="ts">
import { computed } from "vue";
import {
  Calendar,
  ChevronLeft,
  ChevronRight,
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

const currentIndex = computed(() =>
  props.allMonths.findIndex((m) => m.monthId === props.currentMonth.monthId)
);

const prevMonth = computed(() =>
  currentIndex.value > 0 ? props.allMonths[currentIndex.value - 1] : null
);

const nextMonth = computed(() =>
  currentIndex.value >= 0 && currentIndex.value < props.allMonths.length - 1
    ? props.allMonths[currentIndex.value + 1]
    : null
);
</script>

<template>
  <header
    id="app-header"
    class="bg-slate-900/90 backdrop-blur-md border-b border-slate-800 sticky top-0 z-20 px-3 md:px-4 py-2.5 sm:py-3.5 flex items-center justify-between gap-2 sm:gap-4"
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
        id="header-month-nav"
        class="flex items-center bg-slate-800/90 rounded-xl p-1 border border-slate-700/80 shadow-sm"
      >
        <button
          type="button"
          :disabled="!prevMonth"
          class="p-1.5 text-slate-400 hover:text-white disabled:opacity-30 disabled:hover:text-slate-400 transition-colors rounded-lg"
          title="Vorige maand"
          @click="prevMonth && emit('selectMonth', prevMonth.monthId)"
        >
          <ChevronLeft class="w-4 h-4" />
        </button>
        <div class="px-2 sm:px-3 py-0.5 flex flex-col min-w-0">
          <div class="flex items-center gap-1.5 sm:gap-2">
            <Calendar class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-indigo-400 shrink-0" />
            <span class="font-bold text-white text-xs sm:text-sm font-sans tracking-wide truncate">
              {{ currentMonth.monthName }} {{ currentMonth.year }}
            </span>
          </div>
          <span class="text-[10px] sm:text-[11px] text-slate-400 pl-5 sm:pl-6 truncate">
            {{ reportingPeriodLabel }}
          </span>
        </div>
        <button
          type="button"
          :disabled="!nextMonth"
          class="p-1.5 text-slate-400 hover:text-white disabled:opacity-30 disabled:hover:text-slate-400 transition-colors rounded-lg"
          title="Volgende maand"
          @click="nextMonth && emit('selectMonth', nextMonth.monthId)"
        >
          <ChevronRight class="w-4 h-4" />
        </button>
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
          •
          {{
            bankAccount.status === "connected"
              ? bankAccount.consentDaysRemaining != null
                ? bankAccount.consentDaysRemaining === 0
                  ? "verloopt vandaag"
                  : `nog ${bankAccount.consentDaysRemaining}d`
                : bankAccount.lastSync || "gekoppeld"
              : "niet gekoppeld"
          }}
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
