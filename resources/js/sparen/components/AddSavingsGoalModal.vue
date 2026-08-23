<script setup lang="ts">
import { computed, ref, watch } from "vue";
import {
  X,
  PiggyBank,
  ShieldCheck,
  Home,
  Palmtree,
  Cat,
  Car,
  Heart,
  Sparkles,
  CreditCard,
} from "lucide-vue-next";
import type { SavingsGoal, SavingsGoalKind, Transaction, BudgetItem } from "../types";
import {
  isOwnIban,
  matchingSavingsTransactions,
  matchingUnlinkedSavingsTransactions,
} from "../matchSavings";

const props = withDefaults(
  defineProps<{
    isOpen: boolean;
    onClose: () => void;
    onSave: (goalData: Omit<SavingsGoal, "id">, editId?: string) => void;
    editingGoal?: SavingsGoal | null;
    transactions?: Transaction[];
    ownIbans?: string[];
    budgetItems?: BudgetItem[];
  }>(),
  {
    editingGoal: null,
    transactions: () => [],
    ownIbans: () => [],
    budgetItems: () => [],
  }
);

const name = ref("");
const accountIban = ref("");
const bankName = ref("ING Oranje Spaarrekening");
const targetAmount = ref("");
const initialAmount = ref("");
const monthlyContribution = ref("");
const color = ref("emerald");
const iconName = ref("ShieldCheck");
const notes = ref("");
const kind = ref<SavingsGoalKind>("goal");
const categoryBudgetItemId = ref("");

watch(
  () => [props.editingGoal, props.isOpen] as const,
  () => {
    if (!props.isOpen) return;
    if (props.editingGoal) {
      const g = props.editingGoal;
      name.value = g.name;
      accountIban.value = g.accountIban;
      bankName.value = g.bankName || "Spaarrekening";
      targetAmount.value = g.targetAmount ? g.targetAmount.toString() : "";
      initialAmount.value = g.initialAmount ? g.initialAmount.toString() : "";
      monthlyContribution.value = g.monthlyContribution ? g.monthlyContribution.toString() : "";
      color.value = g.color || "emerald";
      iconName.value = g.iconName || "ShieldCheck";
      notes.value = g.notes || "";
      kind.value = g.kind === "pot" ? "pot" : "goal";
      categoryBudgetItemId.value = g.categoryBudgetItemId || "";
    } else {
      name.value = "";
      accountIban.value = "";
      bankName.value = "ING Oranje Spaarrekening";
      targetAmount.value = "2000";
      initialAmount.value = "500";
      monthlyContribution.value = "100";
      color.value = "emerald";
      iconName.value = "PiggyBank";
      notes.value = "";
      kind.value = "goal";
      categoryBudgetItemId.value = "";
    }
  },
  { immediate: true }
);

const expenseBudgetItems = computed(() => props.budgetItems.filter((item) => item.type === "uitgaven"));
const extraMatches = computed(() =>
  matchingUnlinkedSavingsTransactions(
    props.transactions,
    { name: name.value, accountIban: accountIban.value },
    props.ownIbans
  )
);
const allMatches = computed(() =>
  matchingSavingsTransactions(
    props.transactions,
    { name: name.value, accountIban: accountIban.value },
    props.ownIbans
  )
);
const ownIbanEntered = computed(() => isOwnIban(accountIban.value, props.ownIbans));
const previewMatches = computed(() =>
  extraMatches.value.length > 0 ? extraMatches.value : allMatches.value
);

const iconOptions = [
  { id: "ShieldCheck", label: "Buffer / Noodfonds", icon: ShieldCheck },
  { id: "PiggyBank", label: "Spaarvarken / Algemeen", icon: PiggyBank },
  { id: "Home", label: "Woning & Tuin", icon: Home },
  { id: "Palmtree", label: "Vakantie & Reizen", icon: Palmtree },
  { id: "Cat", label: "Huisdier / Kat", icon: Cat },
  { id: "Car", label: "Auto & Vervoer", icon: Car },
  { id: "Heart", label: "Gezin & Cadeaus", icon: Heart },
];

const colorOptions = [
  { id: "emerald", label: "Groen", bg: "bg-emerald-500" },
  { id: "indigo", label: "Indigo", bg: "bg-indigo-500" },
  { id: "amber", label: "Goud", bg: "bg-amber-500" },
  { id: "purple", label: "Paars", bg: "bg-purple-500" },
  { id: "cyan", label: "Cyaan", bg: "bg-cyan-500" },
  { id: "rose", label: "Roze", bg: "bg-rose-500" },
];

const bankSuggestions = [
  "ING Oranje Spaarrekening",
  "Rabobank Doelsparen",
  "Knab Spaarrekening",
  "Bunq Spaarpot",
  "Trade Republic Spaarrekening",
  "Nationale-Nederlanden",
  "ABN AMRO Spaargemak",
  "Overige Bank",
];

function handleSubmit() {
  if (name.value.trim().length < 3) return;
  if (kind.value === "pot" && !categoryBudgetItemId.value) return;

  props.onSave(
    {
      name: name.value.trim(),
      accountIban: ownIbanEntered.value
        ? ""
        : accountIban.value.trim().toUpperCase().replace(/\s+/g, ""),
      bankName: bankName.value.trim(),
      targetAmount: parseFloat(targetAmount.value) || 0,
      initialAmount: parseFloat(initialAmount.value) || 0,
      monthlyContribution: parseFloat(monthlyContribution.value) || 0,
      color: color.value,
      iconName: iconName.value,
      notes: notes.value.trim(),
      kind: kind.value,
      categoryBudgetItemId: categoryBudgetItemId.value || undefined,
    },
    props.editingGoal ? props.editingGoal.id : undefined
  );

  props.onClose();
}

function pickBankSuggestion(value: string) {
  if (value) bankName.value = value;
}
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm animate-in fade-in"
  >
    <div
      class="bg-slate-900 border border-slate-800 w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]"
    >
      <div class="bg-slate-850 px-5 py-4 border-b border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <div
            class="w-8 h-8 rounded-xl bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-center text-indigo-400"
          >
            <PiggyBank class="w-4 h-4" />
          </div>
          <div>
            <h3 class="font-bold text-white text-base">
              {{ editingGoal ? "Spaarrekening & Doel Bewerken" : "Nieuwe Spaarrekening & Doel Koppelen" }}
            </h3>
            <p class="text-[11px] text-slate-400">
              Herken mutaties op omschrijving + optioneel IBAN, zoals op je bankafschrift
            </p>
          </div>
        </div>
        <button
          type="button"
          class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition-colors"
          @click="onClose"
        >
          <X class="w-4 h-4" />
        </button>
      </div>

      <form class="p-5 space-y-4 overflow-y-auto text-xs" @submit.prevent="handleSubmit">
        <div class="p-3 bg-indigo-950/40 border border-indigo-800/50 rounded-xl text-slate-300 space-y-1">
          <span class="font-semibold text-indigo-300 block text-xs flex items-center gap-1.5">
            <Sparkles class="w-3.5 h-3.5 text-yellow-300" />
            Automatische Sparen-Mapping
          </span>
          <p class="text-[11px] text-slate-400 leading-relaxed">
            ING toont interne overboekingen als
            <span class="text-slate-200 font-medium">Van Oranje spaarrekening S13134203</span>
            , vaak met je betaalrekening als tegenpartij. Vul daarom de
            <strong class="text-slate-200">omschrijving</strong> in, en alleen een eigen spaar-IBAN als die
            op de mutatie staat.
          </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="sm:col-span-2">
            <label class="block text-slate-300 font-semibold mb-1.5">Type</label>
            <div class="grid grid-cols-2 gap-2">
              <button
                type="button"
                class="px-3 py-2 rounded-xl border text-left transition-colors"
                :class="
                  kind === 'goal'
                    ? 'border-indigo-500 bg-indigo-950/40 text-white'
                    : 'border-slate-700 bg-slate-800 text-slate-400 hover:text-white'
                "
                @click="kind = 'goal'"
              >
                <span class="font-semibold block">Spaardoel</span>
                <span class="text-[10px] text-slate-400">Buffer, vakantie, auto…</span>
              </button>
              <button
                type="button"
                class="px-3 py-2 rounded-xl border text-left transition-colors"
                :class="
                  kind === 'pot'
                    ? 'border-amber-500 bg-amber-950/30 text-white'
                    : 'border-slate-700 bg-slate-800 text-slate-400 hover:text-white'
                "
                @click="kind = 'pot'"
              >
                <span class="font-semibold block">Potje (verrekening)</span>
                <span class="text-[10px] text-slate-400">Boodschappen, benzine…</span>
              </button>
            </div>
            <p v-if="kind === 'pot'" class="text-[10px] text-amber-200/90 mt-1.5 leading-relaxed">
              Uitgaven landen op de betaalrekening in een rubriek. Dit potje toont hoeveel je nog van pot →
              rekening moet overzetten om te compenseren.
            </p>
          </div>

          <div v-if="kind === 'pot'" class="sm:col-span-2">
            <label class="block text-slate-300 font-semibold mb-1">
              Gekoppelde uitgavenrubriek <span class="text-rose-400">*</span>
            </label>
            <select
              v-model="categoryBudgetItemId"
              required
              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white focus:outline-none focus:border-indigo-500"
            >
              <option value="">Kies begrotingspost…</option>
              <option v-for="item in expenseBudgetItems" :key="item.id" :value="item.id">
                {{ item.group }} › {{ item.name }}
              </option>
            </select>
          </div>

          <div class="sm:col-span-2">
            <label class="block text-slate-300 font-semibold mb-1">
              Omschrijving <span class="text-rose-400">*</span>
            </label>
            <input
              v-model="name"
              type="text"
              required
              minlength="3"
              placeholder="Van Oranje spaarrekening S13134203"
              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
            />
            <span class="text-[10px] text-slate-400 mt-1 block">
              Het herkenbare deel van de mutatie, bijvoorbeeld Oranje spaarrekening of S13134203.
            </span>
          </div>

          <div class="sm:col-span-2">
            <label class="block text-slate-300 font-semibold mb-1">IBAN</label>
            <div class="relative">
              <CreditCard class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                v-model="accountIban"
                type="text"
                placeholder="Alleen als de spaarrekening een eigen IBAN heeft"
                class="w-full bg-slate-800 border border-slate-700 rounded-xl pl-9 pr-3.5 py-2 text-white font-mono placeholder-slate-500 focus:outline-none focus:border-indigo-500 uppercase"
              />
            </div>
            <span v-if="ownIbanEntered" class="text-[10px] text-amber-300 mt-1 block">
              Dit is je betaalrekening. Die slaan we niet als spaar-IBAN op; interne ING-overboekingen herken
              je op de omschrijving.
            </span>
            <span v-else class="text-[10px] text-slate-400 mt-1 block">
              Optioneel. Niet invullen als de tegenpartij je eigen betaal-IBAN is.
            </span>
          </div>

          <div
            v-if="name.trim().length >= 3"
            class="sm:col-span-2 bg-indigo-950/40 border border-indigo-800/50 rounded-lg p-2.5 space-y-1.5"
          >
            <p class="text-[11px] text-indigo-200 font-semibold">
              {{
                allMatches.length === 0
                  ? "Geen mutaties voldoen aan deze omschrijving."
                  : extraMatches.length > 0
                    ? `${extraMatches.length} ongekoppelde ${extraMatches.length === 1 ? "mutatie wordt" : "mutaties worden"} als sparen gekoppeld${allMatches.length > extraMatches.length ? ` · ${allMatches.length - extraMatches.length} al gekoppeld` : ""}.`
                    : `${allMatches.length} ${allMatches.length === 1 ? "mutatie voldoet" : "mutaties voldoen"} al; niets extra te koppelen.`
              }}
            </p>
            <ul v-if="previewMatches.length > 0" class="space-y-1">
              <li
                v-for="tx in previewMatches.slice(0, 4)"
                :key="tx.id"
                class="text-[10px] text-slate-400 truncate"
              >
                {{ tx.date }} · {{ tx.description }}
              </li>
              <li v-if="previewMatches.length > 4" class="text-[10px] text-slate-500">
                + {{ previewMatches.length - 4 }} meer
              </li>
            </ul>
          </div>

          <div class="sm:col-span-2">
            <label class="block text-slate-300 font-semibold mb-1">Bankinstelling</label>
            <div class="flex gap-2">
              <input
                v-model="bankName"
                type="text"
                placeholder="Bijv. Knab, Rabobank, Bunq of ING"
                class="flex-1 bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
              />
              <select
                class="bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-slate-300 text-xs focus:outline-none"
                value=""
                @change="pickBankSuggestion(($event.target as HTMLSelectElement).value)"
              >
                <option value="" disabled>Snelle selectie...</option>
                <option v-for="b in bankSuggestions" :key="b" :value="b">{{ b }}</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-slate-300 font-semibold mb-1">Doelbedrag (€)</label>
            <input
              v-model="targetAmount"
              type="number"
              step="10"
              min="0"
              placeholder="Bijv. 4500"
              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white font-mono placeholder-slate-500 focus:outline-none focus:border-indigo-500"
            />
          </div>

          <div>
            <label class="block text-slate-300 font-semibold mb-1">Huidig / Startsaldo (€)</label>
            <input
              v-model="initialAmount"
              type="number"
              step="10"
              min="0"
              placeholder="Bijv. 1200"
              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white font-mono placeholder-slate-500 focus:outline-none focus:border-indigo-500"
            />
          </div>

          <div>
            <label class="block text-slate-300 font-semibold mb-1">Maandelijkse Inleg (€/mnd)</label>
            <input
              v-model="monthlyContribution"
              type="number"
              step="5"
              min="0"
              placeholder="Bijv. 100"
              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white font-mono placeholder-slate-500 focus:outline-none focus:border-indigo-500"
            />
          </div>

          <div>
            <label class="block text-slate-300 font-semibold mb-1">Kleur Accent</label>
            <div class="flex items-center gap-2 pt-1">
              <button
                v-for="c in colorOptions"
                :key="c.id"
                type="button"
                class="w-6 h-6 rounded-full transition-all"
                :class="[c.bg, color === c.id ? 'ring-2 ring-white scale-110' : 'opacity-60 hover:opacity-100']"
                :title="c.label"
                @click="color = c.id"
              />
            </div>
          </div>

          <div class="sm:col-span-2">
            <label class="block text-slate-300 font-semibold mb-1.5">Icoon & Thema</label>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
              <button
                v-for="opt in iconOptions"
                :key="opt.id"
                type="button"
                class="flex items-center gap-2 p-2 rounded-xl border text-left transition-all"
                :class="
                  iconName === opt.id
                    ? 'bg-indigo-950/60 border-indigo-500 text-white font-semibold'
                    : 'bg-slate-800/60 border-slate-700/60 text-slate-400 hover:text-slate-200'
                "
                @click="iconName = opt.id"
              >
                <component :is="opt.icon" class="w-4 h-4 text-indigo-400 shrink-0" />
                <span class="text-[11px] truncate">{{ opt.label }}</span>
              </button>
            </div>
          </div>

          <div class="sm:col-span-2">
            <label class="block text-slate-300 font-semibold mb-1">Toelichting / Notities</label>
            <input
              v-model="notes"
              type="text"
              placeholder="Bijv. Reservering voor vakantie Frankrijk 2026"
              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
            />
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
          <button
            type="button"
            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl font-medium transition-colors"
            @click="onClose"
          >
            Annuleren
          </button>
          <button
            type="submit"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-xl shadow-md shadow-indigo-600/20 transition-all active:scale-95 flex items-center gap-1.5"
          >
            <PiggyBank class="w-4 h-4" />
            <span>{{ editingGoal ? "Wijzigingen Opslaan" : "Spaarrekening Koppelen" }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
