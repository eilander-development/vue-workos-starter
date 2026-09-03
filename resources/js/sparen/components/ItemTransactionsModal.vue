<script setup lang="ts">
import { computed, ref } from "vue";
import {
  X,
  Receipt,
  Edit2,
  CheckCircle2,
  Clock,
  Sparkles,
  Link2,
  Plus,
  Unlink,
} from "lucide-vue-next";
import type {
  BudgetItem,
  MonthlyBudget,
  Transaction,
  BudgetCategoryGroup,
  SavingsGoal,
} from "../types";
import { isTransactionInReportingMonth } from "../month";
import {
  hasPotEnvelope,
  computePotSettlement,
  potCompensationStatus,
  potGoalsLinkedToItem,
  potTransfersForBudgetItem,
  shadowOverspend,
  type PotTransferRole,
} from "../potSettlement";
import TransactionDate from "./TransactionDate.vue";

type ListedTxRole = "spend" | PotTransferRole;

type ListedTx = {
  tx: Transaction;
  role: ListedTxRole;
};

const props = withDefaults(
  defineProps<{
    isOpen: boolean;
    onClose: () => void;
    budgetItem: BudgetItem | null;
    currentMonth: MonthlyBudget;
    allMonths?: MonthlyBudget[];
    transactions: Transaction[];
    savingsGoals?: SavingsGoal[];
    ownIbans?: string[];
    onUnlinkTransaction?: (txId: string) => void;
    onLinkTransaction?: (txId: string, group: BudgetCategoryGroup, itemId: string) => void;
    onOpenEditBudgetItem?: (item: BudgetItem) => void;
    onAddTransactionToItem?: (itemId: string, itemGroup: BudgetCategoryGroup) => void;
    onOpenPot?: (item: BudgetItem) => void;
  }>(),
  {
    allMonths: () => [],
    savingsGoals: () => [],
    ownIbans: () => [],
  }
);

const filterScope = ref<"current_month" | "all_history">("current_month");
const searchTerm = ref("");

function inActiveScope(tx: Transaction): boolean {
  if (filterScope.value === "current_month") {
    return isTransactionInReportingMonth(tx, props.currentMonth);
  }
  return true;
}

function compareTxNewestFirst(a: Transaction, b: Transaction): number {
  const dateCmp = b.date.localeCompare(a.date);
  if (dateCmp !== 0) return dateCmp;
  return (b.time ?? "").localeCompare(a.time ?? "");
}

const linkedTransactions = computed(() => {
  if (!props.budgetItem) return [];
  return props.transactions.filter(
    (t) => t.budgetItemId === props.budgetItem!.id && inActiveScope(t)
  );
});

const listedTransactions = computed((): ListedTx[] => {
  if (!props.budgetItem) return [];

  const spend: ListedTx[] = linkedTransactions.value.map((tx) => ({
    tx,
    role: "spend" as const,
  }));
  const spendIds = new Set(spend.map((row) => row.tx.id));

  const transfers: ListedTx[] = potTransfersForBudgetItem(
    props.budgetItem.id,
    props.transactions,
    props.savingsGoals,
    props.ownIbans,
    props.budgetItem.actual
  )
    .filter((row) => inActiveScope(row.tx) && !spendIds.has(row.tx.id))
    .map((row) => ({ tx: row.tx, role: row.role }));

  return [...spend, ...transfers].sort((a, b) => compareTxNewestFirst(a.tx, b.tx));
});

const filteredList = computed(() =>
  listedTransactions.value.filter(({ tx }) => {
    if (!searchTerm.value) return true;
    const term = searchTerm.value.toLowerCase();
    return (
      tx.description.toLowerCase().includes(term) ||
      (tx.counterparty && tx.counterparty.toLowerCase().includes(term)) ||
      tx.amount.toString().includes(term) ||
      tx.date.includes(term)
    );
  })
);

const listedTransferIds = computed(
  () => new Set(listedTransactions.value.filter((row) => row.role !== "spend").map((row) => row.tx.id))
);

const suggestedUnlinkedTransactions = computed(() => {
  if (!props.budgetItem) return [];
  const keywords = props.budgetItem.name
    .replace(/[()]/g, "")
    .split(/\s+/)
    .filter((w) => w.length > 2);
  if (keywords.length === 0) return [];

  return props.transactions
    .filter((t) => {
      if (t.budgetItemId === props.budgetItem!.id) return false;
      if (listedTransferIds.value.has(t.id)) return false;
      if (t.linkExcluded) return false;
      if (t.budgetItemId && t.categoryGroup !== "Ongecategoriseerd") return false;
      const desc = (t.description + " " + (t.counterparty || "")).toLowerCase();
      return keywords.some((kw) => desc.includes(kw.toLowerCase()));
    })
    .slice(0, 3);
});

const totalPaidInView = computed(() =>
  linkedTransactions.value.reduce((sum, t) => sum + Math.abs(t.amount), 0)
);
const listedCount = computed(() => listedTransactions.value.length);
const potDepositCount = computed(
  () => listedTransactions.value.filter((row) => row.role === "deposit").length
);
const potWithdrawalTotal = computed(() =>
  listedTransactions.value
    .filter((row) => row.role === "withdrawal")
    .reduce((sum, row) => sum + Math.max(0, row.tx.amount), 0)
);
const isIncome = computed(() => props.budgetItem?.type === "inkomsten");
const isSaving = computed(() => props.budgetItem?.type === "sparen");
const budgetAmount = computed(() => props.budgetItem?.actual ?? 0);
const isPotItem = computed(() => {
  if (!props.budgetItem) return false;
  if (hasPotEnvelope(props.budgetItem)) return true;
  return potGoalsLinkedToItem(props.budgetItem.id, props.savingsGoals).length > 0;
});
const envelopePaid = computed(() => props.budgetItem?.paidOrReceived ?? 0);
const potSettlement = computed(() => {
  if (!props.budgetItem) return null;
  const goal = potGoalsLinkedToItem(props.budgetItem.id, props.savingsGoals)[0];
  if (!goal) return null;
  return computePotSettlement(goal, props.currentMonth, props.transactions);
});
const potShortfall = computed(() => {
  if (potSettlement.value) {
    return potCompensationStatus(potSettlement.value).shortfall;
  }
  return Math.max(0, totalPaidInView.value - potWithdrawalTotal.value);
});
const remainingAmount = computed(() => {
  if (isPotItem.value) {
    return potShortfall.value;
  }
  if (budgetAmount.value <= 0) {
    return 0;
  }
  return Math.max(0, budgetAmount.value - totalPaidInView.value);
});
const remainingLabel = computed(() => {
  if (isPotItem.value) return "Nog te compenseren";
  if (isIncome.value) return "Nog te ontvangen";
  if (isSaving.value) return "Nog te sparen";
  return "Nog te betalen";
});
const potIdleThisMonth = computed(
  () =>
    isPotItem.value &&
    filterScope.value === "current_month" &&
    linkedTransactions.value.length === 0
);

function roleLabel(role: ListedTxRole): string | null {
  if (role === "deposit") return "Storting naar potje";
  if (role === "withdrawal") return "Opname van potje";
  return null;
}
const difference = computed(() => {
  if (isPotItem.value) {
    return budgetAmount.value - envelopePaid.value;
  }
  return isIncome.value
    ? totalPaidInView.value - budgetAmount.value
    : budgetAmount.value - totalPaidInView.value;
});

function openEdit() {
  if (!props.budgetItem || !props.onOpenEditBudgetItem) return;
  props.onClose();
  props.onOpenEditBudgetItem(props.budgetItem);
}

function addTx() {
  if (!props.budgetItem || !props.onAddTransactionToItem) return;
  props.onClose();
  props.onAddTransactionToItem(props.budgetItem.id, props.budgetItem.group);
}

function openPot() {
  if (!props.budgetItem || !props.onOpenPot) return;
  props.onOpenPot(props.budgetItem);
}
</script>

<template>
  <div
    v-if="isOpen && budgetItem"
    id="item-transactions-modal-overlay"
    class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/80 backdrop-blur-sm animate-in fade-in"
  >
    <div class="bg-slate-900 border border-slate-800 w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[92vh]">
      <div class="bg-slate-850 px-5 py-4 border-b border-slate-800 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-3">
          <div
            class="p-2.5 rounded-xl border"
            :class="
              isIncome
                ? 'bg-emerald-500/15 border-emerald-500/30 text-emerald-400'
                : isSaving
                  ? 'bg-blue-500/15 border-blue-500/30 text-blue-400'
                  : 'bg-rose-500/15 border-rose-500/30 text-rose-400'
            "
          >
            <Receipt class="w-5 h-5" />
          </div>
          <div>
            <div class="flex items-center gap-2">
              <h3 class="font-bold text-white text-base">{{ budgetItem.name }}</h3>
              <span class="text-[10px] bg-slate-800 text-slate-300 px-2 py-0.5 rounded-full border border-slate-700 font-medium">
                {{ budgetItem.group }}
              </span>
            </div>
            <button
              v-if="isPotItem && onOpenPot"
              type="button"
              class="mt-1 inline-flex text-[9px] uppercase tracking-wide font-semibold text-amber-300 bg-amber-950 border border-amber-800 px-1.5 py-0.5 rounded hover:bg-amber-900 hover:text-amber-100 transition-colors"
              title="Bekijk potje"
              @click="openPot"
            >
              Potje
            </button>
            <p class="text-xs text-slate-400 mt-0.5">
              {{
                isPotItem
                  ? "Pin-uitgaven vanuit het potje plus de storting naar het potje. Zonder pin-uitgaven deze maand telt de envelop niet als betaald."
                  : "Transactieoverzicht & gekoppelde bankmutaties voor deze begrotingspost"
              }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button
            v-if="onOpenEditBudgetItem"
            type="button"
            class="hidden sm:flex items-center gap-1.5 px-2.5 py-1.5 text-xs text-slate-300 hover:text-white bg-slate-800 hover:bg-slate-700 rounded-lg border border-slate-700 transition-colors"
            title="Begroot bedrag of frequentie aanpassen"
            @click="openEdit"
          >
            <Edit2 class="w-3.5 h-3.5" />
            <span>Post Bewerken</span>
          </button>
          <button
            type="button"
            class="text-slate-400 hover:text-white p-1.5 rounded-lg hover:bg-slate-800 transition-colors"
            @click="onClose"
          >
            <X class="w-5 h-5" />
          </button>
        </div>
      </div>

      <div class="p-4 sm:p-5 border-b border-slate-800 bg-slate-950/40 space-y-3 shrink-0">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 text-xs">
          <div class="bg-slate-900/80 border border-slate-800 p-3 rounded-xl">
            <span class="text-slate-400 text-[11px] block font-medium">
              Begroot ({{ currentMonth.monthName }}):
            </span>
            <span class="text-sm sm:text-base font-bold text-white font-mono mt-0.5 block">
              € {{ budgetAmount.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </span>
          </div>
          <div class="bg-slate-900/80 border border-slate-800 p-3 rounded-xl">
            <span class="text-slate-400 text-[11px] block font-medium">
              {{
                isPotItem
                  ? "Betaald (potje):"
                  : isIncome
                    ? "Ontvangen (Bank):"
                    : "Betaald via Bank:"
              }}
            </span>
            <span
              class="text-sm sm:text-base font-bold font-mono mt-0.5 block"
              :class="isIncome ? 'text-emerald-400' : 'text-rose-400'"
            >
              €
              {{
                (isPotItem ? envelopePaid : totalPaidInView).toLocaleString("nl-NL", {
                  minimumFractionDigits: 2,
                })
              }}
            </span>
          </div>
          <div class="bg-slate-900/80 border border-slate-800 p-3 rounded-xl">
            <span class="text-slate-400 text-[11px] block font-medium">
              {{
                isPotItem
                  ? "Uitgegeven:"
                  : isIncome
                    ? "Verschil:"
                    : "Resterend / Verschil:"
              }}
            </span>
            <span
              class="text-sm sm:text-base font-bold font-mono mt-0.5 block"
              :class="
                isPotItem
                  ? shadowOverspend(budgetItem) > 0
                    ? 'text-rose-400'
                    : 'text-amber-300'
                  : difference >= 0
                    ? 'text-emerald-400'
                    : 'text-rose-400'
              "
            >
              <template v-if="isPotItem">
                €
                {{ totalPaidInView.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </template>
              <template v-else>
                {{ difference >= 0 ? "€ " : "€ -"
                }}{{ Math.abs(difference).toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </template>
            </span>
          </div>
          <div class="bg-slate-900/80 border border-slate-800 p-3 rounded-xl">
            <span class="text-slate-400 text-[11px] block font-medium">{{ remainingLabel }}:</span>
            <span
              class="text-sm sm:text-base font-bold font-mono mt-0.5 block"
              :class="remainingAmount > 0 ? 'text-amber-300' : 'text-emerald-400'"
            >
              € {{ remainingAmount.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </span>
          </div>
          <div class="bg-slate-900/80 border border-slate-800 p-3 rounded-xl">
            <span class="text-slate-400 text-[11px] block font-medium">Mutaties:</span>
            <div class="flex items-center gap-1.5 mt-0.5">
              <span class="text-sm sm:text-base font-bold text-white font-mono">
                {{ listedCount }}
              </span>
              <CheckCircle2 v-if="listedCount > 0" class="w-3.5 h-3.5 text-emerald-400" />
              <Clock v-else class="w-3.5 h-3.5 text-amber-400" />
            </div>
          </div>
        </div>

        <p
          v-if="potIdleThisMonth"
          class="text-[11px] text-amber-300/90 bg-amber-950/30 border border-amber-800/40 rounded-lg px-3 py-2"
        >
          Deze maand nog geen pin-uitgaven op deze post. De envelop telt daardoor nog niet als betaald.
        </p>

        <div
          v-if="budgetItem.monthEntries && budgetItem.monthEntries.length > 0"
          class="bg-slate-900/80 border border-slate-800 rounded-xl p-3"
        >
          <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-semibold text-slate-300 uppercase tracking-wide">
              Geplande openstaande regels ({{ currentMonth.monthName }})
            </span>
            <button
              v-if="onOpenEditBudgetItem"
              type="button"
              class="text-[11px] text-indigo-400 hover:text-indigo-300"
              @click="openEdit"
            >
              Regels bewerken
            </button>
          </div>
          <ul class="space-y-1.5">
            <li
              v-for="entry in budgetItem.monthEntries"
              :key="entry.id"
              class="flex items-center justify-between gap-3 text-xs border-b border-slate-800/80 last:border-0 pb-1.5 last:pb-0"
            >
              <span class="text-slate-200 truncate">{{ entry.description || "Zonder omschrijving" }}</span>
              <span class="font-mono text-amber-300 shrink-0">
                € {{ entry.amount.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </span>
            </li>
          </ul>
          <p class="text-[10px] text-slate-500 mt-2">
            Koppel hieronder bankmutaties aan Openstaand om deze regels af te boeken. Het restant blijft in nog te
            betalen / verwacht eind.
          </p>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 pt-1">
          <div class="flex items-center gap-1 bg-slate-900 p-1 rounded-xl border border-slate-800 w-fit">
            <button
              type="button"
              class="px-3 py-1 rounded-lg text-xs font-semibold transition-all"
              :class="
                filterScope === 'current_month'
                  ? 'bg-indigo-600 text-white shadow-sm'
                  : 'text-slate-400 hover:text-white'
              "
              @click="filterScope = 'current_month'"
            >
              {{ currentMonth.monthName }} {{ currentMonth.year }}
            </button>
            <button
              type="button"
              class="px-3 py-1 rounded-lg text-xs font-semibold transition-all"
              :class="
                filterScope === 'all_history'
                  ? 'bg-indigo-600 text-white shadow-sm'
                  : 'text-slate-400 hover:text-white'
              "
              @click="filterScope = 'all_history'"
            >
              Alle Historie
            </button>
          </div>
          <div class="relative flex-1 max-w-xs">
            <input
              v-model="searchTerm"
              type="text"
              placeholder="Zoek in mutaties..."
              class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
            />
          </div>
        </div>
      </div>

      <div class="p-4 sm:p-5 overflow-y-auto space-y-4 flex-1">
        <div
          v-if="suggestedUnlinkedTransactions.length > 0 && onLinkTransaction"
          class="bg-gradient-to-r from-indigo-950/60 to-slate-900 border border-indigo-500/30 p-3.5 rounded-xl space-y-2.5 text-xs"
        >
          <div class="flex items-center gap-2 text-indigo-300 font-semibold">
            <Sparkles class="w-4 h-4 text-amber-400" />
            <span>Gevonden bankmutaties die mogelijk bij deze post horen:</span>
          </div>
          <div class="space-y-2">
            <div
              v-for="suggestedTx in suggestedUnlinkedTransactions"
              :key="suggestedTx.id"
              class="bg-slate-900/90 border border-slate-700/80 p-2.5 rounded-lg flex items-center justify-between gap-3 text-xs"
            >
              <div class="space-y-0.5 min-w-0">
                <div class="flex items-center gap-2">
                  <TransactionDate :date="suggestedTx.date" :time="suggestedTx.time" size="sm" />
                  <span class="text-white font-medium truncate">{{ suggestedTx.description }}</span>
                </div>
                <p v-if="suggestedTx.counterparty" class="text-[10px] text-slate-400 truncate">
                  Tegenpartij: {{ suggestedTx.counterparty }}
                </p>
              </div>
              <div class="flex items-center gap-2 shrink-0">
                <span class="font-mono font-bold text-rose-400 text-xs">
                  € {{ Math.abs(suggestedTx.amount).toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
                </span>
                <button
                  type="button"
                  class="bg-indigo-600 hover:bg-indigo-500 text-white px-2.5 py-1 rounded-md text-[11px] font-semibold flex items-center gap-1 shadow-sm transition-transform active:scale-95"
                  @click="onLinkTransaction!(suggestedTx.id, budgetItem.group, budgetItem.id)"
                >
                  <Link2 class="w-3 h-3" />
                  <span>Koppel nu</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="space-y-2">
          <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider">
            {{ isPotItem ? "Mutaties" : "Gekoppelde Mutaties" }} ({{ filteredList.length }})
            <span
              v-if="isPotItem && potDepositCount > 0"
              class="ml-1.5 normal-case font-medium text-slate-500"
            >
              · {{ linkedTransactions.length }} pin · {{ potDepositCount }} storting
            </span>
          </h4>

          <div
            v-if="filteredList.length === 0"
            class="text-center py-10 border border-dashed border-slate-800 rounded-2xl bg-slate-950/20 p-6 space-y-3"
          >
            <div class="w-12 h-12 mx-auto rounded-full bg-slate-800/80 flex items-center justify-center text-slate-500">
              <Receipt class="w-6 h-6" />
            </div>
            <div class="space-y-1">
              <p class="font-semibold text-white text-sm">Geen transacties gekoppeld</p>
              <p class="text-xs text-slate-400 max-w-sm mx-auto">
                Er zijn in
                {{
                  filterScope === "current_month"
                    ? `${currentMonth.monthName} ${currentMonth.year}`
                    : "de historie"
                }}
                nog geen banktransacties direct aan <strong>{{ budgetItem.name }}</strong> toegewezen.
              </p>
            </div>
            <button
              v-if="onAddTransactionToItem"
              type="button"
              class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-500 text-white px-3.5 py-2 rounded-xl text-xs font-semibold transition-all shadow-sm active:scale-95"
              @click="addTx"
            >
              <Plus class="w-4 h-4" />
              <span>Boeking handmatig toevoegen</span>
            </button>
          </div>

          <template v-else>
            <div
              v-for="{ tx, role } in filteredList"
              :key="tx.id"
              class="bg-slate-800/60 hover:bg-slate-800 border border-slate-700/70 p-3.5 rounded-xl transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs"
              :class="role !== 'spend' ? 'border-blue-800/50' : ''"
            >
              <div class="space-y-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <TransactionDate :date="tx.date" :time="tx.time" size="sm" />
                  <span class="text-[10px] bg-slate-700/80 text-slate-300 px-1.5 py-0.2 rounded font-mono">
                    {{ tx.source }}
                  </span>
                  <span
                    v-if="roleLabel(role)"
                    class="text-[9px] bg-blue-950 text-blue-300 border border-blue-800 px-1.5 py-0.2 rounded font-medium"
                  >
                    {{ roleLabel(role) }}
                  </span>
                  <span
                    v-else-if="tx.matchedRuleId"
                    class="text-[9px] bg-indigo-950 text-indigo-300 border border-indigo-800 px-1.5 py-0.2 rounded font-mono"
                  >
                    Auto-regel
                  </span>
                </div>
                <h5 class="font-semibold text-white text-sm break-words">{{ tx.description }}</h5>
                <p v-if="tx.counterparty" class="text-[11px] text-slate-400">
                  Tegenpartij: <span class="text-slate-300 font-medium">{{ tx.counterparty }}</span>
                  <span v-if="tx.accountIban" class="font-mono text-slate-500 ml-1.5">({{ tx.accountIban }})</span>
                </p>
              </div>
              <div
                class="flex items-center justify-between sm:justify-end gap-3 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-700/50"
              >
                <div
                  class="font-mono font-bold text-sm sm:text-base"
                  :class="tx.amount > 0 ? 'text-emerald-400' : 'text-rose-400'"
                >
                  {{ tx.amount > 0 ? "+" : "" }}€
                  {{ Math.abs(tx.amount).toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
                </div>
                <button
                  v-if="onUnlinkTransaction && role === 'spend'"
                  type="button"
                  class="text-slate-400 hover:text-rose-400 p-1.5 rounded-lg hover:bg-rose-950/40 border border-transparent hover:border-rose-800/60 transition-all active:scale-90"
                  title="Ontkoppel deze transactie van deze post"
                  @click="onUnlinkTransaction(tx.id)"
                >
                  <Unlink class="w-4 h-4" />
                </button>
              </div>
            </div>
          </template>
        </div>
      </div>

      <div class="bg-slate-850 px-5 py-3.5 border-t border-slate-800 flex items-center justify-between shrink-0 text-xs">
        <div class="flex items-center gap-2">
          <button
            v-if="onAddTransactionToItem"
            type="button"
            class="flex items-center gap-1.5 text-indigo-400 hover:text-indigo-300 font-semibold"
            @click="addTx"
          >
            <Plus class="w-4 h-4" />
            <span>+ Mutatie handmatig toevoegen</span>
          </button>
        </div>
        <button
          type="button"
          class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl font-medium transition-colors"
          @click="onClose"
        >
          Sluiten
        </button>
      </div>
    </div>
  </div>
</template>
