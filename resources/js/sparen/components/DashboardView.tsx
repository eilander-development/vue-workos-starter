import React, { useState } from "react";
import {
  TrendingUp,
  TrendingDown,
  Wallet,
  PiggyBank,
  CheckCircle,
  Clock,
  ArrowUpRight,
  ArrowDownRight,
  ShieldCheck,
  Zap,
  Building2,
  Car,
  Receipt,
  ShoppingBag,
  ExternalLink,
  Filter,
  Layers,
} from "lucide-react";
import {
  ResponsiveContainer,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  Legend,
  CartesianGrid,
  LineChart,
  Line,
} from "recharts";
import { MonthlyBudget, Transaction, BankAccount, ActiveTab } from "../types";
import type { BudgetItem } from "../types";
import { TransactionDate } from "./TransactionDate";
import { monthDatePrefix } from "../month";
import { KpiBreakdownModal } from "./KpiBreakdownModal";
import { budgetItemRows, formulaRows } from "../kpiBreakdown";

type DashboardKpiKey = "balance" | "income" | "expense" | "netto";

function budgetAmount(item: BudgetItem): number {
  return Number(item.actual ?? item.estimated ?? 0);
}

function paidAmount(item: BudgetItem): number {
  const row = item as BudgetItem & { paidOrReceived?: number };
  return Number(row.paidOrReceived ?? row.paidOrReceived ?? 0);
}

function monthLabel(month: MonthlyBudget): string {
  return (month.monthName || (month as MonthlyBudget & { monthName?: string }).monthName || "").slice(0, 3);
}

interface DashboardViewProps {
  currentMonth: MonthlyBudget;
  allMonths: MonthlyBudget[];
  transactions: Transaction[];
  bankAccount: BankAccount;
  onNavigateTab: (tab: ActiveTab) => void;
}

export const DashboardView: React.FC<DashboardViewProps> = ({
  currentMonth,
  allMonths,
  transactions,
  bankAccount,
  onNavigateTab,
}) => {
  const [chartView, setChartView] = useState<"incomeExpense" | "netCashflow">("incomeExpense");
  const [chartMode, setChartMode] = useState<"actual" | "budget">("actual");
  const [kpiKey, setKpiKey] = useState<DashboardKpiKey | null>(null);

  // Calculate Monthly Totals
  const incomeItems = currentMonth.items.filter((i) => i.type === "inkomsten");
  const expenseItems = currentMonth.items.filter((i) => i.type === "uitgaven");
  const savingsItems = currentMonth.items.filter((i) => i.type === "sparen");

  const totalIncomeEstimated = incomeItems.reduce((sum, i) => sum + budgetAmount(i), 0);
  const totalIncomeReceived = incomeItems.reduce((sum, i) => sum + paidAmount(i), 0);

  const totalExpenseEstimated = expenseItems.reduce((sum, i) => sum + budgetAmount(i), 0);
  const totalExpensePaid = expenseItems.reduce((sum, i) => sum + paidAmount(i), 0);
  const totalExpenseRemaining = expenseItems.reduce(
    (sum, i) => sum + Math.max(0, budgetAmount(i) - paidAmount(i)),
    0
  );

  const totalSavingsBudget = savingsItems.reduce((sum, i) => sum + budgetAmount(i), 0);
  const monthId =
    currentMonth.monthId || (currentMonth as MonthlyBudget & { monthId?: string }).monthId || "aug";
  const monthPrefix = monthDatePrefix({ monthId, year: currentMonth.year });
  const savingsFromItems = savingsItems.reduce((sum, i) => sum + paidAmount(i), 0);
  const savingsFromTx = transactions
    .filter((t) => monthPrefix && t.date.startsWith(monthPrefix) && t.type === "Sparen")
    .reduce((sum, t) => sum + Math.abs(t.amount), 0);
  const totalSavingsPaid = Math.max(savingsFromItems, savingsFromTx);
  const totalSavingsRemaining = savingsItems.reduce(
    (sum, i) => sum + Math.max(0, budgetAmount(i) - paidAmount(i)),
    0
  );
  const totalIncomeRemaining = incomeItems.reduce(
    (sum, i) => sum + Math.max(0, budgetAmount(i) - paidAmount(i)),
    0
  );

  const netActual = totalIncomeReceived - totalExpensePaid - totalSavingsPaid;
  const netEstimated = totalIncomeEstimated - totalExpenseEstimated - totalSavingsBudget;

  const unpaidExpenses = expenseItems.filter((i) => budgetAmount(i) > paidAmount(i) && budgetAmount(i) > 0);

  const itemMatchesAliases = (item: BudgetItem, aliases: string[]) => {
    const group = (item.group || "").toLowerCase().trim();
    return aliases.some((alias) => alias.toLowerCase().trim() === group);
  };

  const mainCategories = [
    { name: "Inkomsten", type: "inkomsten" as const, aliases: ["Inkomsten"], icon: TrendingUp },
    { name: "Woning", type: "uitgaven" as const, aliases: ["Woning", "Woning"], icon: Building2 },
    { name: "Dagelijks Leven", type: "uitgaven" as const, aliases: ["Dagelijks Leven", "Dagelijks Leven"], icon: ShoppingBag },
    { name: "Leningen & Hypotheek", type: "uitgaven" as const, aliases: ["Leningen", "Leningen"], icon: Receipt },
    { name: "Vervoersmiddelen", type: "uitgaven" as const, aliases: ["Vervoersmiddelen", "Vervoersmiddelen"], icon: Car },
    { name: "Verzekeringen", type: "uitgaven" as const, aliases: ["Verzekeringen"], icon: ShieldCheck },
    { name: "Sparen & Buffer", type: "sparen" as const, aliases: ["Spaargeld", "Spaargeld", "Sparen"], icon: PiggyBank },
    { name: "Overige Vaste Kosten", type: "uitgaven" as const, aliases: ["Overige Vaste Kosten"], icon: Layers },
    { name: "Overige Kosten", type: "uitgaven" as const, aliases: ["Overige Kosten", "Overige Kosten"], icon: Filter },
  ];

  const categoryGroups = mainCategories.map((cat) => ({
    ...cat,
    items:
      cat.type === "sparen"
        ? savingsItems
        : cat.type === "inkomsten"
        ? incomeItems
        : currentMonth.items.filter((i) => i.type === "uitgaven" && itemMatchesAliases(i, cat.aliases)),
  }));

  const assignedIds = new Set(categoryGroups.flatMap((cat) => cat.items.map((i) => i.id)));
  const leftoverNames = [...new Set(currentMonth.items.filter((i) => !assignedIds.has(i.id)).map((i) => i.group).filter(Boolean))];
  leftoverNames.forEach((groupName) => {
    const items = currentMonth.items.filter((i) => i.group === groupName);
    categoryGroups.push({
      name: groupName,
      type: items[0]?.type ?? "uitgaven",
      aliases: [groupName],
      icon: Layers,
      items,
    });
  });

  const annualChartData = allMonths.map((m) => {
    const pick = chartMode === "budget" ? budgetAmount : paidAmount;
    const inc = m.items.filter((i) => i.type === "inkomsten").reduce((acc, x) => acc + pick(x), 0);
    const exp = m.items.filter((i) => i.type === "uitgaven").reduce((acc, x) => acc + pick(x), 0);
    const sav = m.items.filter((i) => i.type === "sparen").reduce((acc, x) => acc + pick(x), 0);
    return {
      month: monthLabel(m),
      fullName: m.monthName,
      Inkomsten: Math.round(inc),
      Uitgaven: Math.round(exp),
      Sparen: Math.round(sav),
      Netto: Math.round(inc - exp - sav),
    };
  });

  // Calculate free-to-spend balance (Bank balance minus upcoming unpaid bills)
  const accountBalance = Number(
    (bankAccount as BankAccount & { balance?: number }).balance ?? bankAccount.balance ?? 0
  );
  const expectedEnd =
    accountBalance + totalIncomeRemaining - totalExpenseRemaining - totalSavingsRemaining;
  const freeToSpend = accountBalance - totalExpenseRemaining;

  const kpiBreakdown =
    kpiKey === "balance"
      ? (() => {
          const { columns, rows, total } = formulaRows([
            { id: "ing", label: "Saldo ING", amount: accountBalance, tone: "plus" },
            {
              id: "unpaid",
              label: "Nog te betalen",
              amount: -totalExpenseRemaining,
              tone: "minus",
            },
            {
              id: "free",
              label: "Vrij besteedbaar",
              amount: freeToSpend,
              tone: "result",
            },
          ]);
          return {
            title: "Huidig Saldo (ING)",
            formula: "Saldo ING − nog te betalen = vrij besteedbaar",
            subtitle: "Nog te betalen is de som van openstaande uitgavenposten",
            columns,
            rows,
            totalValue: total,
            totalColorClass: freeToSpend >= 0 ? "text-emerald-400" : "text-rose-400",
          };
        })()
      : kpiKey === "income"
        ? (() => {
            const { columns, rows } = budgetItemRows(incomeItems, "paid");
            return {
              title: `Inkomsten (${currentMonth.monthName})`,
              formula: "Som van ontvangen bedragen op inkomstenposten",
              subtitle: `Begroot / geschat: € ${totalIncomeEstimated.toLocaleString("nl-NL", { minimumFractionDigits: 2 })}`,
              columns,
              rows,
              totalValue: totalIncomeReceived,
              totalColorClass: "text-emerald-400",
            };
          })()
        : kpiKey === "expense"
          ? (() => {
              const { columns, rows } = budgetItemRows(expenseItems, "paid");
              return {
                title: `Uitgaven (${currentMonth.monthName})`,
                formula: "Som van betaalde bedragen op uitgavenposten",
                subtitle: `Totaal geschat: € ${totalExpenseEstimated.toLocaleString("nl-NL", { minimumFractionDigits: 2 })}`,
                columns,
                rows,
                totalValue: totalExpensePaid,
                totalColorClass: "text-rose-400",
              };
            })()
          : kpiKey === "netto"
            ? (() => {
                const { columns, rows, total } = formulaRows([
                  { id: "received", label: "Ontvangen", amount: totalIncomeReceived, tone: "plus" },
                  { id: "paid", label: "Betaald", amount: -totalExpensePaid, tone: "minus" },
                  { id: "saved", label: "Gespaard", amount: -totalSavingsPaid, tone: "minus" },
                  {
                    id: "actual",
                    label: "Werkelijk netto",
                    amount: netActual,
                    tone: "result",
                  },
                  { id: "planned", label: "Begroot", amount: netEstimated },
                  { id: "expected", label: "Verwacht eind", amount: expectedEnd },
                ]);
                return {
                  title: "Netto Overschot / Saldo",
                  formula: "Ontvangen − betaald − gespaard = werkelijk netto",
                  subtitle: "Begroot en verwacht eind ter vergelijking",
                  columns,
                  rows,
                  totalValue: total,
                  totalColorClass: netActual >= 0 ? "text-emerald-400" : "text-rose-400",
                };
              })()
            : null;

  return (
    <div id="dashboard-view" className="space-y-6">
      {/* Top Welcome & Notification Bar */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div>
          <h2 className="text-xl font-bold text-white tracking-tight flex items-center gap-2">
            Financieel Overzicht • {currentMonth.monthName} {currentMonth.year}
          </h2>
          <p className="text-sm text-slate-400 mt-0.5">
            Realtime status gesynchroniseerd met ING Bank (IBAN: <span className="font-mono text-slate-300">NL83 INGB 0004 5658 68</span>)
          </p>
        </div>
        <div className="flex items-center gap-3">
          <div className="bg-slate-800/80 border border-slate-700 px-3.5 py-2 rounded-xl text-right">
            <span className="text-[11px] text-slate-400 block font-medium">Vrij Besteedbaar Saldo:</span>
            <span className={`text-base font-bold font-mono ${freeToSpend >= 0 ? "text-emerald-400" : "text-rose-400"}`}>
              € {freeToSpend.toLocaleString("nl-NL", { minimumFractionDigits: 2 })}
            </span>
          </div>
          <button
            id="dashboard-view-full-budget-btn"
            onClick={() => onNavigateTab("maandbegroting")}
            className="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold px-4 py-2.5 rounded-xl shadow-md shadow-indigo-600/20 transition-all"
          >
            <span>PDF Begroting</span>
            <ExternalLink className="w-3.5 h-3.5" />
          </button>
        </div>
      </div>

      {/* Primary KPI Cards Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Card 1: Huidig Saldo */}
        <button
          type="button"
          onClick={() => setKpiKey("balance")}
          className="text-left bg-slate-900 border border-slate-800 hover:border-indigo-500/50 p-5 rounded-2xl shadow-sm transition-all relative overflow-hidden"
        >
          <div className="flex items-center justify-between text-slate-400 mb-3">
            <span className="text-xs font-semibold uppercase tracking-wider">Huidig Saldo (ING)</span>
            <div className="w-8 h-8 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400">
              <Wallet className="w-4 h-4" />
            </div>
          </div>
          <div className="text-2xl font-black text-white font-mono tracking-tight">
            € {accountBalance.toLocaleString("nl-NL", { minimumFractionDigits: 2 })}
          </div>
          <div className="mt-3 flex items-center justify-between text-xs pt-3 border-t border-slate-800/80">
            <span className="text-slate-400">Nog te betalen:</span>
            <span className="font-mono font-semibold text-amber-400">
              -€ {totalExpenseRemaining.toLocaleString("nl-NL", { minimumFractionDigits: 2 })}
            </span>
          </div>
          <p className="mt-2 text-[10px] text-slate-500">klik voor detail</p>
        </button>

        {/* Card 2: Totale Inkomsten */}
        <button
          type="button"
          onClick={() => setKpiKey("income")}
          className="text-left bg-slate-900 border border-slate-800 hover:border-indigo-500/50 p-5 rounded-2xl shadow-sm transition-all"
        >
          <div className="flex items-center justify-between text-slate-400 mb-3">
            <span className="text-xs font-semibold uppercase tracking-wider">Inkomsten ({currentMonth.monthName})</span>
            <div className="w-8 h-8 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
              <TrendingUp className="w-4 h-4" />
            </div>
          </div>
          <div className="text-2xl font-black text-emerald-400 font-mono tracking-tight">
            € {totalIncomeReceived.toLocaleString("nl-NL", { minimumFractionDigits: 2 })}
          </div>
          <div className="mt-3 flex items-center justify-between text-xs pt-3 border-t border-slate-800/80">
            <span className="text-slate-400">Begroot / Geschat:</span>
            <span className="font-mono font-medium text-slate-300">
              € {totalIncomeEstimated.toLocaleString("nl-NL", { minimumFractionDigits: 2 })}
            </span>
          </div>
          <p className="mt-2 text-[10px] text-slate-500">klik voor detail</p>
        </button>

        {/* Card 3: Totale Uitgaven */}
        <button
          type="button"
          onClick={() => setKpiKey("expense")}
          className="text-left bg-slate-900 border border-slate-800 hover:border-indigo-500/50 p-5 rounded-2xl shadow-sm transition-all"
        >
          <div className="flex items-center justify-between text-slate-400 mb-3">
            <span className="text-xs font-semibold uppercase tracking-wider">Uitgaven ({currentMonth.monthName})</span>
            <div className="w-8 h-8 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400">
              <TrendingDown className="w-4 h-4" />
            </div>
          </div>
          <div className="text-2xl font-black text-rose-400 font-mono tracking-tight">
            € {totalExpensePaid.toLocaleString("nl-NL", { minimumFractionDigits: 2 })}
          </div>
          <div className="mt-3 flex items-center justify-between text-xs pt-3 border-t border-slate-800/80">
            <span className="text-slate-400">Totaal Geschat:</span>
            <span className="font-mono font-medium text-slate-300">
              € {totalExpenseEstimated.toLocaleString("nl-NL", { minimumFractionDigits: 2 })}
            </span>
          </div>
          <p className="mt-2 text-[10px] text-slate-500">klik voor detail</p>
        </button>

        {/* Card 4: Netto Maandresultaat */}
        <button
          type="button"
          onClick={() => setKpiKey("netto")}
          className="text-left bg-slate-900 border border-slate-800 hover:border-indigo-500/50 p-5 rounded-2xl shadow-sm transition-all"
        >
          <div className="flex items-center justify-between text-slate-400 mb-3">
            <span className="text-xs font-semibold uppercase tracking-wider">Netto Overschot / Saldo</span>
            <span className="text-[10px] font-semibold text-indigo-400 uppercase tracking-wide">Werkelijk</span>
          </div>
          <div
            className={`text-2xl font-black font-mono tracking-tight ${netActual >= 0 ? "text-emerald-400" : "text-rose-400"}`}
            title="Ontvangen − betaald − gespaard"
          >
            {netActual >= 0 ? "+" : ""}€ {netActual.toLocaleString("nl-NL", { minimumFractionDigits: 2 })}
          </div>
          <div className="mt-3 space-y-1 text-[11px] font-mono pt-3 border-t border-slate-800/80">
            <div
              className="flex items-center justify-between text-slate-300"
              title="Begroot inkomsten − begroot uitgaven − begroot sparen"
            >
              <span className="text-slate-400">Begroot</span>
              <span className={netEstimated >= 0 ? "text-slate-200" : "text-rose-400"}>
                € {netEstimated.toLocaleString("nl-NL", { minimumFractionDigits: 2 })}
              </span>
            </div>
            <div
              className="flex items-center justify-between text-indigo-300"
              title="Huidig ING-saldo + nog te ontvangen − nog te betalen − nog te sparen"
            >
              <span>Verwacht eind</span>
              <span className={expectedEnd >= 0 ? "text-indigo-300" : "text-rose-400"}>
                € {expectedEnd.toLocaleString("nl-NL", { minimumFractionDigits: 2 })}
              </span>
            </div>
            <div className="flex items-center justify-between text-slate-500">
              <span>Gespaard deze maand</span>
              <span>€ {totalSavingsPaid.toLocaleString("nl-NL", { minimumFractionDigits: 2 })}</span>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-slate-500">klik voor detail</p>
        </button>
      </div>

      {/* Middle Row: Main Chart & Action Center */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left 2 Cols: Inkomsten vs Uitgaven Bar Chart */}
        <div className="lg:col-span-2 bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm">
          <div className="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div>
              <h3 className="font-bold text-white text-base">Jaarlijkse Cashflow & Begroting 2026</h3>
              <p className="text-xs text-slate-400">
                {chartMode === "actual"
                  ? "Werkelijke bankmutaties per maand — maanden zonder mutaties blijven leeg"
                  : "Gepland budget per maand (zelfde template tot je een maand aanpast)"}
              </p>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <div className="flex items-center gap-1 bg-slate-800 p-1 rounded-xl border border-slate-700">
                <button
                  onClick={() => setChartMode("actual")}
                  className={`text-xs px-3 py-1 rounded-lg font-medium transition-colors ${
                    chartMode === "actual" ? "bg-emerald-600 text-white" : "text-slate-400 hover:text-white"
                  }`}
                >
                  Werkelijk
                </button>
                <button
                  onClick={() => setChartMode("budget")}
                  className={`text-xs px-3 py-1 rounded-lg font-medium transition-colors ${
                    chartMode === "budget" ? "bg-slate-600 text-white" : "text-slate-400 hover:text-white"
                  }`}
                >
                  Begroot
                </button>
              </div>
              <div className="flex items-center gap-1 bg-slate-800 p-1 rounded-xl border border-slate-700">
                <button
                  onClick={() => setChartView("incomeExpense")}
                  className={`text-xs px-3 py-1 rounded-lg font-medium transition-colors ${
                    chartView === "incomeExpense" ? "bg-indigo-600 text-white" : "text-slate-400 hover:text-white"
                  }`}
                >
                  Inkomsten vs Uitgaven
                </button>
                <button
                  onClick={() => setChartView("netCashflow")}
                  className={`text-xs px-3 py-1 rounded-lg font-medium transition-colors ${
                    chartView === "netCashflow" ? "bg-indigo-600 text-white" : "text-slate-400 hover:text-white"
                  }`}
                >
                  Netto Verloop
                </button>
              </div>
            </div>
          </div>

          <div className="h-72 w-full">
            <ResponsiveContainer width="100%" height="100%">
              {chartView === "incomeExpense" ? (
                <BarChart data={annualChartData} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#334155" opacity={0.5} vertical={false} />
                  <XAxis dataKey="month" stroke="#94a3b8" fontSize={12} tickLine={false} />
                  <YAxis stroke="#94a3b8" fontSize={12} tickFormatter={(val) => `€${val}`} tickLine={false} />
                  <Tooltip
                    contentStyle={{
                      backgroundColor: "#0f172a",
                      borderColor: "#334155",
                      borderRadius: "12px",
                      color: "#f8fafc",
                      fontSize: "12px",
                    }}
                    formatter={(value: any) => [`€ ${Number(value).toLocaleString("nl-NL")}`, ""]}
                  />
                  <Legend wrapperStyle={{ fontSize: "12px", paddingTop: "10px" }} />
                  <Bar dataKey="Inkomsten" fill="#10b981" radius={[4, 4, 0, 0]} maxBarSize={28} />
                  <Bar dataKey="Uitgaven" fill="#ef4444" radius={[4, 4, 0, 0]} maxBarSize={28} />
                  <Bar dataKey="Sparen" fill="#3b82f6" radius={[4, 4, 0, 0]} maxBarSize={28} />
                </BarChart>
              ) : (
                <LineChart data={annualChartData} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#334155" opacity={0.5} vertical={false} />
                  <XAxis dataKey="month" stroke="#94a3b8" fontSize={12} tickLine={false} />
                  <YAxis stroke="#94a3b8" fontSize={12} tickFormatter={(val) => `€${val}`} tickLine={false} />
                  <Tooltip
                    contentStyle={{
                      backgroundColor: "#0f172a",
                      borderColor: "#334155",
                      borderRadius: "12px",
                      color: "#f8fafc",
                      fontSize: "12px",
                    }}
                    formatter={(value: any) => [`€ ${Number(value).toLocaleString("nl-NL")}`, "Netto Resultaat"]}
                  />
                  <Legend wrapperStyle={{ fontSize: "12px", paddingTop: "10px" }} />
                  <Line type="monotone" dataKey="Netto" stroke="#6366f1" strokeWidth={3} dot={{ r: 4, fill: "#6366f1" }} />
                </LineChart>
              )}
            </ResponsiveContainer>
          </div>
        </div>

        {/* Right Col: Openstaande Rekeningen & Actie Center */}
        <div className="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between mb-3">
              <div className="flex items-center gap-2">
                <Clock className="w-4 h-4 text-amber-400" />
                <h3 className="font-bold text-white text-base">Nog te Betalen ({currentMonth.monthName})</h3>
              </div>
              <span className="text-xs bg-amber-500/10 text-amber-400 border border-amber-500/20 px-2 py-0.5 rounded-full font-mono font-medium">
                {unpaidExpenses.length} posten
              </span>
            </div>
            <p className="text-xs text-slate-400 mb-3">
              Overzicht van geplande vaste lasten die deze maand nog afgeschreven moeten worden.
            </p>

            <div className="space-y-2 max-h-64 overflow-y-auto pr-1">
              {unpaidExpenses.length === 0 ? (
                <div className="p-6 text-center text-slate-400 bg-slate-800/40 rounded-xl border border-slate-800">
                  <CheckCircle className="w-8 h-8 text-emerald-400 mx-auto mb-2" />
                  <p className="text-sm font-medium text-white">Alle rekeningen zijn voldaan!</p>
                  <p className="text-xs text-slate-400 mt-1">Geen openstaande posten voor {currentMonth.monthName}.</p>
                </div>
              ) : (
                unpaidExpenses.map((item) => {
                  const remaining = budgetAmount(item) - paidAmount(item);
                  return (
                    <div
                      key={item.id}
                      className="p-3 bg-slate-800/60 hover:bg-slate-800 rounded-xl border border-slate-700/60 flex items-center justify-between gap-3 transition-colors"
                    >
                      <div className="overflow-hidden">
                        <p className="text-xs font-semibold text-white truncate">{item.name}</p>
                        <span className="text-[10px] text-slate-400">{item.group}</span>
                      </div>
                      <div className="flex items-center gap-2 shrink-0">
                        <span className="text-xs font-bold font-mono text-amber-400">
                          € {remaining.toLocaleString("nl-NL", { minimumFractionDigits: 2 })}
                        </span>
                        <span className="text-[10px] font-medium bg-slate-700/60 text-slate-300 px-2 py-0.5 rounded-lg">
                          In afwachting
                        </span>
                      </div>
                    </div>
                  );
                })
              )}
            </div>
          </div>

          <div className="mt-4 pt-4 border-t border-slate-800 flex items-center justify-between text-xs">
            <span className="text-slate-400 font-medium">Totaal openstaand:</span>
            <span className="font-mono font-bold text-amber-400 text-sm">
              € {totalExpenseRemaining.toLocaleString("nl-NL", { minimumFractionDigits: 2 })}
            </span>
          </div>
        </div>
      </div>

      {/* Category Budget Breakdown Progress Grid */}
      <div className="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div className="flex items-center justify-between mb-4">
          <div>
            <h3 className="font-bold text-white text-base">Budget vs Werkelijk per Hoofdcategorie</h3>
            <p className="text-xs text-slate-400">Bekijk de bestedingen per rubriek voor {currentMonth.monthName}</p>
          </div>
          <button
            onClick={() => onNavigateTab("categorieen")}
            className="text-xs text-indigo-400 hover:text-indigo-300 font-medium flex items-center gap-1"
          >
            <span>Alle Categorieën</span>
            <ArrowUpRight className="w-3.5 h-3.5" />
          </button>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {categoryGroups.map((cat) => {
            const Icon = cat.icon;
            const groupEstimated = cat.items.reduce((s, i) => s + budgetAmount(i), 0);
            const groupPaid = cat.items.reduce((s, i) => s + paidAmount(i), 0);
            const percentage = groupEstimated > 0 ? Math.min(100, Math.round((groupPaid / groupEstimated) * 100)) : 0;
            const isOverBudget = groupPaid > groupEstimated && groupEstimated > 0;

            return (
              <div
                key={cat.name}
                className="p-4 bg-slate-800/50 hover:bg-slate-800/80 rounded-xl border border-slate-700/60 transition-all"
              >
                <div className="flex items-center justify-between mb-2">
                  <div className="flex items-center gap-2.5">
                    <div className="w-8 h-8 rounded-lg bg-slate-700/80 flex items-center justify-center text-indigo-400">
                      <Icon className="w-4 h-4" />
                    </div>
                    <div>
                      <h4 className="text-xs font-bold text-white">{cat.name}</h4>
                      <p className="text-[10px] text-slate-400">{cat.items.length} posten</p>
                    </div>
                  </div>
                  <span className={`text-xs font-mono font-bold ${isOverBudget ? "text-rose-400" : "text-slate-300"}`}>
                    {percentage}%
                  </span>
                </div>

                {/* Progress bar */}
                <div className="w-full bg-slate-700/60 h-2 rounded-full overflow-hidden my-2.5">
                  <div
                    className={`h-full rounded-full transition-all ${
                      isOverBudget
                        ? "bg-rose-500"
                        : cat.type === "inkomsten"
                        ? "bg-emerald-500"
                        : cat.type === "sparen"
                        ? "bg-blue-500"
                        : "bg-indigo-500"
                    }`}
                    style={{ width: `${percentage}%` }}
                  />
                </div>

                <div className="flex items-center justify-between text-[11px] font-mono text-slate-400">
                  <span>Betaald: €{groupPaid.toFixed(2)}</span>
                  <span>Begroot: €{groupEstimated.toFixed(2)}</span>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Bottom Row: Recent Live Bank Transactions */}
      <div className="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div className="flex items-center justify-between mb-4">
          <div>
            <h3 className="font-bold text-white text-base">Laatste Live Banktransacties (ING Bank)</h3>
            <p className="text-xs text-slate-400">Realtime mutaties automatisch gekoppeld via EnableBanking PSD2</p>
          </div>
          <button
            id="dashboard-all-transactions-btn"
            onClick={() => onNavigateTab("transacties")}
            className="text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white px-3 py-1.5 rounded-xl border border-slate-700 font-medium transition-colors"
          >
            Bekijk alle transacties ({transactions.length})
          </button>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead>
              <tr className="border-b border-slate-800 text-slate-400 font-semibold uppercase tracking-wider">
                <th className="py-2.5 px-3">Datum & Tijd</th>
                <th className="py-2.5 px-3">Omschrijving</th>
                <th className="py-2.5 px-3">Categorie</th>
                <th className="py-2.5 px-3">Type</th>
                <th className="py-2.5 px-3 text-right">Bedrag</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800/60">
              {transactions.slice(0, 6).map((tx) => {
                const isIncome = tx.amount > 0;
                return (
                  <tr key={tx.id} className="hover:bg-slate-800/40 transition-colors">
                    <td className="py-3 px-3 whitespace-nowrap">
                      <TransactionDate date={tx.date} time={tx.time} />
                    </td>
                    <td className="py-3 px-3">
                      <p className="font-medium text-white max-w-md truncate">{tx.description}</p>
                      {tx.counterparty && <span className="text-[10px] text-slate-400">{tx.counterparty}</span>}
                    </td>
                    <td className="py-3 px-3">
                      <span className="inline-block bg-slate-800 text-slate-300 px-2 py-0.5 rounded-md border border-slate-700 text-[11px] font-medium">
                        {tx.categoryGroup}
                      </span>
                    </td>
                    <td className="py-3 px-3">
                      <span
                        className={`text-[10px] font-bold uppercase px-2 py-0.5 rounded ${
                          isIncome
                            ? "bg-emerald-950/60 text-emerald-400 border border-emerald-800/50"
                            : tx.type === "Sparen"
                            ? "bg-blue-950/60 text-blue-400 border border-blue-800/50"
                            : "bg-rose-950/60 text-rose-400 border border-rose-800/50"
                        }`}
                      >
                        {tx.type}
                      </span>
                    </td>
                    <td
                      className={`py-3 px-3 text-right font-mono font-bold text-sm whitespace-nowrap ${
                        isIncome ? "text-emerald-400" : "text-rose-400"
                      }`}
                    >
                      {isIncome ? "+" : ""}€ {Math.abs(tx.amount).toLocaleString("nl-NL", { minimumFractionDigits: 2 })}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      <KpiBreakdownModal
        isOpen={Boolean(kpiBreakdown)}
        title={kpiBreakdown?.title ?? ""}
        formula={kpiBreakdown?.formula ?? ""}
        subtitle={kpiBreakdown?.subtitle}
        columns={kpiBreakdown?.columns ?? []}
        rows={kpiBreakdown?.rows ?? []}
        totalValue={kpiBreakdown?.totalValue ?? 0}
        totalColorClass={kpiBreakdown?.totalColorClass}
        onClose={() => setKpiKey(null)}
      />
    </div>
  );
};
