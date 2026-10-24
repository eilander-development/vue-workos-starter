<script setup lang="ts">
import ModalShell from "./ModalShell.vue";
import { computed, ref } from "vue";
import type { SyncReport } from "../syncReport";
import { isPendingSyncIssue, pendingSyncSummary } from "../syncReport";
const props = defineProps<{ report: SyncReport }>();
const emit = defineEmits<{ close: [] }>();
const filter = ref("all");
const search = ref("");
const rows = computed(() => (props.report.issues ?? []).filter(row => {
  if (filter.value === "blocked" && isPendingSyncIssue(row)) return false;
  if (filter.value === "pending" && !isPendingSyncIssue(row)) return false;
  const text = `${row.description} ${row.date ?? ""} ${row.reference ?? ""} ${row.account ?? ""} ${row.reason}`.toLowerCase();
  return text.includes(search.value.trim().toLowerCase());
}));
function date(value: string | null) {
  return value ? value.slice(0, 10).split("-").reverse().join("-") : "Ontbreekt";
}
function euro(value: number | null) {
  return value === null ? "Ongeldig / ontbreekt" : value.toLocaleString("nl-NL", { style: "currency", currency: "EUR" });
}
</script>

<template>
  <ModalShell role="dialog" aria-modal="true" aria-labelledby="sync-report-title" :on-close="() => emit('close')" :close-on-backdrop="false">
    <div class="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-4xl max-h-[90vh] flex flex-col text-slate-200">
      <div class="p-5 border-b border-slate-800 flex justify-between items-start gap-4">
        <div>
          <h2 id="sync-report-title" class="text-lg font-bold text-white">Synchronisatierapport</h2>
          <p class="text-xs text-slate-400 mt-1">{{ report.imported }} nieuw &middot; {{ report.duplicates }} al bekend &middot; {{ report.blocked ?? 0 }} geblokkeerd &middot; {{ report.pending ?? 0 }} nog niet geboekt</p>
          <p v-if="report.pending" class="text-xs text-amber-200 mt-2">{{ pendingSyncSummary(report) }}.</p>
          <p v-if="report.syncedAt" class="text-xs text-slate-400 mt-1">{{ new Date(report.syncedAt).toLocaleString('nl-NL') }}</p>
        </div>
        <button type="button" class="px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700" @click="emit('close')">Sluiten</button>
      </div>
      <div class="p-4 flex flex-wrap gap-3 border-b border-slate-800">
        <label class="text-xs">Toon
          <select v-model="filter" class="ml-2 bg-slate-800 rounded-lg p-2">
            <option value="all">Alle aandachtspunten</option>
            <option value="blocked">Geblokkeerd</option>
            <option value="pending">Nog niet geboekt</option>
          </select>
        </label>
        <input v-model="search" aria-label="Zoek in synchronisatierapport" placeholder="Zoek omschrijving, datum of referentie" class="bg-slate-800 rounded-lg px-3 py-2 text-xs flex-1 min-w-48" />
      </div>
      <div class="p-5 overflow-y-auto space-y-4">
        <p v-if="filter !== 'blocked' && report.pending" class="text-sm text-slate-300">
          Bij ‘Nog niet geboekt’ zie je betalingen die op de bank wachten en oude voorlopige registraties die nog verwerkt moeten worden. De melding bij elke betaling geeft aan wat er nog nodig is.
        </p>
        <p v-if="!rows.length" class="text-sm text-slate-400">Geen transacties voor deze selectie.</p>
        <section v-for="(row, index) in rows" :key="index" class="border border-slate-700 rounded-xl p-4 space-y-3">
          <div class="flex justify-between gap-3">
            <div>
              <p class="font-semibold text-white">{{ row.description }}</p>
              <p class="text-xs text-slate-400">{{ date(row.date) }} &middot; {{ euro(row.amount) }}</p>
            </div>
            <div class="text-xs shrink-0 text-right" :class="isPendingSyncIssue(row) ? 'text-amber-300' : 'text-rose-300'">
              <span>{{ isPendingSyncIssue(row) ? 'Nog niet geboekt' : 'Geblokkeerd' }}</span>
              <span v-if="row.code === 'pending'" class="block mt-1 text-slate-400">Wacht op de bank</span>
              <span v-if="row.code === 'pending_booking'" class="block mt-1">Verwerking nodig</span>
            </div>
          </div>
          <p class="text-sm text-slate-300">{{ row.reason }}</p>
          <p v-if="row.code === 'pending_booking' && row.existing.some(existing => !existing.differences.length)" class="text-sm text-amber-200">
            De definitieve boeking staat ook al opgeslagen. De oude voorlopige registratie moet nog worden gecontroleerd, zodat dezelfde betaling niet dubbel blijft staan.
          </p>
          <p class="text-xs text-slate-400 break-all">Rekening: {{ row.account || 'ontbreekt' }} &middot; Bankreferentie: {{ row.reference || 'ontbreekt' }}</p>
          <div v-for="existing in row.existing" :key="existing.id" class="bg-slate-950/60 rounded-lg p-3 text-xs space-y-2">
            <p class="text-slate-300">Bestaande boeking: {{ existing.description }}</p>
            <table class="w-full text-left">
              <thead><tr class="text-slate-400"><th class="pb-2">Gegeven</th><th>Opgeslagen</th><th>{{ row.code === 'pending_booking' ? 'Definitieve boeking' : 'Nu van bank' }}</th></tr></thead>
              <tbody>
                <tr :class="existing.differences.includes('boekdatum') ? 'text-amber-300' : ''"><td>{{ row.code === 'pending_booking' ? 'Datum' : 'Boekdatum' }}</td><td>{{ date(existing.date) }}</td><td>{{ date(row.date) }}</td></tr>
                <tr :class="existing.differences.includes('bedrag') ? 'text-amber-300' : ''"><td>Bedrag</td><td>{{ euro(existing.amount) }}</td><td>{{ euro(row.amount) }}</td></tr>
              </tbody>
            </table>
            <p class="text-amber-300">Afwijkend: {{ existing.differences.join(', ') || 'geen verschil met deze rij' }}</p>
          </div>
        </section>
      </div>
    </div>
  </ModalShell>
</template>
