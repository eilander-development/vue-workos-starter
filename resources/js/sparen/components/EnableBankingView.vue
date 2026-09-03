<script setup lang="ts">
import { ref } from "vue";
import {
  Landmark,
  RefreshCw,
  ShieldCheck,
  CheckCircle2,
  AlertTriangle,
  Plus,
  Key,
  PiggyBank,
} from "lucide-vue-next";
import type { BankAccount, SavingsGoal, MonthlyBudget, Transaction } from "../types";
import { computePotSettlement, isPotGoal } from "../potSettlement";

const props = withDefaults(
  defineProps<{
    bankAccount: BankAccount;
    savingsGoals: SavingsGoal[];
    currentMonth?: MonthlyBudget;
    transactions?: Transaction[];
    onSync: () => void;
    isSyncing: boolean;
    onOpenAddGoal: () => void;
  }>(),
  {
    transactions: () => [],
  }
);

const autoSync = ref(true);
const syncInterval = ref("30");
const isConnected = () => props.bankAccount.status === "connected";
const consentExpiringSoon = () =>
  isConnected() &&
  props.bankAccount.consentDaysRemaining != null &&
  props.bankAccount.consentDaysRemaining <= 14;

function formatLastSync(iso: string | null | undefined, fallback: string): string {
  if (iso) {
    const parsed = new Date(iso);
    if (!Number.isNaN(parsed.getTime())) {
      return parsed.toLocaleString("nl-NL", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      });
    }
  }
  return fallback || "—";
}

function formatConsentDate(iso: string | null | undefined): string {
  if (!iso) {
    return "";
  }

  return new Date(iso).toLocaleDateString("nl-NL", {
    day: "numeric",
    month: "long",
    year: "numeric",
  });
}

function consentStatusLabel(): string {
  const account = props.bankAccount;
  const until = formatConsentDate(account.consentValidUntil);

  if (isConnected()) {
    if (account.consentDaysRemaining == null) {
      return until ? `Geldig tot ${until}` : "Geldig";
    }
    if (account.consentDaysRemaining === 0) {
      return until ? `Verloopt vandaag (${until})` : "Verloopt vandaag";
    }
    const days =
      account.consentDaysRemaining === 1
        ? "nog 1 dag"
        : `nog ${account.consentDaysRemaining} dagen`;
    return until ? `Geldig tot ${until} (${days})` : `Geldig (${days})`;
  }

  if (account.consentValidUntil) {
    return until ? `Verlopen op ${until}` : "Verlopen — opnieuw koppelen";
  }

  return "Niet gekoppeld";
}

function potFor(goal: SavingsGoal) {
  return isPotGoal(goal) && props.currentMonth
    ? computePotSettlement(goal, props.currentMonth, props.transactions)
    : null;
}
</script>

<template>
  <div id="enablebanking-view" class="space-y-6">
    <div
      class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4"
    >
      <div>
        <div class="flex items-center gap-2">
          <Landmark class="w-5 h-5 text-indigo-400" />
          <h2 class="text-xl font-bold text-white tracking-tight">
            EnableBanking PSD2 Bankkoppeling & Rekeningen
          </h2>
        </div>
        <p class="text-xs text-slate-400 mt-1">
          Gecertificeerde Open Banking API koppeling met automatische IBAN-toewijzing voor spaardoelen
        </p>
      </div>

      <div class="flex items-center gap-3">
        <div
          class="flex items-center gap-2 border px-3 py-1.5 rounded-xl text-xs font-semibold"
          :class="
            isConnected()
              ? 'bg-emerald-950/60 border-emerald-800/60 text-emerald-400'
              : 'bg-amber-950/60 border-amber-800/60 text-amber-400'
          "
        >
          <span
            class="w-2 h-2 rounded-full"
            :class="isConnected() ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400'"
          />
          <span>{{ isConnected() ? "PSD2 Verbinding Actief" : "PSD2 Niet Gekoppeld" }}</span>
        </div>

        <button
          id="enablebanking-sync-now-btn"
          type="button"
          :disabled="isSyncing"
          class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-xl text-xs font-semibold shadow-md shadow-indigo-600/20 transition-all active:scale-95 disabled:opacity-50"
          @click="onSync"
        >
          <RefreshCw class="w-3.5 h-3.5" :class="isSyncing ? 'animate-spin' : ''" />
          <span>{{ isSyncing ? "Gegevens Ophalen..." : "Nu Synchroniseren" }}</span>
        </button>
      </div>
    </div>

    <div
      class="bg-gradient-to-br from-slate-900 via-slate-850 to-slate-900 border border-slate-700/80 p-6 rounded-2xl shadow-md relative overflow-hidden"
    >
      <div class="flex items-start justify-between">
        <div class="flex items-center gap-3">
          <div
            class="w-12 h-12 rounded-xl bg-orange-500/10 border border-orange-500/30 flex items-center justify-center text-orange-400 font-black text-base"
          >
            ING
          </div>
          <div>
            <div class="flex items-center gap-2">
              <h3 class="font-bold text-white text-base">ING Betaalrekening</h3>
              <span
                class="text-[10px] font-semibold border px-2 py-0.5 rounded-full"
                :class="
                  isConnected()
                    ? 'bg-emerald-950 text-emerald-400 border-emerald-800'
                    : 'bg-amber-950 text-amber-400 border-amber-800'
                "
              >
                {{ isConnected() ? "Direct Verbonden via PSD2" : "Nog niet verbonden via PSD2" }}
              </span>
            </div>
            <p class="text-xs text-slate-400">Mark Eilander</p>
          </div>
        </div>
        <span class="text-[11px] font-mono text-slate-400 text-right">
          <span class="block">Laatste update: {{ formatLastSync(bankAccount.lastSyncedAt, bankAccount.lastSync) }}</span>
          <span v-if="bankAccount.consentValidUntil" class="block mt-0.5" :class="isConnected() ? 'text-slate-500' : 'text-amber-400'">
            {{
              isConnected()
                ? `Consent tot ${formatConsentDate(bankAccount.consentValidUntil)}`
                : `Verlopen op ${formatConsentDate(bankAccount.consentValidUntil)}`
            }}
          </span>
        </span>
      </div>

      <div class="mt-6 flex flex-col sm:flex-row sm:items-baseline justify-between gap-4">
        <div>
          <span class="text-xs text-slate-400 block font-medium">Huidig Rekening Saldo:</span>
          <div class="text-3xl font-black text-white font-mono tracking-tight mt-1">
            € {{ bankAccount.balance.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
          </div>
        </div>

        <div class="bg-slate-800/80 border border-slate-700 px-4 py-2 rounded-xl text-xs font-mono">
          <span class="text-slate-400 block text-[10px]">IBAN Betaalrekening:</span>
          <span class="text-slate-200 font-bold">{{ bankAccount.iban || "—" }}</span>
        </div>
      </div>
    </div>

    <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <div class="flex items-center gap-2">
            <PiggyBank class="w-5 h-5 text-indigo-400" />
            <h3 class="font-bold text-white text-base">Gekoppelde Spaarrekeningen & Spaardoelen</h3>
          </div>
          <p class="text-xs text-slate-400 mt-0.5">
            Spaardoelen én potjes (boodschappen/benzine). Potjes tonen hoeveel je nog van pot → rekening moet
            overzetten.
          </p>
        </div>

        <button
          id="enablebanking-add-savings-btn"
          type="button"
          class="flex items-center gap-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white px-3.5 py-2 rounded-xl text-xs font-semibold border border-slate-700 transition-all self-start sm:self-auto"
          @click="onOpenAddGoal"
        >
          <Plus class="w-3.5 h-3.5" />
          <span>Spaarrekening / Potje Toevoegen</span>
        </button>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 pt-2">
        <div
          v-for="goal in savingsGoals"
          :key="goal.id"
          class="p-3.5 bg-slate-800/70 rounded-xl border border-slate-700/80 flex flex-col justify-between"
        >
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <span class="font-bold text-white text-xs truncate max-w-[170px]">{{ goal.name }}</span>
              <span
                class="text-[9px] border px-1.5 py-0.5 rounded font-semibold uppercase"
                :class="
                  potFor(goal)
                    ? 'bg-amber-950 text-amber-300 border-amber-800'
                    : 'bg-indigo-950 text-indigo-400 border-indigo-800'
                "
              >
                {{ potFor(goal) ? "Potje" : "Spaardoel" }}
              </span>
            </div>
            <span class="text-[11px] text-slate-400 block mb-2">{{ goal.bankName }}</span>

            <div class="bg-slate-900/80 px-2 py-1 rounded text-[11px] text-slate-300 border border-slate-800 space-y-1">
              <div>
                <span class="text-[9px] text-slate-500 block">Omschrijving</span>
                <span class="font-semibold text-white break-words">{{ goal.name }}</span>
              </div>
              <div>
                <span class="text-[9px] text-slate-500 block">IBAN</span>
                <span class="font-semibold font-mono text-emerald-400">
                  {{ goal.accountIban || "Geen eigen spaar-IBAN" }}
                </span>
              </div>
              <div v-if="potFor(goal)?.budgetItem">
                <span class="text-[9px] text-slate-500 block">Rubriek</span>
                <span class="font-semibold text-white">
                  {{ potFor(goal)!.budgetItem!.group }} › {{ potFor(goal)!.budgetItem!.name }}
                </span>
              </div>
            </div>

            <div
              v-if="potFor(goal)"
              class="mt-2 bg-amber-950/30 border border-amber-800/50 rounded-lg px-2 py-1.5 space-y-0.5 text-[10px] font-mono"
            >
              <div class="flex justify-between text-slate-300">
                <span>Begroot / in pot</span>
                <span>
                  € {{ potFor(goal)!.budgeted.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
                </span>
              </div>
              <div class="flex justify-between text-rose-300">
                <span>Uitgegeven (bank)</span>
                <span>
                  € {{ potFor(goal)!.spent.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
                </span>
              </div>
              <div class="flex justify-between text-slate-400">
                <span>Al gecompenseerd</span>
                <span>
                  €
                  {{ potFor(goal)!.compensated.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
                </span>
              </div>
              <div
                class="flex justify-between font-bold pt-0.5 border-t border-amber-800/40"
                :class="potFor(goal)!.toTransfer > 0 ? 'text-amber-300' : 'text-emerald-400'"
              >
                <span>Nog over te zetten</span>
                <span>
                  €
                  {{ potFor(goal)!.toTransfer.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
                </span>
              </div>
            </div>
          </div>

          <div class="mt-3 pt-2 border-t border-slate-750 flex items-center justify-between text-[10px] text-slate-400">
            <span>Inleg: €{{ goal.monthlyContribution }}/mnd</span>
            <span>
              {{
                potFor(goal)
                  ? `${potFor(goal)!.linkedTxCount} mutaties`
                  : `Doel: €${goal.targetAmount}`
              }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div class="flex items-center gap-2 mb-4">
          <ShieldCheck class="w-5 h-5 text-emerald-400" />
          <h3 class="font-bold text-white text-base">PSD2 Autorisatie & Beveiliging</h3>
        </div>

        <div class="space-y-3 text-xs">
          <div class="flex justify-between py-2 border-b border-slate-800">
            <span class="text-slate-400">Verlener:</span>
            <span class="font-semibold text-white">EnableBanking Open Banking AISP</span>
          </div>
          <div class="flex justify-between py-2 border-b border-slate-800">
            <span class="text-slate-400">Gekoppelde Betaalbank:</span>
            <span class="font-semibold text-white">ING Bank N.V. (Nederland)</span>
          </div>
          <div class="flex justify-between py-2 border-b border-slate-800">
            <span class="text-slate-400">Consent Status:</span>
            <span
              class="font-semibold flex items-center gap-1 text-right max-w-[70%]"
              :class="
                isConnected()
                  ? consentExpiringSoon()
                    ? 'text-amber-400'
                    : 'text-emerald-400'
                  : 'text-amber-400'
              "
            >
              <CheckCircle2 v-if="isConnected() && !consentExpiringSoon()" class="w-3.5 h-3.5 shrink-0" />
              <AlertTriangle v-else class="w-3.5 h-3.5 shrink-0" />
              {{ consentStatusLabel() }}
            </span>
          </div>
          <div class="flex justify-between py-2 border-b border-slate-800">
            <span class="text-slate-400">Sessie Encryptie:</span>
            <span class="font-mono text-slate-300">TLS 1.3 / AES-256 GCM</span>
          </div>
          <div class="flex justify-between py-2">
            <span class="text-slate-400">Toegestane Rechten:</span>
            <span class="text-slate-200">Alleen Leestoegang (Saldo & Transactiehistorie)</span>
          </div>
        </div>
      </div>

      <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div class="flex items-center gap-2 mb-4">
          <Key class="w-5 h-5 text-indigo-400" />
          <h3 class="font-bold text-white text-base">Synchronisatie Instellingen</h3>
        </div>

        <div class="space-y-4 text-xs">
          <div class="flex items-center justify-between">
            <div>
              <span class="font-semibold text-white block">Automatische Achtergrond Sync</span>
              <span class="text-slate-400 text-[11px]">Periodiek saldo & mutaties synchroniseren</span>
            </div>
            <input
              v-model="autoSync"
              type="checkbox"
              class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 bg-slate-800 border-slate-700 cursor-pointer"
            />
          </div>

          <div>
            <label class="block text-slate-400 font-medium mb-1.5">
              Achtergrond Sync Interval (PSD2 Limiet)
            </label>
            <select
              v-model="syncInterval"
              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-white text-xs focus:outline-none focus:border-indigo-500"
            >
              <option value="360">Elke 6 uur (Max 4x/dag — conform PSD2)</option>
              <option value="720">2x per dag (Ochtend & Avond)</option>
              <option value="1440">1x per dag (Dagelijkse update)</option>
              <option value="0">Alleen bij openen app & handmatig</option>
            </select>
          </div>

          <div
            class="p-3 bg-slate-800/60 rounded-xl border border-slate-700/60 text-[11px] text-slate-400 space-y-1.5"
          >
            <span class="text-slate-200 font-semibold block">PSD2 Wetgeving & Gratis Koppeling:</span>
            <p>
              Volgens de Europese PSD2-richtlijn mogen banken (zoals ING) maximaal
              <strong class="text-amber-300">4 achtergrond-syncs per 24 uur</strong> uitvoeren zonder dat je
              actief bent.
            </p>
            <p class="text-emerald-400">
              ✓ <strong>Onbeperkt bij app-gebruik:</strong> Elke keer dat je de app opent of op
              <em>"Nu Synchroniseren"</em> klikt, wordt er wél direct een live verversing gedaan.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
