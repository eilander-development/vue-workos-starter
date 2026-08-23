<script setup lang="ts">
import { computed, ref } from "vue";
import { Sliders, Plus, Play, CheckCircle2, Trash2, Search, Sparkles, Tag } from "lucide-vue-next";
import type { Rule, Transaction, BudgetItem } from "../types";

const props = withDefaults(
  defineProps<{
    rules: Rule[];
    onAddRule: () => void;
    onToggleRule: (id: string, active: boolean) => void;
    onDeleteRule: (id: string) => void;
    onApplyRulesToAll: () => void;
    transactions: Transaction[];
    budgetItems?: BudgetItem[];
  }>(),
  {
    budgetItems: () => [],
  }
);

const searchTerm = ref("");
const testInput = ref("AH 8732 APELDOORN NLD Google Pay");
const appliedMessage = ref<string | null>(null);

const budgetItemMap = computed(() => {
  const map = new Map<string, BudgetItem>();
  props.budgetItems.forEach((i) => map.set(i.id, i));
  return map;
});

const filteredRules = computed(() =>
  props.rules.filter(
    (r) =>
      r.name.toLowerCase().includes(searchTerm.value.toLowerCase()) ||
      r.keyword.toLowerCase().includes(searchTerm.value.toLowerCase()) ||
      r.targetGroup.toLowerCase().includes(searchTerm.value.toLowerCase()) ||
      (r.targetBudgetItemId &&
        budgetItemMap.value
          .get(r.targetBudgetItemId)
          ?.name.toLowerCase()
          .includes(searchTerm.value.toLowerCase()))
  )
);

const matchedRule = computed(() =>
  props.rules.find(
    (r) => r.isActive && testInput.value.toLowerCase().includes(r.keyword.toLowerCase())
  )
);
const matchedItem = computed(() =>
  matchedRule.value?.targetBudgetItemId
    ? budgetItemMap.value.get(matchedRule.value.targetBudgetItemId)
    : undefined
);

function handleRunAll() {
  props.onApplyRulesToAll();
  appliedMessage.value = `Alle ${props.rules.length} koppelregels succesvol toegepast op ${props.transactions.length} transacties!`;
  window.setTimeout(() => {
    appliedMessage.value = null;
  }, 4000);
}
</script>

<template>
  <div id="koppelregels-view" class="space-y-6">
    <div
      class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4"
    >
      <div>
        <div class="flex items-center gap-2">
          <Sliders class="w-5 h-5 text-indigo-400" />
          <h2 class="text-xl font-bold text-white tracking-tight">
            Automatische Koppelregels ({{ rules.length }})
          </h2>
        </div>
        <p class="text-xs text-slate-400 mt-1">
          Herken bankafschriften automatisch op basis van trefwoorden en ken direct de juiste categorie én
          begrotingspost toe
        </p>
      </div>
      <div class="flex items-center gap-3">
        <button
          id="koppelregels-run-all-btn"
          type="button"
          class="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-500 text-white px-3.5 py-2 rounded-xl text-xs font-semibold shadow-md shadow-indigo-600/20 transition-all active:scale-95"
          @click="handleRunAll"
        >
          <Play class="w-3.5 h-3.5" />
          <span>Nu Toepassen op Alle Transacties</span>
        </button>
        <button
          id="koppelregels-add-rule-btn"
          type="button"
          class="flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-500 text-white px-3.5 py-2 rounded-xl text-xs font-semibold shadow-sm transition-all active:scale-95"
          @click="onAddRule"
        >
          <Plus class="w-3.5 h-3.5" />
          <span>Nieuwe Regel</span>
        </button>
      </div>
    </div>

    <div
      v-if="appliedMessage"
      class="bg-emerald-950/80 border border-emerald-700/80 p-4 rounded-2xl flex items-center gap-3 text-emerald-300 text-xs animate-in fade-in slide-in-from-top-2"
    >
      <CheckCircle2 class="w-5 h-5 text-emerald-400 shrink-0" />
      <span class="font-medium">{{ appliedMessage }}</span>
    </div>

    <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm">
      <div class="flex items-center gap-2 mb-3">
        <Sparkles class="w-4 h-4 text-amber-400" />
        <h3 class="text-sm font-bold text-white">Live Regel-Tester</h3>
      </div>
      <p class="text-xs text-slate-400 mb-3">
        Typ een bankafschrift-omschrijving in om direct te testen welke regel en post geactiveerd wordt:
      </p>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <input
          v-model="testInput"
          type="text"
          placeholder="Typ een omschrijving (bijv. Albert Heijn, Shell, Netflix)..."
          class="bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-white placeholder-slate-400 focus:outline-none focus:border-indigo-500"
        />
        <div class="p-3 bg-slate-800/80 rounded-xl border border-slate-700 text-xs">
          <div v-if="matchedRule" class="flex items-center gap-2 text-emerald-400">
            <CheckCircle2 class="w-4 h-4 shrink-0" />
            <div>
              <span class="font-bold">{{ matchedRule.name }}</span>
              <div class="text-slate-400 text-[10px] flex items-center gap-1.5 mt-0.5">
                <span>
                  Rubriek: <strong class="text-slate-300">{{ matchedRule.targetGroup }}</strong>
                </span>
                <template v-if="matchedItem">
                  <span>•</span>
                  <span>
                    Post: <strong class="text-indigo-300">{{ matchedItem.name }}</strong>
                  </span>
                </template>
              </div>
            </div>
          </div>
          <span v-else class="text-slate-400 italic">Geen match gevonden voor deze tekst</span>
        </div>
      </div>
    </div>

    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-sm">
      <div class="relative">
        <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
        <input
          v-model="searchTerm"
          type="text"
          placeholder="Zoek in regels op trefwoord, categorie, post of doel..."
          class="w-full bg-slate-800/80 border border-slate-700 rounded-xl pl-9 pr-4 py-2 text-xs text-white placeholder-slate-400 focus:outline-none focus:border-indigo-500"
        />
      </div>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr
              class="border-b border-slate-800 bg-slate-950/40 text-slate-400 font-semibold uppercase tracking-wider"
            >
              <th class="py-3 px-4">Status</th>
              <th class="py-3 px-4">Regelnaam</th>
              <th class="py-3 px-4">Trefwoord (Filter)</th>
              <th class="py-3 px-4">Doel Categorie & Post</th>
              <th class="py-3 px-4">Type</th>
              <th class="py-3 px-4 text-center">Gematcht</th>
              <th class="py-3 px-4 text-right">Acties</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr
              v-for="rule in filteredRules"
              :key="rule.id"
              class="hover:bg-slate-800/40 transition-colors"
            >
              <td class="py-3.5 px-4">
                <input
                  type="checkbox"
                  :checked="rule.isActive"
                  class="w-4 h-4 accent-indigo-600 rounded cursor-pointer"
                  :title="rule.isActive ? 'Regel is actief' : 'Regel is gepauzeerd'"
                  @change="
                    onToggleRule(rule.id, ($event.target as HTMLInputElement).checked)
                  "
                />
              </td>
              <td class="py-3.5 px-4 font-semibold text-white">{{ rule.name }}</td>
              <td class="py-3.5 px-4">
                <span
                  class="font-mono bg-slate-800 text-indigo-300 px-2.5 py-1 rounded-lg border border-slate-700 text-xs"
                >
                  {{ rule.keyword }}
                </span>
              </td>
              <td class="py-3.5 px-4">
                <div class="space-y-0.5">
                  <span
                    class="bg-slate-800 text-slate-200 px-2 py-0.5 rounded-md border border-slate-700 font-medium text-xs"
                  >
                    {{ rule.targetGroup }}
                  </span>
                  <div
                    v-if="rule.targetBudgetItemId && budgetItemMap.get(rule.targetBudgetItemId)"
                    class="text-[11px] text-indigo-300 flex items-center gap-1 mt-0.5"
                  >
                    <Tag class="w-3 h-3 text-indigo-400" />
                    <span>
                      Post:
                      <strong>{{ budgetItemMap.get(rule.targetBudgetItemId)!.name }}</strong>
                    </span>
                  </div>
                </div>
              </td>
              <td class="py-3.5 px-4">
                <span
                  class="text-[10px] font-bold uppercase px-2 py-0.5 rounded"
                  :class="
                    rule.targetType === 'inkomsten'
                      ? 'bg-emerald-950/60 text-emerald-400 border border-emerald-800/50'
                      : rule.targetType === 'sparen'
                        ? 'bg-blue-950/60 text-blue-400 border border-blue-800/50'
                        : 'bg-rose-950/60 text-rose-400 border border-rose-800/50'
                  "
                >
                  {{ rule.targetType }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-center font-mono text-slate-300 font-semibold">
                {{ rule.matchedCount }}x
              </td>
              <td class="py-3.5 px-4 text-right">
                <button
                  type="button"
                  class="p-1.5 text-slate-400 hover:text-rose-400 hover:bg-slate-800 rounded-lg transition-colors"
                  title="Verwijder regel"
                  @click="onDeleteRule(rule.id)"
                >
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
