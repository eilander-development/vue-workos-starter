<script setup lang="ts">
import { onMounted, ref } from "vue";
import { loadDuplicateTransactions, removeDuplicateTransaction } from "../api";

type Row = { id: number; key: string; date: string; description: string; amount: number; account: string; importedAt: string | null; category: string | null; budget: string | null };
type Group = { reference: string; rows: Row[] };
const emit = defineEmits<{ close: []; deleted: [key: string] }>();
const groups = ref<Group[]>([]);
const keepIds = ref<Record<string, number>>({});
const pending = ref<{ keep: Row; remove: Row } | null>(null);
const loading = ref(true);
const saving = ref(false);
const error = ref("");

onMounted(async () => {
  try {
    const data = await loadDuplicateTransactions();
    groups.value = data.groups;
    groups.value.forEach((group) => { keepIds.value[group.rows[0].key] = group.rows[0].id; });
  } catch (e) { error.value = e instanceof Error ? e.message : "Laden mislukt"; }
  finally { loading.value = false; }
});

function review(group: Group, remove: Row) {
  const keep = group.rows.find(row => row.id === keepIds.value[group.rows[0].key]);
  if (keep && keep.id !== remove.id) pending.value = { keep, remove };
}

async function confirmRemoval() {
  if (!pending.value || saving.value) return;
  saving.value = true;
  error.value = "";
  const { keep, remove } = pending.value;
  try {
    const result = await removeDuplicateTransaction(keep.id, remove.id);
    emit("deleted", result.deletedKey);
    groups.value = groups.value.map(group => {
      const selected = keepIds.value[group.rows[0].key];
      const rows = group.rows.filter(row => row.id !== remove.id);
      if (rows.length) keepIds.value[rows[0].key] = selected;
      return { ...group, rows };
    }).filter(group => group.rows.length > 1);
    pending.value = null;
  } catch (e) { error.value = e instanceof Error ? e.message : "Verwijderen mislukt"; }
  finally { saving.value = false; }
}

function euro(amount: number) {
  return amount.toLocaleString("nl-NL", { style: "currency", currency: "EUR" });
}
</script>

<template>
  <div class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="duplicates-title">
    <div class="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-3xl max-h-[90vh] flex flex-col text-slate-200">
      <div class="p-5 border-b border-slate-800 flex justify-between items-start gap-4">
        <div>
          <h2 id="duplicates-title" class="text-lg font-bold text-white">Dubbele transacties</h2>
          <p class="text-xs text-slate-400 mt-1">Alle periodes &middot; dezelfde bankreferentie, rekening, datum en bedrag.</p>
          <p class="text-xs text-slate-400 mt-1">Bewaar de rij met de juiste koppeling. Een ontbrekende koppeling op de bewaarde rij blokkeert het verwijderen.</p>
        </div>
        <button type="button" :disabled="saving" class="text-sm hover:text-white disabled:opacity-50" @click="emit('close')">Sluiten</button>
      </div>
      <div class="p-5 overflow-y-auto space-y-4">
        <p v-if="error" role="alert" class="text-rose-300">{{ error }}</p>
        <p v-if="loading" class="text-sm">Dubbele transacties zoeken&hellip;</p>
        <p v-else-if="!groups.length && !error" class="text-sm">Geen bevestigde dubbele banktransacties gevonden.</p>
        <section v-for="group in groups" :key="group.rows[0].key" class="border border-slate-700 rounded-xl overflow-hidden">
          <div class="bg-slate-800 p-3 text-xs space-y-1">
            <p class="font-semibold">{{ group.rows[0].date }} &middot; {{ euro(group.rows[0].amount) }} &middot; {{ group.rows.length }} rijen</p>
            <p class="break-all text-slate-400">Bankreferentie: {{ group.reference }} &middot; {{ group.rows[0].account }}</p>
          </div>
          <div v-for="row in group.rows" :key="row.id" class="p-3 border-t border-slate-800 flex flex-wrap items-center gap-3 text-xs">
            <label class="flex items-center gap-2 shrink-0">
              <input v-model="keepIds[group.rows[0].key]" type="radio" :name="`keep-${group.rows[0].key}`" :value="row.id" :disabled="saving || !!pending" />
              Bewaren
            </label>
            <div class="flex-1 min-w-40">
              <p class="text-white">{{ row.description }}</p>
              <p class="text-indigo-300 mt-1">{{ row.category || 'Geen categorie' }} / {{ row.budget || 'Geen begrotingspost' }}</p>
              <p class="text-slate-400 mt-1">Rij #{{ row.id }} &middot; ge&iuml;mporteerd {{ row.importedAt || 'onbekend' }}</p>
            </div>
            <span class="font-mono">{{ euro(row.amount) }}</span>
            <button type="button" :disabled="row.id === keepIds[group.rows[0].key] || saving || !!pending" class="text-rose-300 border border-rose-900 rounded-lg px-3 py-2 disabled:opacity-30" @click="review(group, row)">Dubbele rij verwijderen</button>
          </div>
        </section>
      </div>
      <div v-if="pending" class="p-5 border-t border-rose-900 bg-rose-950/30 text-sm space-y-3">
        <p>Rij #{{ pending.remove.id }} ({{ euro(pending.remove.amount) }}) verwijderen en rij #{{ pending.keep.id }} bewaren? Dit verwijdert de dubbele registratie uit de gedeelde database; het banksaldo blijft ongewijzigd.</p>
        <div class="flex gap-3">
          <button type="button" :disabled="saving" class="bg-rose-600 rounded-lg px-4 py-2 disabled:opacity-50" @click="confirmRemoval">{{ saving ? 'Bezig...' : 'Ja, dubbele rij verwijderen' }}</button>
          <button type="button" :disabled="saving" class="bg-slate-800 rounded-lg px-4 py-2" @click="pending = null">Annuleren</button>
        </div>
      </div>
    </div>
  </div>
</template>
