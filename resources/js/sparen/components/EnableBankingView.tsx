import React, { useState } from "react";
import {
  Landmark,
  Wifi,
  RefreshCw,
  ShieldCheck,
  CheckCircle2,
  AlertTriangle,
  ArrowDownRight,
  ArrowUpRight,
  Plus,
  Key,
  ExternalLink,
  CreditCard,
  PiggyBank,
  Info,
  Layers,
} from "lucide-react";
import { BankAccount, SavingsGoal, MonthlyBudget, Transaction } from "../types";
import { computePotSettlement, isPotGoal } from "../potSettlement";

interface EnableBankingViewProps {
  bankAccount: BankAccount;
  savingsGoals: SavingsGoal[];
  currentMonth?: MonthlyBudget;
  transactions?: Transaction[];
  onSync: () => void;
  isSyncing: boolean;
  onOpenAddGoal: () => void;
}

export const EnableBankingView: React.FC<EnableBankingViewProps> = ({
  bankAccount,
  savingsGoals,
  currentMonth,
  transactions = [],
  onSync,
  isSyncing,
  onOpenAddGoal,
}) => {
  const [autoSync, setAutoSync] = useState(true);
  const [syncInterval, setSyncInterval] = useState("30");
  const isConnected = bankAccount.status === "connected";

  return (
    <div id="enablebanking-view" className="space-y-6">
      {/* Top Header */}
      <div className="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <Landmark className="w-5 h-5 text-indigo-400" />
            <h2 className="text-xl font-bold text-white tracking-tight">
              EnableBanking PSD2 Bankkoppeling & Rekeningen
            </h2>
          </div>
          <p className="text-xs text-slate-400 mt-1">
            Gecertificeerde Open Banking API koppeling met automatische IBAN-toewijzing voor spaardoelen
          </p>
        </div>

        <div className="flex items-center gap-3">
          <div
            className={`flex items-center gap-2 border px-3 py-1.5 rounded-xl text-xs font-semibold ${
              isConnected
                ? "bg-emerald-950/60 border-emerald-800/60 text-emerald-400"
                : "bg-amber-950/60 border-amber-800/60 text-amber-400"
            }`}
          >
            <span className={`w-2 h-2 rounded-full ${isConnected ? "bg-emerald-400 animate-pulse" : "bg-amber-400"}`} />
            <span>{isConnected ? "PSD2 Verbinding Actief" : "PSD2 Niet Gekoppeld"}</span>
          </div>

          <button
            id="enablebanking-sync-now-btn"
            onClick={onSync}
            disabled={isSyncing}
            className="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-xl text-xs font-semibold shadow-md shadow-indigo-600/20 transition-all active:scale-95 disabled:opacity-50"
          >
            <RefreshCw className={`w-3.5 h-3.5 ${isSyncing ? "animate-spin" : ""}`} />
            <span>{isSyncing ? "Gegevens Ophalen..." : "Nu Synchroniseren"}</span>
          </button>
        </div>
      </div>

      {/* Primary PSD2 Connected Account */}
      <div className="bg-gradient-to-br from-slate-900 via-slate-850 to-slate-900 border border-slate-700/80 p-6 rounded-2xl shadow-md relative overflow-hidden">
        <div className="flex items-start justify-between">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-xl bg-orange-500/10 border border-orange-500/30 flex items-center justify-center text-orange-400 font-black text-base">
              ING
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="font-bold text-white text-base">ING Betaalrekening</h3>
                <span
                  className={`text-[10px] font-semibold border px-2 py-0.5 rounded-full ${
                    isConnected
                      ? "bg-emerald-950 text-emerald-400 border-emerald-800"
                      : "bg-amber-950 text-amber-400 border-amber-800"
                  }`}
                >
                  {isConnected ? "Direct Verbonden via PSD2" : "Nog niet verbonden via PSD2"}
                </span>
              </div>
              <p className="text-xs text-slate-400">Mark Eilander</p>
            </div>
          </div>
          <span className="text-[11px] font-mono text-slate-400">
            Laatste sync: {bankAccount.lastSync || "—"}
          </span>
        </div>

        <div className="mt-6 flex flex-col sm:flex-row sm:items-baseline justify-between gap-4">
          <div>
            <span className="text-xs text-slate-400 block font-medium">Huidig Rekening Saldo:</span>
            <div className="text-3xl font-black text-white font-mono tracking-tight mt-1">
              € {bankAccount.balance.toLocaleString("nl-NL", { minimumFractionDigits: 2 })}
            </div>
          </div>

          <div className="bg-slate-800/80 border border-slate-700 px-4 py-2 rounded-xl text-xs font-mono">
            <span className="text-slate-400 block text-[10px]">IBAN Betaalrekening:</span>
            <span className="text-slate-200 font-bold">{bankAccount.iban || "—"}</span>
          </div>
        </div>
      </div>

      {/* Spaarrekeningen & Spaardoelen Mapping Section */}
      <div className="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm space-y-4">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <div className="flex items-center gap-2">
              <PiggyBank className="w-5 h-5 text-indigo-400" />
              <h3 className="font-bold text-white text-base">
                Gekoppelde Spaarrekeningen & Spaardoelen
              </h3>
            </div>
            <p className="text-xs text-slate-400 mt-0.5">
              Spaardoelen én potjes (boodschappen/benzine). Potjes tonen hoeveel je nog van pot → rekening moet overzetten.
            </p>
          </div>

          <button
            id="enablebanking-add-savings-btn"
            onClick={onOpenAddGoal}
            className="flex items-center gap-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white px-3.5 py-2 rounded-xl text-xs font-semibold border border-slate-700 transition-all self-start sm:self-auto"
          >
            <Plus className="w-3.5 h-3.5" />
            <span>Spaarrekening / Potje Toevoegen</span>
          </button>
        </div>

        {/* List of Registered Savings Accounts */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 pt-2">
          {savingsGoals.map((goal) => {
            const pot =
              isPotGoal(goal) && currentMonth
                ? computePotSettlement(goal, currentMonth, transactions)
                : null;

            return (
            <div
              key={goal.id}
              className="p-3.5 bg-slate-800/70 rounded-xl border border-slate-700/80 flex flex-col justify-between"
            >
              <div>
                <div className="flex items-center justify-between mb-1.5">
                  <span className="font-bold text-white text-xs truncate max-w-[170px]">{goal.name}</span>
                  <span
                    className={`text-[9px] border px-1.5 py-0.5 rounded font-semibold uppercase ${
                      pot
                        ? "bg-amber-950 text-amber-300 border-amber-800"
                        : "bg-indigo-950 text-indigo-400 border-indigo-800"
                    }`}
                  >
                    {pot ? "Potje" : "Spaardoel"}
                  </span>
                </div>
                <span className="text-[11px] text-slate-400 block mb-2">{goal.bankName}</span>

                <div className="bg-slate-900/80 px-2 py-1 rounded text-[11px] text-slate-300 border border-slate-800 space-y-1">
                  <div>
                    <span className="text-[9px] text-slate-500 block">Omschrijving</span>
                    <span className="font-semibold text-white break-words">{goal.name}</span>
                  </div>
                  <div>
                    <span className="text-[9px] text-slate-500 block">IBAN</span>
                    <span className="font-semibold font-mono text-emerald-400">
                      {goal.accountIban || "Geen eigen spaar-IBAN"}
                    </span>
                  </div>
                  {pot?.budgetItem && (
                    <div>
                      <span className="text-[9px] text-slate-500 block">Rubriek</span>
                      <span className="font-semibold text-white">
                        {pot.budgetItem.group} › {pot.budgetItem.name}
                      </span>
                    </div>
                  )}
                </div>

                {pot && (
                  <div className="mt-2 bg-amber-950/30 border border-amber-800/50 rounded-lg px-2 py-1.5 space-y-0.5 text-[10px] font-mono">
                    <div className="flex justify-between text-slate-300">
                      <span>Begroot / in pot</span>
                      <span>€ {pot.budgeted.toLocaleString("nl-NL", { minimumFractionDigits: 2 })}</span>
                    </div>
                    <div className="flex justify-between text-rose-300">
                      <span>Uitgegeven (bank)</span>
                      <span>€ {pot.spent.toLocaleString("nl-NL", { minimumFractionDigits: 2 })}</span>
                    </div>
                    <div className="flex justify-between text-slate-400">
                      <span>Al gecompenseerd</span>
                      <span>€ {pot.compensated.toLocaleString("nl-NL", { minimumFractionDigits: 2 })}</span>
                    </div>
                    <div
                      className={`flex justify-between font-bold pt-0.5 border-t border-amber-800/40 ${
                        pot.toTransfer > 0 ? "text-amber-300" : "text-emerald-400"
                      }`}
                    >
                      <span>Nog over te zetten</span>
                      <span>€ {pot.toTransfer.toLocaleString("nl-NL", { minimumFractionDigits: 2 })}</span>
                    </div>
                  </div>
                )}
              </div>

              <div className="mt-3 pt-2 border-t border-slate-750 flex items-center justify-between text-[10px] text-slate-400">
                <span>Inleg: €{goal.monthlyContribution}/mnd</span>
                <span>{pot ? `${pot.linkedTxCount} mutaties` : `Doel: €${goal.targetAmount}`}</span>
              </div>
            </div>
            );
          })}
        </div>
      </div>

      {/* Connection Details & API Settings */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm">
          <div className="flex items-center gap-2 mb-4">
            <ShieldCheck className="w-5 h-5 text-emerald-400" />
            <h3 className="font-bold text-white text-base">PSD2 Autorisatie & Beveiliging</h3>
          </div>

          <div className="space-y-3 text-xs">
            <div className="flex justify-between py-2 border-b border-slate-800">
              <span className="text-slate-400">Verlener:</span>
              <span className="font-semibold text-white">EnableBanking Open Banking AISP</span>
            </div>
            <div className="flex justify-between py-2 border-b border-slate-800">
              <span className="text-slate-400">Gekoppelde Betaalbank:</span>
              <span className="font-semibold text-white">ING Bank N.V. (Nederland)</span>
            </div>
            <div className="flex justify-between py-2 border-b border-slate-800">
              <span className="text-slate-400">Consent Status:</span>
              <span className="text-emerald-400 font-semibold flex items-center gap-1">
                <CheckCircle2 className="w-3.5 h-3.5" /> Geldig (Nog 88 dagen)
              </span>
            </div>
            <div className="flex justify-between py-2 border-b border-slate-800">
              <span className="text-slate-400">Sessie Encryptie:</span>
              <span className="font-mono text-slate-300">TLS 1.3 / AES-256 GCM</span>
            </div>
            <div className="flex justify-between py-2">
              <span className="text-slate-400">Toegestane Rechten:</span>
              <span className="text-slate-200">Alleen Leestoegang (Saldo & Transactiehistorie)</span>
            </div>
          </div>
        </div>

        <div className="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm">
          <div className="flex items-center gap-2 mb-4">
            <Key className="w-5 h-5 text-indigo-400" />
            <h3 className="font-bold text-white text-base">Synchronisatie Instellingen</h3>
          </div>

          <div className="space-y-4 text-xs">
            <div className="flex items-center justify-between">
              <div>
                <span className="font-semibold text-white block">Automatische Achtergrond Sync</span>
                <span className="text-slate-400 text-[11px]">Periodiek saldo & mutaties synchroniseren</span>
              </div>
              <input
                type="checkbox"
                checked={autoSync}
                onChange={(e) => setAutoSync(e.target.checked)}
                className="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 bg-slate-800 border-slate-700 cursor-pointer"
              />
            </div>

            <div>
              <label className="block text-slate-400 font-medium mb-1.5">Achtergrond Sync Interval (PSD2 Limiet)</label>
              <select
                value={syncInterval}
                onChange={(e) => setSyncInterval(e.target.value)}
                className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-white text-xs focus:outline-none focus:border-indigo-500"
              >
                <option value="360">Elke 6 uur (Max 4x/dag — conform PSD2)</option>
                <option value="720">2x per dag (Ochtend & Avond)</option>
                <option value="1440">1x per dag (Dagelijkse update)</option>
                <option value="0">Alleen bij openen app & handmatig</option>
              </select>
            </div>

            <div className="p-3 bg-slate-800/60 rounded-xl border border-slate-700/60 text-[11px] text-slate-400 space-y-1.5">
              <span className="text-slate-200 font-semibold block">PSD2 Wetgeving & Gratis Koppeling:</span>
              <p>
                Volgens de Europese PSD2-richtlijn mogen banken (zoals ING) maximaal <strong className="text-amber-300">4 achtergrond-syncs per 24 uur</strong> uitvoeren zonder dat je actief bent.
              </p>
              <p className="text-emerald-400">
                ✓ <strong>Onbeperkt bij app-gebruik:</strong> Elke keer dat je de app opent of op <em>"Nu Synchroniseren"</em> klikt, wordt er wél direct een live verversing gedaan.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};
